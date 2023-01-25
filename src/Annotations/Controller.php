<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Controller {

	public string $prefix;

	public function __construct(
		string $prefix,
	) {
		$this->prefix = trim($prefix, '/');
	}

}
