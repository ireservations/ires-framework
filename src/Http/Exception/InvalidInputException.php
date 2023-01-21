<?php

namespace Framework\Http\Exception;

use Exception;

class InvalidInputException extends Exception {

	protected $invalid = [];

	public function __construct( $message, array $invalid = [] ) {
		parent::__construct($message ?? '');

		$this->invalid = $invalid;
	}

	public function getInvalids() {
		return $this->invalid;
	}

}
