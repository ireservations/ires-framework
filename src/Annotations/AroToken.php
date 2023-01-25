<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class AroToken {

	public function __construct(
		public ?int $arg = null,
	) {}
}
