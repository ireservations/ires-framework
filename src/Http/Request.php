<?php

namespace Framework\Http;

use App\Services\Session\User;

class Request {

	static function https() {
		return ($_SERVER['SERVER_PORT'] ?? '') === '443' || ($_SERVER['HTTPS'] ?? '') === 'on';
	}

	static function scheme() {
		return self::https() ? 'https://' : 'http://';
	}

	static function origin() {
		$host = self::host();
		$port = self::port();
		$port = in_array($port, [80, 443]) ? '' : ':' . $port;

		return self::scheme() . $host . $port;
	}

	static function port() {
		return $_SERVER['SERVER_PORT'] ?? (self::https() ? 443 : 80);
	}

	static function host() {
		return $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
	}

	static function ip() {
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	static function ua() {
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	static function referrer() {
		return $_SERVER['HTTP_REFERER'] ?? '';
	}

	static function method() {
		return strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
	}


	static function debug() {
		return DEBUG;
	}

	static function semidebug() {
		static $cache = null;
		if ( $cache === null ) {
			$cache = self::debug() || in_array(self::ip(), SEMIDEBUG_IPS);
		}

		return $cache;
	}

	static function superSuperAdmin() {
		static $cache = null;
		if ( $cache === null ) {
			$cache = User::logincheck() && in_array(User::id(), SUPERADMIN_IDS);
		}

		return $cache;
	}


	static function mobileDevice() {
		$ua = strtolower(self::ua());
		return is_int(strpos($ua, 'mobile')) || is_int(strpos($ua, 'opera mini')) || is_int(strpos($ua, 'opera mobi'));
	}

	static function mobileVersion() {
		return preg_match('#^/mobile(/|$)#', static::uri()) > 0;
	}

	static function fromMobileVersion() {
		return self::method() == 'POST' && strpos(self::referrer(), '/mobile') !== false;
	}


	static function uri() {
		static $cache = null;
		if ( $cache === null ) {
			$uri = explode('?', static::fullUri());
			$cache = rtrim($uri[0], '/');
		}

		return $cache;
	}

	static function fullUri( $appendable = false ) {
		static $cache = null;
		if ( $cache === null ) {
			$cache = self::cli() ? 'CLI' : ($_SERVER['REQUEST_URI'] ?? '');
		}

		if ( !$appendable ) {
			return $cache;
		}

		$delim = strpos($cache, '?') === false ? '?' : '&';
		return $cache . $delim;
	}


	static function ajax() {
		return !empty($_REQUEST['ajax']) || !empty($_SERVER['HTTP_AJAX']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
	}


	static function cli() {
		return php_sapi_name() === 'cli';
	}

	static function cliDirectory() {
		return $_SERVER['PWD'] ?? '';
	}

	static function cliCommand() {
		return implode(' ', $_SERVER['argv'] ?? []);
	}


	static function action( ...$actions ) {
		$_action = $_REQUEST['_action'] ?? '';
		$bools = array_map(function( $name ) use ( $_action ) {
			return $_action === $name;
		}, $actions);

		// Last action is the default
		if ( !in_array(true, $bools, true) ) {
			$bools[count($bools) - 1] = true;
		}

		return $bools;
	}

}
