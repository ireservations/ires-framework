<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route {

	public string $method = 'all';

	final public function __construct(
		public readonly string $path,
		public readonly ?string $name = null,
		/** @var AssocArray */
		public readonly array $options = [],
	) {}

}
