<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Controller {

	public function __construct(
		public string $prefix,
	) {}

}
