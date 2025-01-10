<?php

namespace Framework\Aro\Relationship;

use Closure;
use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

abstract class ToScalar extends ActiveRecordRelationship {

	protected string $throughTable;
	protected null|string|Closure $cast = null;

	protected string $returnType = 'string';

	public function __construct( ?ActiveRecordObject $source, string $targetColumn, string $throughTable, string $foreignColumn, ?string $localColumn = null ) {
		parent::__construct($source, $targetColumn, $foreignColumn, $localColumn);

		$this->throughTable = $throughTable;
	}

	/**
	 * @return $this
	 */
	public function returnType( string $type ) {
		$this->returnType = $type;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function cast( null|string|Closure $callback ) {
		$this->cast = $callback;
		return $this;
	}

	protected function getTargetTable() : string {
		return $this->throughTable;
	}

	protected function castValue( mixed $value ) : null|bool|int|float|string {
		return $this->cast ? call_user_func($this->cast, $value) : $value;
	}

	/**
	 * @param array<null|bool|int|float|string> $values
	 * @return array<null|bool|int|float|string>
	 */
	protected function castValues( array $values ) : array {
		return $this->cast ? array_map($this->cast, $values) : $values;
	}

}
