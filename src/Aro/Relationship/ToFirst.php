<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToFirst extends ActiveRecordRelationship {

	protected ?string $localColumn = null;

	public function __construct( ?ActiveRecordObject $source, string $targetClass, string $relationColumn ) {
		parent::__construct($source, $targetClass, $relationColumn);
	}

	/**
	 * @return $this
	 */
	public function localColumn( ?string $column ) {
		$this->localColumn = $column;
		return $this;
	}

	protected function fetch() : ?ActiveRecordObject {
		$foreignId = $this->getForeignId($this->source, $this->localColumn);
		if ( !$foreignId ) return null;

		$where = $this->getWhereOrder([$this->foreign => $foreignId]);

		$object = call_user_func([$this->target, 'findFirst'], $where);
		$object and $this->loadEagers([$object]);
		return $object;
	}

	/**
	 * @return ActiveRecordObject[]
	 */
	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		foreach ( $objects as $object ) {
			$object->setGot($name, null);
		}

		$objects = $this->keyByPk($objects, $this->localColumn);
		if ( count($objects) == 0 ) return [];

		$foreignIds = $this->getForeignIds($objects, $this->localColumn);
		$where = $this->getWhereOrder([$this->foreign => $foreignIds]);
		$targets = call_user_func([$this->target, 'findMany'], $where);

		foreach ( $targets as $target ) {
			$fk = $target->$foreignColumn;
			if ( $fk && isset($objects[$fk]) && !$objects[$fk]->$name ) {
				$objects[$fk]->setGot($name, $target);
			}
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return '?\\' . $this->target;
	}

}
