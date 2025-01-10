<?php

namespace Framework\Tpl;

use JsonSerializable;

class HtmlString implements JsonSerializable {

	public function __construct(
		protected string $string,
	) {}

	public function __toString() : string {
		return $this->string;
	}

	public function jsonSerialize() : mixed {
		return $this->string;
	}
}
