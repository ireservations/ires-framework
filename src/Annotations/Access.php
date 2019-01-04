<?php

namespace Framework\Annotations;

/**
 * @Annotation
 */
class Access {
	/** @var string */
	public $value;

	/** @var int */
	public $arg = -1;
}
