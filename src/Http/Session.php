<?php

namespace Framework\Http;

class Session {

	const REMEMBER_N_DAYS = 15;

	static function exists() {
		$name = session_name();
		return isset($_COOKIE[$name]);
	}

	static function init() {
		ini_set('session.cookie_path', '/');
		ini_set('session.cookie_httponly', 1);
		ini_set('session.cookie_secure', Request::https());

		static::exists() && static::start();
	}

	static function started() {
		return isset($_SESSION) && is_array($_SESSION);
	}

	static function required( $refresh = false ) {
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

	static function start() {
		@session_start();
	}

	static function refresh() {
		session_regenerate_id(true);
	}

	static function destroy( $section = null ) {
		static::exists() && !static::started() && static::start();

		if ( $section ) {
			if ( isset($_SESSION[SESSION_NAME]) ) {
				unset($_SESSION[SESSION_NAME][$section]);
			}
			return false;
		}

		@session_destroy();
		return $_SESSION = false;
	}

	static function get( $key, $alt = null ) {
		if ( static::started() ) {
			return array_get($_SESSION[SESSION_NAME] ?? [], $key) ?? $alt;
		}

		return $alt;
	}

	static function set( $key, $value ) {
		static::required();

		array_set($_SESSION[SESSION_NAME], $key, $value);

		return $value;
	}

	static function push( $key, $value ) {
		static::required();

		$list = array_get($_SESSION[SESSION_NAME] ?? [], $key);
		if ( !is_array($list) ) {
			$list = [];
		}
		$list[] = $value;
		array_set($_SESSION[SESSION_NAME], $key, $list);
	}


	// Cookie
	static function domain() {
		return HTTP_HOST;
	}

	static function cookie( $name, $value, $expire = null ) {
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

		$domain = null;
		$httponly = true;
		$secure = Request::https();
		return setcookie($name, $value, $expire, '/', $domain, $secure, $httponly);
	}

}
