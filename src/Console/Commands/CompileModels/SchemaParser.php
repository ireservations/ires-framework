<?php

namespace Framework\Console\Commands\CompileModels;

use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use RuntimeException;

/**
 * @phpstan-type TableColumns array<string, SchemaFieldDefinition>
 * @phpstan-type AllColumns array<string, TableColumns>
 */
class SchemaParser {

	/** @var AllColumns */
	protected array $allColumns;

	public function __construct(
		protected string $filepath,
	) {}

	/**
	 * @return AllColumns
	 */
	public function getAllColumns() : array {
		return $this->allColumns ??= $this->makeAllColumns();
	}

	/**
	 * @return TableColumns
	 */
	public function getTableColumns(string $table) : array {
		return $this->getAllColumns()[$table] ?? [];
	}

	/**
	 *
	 */
	public function getColumn(string $table, string $column) : ?SchemaFieldDefinition {
		return $this->getTableColumns($table)[$column] ?? null;
	}

	/**
	 * @return AllColumns
	 */
	protected function makeAllColumns() : array {
		if (!$this->filepath) {
			return [];
		}

		if (!file_exists($this->filepath)) {
			throw new RuntimeException(sprintf("SQL structure dump file '%s' does not exist.", $this->filepath));
		}

		$dumpSql = file_get_contents($this->filepath);

		$schemaParser = new Parser($dumpSql);

		$tables = [];
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
