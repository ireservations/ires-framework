<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\TextResponse;
use Exception;

class NOKResponseException extends Exception {

	public function __construct( $error ) {
		if ( $error instanceof TextResponse ) {
			$error = $error->data;
		}
		elseif ( $error instanceof InvalidInputException ) {
			$error = $error->getFullMessage();
		}
		elseif ( $error instanceof Exception ) {
			$error = $error->getMessage();
		}

		if ( !$error || !is_scalar($error) ) {
			$error = 'Unknown error';
		}

		parent::__construct($error);
	}

}
