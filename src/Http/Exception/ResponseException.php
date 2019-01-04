<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\Response;
use Exception;

class ResponseException extends Exception {

	protected $response;

	public function __construct( Response $response ) {
		parent::__construct('');

		$this->response = $response;
	}

	public function getResponse() {
		return $this->response;
	}

}
