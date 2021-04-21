<?php

namespace Framework\Http\Response;

class TextResponse extends Response {

	protected $contentType = 'text/plain';
	protected $contentTypeCharset = self::CHARSET_UTF8;

	public function printContent() {
		echo $this->data;
	}

	public function __toString() {
		return $this->data;
	}

}
