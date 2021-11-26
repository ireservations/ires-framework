<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;

class ToManyScalar extends ToScalar {

	protected function fetch() {
		$db = $this->db();
		$id = $this->getForeignId($this->source, $this->local);

		$columns = $this->key ? "$this->key, $this->target" : $this->target;
		$joins = $this->buildJoins();
		$whereOrder = $this->getWhereOrder([$this->foreign => $id]);
		$values = $db->fetch_fields("
			select distinct $columns
			from {$this->getTargetTable()}
			$joins
			where $whereOrder
		");
		if ( !$this->key ) {
			$values = array_values($values);
		}

		return $this->castValues($values);
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects, $this->local);

		$keyColumn = $this->key ?: '1';
		$joins = $this->buildJoins();
		$whereOrder = $this->getWhereOrder([$this->foreign => $ids]);
		$links = $db->fetch("
			select distinct $this->foreign _foreign, $keyColumn _key, $this->target _value
			from {$this->getTargetTable()}
			$joins
			where $whereOrder
		");

		$objects = $this->keyByPk($objects, $this->local);

		$grouped = [];
		foreach ( $links as $link ) {
			if ( $this->key ) {
				$grouped[ $link['_foreign'] ][ $link['_key'] ] = $link['_value'];
			}
			else {
				$grouped[ $link['_foreign'] ][] = $link['_value'];
			}
		}

		foreach ( $objects as $object ) {
			$id = $this->getForeignId($object, $this->local);
			$object->setGot($name, $this->castValues($grouped[$id] ?? []));
		}

		return array_column($links, '_value');
	}

	/** @return  */
	public function getReturnType() {
		return $this->returnType . '[]';
	}

}
