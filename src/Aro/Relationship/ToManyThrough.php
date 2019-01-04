<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToManyThrough extends ActiveRecordRelationship {

	protected $throughRelationship;

	public function __construct( ActiveRecordObject $source = null, $targetClass, $throughRelationship ) {
		parent::__construct($source, $targetClass, null);

		$this->throughRelationship = $throughRelationship;
	}

	protected function getTargetIds( array $objects ) {
		/** @var ActiveRecordObject $class */
		$class = get_class($objects[0]);
		return call_user_func([$class, 'eager'], $this->throughRelationship, $objects);
	}

	protected function fetch() {
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
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
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

	public function getReturnType() {
		return '\\' . $this->target . '[]';
	}

}
