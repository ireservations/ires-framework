<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToManyThrough extends ActiveRecordRelationship {

	protected string $throughRelationship;

	public function __construct( ?ActiveRecordObject $source, string $targetClass, string $throughRelationship ) {
		parent::__construct($source, $targetClass, null);

		$this->throughRelationship = $throughRelationship;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return int[]
	 */
	protected function getTargetIds( array $objects ) : array {
		$class = get_class($objects[0]);
		return call_user_func([$class, 'eager'], $this->throughRelationship, $objects);
	}

	/**
	 * @return ActiveRecordObject[]
	 */
	protected function fetch() : array {
		$targetIds = $this->source->{$this->throughRelationship};
		if ( count($targetIds) == 0 ) {
			return [];
		}

		$where = $this->getWhereOrder([
			$this->getFullTargetColumn($this->getTargetPk()) => $targetIds,
		]);
		$targets = $this->findMany($where);
		$targets = $this->keyByKey($targets);

		count($targets) and $this->loadEagers($targets);
		return $targets;
	}

	/**
	 * @return ActiveRecordObject[]
	 */
	protected function fetchAll( array $objects ) : array {
		$name = $this->name;

		$targetIds = $this->getTargetIds($objects);
		if ( count($targetIds) == 0 ) {
			return [];
		}

		$where = $this->getWhereOrder([
			$this->getFullTargetColumn($this->getTargetPk()) => array_unique($targetIds),
		]);
		$targets = $this->findMany($where);
		$targets = $this->keyByKey($targets, $this->getTargetPk());

		$grouped = [];
		foreach ( $objects as $object ) {
			foreach ( $object->{$this->throughRelationship} as $id ) {
				if ( $target = $targets[$id] ?? null ) {
					$grouped[$object->getPKValue()][] = $target;
				}
			}
		}

		foreach ( $objects as $object ) {
			$object->setGot($name, $grouped[$object->getPKValue()] ?? []);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return '\\' . $this->target . '[]';
	}

}
