<?php

namespace Framework\Console\Commands\CompileModels;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IncludesTablesAttribute {

	public function __construct(
		/** @var list<string> */
		protected array $tables,
	) {}

	/**
	 * @return list<string>
	 */
	public function getTables() : array {
		return $this->tables;
	}

}
