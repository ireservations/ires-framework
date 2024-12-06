<?php

namespace Framework\Http;

class Session {

	protected const REMEMBER_N_DAYS = 15;

	static public string $samesite = 'Lax';

	static protected function exists() {
		$name = session_name();
		return isset($_COOKIE[$name]);
	}

	static public function init() {
		static::exists() && static::start();
	}

	static protected function started() {
		return isset($_SESSION) && is_array($_SESSION);
	}

	static public function required( bool $refresh = false ) {
		$started = static::started();

		if ( $started ) {
			$refresh && static::refresh();
		}
		elseif ( static::exists() ) {
			static::start();
			$refresh && static::refresh();
		}
		else {
			static::start();
		}
	}

	static protected function start() {
		@session_set_cookie_params(self::cookieParams());
		@session_start();
	}

	static protected function refresh() {
		session_regenerate_id(true);
	}

	static public function destroy( $section = null ) {
		if ( static::exists() && !static::started() ) {
			static::start();
		}

		if ( $section ) {
			if ( isset($_SESSION[SESSION_NAME]) ) {
				unset($_SESSION[SESSION_NAME][$section]);
			}
			return false;
		}

		@session_destroy();
		return $_SESSION = false;
	}

	static public function get( $key, $alt = null ) {
		if ( static::started() ) {
			return array_get($_SESSION[SESSION_NAME] ?? [], $key) ?? $alt;
		}

		return $alt;
	}

	static public function set( $key, $value ) {
		static::required();

		array_set($_SESSION[SESSION_NAME], $key, $value);

		return $value;
	}

	static public function push( $key, $value ) {
		static::required();

		$list = array_get($_SESSION[SESSION_NAME] ?? [], $key);
		if ( !is_array($list) ) {
			$list = [];
		}
		$list[] = $value;
		array_set($_SESSION[SESSION_NAME], $key, $list);
	}


	// Cookie
	static protected function domain() {
		return HTTP_HOST;
	}

	static public function cookie( $name, $value, $expire = null ) {
		if ( $expire === null ) {
			$expire = time() + static::REMEMBER_N_DAYS * 86400;
			// $_COOKIE[$name] = $value;
		}
		elseif ( $expire == -1 ) {
			$expire = 1;
			unset($_COOKIE[$name]);
		}
		else {
			$expire += time();
			// $_COOKIE[$name] = $value;
		}

		return setcookie($name, $value, self::cookieParams() + [
			'expires' => $expire,
		]);
	}

	static protected function cookieParams() : array {
		return [
			'path' => '/',
			'samesite' => self::$samesite,
			'secure' => Request::https(),
			'httponly' => true,
		];
	}

}
