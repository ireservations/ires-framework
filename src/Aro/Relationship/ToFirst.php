<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToFirst extends ActiveRecordRelationship {

	public function __construct( ActiveRecordObject $source = null, $targetClass, $relationColumn ) {
		parent::__construct($source, $targetClass, $relationColumn);
	}

	protected function fetch() {
		$foreignId = $this->getForeignId($this->source);
		if ( !$foreignId ) return;

		$where = $this->getWhereOrder([$this->foreign => $foreignId]);

		$object = call_user_func([$this->target, 'findFirst'], $where);
		$object and $this->loadEagers([$object]);
		return $object;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		$objects = $this->keyByPk($objects);

		$foreignIds = $this->getForeignIds($objects);
		$where = $this->getWhereOrder([$this->foreign => $foreignIds]);
		$targets = call_user_func([$this->target, 'findMany'], $where);

		foreach ( $objects as $object ) {
			$object->setGot($name, null);
		}

		foreach ( $targets as $target ) {
			$fk = $target->$foreignColumn;
			if ( $fk && isset($objects[$fk]) ) {
				$objects[$fk]->setGot($name, $target);
			}
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() {
		return '\\' . $this->target;
	}

}
