<?php

namespace Framework\Console\Commands\CompileModels;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IncludesTablesAttribute {

	public function __construct(
		protected array $tables,
	) {}

	public function getTables() : array {
		return $this->tables;
	}

}
