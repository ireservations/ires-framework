<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Access implements ControllerAnnotationInterface {

	public function __construct(
		public string $name,
		public ?int $arg = null,
	) {}

	public function controllerName() : string {
		return 'accessZones';
	}

	public function controllerIsMultiple() : bool {
		return true;
	}

	public function controllerSingleValue() : mixed {
		return trim($this->name, '-+');
	}

}
