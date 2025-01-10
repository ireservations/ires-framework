<?php

namespace Framework\Http\Response;

class JsonResponse extends Response {

	protected $contentType = 'application/json';
	protected $contentTypeCharset = 'utf-8';

	public ?string $jsonp;

	/**
	 * @param AssocArray $data
	 */
	public function __construct( array $data, ?int $code = null, ?string $jsonp = null ) {
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

	public function __toString() {
		return json_encode($this->data);
	}

}
