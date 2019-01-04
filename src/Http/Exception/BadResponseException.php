<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\Response;

class BadResponseException extends ResponseException {

	public function __construct( Response $response ) {
		parent::__construct($response);
		$response->code = 400;
	}

}
