<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

abstract class ToScalar extends ActiveRecordRelationship {

	/** @var string */
	protected $target;
	protected $throughTable;
	protected $cast;

	protected $returnType = 'string';

	public function __construct( ActiveRecordObject $source = null, $targetColumn, $throughTable, $foreignColumn, $localColumn = null ) {
		parent::__construct($source, $targetColumn, $foreignColumn, $localColumn);

		$this->throughTable = $throughTable;
	}

	public function returnType( $type ) {
		$this->returnType = $type;
		return $this;
	}

	public function cast( $callback ) {
		$this->cast = $callback;
		return $this;
	}

	protected function getTargetTable() {
		return $this->throughTable;
	}

	protected function castValue( $value ) {
		return $this->cast ? call_user_func($this->cast, $value) : $value;
	}

	protected function castValues( $values ) {
		return $this->cast ? array_map($this->cast, $values) : $values;
	}

}
