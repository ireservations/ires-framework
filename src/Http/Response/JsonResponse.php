<?php

namespace Framework\Http\Response;

class JsonResponse extends Response {

	protected $contentType = 'application/json';
	protected $contentTypeCharset = 'utf-8';

	public $jsonp;

	public function __construct( array $data, $code = null, $jsonp = null ) {
		parent::__construct($data, $code);

		$this->jsonp = $jsonp;
	}

	public function printHeaders() {
		$this->printResponseCode();
		$this->printDebugHeaders();

		if ( $this->jsonp ) {
			$this->contentType = 'application/javascript';
		}
		$this->printContentType();
	}

	public function printContent() {
		$json = json_encode($this->data);

		if ( $this->jsonp ) {
			echo "{$this->jsonp}({$json})";
		}
		else {
			echo $json;
		}
	}

}
