<?php

namespace Framework\Aro;

use App\Services\Aro\AppActiveRecordObject;

class ActiveRecordCache {

	/** @var array<class-string<AppActiveRecordObject>, array<int, AppActiveRecordObject>> */
	protected array $objects = [];

	/**
	 * @param AppActiveRecordObject[] $objects
	 * @param-out AppActiveRecordObject[] $objects
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

	public function addOne( string $class, string $id, AppActiveRecordObject $object ) : void {
		$this->objects[$class][$id] = $object;
	}

	public function get( string $class, string $id ) : AppActiveRecordObject {
		return $this->objects[$class][$id];
	}

	public function has( string $class, string $id ) : bool {
		return isset($this->objects[$class][$id]);
	}

}
