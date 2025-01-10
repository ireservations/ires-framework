<?php

namespace Framework\Aro\Relationship;

class ToManyThroughProperty extends ToManyThrough {

	protected function getTargetIds( array $objects ) : array {
		$targetIds = [];
		foreach ( $objects as $object ) {
			$ids = (array) $object->{$this->throughRelationship};
			foreach ( $ids as $id ) {
				$targetIds[] = $id;
			}
		}

		return $targetIds;
	}

}
