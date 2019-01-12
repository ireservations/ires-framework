<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;

class ToManyScalar extends ToScalar {

	protected function fetch() {
		$db = $this->db();
		$id = $this->getForeignId($this->source, $this->local);
		$values = array_values($db->select_fields($this->getTargetTable(), $this->target, [$this->foreign => $id]));
		return $this->castValues($values);
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects, $this->local);

		$links = $db->select($this->getTargetTable(), [$this->foreign => $ids]);

		$objects = $this->keyByPk($objects, $this->local);

		$grouped = [];
		foreach ( $links as $link ) {
			$grouped[ $link[$this->foreign] ][] = $link[$this->target];
		}

		foreach ( $objects as $object ) {
			$id = $this->getForeignId($object, $this->local);
			$object->setGot($name, $this->castValues($grouped[$id] ?? []));
		}

		return array_column($links, $this->target);
	}

	/** @return  */
	public function getReturnType() {
		return $this->returnType . '[]';
	}

}
