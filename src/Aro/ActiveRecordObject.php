<?php

namespace Framework\Aro;

use ArrayAccess;
use db_exception;
use db_generic;

/**
 * @property db_generic $db
 *
 * @implements ArrayAccess<string, mixed>
 *
 * @phpstan-import-type Where from db_generic
 * @phpstan-import-type Args from db_generic
 */
#[\AllowDynamicProperties]
abstract class ActiveRecordObject implements ArrayAccess {

	use ValidatesTokens;

	public const int ITERATOR_PAGE_SIZE = 500;


	/** Whether to allow all array access, or to debug_exit() */
	public static bool $allowArrayAccess = false;



	protected static db_generic $_db;

	public static function setDbObject( db_generic $db ) : void {
		self::$_db = $db;
	}

	public static function getDbObject() : db_generic {
		return self::$_db;
	}



	protected static ?ActiveRecordCache $_objectCache = null;

	/**
	 *
	 */
	final public static function setCache( ?ActiveRecordCache $cache = null ) : void {
		self::$_objectCache = $cache;
	}



	/**
	 * The table's name
	 *
	 * @var string
	 */
	protected static $_table = '';

	/**
	 * The name of the PK field
	 *
	 * @var string
	 */
	protected static $_pk = '';

	/**
	 * Auto-nullable fields
	 *
	 * @var string[]
	 */
	protected static $_nullables = [];



	/**
	 * Whether the db data have been loaded
	 */
	protected bool $_loaded = true;

	/**
	 * Magic getter cache
	 *
	 * @var AssocArray
	 */
	protected array $_got = [];

	/**
	 * Any cache for this specific entity
	 *
	 * @var AssocArray
	 */
	protected array $_cache = [];



	static public function _table() : string {
		return static::$_table;
	}


	static public function _pk() : string {
		return static::$_pk;
	}


	/**
	 * @return list<null|scalar>
	 */
	static public function getDistincts( string $field ) : array {
		return static::$_db->select_fields(static::$_table, $field, "$field IS NOT NULL GROUP BY $field ORDER BY $field");
	}

	/**
	 * @param Where $conditions
	 */
	static public function deletes( string|array $conditions, mixed ...$args ) : void {
		static::$_db->delete(static::$_table, $conditions, ...$args);
	}

	/**
	 * @param AssocArray $update
	 * @param Where $conditions
	 */
	static public function updates( array $update, string|array $conditions, mixed ...$args ) : void {
		static::$_db->update(static::$_table, $update, $conditions, ...$args);
	}



	protected function to_one( string $targetClass, string $foreignColumn ) : Relationship\ToOne {
		return new Relationship\ToOne($this, $targetClass, $foreignColumn);
	}

	protected function to_first( string $targetClass, string $relationColumn ) : Relationship\ToFirst {
		return new Relationship\ToFirst($this, $targetClass, $relationColumn);
	}

	protected function to_one_scalar_table( string $targetColumn, string $throughTable, string $foreignColumn, ?string $localColumn = null ) : Relationship\ToOneScalarTable {
		return new Relationship\ToOneScalarTable($this, $targetColumn, $throughTable, $foreignColumn, $localColumn);
	}

	protected function to_many( string $targetClass, string $foreignColumn ) : Relationship\ToMany {
		return new Relationship\ToMany($this, $targetClass, $foreignColumn);
	}

	protected function to_count( string $targetClass, string $foreignColumn ) : Relationship\ToOneScalarTable {
		$targetTable = call_user_func([$targetClass, '_table']);
		return (new Relationship\ToOneScalarTable($this, 'count(1)', $targetTable, $foreignColumn))
			->default(0)
			->cast('intval')
			->returnType('int');
	}

	protected function to_count_table( string $targetTable, string $foreignColumn ) : Relationship\ToOneScalarTable {
		return (new Relationship\ToOneScalarTable($this, 'count(1)', $targetTable, $foreignColumn))
			->default(0)
			->cast('intval')
			->returnType('int');
	}

	protected function to_many_through( string $targetClass, string $throughRelationsip ) : Relationship\ToManyThrough {
		return new Relationship\ToManyThrough($this, $targetClass, $throughRelationsip);
	}

	protected function to_many_through_property( string $targetClass, string $throughProperty ) : Relationship\ToManyThroughProperty {
		return new Relationship\ToManyThroughProperty($this, $targetClass, $throughProperty);
	}

	protected function to_many_scalar( string $targetColumn, string $throughTable, string $foreignColumn, ?string $localColumn = null ) : Relationship\ToManyScalar {
		return new Relationship\ToManyScalar($this, $targetColumn, $throughTable, $foreignColumn, $localColumn);
	}


	/**
	 * @param list<self> $objects
	 * @return list<self|scalar>
	 */
	static public function eager( string $name, array $objects ) : array {
		if ( count($objects) == 0 ) {
			return [];
		}

		/** @var ActiveRecordRelationship $relationship */
		$relationship = call_user_func([new static(), "relate_$name"]);
		return $relationship->name($name)->loadAll($objects);
	}

	/**
	 * @param array<self> $objects
	 * @param list<string> $names
	 */
	static public function eagers( array $objects, array $names ) : void {
		$return = [];
		foreach ( $names as $name ) {
			$parts = explode('.', $name);
			/** @var self[] $sources */
			$sources = count($parts) == 1 ? $objects : $return[ implode('.', array_slice($parts, 0, -1)) ];
			if ( count($sources) == 0 ) {
				$return[$name] = [];
				continue;
			}

			$class = get_class(array_first($sources));
			$return[$name] = call_user_func([$class, 'eager'], end($parts), $sources);
		}
	}



	/**
	 * @param AssocArray $data
	 */
	final public function __construct( $data = null ) {
		if ( is_array($data) && count($data) ) {
			$this->fill($data);
		}
	}


	/**
	 *
	 */
	public function __isset( string $name ) : bool {
		return $this->existsMagicProperty($name);
	}

	/**
	 *
	 */
	public function &__get( string $name ) : mixed {
		if ( $this->gotGot($name) ) {
			return $this->_got[$name];
		}

		if ( $this->existsRelationship($name) ) {
			$this->_got[$name] = $this->resolveRelationship($name);
			return $this->_got[$name];
		}

		if ( $this->existsGetter($name) ) {
			$this->_got[$name] = $this->resolveGetter($name);
			return $this->_got[$name];
		}

		if ( !$this->_loaded ) {
			$this->refresh();

			if ( property_exists($this, $name) ) {
				return $this->$name;
			}
		}

		$this->_got[$name] = null;
		return $this->_got[$name];
	}


	/**
	 * Getter for db object for any ARO in any project: $user->db.
	 */
	protected function get_db() : db_generic {
		return static::$_db;
	}


	protected function existsMagicProperty( string $name ) : bool {
		return $this->gotGot($name) || $this->existsGetter($name) || $this->existsRelationship($name);
	}

	public function existsGetter( string $name ) : bool {
		return method_exists($this, 'get_' . $name);
	}

	protected function resolveGetter( string $name ) : mixed {
		$method = [$this, 'get_' . $name];
		return call_user_func($method);
	}

	public function setGot( string $name, mixed $value ) : void {
		$this->_got[$name] = $value;
	}

	public function getGot( string $name ) : mixed {
		return $this->_got[$name] ?? null;
	}

	public function gotGot( string $name ) : bool {
		return array_key_exists($name, $this->_got);
	}

	public function existsRelationship( string $name ) : bool {
		return method_exists($this, 'relate_' . $name);
	}

	protected function resolveRelationship( string $name ) : mixed {
		$method = [$this, 'relate_' . $name];
		/** @var ActiveRecordRelationship $relationship */
		$relationship = call_user_func($method);
		return $relationship->name($name)->load();
	}


	/**
	 * @param string $clause
	 * @return string
	 */
	static public function getQuery( $clause ) {
		$szQuery = 'SELECT * FROM ' . static::$_table;
		if ( $clause ) {
			$szQuery .= ' WHERE ' . $clause;
		}
		return $szQuery;
	}


	/**
	 * Returns all records it finds
	 *
	 * @param Where $conditions
	 * @param null|string|Args $keyByOrArgs
	 * @return static[]
	 */
	static public function fetch( string|array $conditions, null|string|array $keyByOrArgs = null ) : array {
		if ( is_array($conditions) ) {
			$conditions = static::$_db->stringifyConditions($conditions);
		}

		$query = static::getQuery($conditions);
		$objects = static::byQuery($query, $keyByOrArgs);

		// Add to cache
		if ( count($objects) ) {
			self::_allToCache($objects);
		}

		return $objects;
	}


	/**
	 * Returns all records it finds
	 *
	 * @param Where $conditions
	 * @param Args $args
	 * @param AssocArray $options
	 * @return ActiveRecordFetchGenerator<static>
	 */
	static public function fetchIterator( string|array $conditions, array $args = [], array $options = [] ) : ActiveRecordFetchGenerator {
		if ( is_array($conditions) ) {
			$conditions = static::$_db->stringifyConditions($conditions);
		}

		return new ActiveRecordFetchGenerator(get_called_class(), $conditions, $args, $options);
	}


	/**
	 * Add/replace many records to/in the object cache
	 *
	 * @template T of self
	 * @param T[] $objects
	 * @param-out T[] $objects
	 */
	static public function _allToCache( array &$objects ) : void {
		if ( self::$_objectCache) {
			self::$_objectCache->addMany($objects);
		}
	}


	/**
	 * Add one record to the object cache
	 *
	 * @param null|int|string $id
	 */
	static public function _oneToCache( string $class, $id, self $object ) : void {
		if ( self::$_objectCache ) {
			self::$_objectCache->addOne($class, $id, $object);
		}
	}


	/**
	 * Get one record from the object cache
	 *
	 * @param null|int|string $id
	 * @return ?static
	 */
	static protected function _fromCache( $id ) {
		if ( self::$_objectCache ) {
			$class = get_called_class();
			$id = (string) $id;

			if ( self::$_objectCache->has($class, $id) ) {
				return self::$_objectCache->get($class, $id);
			}
		}

		return null;
	}


	/**
	 * @param null|int|string $id
	 * @return ?static
	 */
	static public function find( $id ) {
		if ( !$id ) {
			return null;
		}

		try {
			return static::byPK($id);
		}
		catch ( ActiveRecordException $ex ) {}

		return null;
	}

	/**
	 * @param null|int|string $id
	 * @return ?static
	 */
	static public function load( $id ) {
		return static::find($id);
	}


	/**
	 * Must return one record
	 *
	 * @param null|int|string $id
	 * @return static
	 */
	static public function byPK( $id ) {
		if ( $object = static::_fromCache($id) ) {
			return $object;
		}

		$query = static::$_table . '.' . static::$_pk . ' = ?';
		return static::findOne($query, $id);
	}


	/**
	 * Will return any first record that matches
	 *
	 * @param Where $conditions
	 * @return ?static
	 */
	static public function findFirst( string|array $conditions, mixed ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$objects = static::fetch("$conditions LIMIT 1");
		return $objects[0] ?? null;
	}


	/**
	 * Must return one record
	 *
	 * @param Where $conditions
	 * @return static
	 */
	static public function findOne( string|array $conditions, mixed ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$objects = static::fetch($conditions . ' LIMIT 2');

		if ( count($objects) != 1 ) {
			throw new ActiveRecordException('Not exactly one record found', count($objects), get_called_class());
		}

		return $objects[0];
	}


	/**
	 * @param null|string|Args $keyByOrArgs
	 * @return static[]
	 */
	static public function byQuery( string $query, null|string|array $keyByOrArgs = null ) : array {
		$fetchOption = [];
		$keyBy = null;
		if ( is_string($keyByOrArgs) ) {
			$keyBy = $keyByOrArgs;
		}
		elseif ( is_array($keyByOrArgs) ) {
			$fetchOption = $keyByOrArgs;
		}

		$records = static::$_db->fetch($query, $fetchOption);

		$objects = array();
		foreach ( $records AS $record ) {
			$object = new static($record);
			if ( $keyBy ) {
				$objects[ $object->$keyBy ] = $object;
			}
			else {
				$objects[] = $object;
			}
		}

		return $objects;
	}


	/**
	 * @param Args $args
	 * @param AssocArray $options
	 * @return ActiveRecordGenerator<static>
	 */
	static public function byQueryIterator( string $query, array $args = [], array $options = [] ) : ActiveRecordGenerator {
		return new ActiveRecordGenerator(get_called_class(), $query, $args, $options);
	}


	/**
	 * Returns 0 or more records - in assoc array
	 *
	 * @param Where $conditions
	 * @return static[]
	 */
	static public function findManyByField( string|array $conditions, string $field, mixed ...$args ) : array {
		$query = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::fetch($query, $field);
	}


	/**
	 * Returns 0 or more records
	 *
	 * @param Where $conditions
	 * @return static[]
	 */
	static public function findMany( string|array $conditions, mixed ...$args ) : array {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::fetch($conditions);
	}


	/**
	 * Returns records by PK search
	 *
	 * @param list<int|string> $ids
	 * @return static[]
	 */
	static public function findManyByPK( array $ids, bool $byField = false ) : array {
		if ( !$ids ) {
			return [];
		}

		$table = static::$_table;
		$pk = static::$_pk;
		$query = $table . '.' . $pk . ' IN (?)';

		if ( $byField ) {
			return static::findManyByField($query, $pk, $ids);
		}

		return static::findMany($query, $ids);
	}


	/**
	 * @param Where $conditions
	 * @return ?static
	 */
	static public function any( string|array $conditions = '1', mixed ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::findFirst("$conditions ORDER BY RAND()");
	}


	/**
	 * @param Where $conditions
	 */
	static public function count( string|array $conditions, mixed ...$args ) : int {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$szSqlQuery = static::getQuery($conditions);
		return static::$_db->count_rows($szSqlQuery);
	}



	/**
	 * @param array<int|string> $ids
	 * @param AssocArray $conditions
	 */
	static public function saveOrder( array $ids, string $column, array $conditions = [] ) : void {
		$db = static::$_db;
		$table = static::$_table;
		$pk = static::$_pk;

		$db->begin();
		foreach ( array_values($ids) as $o => $id) {
			$db->update($table, array($column => $o), array($pk => $id) + $conditions);
		}
		$db->commit();
	}



	/**
	 * Inserts a record into this table
	 *
	 * @param AssocArray $data
	 * @return static
	 */
	static public function insert( array $data ) {
		static::presave($data);

		static::$_db->insert(static::$_table, $data);
		$id = static::$_db->insert_id();
		if ( static::$_pk && $id ) {
			$data[static::$_pk] = $id;
		}
		$object = new static($data);
		$object->_loaded = false;
		if ( static::$_pk && $id  ) {
			static::_oneToCache(static::class, $id, $object);
		}
		return $object;
	}



	/**
	 * @return $this
	 */
	public function refresh() {
		$this->_loaded = true;

		// prepare fields & values
		$pk = static::$_pk;
		$id = property_exists($this, $pk) ? $this->$pk : null;

		if ( $id === null ) {
			return $this;
		}

		$field = static::$_table . '.' . $pk;
		$conditions = $field.' = '.static::$_db->escapeAndQuote($id);

		// fetch fressh data
		$query = $this->getQuery($conditions);
		$data = static::$_db->fetch($query);

		if ( !count($data) ) {
			throw new ActiveRecordException("Refresh-record doesn't exist!?", 0, get_class($this));
		}

		// remove cached getters/relations
		$this->clean();

		// populate object
		return $this->fill($data[0]);
	}


	/**
	 * Remove all properties
	 */
	public function clean() : void {
		$this->_got = [];
	}



	/**
	 * @return void
	 */
	public function init() {
		$this->initCache();
	}

	/**
	 *
	 */
	public function initCache() : void {
		$this->_got = [];
		$this->_cache = [];
	}

	protected function initTimes( string ...$fields ) : void {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) && is_string($this->$field) ) {
				$this->$field = substr($this->$field, 0 ,5);
			}
		}
	}

	protected function initFloats( string ...$fields ) : void {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) ) {
				$this->$field = (float) $this->$field;
			}
		}
	}

	protected function initInts( string ...$fields ) : void {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) ) {
				$this->$field = (int) $this->$field;
			}
		}
	}



	/**
	 * @param AssocArray $data
	 * @param-out AssocArray $data
	 * @return void
	 */
	static public function presave( array &$data ) {
		self::presaveId($data);
		self::presaveNullables($data);
	}

	/**
	 * @param AssocArray $data
	 * @param-out AssocArray $data
	 */
	static public function presaveNullables( array &$data ) : void {
		foreach ( static::$_nullables as $column ) {
			if ( isset($data[$column]) && ( $data[$column] === '' || $data[$column] === [] ) ) {
				$data[$column] = null;
			}
		}
	}

	/**
	 * @param AssocArray $data
	 * @param-out AssocArray $data
	 */
	static public function presaveId( array &$data ) : void {
		unset($data['id']);
	}

	/**
	 * @param AssocArray $data
	 * @param-out AssocArray $data
	 */
	static public function presaveTrim( array &$data ) : void {
		foreach ( $data as $column => $value ) {
			if ( is_string($value) ) {
				$data[$column] = trim($value);
			}
		}
	}

	/**
	 * @param AssocArray $data
	 * @param-out AssocArray $data
	 */
	static public function presaveCSVs( array &$data, string ...$fields ) : void {
		foreach ( $fields as $field ) {
			if ( isset($data[$field]) && is_array($data[$field]) ) {
				$data[$field] = implode(',', $data[$field]);
			}
		}
	}

	/**
	 * @param AssocArray $data
	 * @return void
	 */
	static public function presaveFloats( array &$data, string ...$fields ) {
		foreach ( $fields as $field ) {
			if ( isset($data[$field]) ) {
				$data[$field] = (float) $data[$field];
			}
		}
	}



	protected function _cache( string $type, string $key, callable $callback ) : mixed {
		if ( !isset($this->_cache[$type][$key]) ) {
			$value = $callback($this);
			$this->_cache[$type][$key] = isset($value) ? $value : false;
		}

		return $this->_cache[$type][$key];
	}



	/**
	 * @param list<string> $props
	 * @return AssocArray
	 */
	public function extractOnly( array $props ) : array {
		$data = [];
		foreach ( $props as $item ) {
			$data[$item] = $this->$item;
		}

		return $data;
	}


	/**
	 * @param array<int|string, mixed> $updates
	 * @return bool
	 */
	public function update( array $updates ) {
		static::presave($updates);

		$pk = $this::$_pk;
		$conditions = array($pk => $this->$pk);
		$updated = static::$_db->update($this::$_table, $updates, $conditions);

		$this->fill($updates);

		return $updated;
	}


	/**
	 * @return AssocArray
	 */
	public function makeArray() : array {
		$props = get_object_vars($this);
		foreach ( $props as $name => $value ) {
			if ( $name[0] == '_' ) {
				unset($props[$name]);
			}
		}

		return $props;
	}


	/**
	 * @return bool
	 */
	public function delete() {
		$pk = $this::$_pk;
		return static::$_db->delete($this::$_table, array(
			$pk => $this->$pk,
		));
	}


	/**
	 * @param AssocArray $data
	 * @return $this
	 */
	public function fill( array $data ) {
		foreach ( $data AS $k => $v ) {
			if ( is_string($k) ) {
				$this->$k = $v;
			}
		}

		$this->init();

		return $this;
	}


	/**
	 * Returns the PK value for this object
	 * @return ?int
	 */
	public function getPKValue() {
		$key = $this::$_pk;
		if ( $key ) {
			return $this->$key;
		}
		return null;
	}



	/**
	 * ArrayAccess -- isset(obj[x])
	 */
	public function offsetExists($offset) : bool {
		return property_exists($this, $offset) || $this->existsMagicProperty($offset);
	}


	/**
	 * ArrayAccess -- obj[x]
	 */
	public function offsetGet($offset) : mixed {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		return $this->$offset;
	}


	/**
	 * ArrayAccess -- obj[x] = y
	 */
	public function offsetSet($offset, $value) : void {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		$this->$offset = $value;
	}


	/**
	 * ArrayAccess -- unset(obj[x])
	 */
	public function offsetUnset($offset) : void {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		unset($this->$offset);
	}


}
