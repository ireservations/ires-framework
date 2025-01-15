<?php

/** @var list<TestModel2> */
$objs = [];

\PHPStan\Testing\assertType(
	'array<int, string>',
	aro_options($objs),
);
\PHPStan\Testing\assertType(
	'array<int, string>',
	aro_options($objs, null, null),
);
\PHPStan\Testing\assertType(
	'array<int, string>',
	aro_options($objs, 'test_string'),
);
\PHPStan\Testing\assertType(
	'array<int, int>',
	aro_options($objs, 'test_int'),
);
\PHPStan\Testing\assertType(
	'array<int, string>',
	aro_options($objs, null, 'test_int'),
);
\PHPStan\Testing\assertType(
	'array<int, string>',
	aro_options($objs, 'test_model.test_string'),
);
\PHPStan\Testing\assertType(
	'array<string, string>',
	aro_options($objs, 'test_model.test_string', 'test_model.test_string'),
);
\PHPStan\Testing\assertType(
	'array<string, int>',
	aro_options($objs, 'test_model.test_int', 'test_model.test_string'),
);

\PHPStan\Testing\assertType(
	'array<float|int|string>',
	aro_options($objs, 'test_model.foo', 'test_int'), // @phpstan-ignore argument.type
);
