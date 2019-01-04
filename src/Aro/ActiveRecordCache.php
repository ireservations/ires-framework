<?php

namespace Framework\Aro;

class ActiveRecordCache {

	protected $objects = [];

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return void
	 */
	public function addMany( array &$objects ) {
		$class = null;
		foreach ( $objects as $i => $object ) {
			if ( !$class ) {
				$class = get_class($object);
			}

			$id = (string) $object->getPKValue();
			if ( $id ) {
				if ( $this->has($class, $id) ) {
					$objects[$i] = $this->get($class, $id);
				}
				else {
					$this->addOne($class, $id, $object);
				}
			}
		}
	}

	/**
	 * @return void
	 */
	public function addOne( $class, $id, ActiveRecordObject $object ) {
		$this->objects[$class][$id] = $object;
	}

	/**
	 * @return ActiveRecordObject
	 */
	public function get( $class, $id ) {
		return $this->objects[$class][$id];
	}

	/**
	 * @return bool
	 */
	public function has( $class, $id ) {
		return isset($this->objects[$class][$id]);
	}

}
