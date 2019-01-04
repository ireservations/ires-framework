<?php

namespace Framework\Errors;

use ErrorException;

class ErrorHandler {

	static protected $handler = null;

	static protected $noticesAllowedIn = [];

	static public $throwErrorException = false;

	static public function overrideHandler( callable $handler ) {
		static::$handler = $handler;
	}

	static public function reset() {
		static::$handler = null;
	}

	static public function allowNoticesIn( array $paths ) {
		static::$noticesAllowedIn = $paths;
	}

	static public function failOnNotice( $file ) {
		foreach ( static::$noticesAllowedIn as $path ) {
			if ( strpos($file, $path) !== false ) {
				return false;
			}
		}

		return true;
	}

	static public function handleError( $severity, $error, $file = '', $line = 0 ) {
		if ( error_reporting() & $severity ) {
			if ( static::$handler ) {
				call_user_func(static::$handler, $severity, $error, $file, $line);
				return;
			}

			static::handleErrorDefault($severity, $error, $file, $line);
		}
	}

	static protected function handleErrorDefault( $severity, $error, $file = '', $line = 0 ) {
		$file = str_replace('\\', '/', substr($file, strlen(SCRIPT_ROOT)+1));

		if ( $severity != E_NOTICE || static::failOnNotice($file) ) {
			static::except($error, $file, $line);
		}
	}

	static protected function except( $error, $file = '', $line = 0 ) {
		if ( static::$throwErrorException ) {
			throw new ErrorException($error, 0, 0, $file, $line);
		}

		debug_exit("`$error` in `$file`:$line");
	}

}
