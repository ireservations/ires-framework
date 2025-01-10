<?php

namespace Framework\Aro;

use Exception;

class ActiveRecordException extends Exception {

	public function __construct(
		string $message = '',
		int $code = 0,
		public string $class = 'Unknown',
	) {
		parent::__construct($message, $code);
	}

}
