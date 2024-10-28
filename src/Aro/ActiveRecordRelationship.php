<?php

namespace Framework\Aro;

use db_generic;

abstract class ActiveRecordRelationship {

	protected $name;
	protected $eager = [];
	/** @var ActiveRecordObject */
	protected $source;
	/** @var ActiveRecordObject */
	protected $target;
	protected $foreign;
	protected $local;
	protected $where;
	protected $order;
	protected $key;
	protected $joins = [];
	protected $default = null;

	public function __construct( ActiveRecordObject $source = null, $targetClass, $foreignColumn, $localColumn = null ) {
		$this->source = $source;
		$this->target = $targetClass;
		$this->foreign = $foreignColumn;
		$this->local = $localColumn;
	}

	public function load() {
		return $this->fetch();
	}

	public function loadAll( array $objects ) {
		return count($objects) ? $this->fetchAll($objects) : [];
	}

	abstract public function getReturnType();

	protected function loadEagers( array $targets ) {
		$target = reset($targets);
		foreach ( $this->eager as $name ) {
			$target::eager($name, $targets);
		}
	}

	abstract protected function fetch();

	abstract protected function fetchAll( array $objects );

	/** @return $this */
	public function name( $name ) {
		$this->name = $name;
		return $this;
	}

	/** @return $this */
	public function eager( array $names ) {
		$this->eager = $names;
		return $this;
	}

	/** @return $this */
	public function where( $where, ...$params ) {
		$this->where = $this->source->db->qmarks($where, ...$params);
		return $this;
	}

	/** @return $this */
	public function order( $order ) {
		$this->order = $order;
		return $this;
	}

	/** @return $this */
	public function key( $key ) {
		$this->key = $key;
		return $this;
	}

	/** @return $this */
	public function default( $default ) {
		$this->default = $default;
		return $this;
	}

	/** @return $this */
	public function join( $table, $on, ...$params ) {
		$on = $this->source->db->qmarks($on, ...$params);
		$this->joins[] = [$table, $on];
		return $this;
	}

	/**
	 * @return db_generic
	 */
	protected function db() {
		/** @var db_generic $db */
		global $db;
		return $db;
	}

	protected function getWhereOrder( array $conditions ) {
		$db = $this->db();
		$conditions = $db->stringifyConditions($conditions);
		$this->where and $conditions .= ' AND ' . $this->where;
		$order = $this->order ? " ORDER BY {$this->order}" : '';
		return $conditions . $order;
	}

	protected function buildJoins() {
		$joins = [];
		foreach ( $this->joins as list($table, $on) ) {
			$joins[] = "join $table on $on";
		}
		return implode("\n", $joins);
	}

	/**
	 * @param ActiveRecordObject $object
	 */
	protected function getForeignId( $object, $column = null ) {
		return $column ? $object->$column : $object->getPKValue();
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 */
	protected function getForeignIds( array $objects, $column = null ) {
		$ids = [];
		foreach ( $objects as $object ) {
			$ids[] = $column ? $object->$column : $object->getPKValue();
		}

		return array_filter($ids);
	}

	protected function getKey( ActiveRecordObject $object ) {
		if ( $this->key === null ) {
			return $object->getPKValue();
		}

		if ( $this->key ) {
			return $object->{$this->key};
		}
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return ActiveRecordObject[]
	 */
	protected function keyByPk( array $objects, $key = null ) {
		$keyed = [];
		foreach ( $objects as $object ) {
			$id = $key ? $object->$key : $object->getPKValue();
			if ( $id ) {
				$keyed[$id] = $object;
			}
		}

		return $keyed;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return ActiveRecordObject[]
	 */
	protected function keyByKey( array $objects, $key = null ) {
		$targetClass = $this->target;
		$key = $key ?? $this->key ?? $targetClass::_pk();

		if ( !$key ) {
			return $objects;
		}

		$keyed = [];
		foreach ( $objects as $object ) {
			$keyed[ $object->$key ] = $object;
		}

		return $keyed;
	}

	/**
	 * @return ActiveRecordObject[]
	 */
	protected function findMany( $where ) {
		$targets = call_user_func([$this->target, 'findMany'], $where);
		return $targets;
	}

	protected function getTargetTable() {
		$targetClass = $this->target;
		return $targetClass::_table();
	}

	protected function getTargetPk() {
		$targetClass = $this->target;
		return $targetClass::_pk();
	}

	protected function getFullTargetColumn( $column ) {
		return $this->getTargetTable() . '.' . $column;
	}

}
