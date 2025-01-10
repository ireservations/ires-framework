<?php

namespace Framework\Http\Exception;

use Exception;

class InvalidInputException extends Exception implements FullMessageException {

	public function __construct(
		?string $message,
		/** @var array<array-key, string> */
		protected array $invalid = [],
	) {
		parent::__construct($message ?? '');
	}

	/**
	 * @return array<array-key, string>
	 */
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
