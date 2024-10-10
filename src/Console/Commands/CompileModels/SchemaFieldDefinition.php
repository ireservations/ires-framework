<?php

namespace Framework\Console\Commands\CompileModels;

class SchemaFieldDefinition {

	public function __construct(
		protected string $dbType,
		protected bool $nullable,
	) {}

	public function getPhpType() : string {
		if ( in_array($this->dbType, ['TINYINT', 'SMALLINT', 'INT', 'BIGINT']) ) {
			return 'int';
		}

		if ( in_array($this->dbType, ['DECIMAL', 'FLOAT']) ) {
			return 'float';
		}

		return 'string';
	}

	public function getNullablePhpType() : string {
		$nullable = $this->nullable ? '?' : '';
		return $nullable . $this->getPhpType();
	}

}
