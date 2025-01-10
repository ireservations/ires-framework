<?php

namespace Framework\Http;

class Session {

	/** @var int */
	protected const REMEMBER_N_DAYS = 15;

	static public string $samesite = 'Lax';

	static protected function exists() : bool {
		$name = session_name();
		return isset($_COOKIE[$name]);
	}

	static public function init() : void {
		static::exists() && static::start();
	}

	static protected function started() : bool {
		return isset($_SESSION) && is_array($_SESSION); // @phpstan-ignore function.alreadyNarrowedType
	}

	static public function required( bool $refresh = false ) : void {
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

	static protected function start() : void {
		@session_set_cookie_params(self::cookieParams());
		@session_start();
	}

	static protected function refresh() : void {
		session_regenerate_id(true);
	}

	static public function destroy( ?string $section = null ) : void {
		if ( static::exists() && !static::started() ) {
			static::start();
		}

		if ( $section ) {
			if ( isset($_SESSION[SESSION_NAME]) ) {
				unset($_SESSION[SESSION_NAME][$section]);
			}
			return;
		}

		@session_destroy();
		$_SESSION = [];
	}

	static public function get( string $key, mixed $alt = null ) : mixed {
		if ( static::started() ) {
			return array_get($_SESSION[SESSION_NAME] ?? [], $key) ?? $alt;
		}

		return $alt;
	}

	static public function set( string $key, mixed $value ) : mixed {
		static::required();

		array_set($_SESSION[SESSION_NAME], $key, $value);

		return $value;
	}

	static public function push( string $key, mixed $value ) : void {
		static::required();

		$list = array_get($_SESSION[SESSION_NAME] ?? [], $key);
		if ( !is_array($list) ) {
			$list = [];
		}
		$list[] = $value;
		array_set($_SESSION[SESSION_NAME], $key, $list);
	}


	// Cookie
	static protected function domain() : string {
		return HTTP_HOST;
	}

	static public function cookie( string $name, mixed $value, ?int $expire = null ) : void {
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

		setcookie($name, $value, self::cookieParams() + [
			'expires' => $expire,
		]);
	}

	/**
	 * @return AssocArray
	 */
	static protected function cookieParams() : array {
		return [
			'path' => '/',
			'samesite' => self::$samesite,
			'secure' => Request::https(),
			'httponly' => true,
		];
	}

}
