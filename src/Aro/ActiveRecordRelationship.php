<?php

namespace Framework\Aro;

use db_generic;

abstract class ActiveRecordRelationship {

	protected string $name;
	/** @var list<string> */
	protected array $eager = [];
	protected ?string $where = null;
	protected ?string $order = null;
	protected null|false|string $key = null;
	/** @var list<array{string, string}> */
	protected array $joins = [];
	protected mixed $default = null;

	public function __construct(
		protected ActiveRecordObject $source,
		/** @var class-string<ActiveRecordObject> */
		protected string $target,
		protected ?string $foreign,
		protected ?string $local = null,
	) {}

	public function load() : mixed {
		return $this->fetch();
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return ActiveRecordObject[]
	 */
	public function loadAll( array $objects ) : array {
		return count($objects) ? $this->fetchAll($objects) : [];
	}

	abstract public function getReturnType() : string;

	/**
	 * @param ActiveRecordObject[] $targets
	 */
	protected function loadEagers( array $targets ) : void {
		$target = reset($targets);
		foreach ( $this->eager as $name ) {
			$target::eager($name, $targets);
		}
	}

	abstract protected function fetch() : mixed;

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return mixed[]
	 */
	abstract protected function fetchAll( array $objects ) : array;

	/**
	 * @return $this
	 */
	public function name( string $name ) {
		$this->name = $name;
		return $this;
	}

	/**
	 * @param list<string> $names
	 * @return $this
	 */
	public function eager( array $names ) {
		$this->eager = $names;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function where( ?string $where, mixed ...$params ) {
		$this->where = !$where ? null : $this->db()->qmarks($where, ...$params);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function order( ?string $order ) {
		$this->order = $order;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function key( null|false|string $key ) {
		$this->key = $key;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function default( mixed $default ) {
		$this->default = $default;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function join( string $table, string $on, mixed ...$params ) {
		$on = $this->db()->qmarks($on, ...$params);
		$this->joins[] = [$table, $on];
		return $this;
	}

	protected function db() : db_generic {
		return ActiveRecordObject::getDbObject();
	}

	/**
	 * @param list<string>|array<array-key, mixed> $conditions
	 */
	protected function getWhereOrder( array $conditions ) : string {
		$db = $this->db();
		$conditions = $db->stringifyConditions($conditions);
		if ( $this->where ) $conditions .= ' AND ' . $this->where;
		$order = $this->order ? " ORDER BY {$this->order}" : '';
		return $conditions . $order;
	}

	protected function buildJoins() : string {
		$joins = [];
		foreach ( $this->joins as list($table, $on) ) {
			$joins[] = "join $table on $on";
		}
		return implode("\n", $joins);
	}

	protected function getForeignId( ActiveRecordObject $object, ?string $column = null ) : ?int {
		return $column ? $object->$column : $object->getPKValue();
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return list<int>
	 */
	protected function getForeignIds( array $objects, ?string $column = null ) : array {
		$ids = [];
		foreach ( $objects as $object ) {
			$ids[] = $column ? $object->$column : $object->getPKValue();
		}

		return array_filter($ids);
	}

	protected function getKey( ActiveRecordObject $object ) : ?int {
		if ( $this->key === null ) {
			return $object->getPKValue();
		}

		if ( $this->key ) {
			return $object->{$this->key};
		}

		return null;
	}

	/**
	 * @param ActiveRecordObject[] $objects
	 * @return ActiveRecordObject[]
	 */
	protected function keyByPk( array $objects, ?string $key = null ) : array {
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
	protected function keyByKey( array $objects, ?string $key = null ) : array {
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
	protected function findMany( string $where ) : array {
		$targets = call_user_func([$this->target, 'findMany'], $where);
		return $targets;
	}

	protected function getTargetTable() : string {
		$targetClass = $this->target;
		return $targetClass::_table();
	}

	protected function getTargetPk() : string {
		$targetClass = $this->target;
		return $targetClass::_pk();
	}

	protected function getFullTargetColumn( string $column ) : string {
		return $this->getTargetTable() . '.' . $column;
	}

}
