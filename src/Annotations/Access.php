<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Access {

	public function __construct(
		public string $name,
		public ?int $arg = null,
	) {}

}
