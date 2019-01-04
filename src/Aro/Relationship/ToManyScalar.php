<?php

namespace Framework\Aro\Relationship;

use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;

class ToManyScalar extends ActiveRecordRelationship {

	/** @var string */
	protected $target;
	protected $throughTable;

	public function __construct( ActiveRecordObject $source = null, $targetColumn, $throughTable, $foreignColumn ) {
		parent::__construct($source, $targetColumn, $foreignColumn);

		$this->throughTable = $throughTable;
	}

	protected function fetch() {
		$db = $this->db();
		return array_values($db->select_fields($this->throughTable, $this->target, [$this->foreign => $this->source->getPKValue()]));
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = $this->getForeignIds($objects);

		$links = $db->select($this->throughTable, [$this->foreign => $ids]);

		$objects = $this->keyByPk($objects);

		$grouped = [];
		foreach ( $links as $link ) {
			$grouped[ $link[$this->foreign] ][] = $link[$this->target];
		}

		foreach ( $objects as $object ) {
			$object->setGot($name, $grouped[$object->getPKValue()] ?? []);
		}

		return array_column($links, $this->target);
	}

	/** @return  */
	public function getReturnType() {
		return 'int|string';
	}

}
