<?php

namespace Framework\Console\Commands\CompileModels;

use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

class SchemaParser {

	static public function parseDumpSqlTables( string $dumpSql ) : array {
		$schemaParser = new Parser($dumpSql);

		$tables = [];
// $dbTypes = [];
		foreach ( $schemaParser->statements as $createTable ) {
			if ( !($createTable instanceof CreateStatement) ) continue;
			if ( !$createTable->name || !$createTable->name->table ) continue;

			$fields = [];
			foreach ( $createTable->fields as $createField ) {
				if ( !$createField->name || !$createField->type ) continue;

				$dbType = $createField->type->name;
				if ( $dbType == 'ENUM' && self::enumIsInt($createField->type) ) {
					$dbType = 'INT';
				}
// $dbTypes[] = $dbType;

				$fields[$createField->name] = new SchemaFieldDefinition(
					$dbType,
					!$createField->options->has('NOT NULL'),
				);
			}
			$tables[$createTable->name->table] = $fields;
		}
// dump(array_count_values($dbTypes));

		return $tables;
	}

	static protected function enumIsInt( DataType $enumType ) : bool {
		$numbers = array_map(function(string $value) {
			return preg_match("#^'\d+'$#", $value) ? 1 : 0;
		}, $enumType->parameters);
		return array_sum($numbers) == count($numbers);
	}

}
