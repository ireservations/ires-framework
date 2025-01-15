<?php

use Framework\Aro\ActiveRecordObject;

/** @var list<TestModel1> */
$objs = [];

\PHPStan\Testing\assertType(
	'array<int, TestModel1>',
	aro_key($objs),
);
\PHPStan\Testing\assertType(
	'array<int, TestModel1>',
	aro_key($objs, 'test_int'),
);
\PHPStan\Testing\assertType(
	'array<string, TestModel1>',
	aro_key($objs, 'test_string'),
);

/** @var list<TestModel2> */
$objs = [];

\PHPStan\Testing\assertType(
	'array<int, TestModel2>',
	aro_key($objs),
);
\PHPStan\Testing\assertType(
	'array<string, TestModel2>',
	aro_key($objs, 'test_string'),
);
\PHPStan\Testing\assertType(
	'array<TestModel2>',
	aro_key($objs, 'foo'), // @phpstan-ignore argument.type
);
\PHPStan\Testing\assertType(
	'array<TestModel2>',
	aro_key($objs, 'test_model.test_int'), // @phpstan-ignore argument.type
);
