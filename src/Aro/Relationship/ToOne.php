<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToOne extends ActiveRecordRelationship {

	protected function fetch() {
		if ( $foreignId = $this->getForeignId($this->source, $this->foreign) ) {
			$object = call_user_func([$this->target, 'find'], $foreignId);
			$object and $this->loadEagers([$object]);
			return $object;
		}
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		$foreignIds = $this->getForeignIds($objects, $foreignColumn);
		$targets = call_user_func([$this->target, 'findManyByPK'], array_unique($foreignIds));
		$targets = $this->keyByKey($targets, $this->getTargetPk());

		foreach ( $objects as $object ) {
			$object->setGot($name, $targets[$object->$foreignColumn] ?? null);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() {
		return '?\\' . $this->target;
	}

}
