<?php

namespace Framework\Annotations;

/**
 * @Annotation
 */
class Access {
	const NO_ARG = -1;

	/** @var string */
	public $value;

	/** @var int */
	public $arg = self::NO_ARG;

	/** @var int[] */
	public $args = [];

	/** @return int[] */
	public function getArgs() {
		if ($this->arg != self::NO_ARG) {
			return [$this->arg];
		}

		return $this->args;
	}
}
