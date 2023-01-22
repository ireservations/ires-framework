<?php

namespace Framework\Http\Exception;

use Exception;

class InvalidInputException extends Exception {

	protected array $invalid = [];

	public function __construct( ?string $message, array $invalid = [] ) {
		parent::__construct($message ?? '');

		$this->invalid = $invalid;
	}

	public function getInvalids() : array {
		return $this->invalid;
	}

	public function getFullMessage() : string {
		$message = $this->getMessage() ?: trans('INVALID_PARAMETERS');
		if ( count($this->invalid) ) {
			$message .= ":\n\n* " . implode("\n* ", $this->invalid);
		}
		return $message;
	}

}
