<?php

use Framework\Aro\ActiveRecordObject;

class TestModel1 extends ActiveRecordObject {
	protected static $_pk = 'test_int';

	public int $id = 1;

	public int $test_int = 123;
	public string $test_string = 'asd';

	/**
	 * @param aro-property<self> $prop
	 */
	public function getPropValue(string $prop) : mixed {
		return $this->$prop;
	}
}
