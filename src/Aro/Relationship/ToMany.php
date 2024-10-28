<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToMany extends ActiveRecordRelationship {

	protected function fetch() {
		$id = $this->getForeignId($this->source);
		$where = $this->getWhereOrder([
			$this->getFullTargetColumn($this->foreign) => $id,
		]);
		$targets = $this->findMany($where);
		$targets = $this->keyByKey($targets);

		count($targets) and $this->loadEagers($targets);
		return $targets;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$ids = $this->getForeignIds($objects);

		$foreignColumn = $this->foreign;
		$where = $this->getWhereOrder([
			$this->getFullTargetColumn($foreignColumn) => $ids,
		]);

		$targets = $this->findMany($where);

		$grouped = [];
		foreach ( $targets as $target ) {
			if ( $key = $this->getKey($target) ) {
				$grouped[ $target->$foreignColumn ][$key] = $target;
			}
			else {
				$grouped[ $target->$foreignColumn ][] = $target;
			}
		}

		foreach ( $objects as $object ) {
			$object->setGot($name, $grouped[$object->getPKValue()] ?? []);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function toCount() : ToOneScalarTable {
		$table = call_user_func([$this->target, '_table']);
		return (new ToOneScalarTable($this->source, 'count(1)', $table, $this->foreign))
			->where($this->where)
			->default(0)
			->returnType('int');
	}

	public function getReturnType() {
		return '\\' . $this->target . '[]';
	}

}
