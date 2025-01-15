<?php

use Framework\Aro\ActiveRecordObject;

class TestModel2 extends ActiveRecordObject {
	protected static $_pk = 'id';

	public int $id = 1;

	public int $test_int = 123;
	public string $test_string = 'asd';

	protected function get_test_model() : TestModel1 {
		return new TestModel1();
	}
}
