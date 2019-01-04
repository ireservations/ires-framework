<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToOneScalarTable extends ActiveRecordRelationship {

	/** @var string */
	protected $target;
	protected $throughTable;

	protected $returnType = 'string';

	public function __construct( ActiveRecordObject $source = null, $targetColumn, $throughTable, $foreignColumn ) {
		parent::__construct($source, $targetColumn, $foreignColumn);

		$this->throughTable = $throughTable;
	}

	public function returnType( $type ) {
		$this->returnType = $type;
		return $this;
	}

	protected function getTargetTable() {
		return $this->throughTable;
	}

	protected function fetch() {
		$db = $this->db();

		$qForeignColumn = $this->getFullTargetColumn($this->foreign);
		$where = $this->getWhereOrder([
			$qForeignColumn => $this->source->getPKValue(),
		]);

		$table = $this->getTargetTable();
		$joins = $this->buildJoins();
		$sql = "
			select $this->target
			from $table
			$joins
			where $where
		";
		$value = $db->fetch_one($sql);
		return $value === null || $value === false ? $this->default : $value;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects);

		$foreignColumn = $this->foreign;
		$qForeignColumn = $this->getFullTargetColumn($foreignColumn);
		$where = $this->getWhereOrder([
			$qForeignColumn => $ids,
		]);

		$table = $this->getTargetTable();
		$joins = $this->buildJoins();
		$sql = "
			select $qForeignColumn, $this->target
			from $table
			$joins
			where $where
			group by $qForeignColumn
		";
		$targets = $db->fetch_fields($sql);

		foreach ( $objects as $object ) {
			$object->setGot($name, $targets[ $object->getPKValue() ] ?? $this->default);
		}

		return $targets;
	}

	public function getReturnType() {
		return $this->returnType;
	}

}
