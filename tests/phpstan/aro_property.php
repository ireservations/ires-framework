<?php

$obj = new TestModel1();

\PHPStan\Testing\assertType(
	'mixed',
	$obj->getPropValue('test_int'),
);
\PHPStan\Testing\assertType(
	'mixed',
	$obj->getPropValue('foo'), // @phpstan-ignore argument.type
);
