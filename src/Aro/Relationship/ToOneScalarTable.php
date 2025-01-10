<?php

namespace Framework\Aro\Relationship;


class ToOneScalarTable extends ToScalar {

	protected function fetch() : null|bool|int|float|string {
		$db = $this->db();

		$qForeignColumn = $this->getFullTargetColumn($this->foreign);
		$where = $this->getWhereOrder([
			$qForeignColumn => $this->getForeignId($this->source, $this->local),
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
		return $value === null || $value === false ? $this->default : $this->castValue($value);
	}

	/**
	 * @return array<array-key, ?scalar>
	 */
	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects, $this->local);

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
			$id = $this->getForeignId($object, $this->local);
			$object->setGot($name, $this->castValue($targets[$id] ?? $this->default));
		}

		return $targets;
	}

	public function getReturnType() : string {
		$nullable = $this->default === null ? '|null' : '';
		return $this->returnType . $nullable;
	}

}
