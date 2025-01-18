<?php

namespace Framework\Http;

use App\Services\Http\AppController;

final class CompiledController {

	public function __construct(
		public readonly string $path,
		/** @var class-string<AppController> */
		public readonly string $class,
		/** @var AssocArray */
		public readonly array $options = [],
	) {}

	/**
	 * @param array{path: string, class: class-string<AppController>, options: AssocArray} $props
	 */
	static public function __set_state(array $props) : self {
		return new self($props['path'], $props['class'], $props['options']);
	}

}
