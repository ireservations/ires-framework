<?php

$finder = (new PhpCsFixer\Finder())
	->in([
		'src',
	]);

return (new PhpCsFixer\Config())
	->setCacheFile('/tmp/php-cs-fixer-iresfw')
	->setUsingCache(false)
	->setRules([
		'no_unused_imports' => true,
		'phpdoc_line_span' => [
			'const' => 'single',
			'property' => 'single',
		],
	])
	->setIndent("\t")
	->setLineEnding("\n")
	->setFinder($finder);
