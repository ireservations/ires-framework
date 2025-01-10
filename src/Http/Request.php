<?php

namespace Framework\Http;


class Request {

	static protected string $_semidebug;
	static protected string $_uri;
	static protected string $_fullUri;

	static public function https() : bool {
		return ($_SERVER['SERVER_PORT'] ?? '') === '443' || ($_SERVER['HTTPS'] ?? '') === 'on';
	}

	static public function scheme() : string {
		return self::https() ? 'https://' : 'http://';
	}

	static public function origin() : string {
		$host = self::host();
		$port = self::port();
		$port = in_array($port, [80, 443]) ? '' : ':' . $port;

		return self::scheme() . $host . $port;
	}

	static public function port() : int {
		return $_SERVER['SERVER_PORT'] ?? (self::https() ? 443 : 80);
	}

	static public function host() : string {
		return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
	}

	static public function ip() : string {
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	static public function ua() : string {
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	static public function referrer() : string {
		return $_SERVER['HTTP_REFERER'] ?? '';
	}

	static public function method() : string {
		return strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
	}


	static public function debug() : bool {
		return DEBUG;
	}

	static public function semidebug() : bool {
		return self::$_semidebug ??= self::debug() || in_array(self::ip(), SEMIDEBUG_IPS);
	}


	static public function mobileDevice() : bool {
		$ua = strtolower(self::ua());
		return is_int(strpos($ua, 'mobile')) || is_int(strpos($ua, 'opera mini')) || is_int(strpos($ua, 'opera mobi'));
	}


	static public function uri() : string {
		return self::$_uri ??= rtrim(explode('?', static::fullUri())[0], '/');
	}

	static public function fullUri( bool $appendable = false ) : string {
		self::$_fullUri ??= self::cli() ? 'CLI' : strval($_SERVER['REQUEST_URI'] ?? '');

		if ( !$appendable ) {
			return self::$_fullUri;
		}

		$delim = strpos(self::$_fullUri, '?') === false ? '?' : '&';
		return self::$_fullUri . $delim;
	}


	static public function ajax() : bool {
		return !empty($_REQUEST['ajax']) || !empty($_SERVER['HTTP_AJAX']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
	}

	static public function csrfReferrer() : bool {
		if ( self::method() !== 'POST' ) return true;

		$referrerHost = parse_url(self::referrer(), PHP_URL_HOST);
		$nowHost = self::host();

		return $referrerHost && $nowHost && $referrerHost === $nowHost;
	}


	static public function cli() : bool {
		return php_sapi_name() === 'cli';
	}

	static public function cliDirectory() : string {
		return $_SERVER['PWD'] ?? '';
	}

	static public function cliCommand() : string {
		return implode(' ', $_SERVER['argv'] ?? []);
	}


	/**
	 * @return list<bool>
	 */
	static public function action( string ...$actions ) : array {
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
