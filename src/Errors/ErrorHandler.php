<?php

namespace Framework\Errors;

use Closure;
use ErrorException;

class ErrorHandler {

	static protected ?Closure $handler = null;

	/** @var list<string> */
	static protected array $noticesAllowedIn = [];

	static public bool $throwErrorException = false;

	static public function overrideHandler( Closure $handler ) : void {
		static::$handler = $handler;
	}

	static public function reset() : void {
		static::$handler = null;
	}

	/**
	 * @param list<string> $paths
	 */
	static public function allowNoticesIn( array $paths ) : void {
		static::$noticesAllowedIn = $paths;
	}

	static public function failOnNotice( string $file ) : bool {
		foreach ( static::$noticesAllowedIn as $path ) {
			if ( strpos($file, $path) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return void  Not native void, because PHP acts on inner return type weirdly
	 */
	static public function handleError( int $severity, string $error, string $file = '', int $line = 0 ) {
		if ( error_reporting() & $severity ) {
			if ( static::$handler ) {
				call_user_func(static::$handler, $severity, $error, $file, $line);
				return;
			}

			static::handleErrorDefault($severity, $error, $file, $line);
		}
	}

	static protected function handleErrorDefault( int $severity, string $error, string $file = '', int $line = 0 ) : void {
		$file = str_replace('\\', '/', substr($file, strlen(SCRIPT_ROOT)+1));

		if ( $severity != E_NOTICE || static::failOnNotice($file) ) {
			static::except($error, $file, $line);
		}
	}

	static protected function except( string $error, string $file = '', int $line = 0 ) : void {
		if ( static::$throwErrorException ) {
			throw new ErrorException($error, 0, 0, $file, $line);
		}

		debug_exit("`$error` in `$file`:$line");
	}

}
