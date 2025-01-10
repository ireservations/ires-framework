<?php

namespace Framework\Aro;

class ActiveRecordCache {

	/** @var array<class-string<ActiveRecordObject>, array<int, ActiveRecordObject>> */
	protected array $objects = [];

	/**
	 * @template T of ActiveRecordObject
	 * @param T[] $objects
	 * @param-out T[] $objects
	 */
	public function addMany( array &$objects ) : void {
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
	 * @template T of ActiveRecordObject
	 * @param class-string<T> $class
	 * @param T $object
	 */
	public function addOne( string $class, string $id, ActiveRecordObject $object ) : void {
		$this->objects[$class][$id] = $object;
	}

	/**
	 * @template T of ActiveRecordObject
	 * @param class-string<T> $class
	 * @return T
	 */
	public function get( string $class, string $id ) : ActiveRecordObject {
		return $this->objects[$class][$id];
	}

	public function has( string $class, string $id ) : bool {
		return isset($this->objects[$class][$id]);
	}

}
