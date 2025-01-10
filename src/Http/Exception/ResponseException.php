<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\Response;
use Exception;

class ResponseException extends Exception {

	public function __construct(
		protected Response $response,
	) {
		parent::__construct('');
	}

	public function getResponse() : Response {
		return $this->response;
	}

}
