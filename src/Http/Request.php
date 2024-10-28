<?php

namespace Framework\Http;

use App\Services\Session\User;

class Request {

	static function https() : bool {
		return ($_SERVER['SERVER_PORT'] ?? '') === '443' || ($_SERVER['HTTPS'] ?? '') === 'on';
	}

	static function scheme() : string {
		return self::https() ? 'https://' : 'http://';
	}

	static function origin() : string {
		$host = self::host();
		$port = self::port();
		$port = in_array($port, [80, 443]) ? '' : ':' . $port;

		return self::scheme() . $host . $port;
	}

	static function port() : int {
		return $_SERVER['SERVER_PORT'] ?? (self::https() ? 443 : 80);
	}

	static function host() : string {
		return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
	}

	static function ip() : string {
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	static function ua() : string {
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	static function referrer() : string {
		return $_SERVER['HTTP_REFERER'] ?? '';
	}

	static function method() : string {
		return strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
	}


	static function debug() : bool {
		return DEBUG;
	}

	static function semidebug() : bool {
		static $cache = null;
		if ( $cache === null ) {
			$cache = self::debug() || in_array(self::ip(), SEMIDEBUG_IPS);
		}

		return $cache;
	}


	static function mobileDevice() : bool {
		$ua = strtolower(self::ua());
		return is_int(strpos($ua, 'mobile')) || is_int(strpos($ua, 'opera mini')) || is_int(strpos($ua, 'opera mobi'));
	}


	static function uri() : string {
		static $cache = null;
		if ( $cache === null ) {
			$uri = explode('?', static::fullUri());
			$cache = rtrim($uri[0], '/');
		}

		return $cache;
	}

	static function fullUri( bool $appendable = false ) : string {
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


	static function ajax() : bool {
		return !empty($_REQUEST['ajax']) || !empty($_SERVER['HTTP_AJAX']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
	}

	static function csrfReferrer() : bool {
		if ( self::method() !== 'POST' ) return true;

		$referrerHost = parse_url(self::referrer(), PHP_URL_HOST);
		$nowHost = self::host();

		return $referrerHost && $nowHost && $referrerHost === $nowHost;
	}


	static function cli() : bool {
		return php_sapi_name() === 'cli';
	}

	static function cliDirectory() : string {
		return $_SERVER['PWD'] ?? '';
	}

	static function cliCommand() : string {
		return implode(' ', $_SERVER['argv'] ?? []);
	}


	/**
	 * @param list<string> $actions
	 * @return list<bool>
	 */
	static function action( ...$actions ) : array {
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
