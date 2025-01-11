<?php

namespace Framework\Aro\Relationship;


class ToManyScalar extends ToScalar {

	protected ?string $keyType = null;

	/**
	 * @return $this
	 */
	public function keyType( ?string $type ) {
		$this->keyType = $type;
		return $this;
	}

	/**
	 * @return array<array-key, ?scalar>
	 */
	protected function fetch() : array {
		$db = $this->db();
		$id = $this->getForeignId($this->source, $this->local);

		$columns = $this->key ? "$this->key, $this->target" : $this->target;
		$joins = $this->buildJoins();
		$whereOrder = $this->getWhereOrder([$this->foreign => $id]);
		$values = $db->fetch_fields("
			select distinct $columns
			from {$this->getTargetTable()}
			$joins
			where $whereOrder
		");
		if ( !$this->key ) {
			$values = array_values($values);
		}

		return $this->castValues($values);
	}

	/**
	 * @return array<array-key, ?scalar>
	 */
	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects, $this->local);

		$keyColumn = $this->key ?: '1';
		$joins = $this->buildJoins();
		$whereOrder = $this->getWhereOrder([$this->foreign => $ids]);
		$links = $db->fetch("
			select distinct $this->foreign _foreign, $keyColumn _key, $this->target _value
			from {$this->getTargetTable()}
			$joins
			where $whereOrder
		");

		$objects = $this->keyByPk($objects, $this->local);

		$grouped = [];
		foreach ( $links as $link ) {
			if ( $this->key ) {
				$grouped[ $link['_foreign'] ][ $link['_key'] ] = $link['_value'];
			}
			else {
				$grouped[ $link['_foreign'] ][] = $link['_value'];
			}
		}

		foreach ( $objects as $object ) {
			$id = $this->getForeignId($object, $this->local);
			$object->setGot($name, $this->castValues($grouped[$id] ?? []));
		}

		return array_column($links, '_value');
	}

	public function getReturnType() : string {
		if ($this->keyType) {
			return sprintf('array<%s, %s>', $this->keyType, $this->returnType);
		}

		return sprintf('array<%s>', $this->returnType);
	}

}
