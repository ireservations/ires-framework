<?php

namespace Framework\Tpl;

use JsonSerializable;

class HtmlString implements JsonSerializable {

	protected $string;

	public function __construct( $string ) {
		$this->string = $string;
	}

	public function __toString() {
		return (string) $this->string;
	}

	public function jsonSerialize() : mixed {
		return (string) $this->string;
	}
}
