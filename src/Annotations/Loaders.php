<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_ALL)]
class Loaders {

	public function __construct(
		public array $methods,
	) {}

}
