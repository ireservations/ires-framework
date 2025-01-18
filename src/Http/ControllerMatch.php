<?php

namespace Framework\Http;

class ControllerMatch {

	public function __construct(
		public readonly CompiledController $compiledCtrlr,
		/** @var list<mixed> */
		public readonly array $ctrlrArgs,
		public readonly string $actionPath,
	) {}

}
