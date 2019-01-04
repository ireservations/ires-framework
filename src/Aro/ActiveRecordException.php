<?php

namespace Framework\Aro;

class ActiveRecordException extends \Exception {

	public $class;

	function __construct($a = '', $b = 0, $class = 'Unknown') {
		parent::__construct($a, $b);
		$this->class = $class;
	}

}
