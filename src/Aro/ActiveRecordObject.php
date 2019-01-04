<?php

namespace Framework\Aro;

use ArrayAccess;
use db_exception;
use db_generic;
use Generator;

/**
 * @property int $id
 * @property db_generic $db
 */
abstract class ActiveRecordObject implements ArrayAccess {

	use ValidatesTokens;

	const ITERATOR_LIMIT = 200;


	/**
	 * Whether to allow all array access, or to debug_exit()
	 */
	public static $allowArrayAccess = false;



	/**
	 * The global, default database object
	 *
	 * @var db_generic
	 */
	protected static $_db;

	public static function setDbObject( db_generic $db ) {
		self::$_db = $db;
	}

	public static function getDbObject() {
		return self::$_db;
	}



	/**
	 * @var ActiveRecordCache
	 */
	protected static $_objectCache;

	/**
	 *
	 */
	final public static function setCache( ActiveRecordCache $cache = null ) {
		self::$_objectCache = $cache;
	}



	/**
	 * The table's name
	 */
	protected static $_table = '';

	/**
	 * The name of the PK field
	 */
	protected static $_pk = '';

	/**
	 * Auto-nullable fields
	 */
	protected static $_nullables = [];



	/**
	 * Whether the db data have been loaded
	 */
	protected $_loaded = false;

	/**
	 * Magic getter cache
	 */
	protected $_got = [];

	/**
	 * Any cache for this specific entity
	 */
	protected $_cache = [];



	/**
	 * @return string
	 */
	static public function _table() {
		return static::$_table;
	}


	/**
	 * @return string
	 */
	static public function _pk() {
		return static::$_pk;
	}


	/**
	 * @return array
	 */
	static function getDistincts( $field ) {
		return static::$_db->select_fields(static::$_table, $field, "$field IS NOT NULL GROUP BY $field ORDER BY $field");
	}



	protected function to_one( $targetClass, $foreignColumn ) {
		return new Relationship\ToOne($this, $targetClass, $foreignColumn);
	}

	protected function to_first( $targetClass, $relationColumn ) {
		return new Relationship\ToFirst($this, $targetClass, $relationColumn);
	}

	protected function to_one_scalar_table( $targetColumn, $throughTable, $foreignColumn ) {
		return new Relationship\ToOneScalarTable($this, $targetColumn, $throughTable, $foreignColumn);
	}

	protected function to_many( $targetClass, $foreignColumn ) {
		return new Relationship\ToMany($this, $targetClass, $foreignColumn);
	}

	/**
	 * @param self $targetClass
	 */
	protected function to_count( $targetClass, $foreignColumn ) {
		$targetTable = call_user_func([$targetClass, '_table']);
		return (new Relationship\ToOneScalarTable($this, 'count(1)', $targetTable, $foreignColumn))
			->default(0)
			->returnType('int');
	}

	protected function to_count_table( $targetTable, $foreignColumn ) {
		return (new Relationship\ToOneScalarTable($this, 'count(1)', $targetTable, $foreignColumn))
			->default(0)
			->returnType('int');
	}

	protected function to_many_through( $targetClass, $throughRelationsip ) {
		return new Relationship\ToManyThrough($this, $targetClass, $throughRelationsip);
	}

	protected function to_many_through_property( $targetClass, $throughProperty ) {
		return new Relationship\ToManyThroughProperty($this, $targetClass, $throughProperty);
	}

	protected function to_many_scalar( $targetColumn, $throughTable, $foreignColumn ) {
		return new Relationship\ToManyScalar($this, $targetColumn, $throughTable, $foreignColumn);
	}

	/**
	 * @return array
	 */
	static public function eager( $name, array $objects ) {
		if ( count($objects) == 0 ) {
			return [];
		}

		/** @var ActiveRecordRelationship $relationship */
		$relationship = call_user_func([new static(), "relate_$name"]);
		return $relationship->name($name)->loadAll($objects);
	}



	/**
	 *
	 */
	public function __construct( $data = null, $loaded = false ) {
		$this->_loaded = $loaded;

		if ( null !== $data && !is_scalar($data) ) {
			$this->fill($data);
		}
	}


	/**
	 *
	 */
	public function __isset( $name ) {
		return $this->existsMagicProperty($name);
	}

	/**
	 *
	 */
	public function &__get( $name ) {
		if ( !$this->_loaded ) {
			$this->refresh();
		}

		if ( property_exists($this, $name) ) {
			return $this->$name;
		}

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

		$this->_got[$name] = null;
		return $this->_got[$name];
	}


	/**
	 * @return db_generic
	 */
	protected function get_db() {
		return static::$_db;
	}


	protected function existsMagicProperty( $name ) {
		return $this->gotGot($name) || $this->existsGetter($name) || $this->existsRelationship($name);
	}

	public function existsGetter( $name ) {
		return method_exists($this, 'get_' . $name);
	}

	protected function resolveGetter( $name ) {
		$method = [$this, 'get_' . $name];
		return call_user_func($method);
	}

	public function setGot( $name, $value ) {
		$this->_got[$name] = $value;
	}

	public function getGot( $name ) {
		return $this->_got[$name] ?? null;
	}

	public function gotGot( $name ) {
		return array_key_exists($name, $this->_got);
	}

	public function existsRelationship( $name ) {
		return method_exists($this, 'relate_' . $name);
	}

	protected function resolveRelationship( $name ) {
		$method = [$this, 'relate_' . $name];
		/** @var ActiveRecordRelationship $relationship */
		$relationship = call_user_func($method);
		return $relationship->name($name)->load();
	}


	/**
	 *
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
	 * @return static[]
	 */
	static public function fetch( $conditions, $f_szKeyField = null ) {
		if ( is_array($conditions) ) {
			$conditions = static::$_db->stringifyConditions($conditions);
		}

		$query = static::getQuery($conditions);
		$objects = static::byQuery($query, $f_szKeyField);

		// Add to cache
		if ( $objects && is_array($objects) ) {
			self::_allToCache($objects);
		}

		return $objects;
	}


	/**
	 * Returns all records it finds
	 * @return Generator|static[]
	 */
	static public function fetchIterator( $conditions, array $args = [] ) {
		if ( is_array($conditions) ) {
			$conditions = static::$_db->stringifyConditions($conditions);
		}

		$query = static::getQuery($conditions);
		return static::byQueryIterator($query, $args);
	}


	/**
	 * Add/replace many records to/in the object cache
	 */
	static public function _allToCache( array &$objects ) {
		if ( self::$_objectCache) {
			self::$_objectCache->addMany($objects);
		}
	}


	/**
	 * Add one record to the object cache
	 */
	static public function _oneToCache( $class, $id, self $object ) {
		if ( self::$_objectCache) {
			self::$_objectCache->addOne($class, $id, $object);
		}
	}


	/**
	 * Get one record from the object cache
	 * @return static
	 */
	static public function _fromCache( $id ) {
		if ( self::$_objectCache) {
			$class = get_called_class();
			$id = (string) $id;

			if ( self::$_objectCache->has($class, $id) ) {
				return self::$_objectCache->get($class, $id);
			}
		}
	}


	/**
	 * @return static
	 */
	static function find( $id ) {
		if ( !$id ) {
			return null;
		}

		try {
			return static::byPK($id);
		}
		catch ( ActiveRecordException $ex ) {}
	}

	/**
	 * @return static
	 */
	static function load( $id ) {
		return static::find($id);
	}


	/**
	 * Must return one record
	 * @throws ActiveRecordException
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
	 * @return static
	 */
	static public function findFirst( $conditions, ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$objects = static::fetch("$conditions LIMIT 1");
		if ( count($objects) ) {
			return $objects[0];
		}
	}


	/**
	 * Must return one record
	 * @throws ActiveRecordException
	 * @return static
	 */
	static public function findOne( $conditions, ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$objects = static::fetch($conditions . ' LIMIT 2');

		if ( count($objects) != 1 ) {
			throw new ActiveRecordException('Not exactly one record found', count($objects), get_called_class());
		}

		return $objects[0];
	}


	/**
	 * @throws db_exception
	 * @return static[]
	 */
	static public function byQuery( $f_szSqlQuery, $f_szKeyField = null ) {
		$option = false;
		if ( true === $f_szKeyField ) {
			$option = true;
			$f_szKeyField = null;
		}
		else if ( is_array($f_szKeyField) ) {
			$option = $f_szKeyField;
			$f_szKeyField = null;
		}

		$records = static::$_db->fetch($f_szSqlQuery, $option);
		if ( $records === false ) {
			throw new db_exception(static::$_db->error, static::$_db->errno);
		}

		$objects = array();
		foreach ( $records AS $record ) {
			$object = new static($record, true);
			$object->_loaded = true;
			if ( $f_szKeyField ) {
				$objects[ $object->$f_szKeyField ] = $object;
			}
			else {
				$objects[] = $object;
			}
		}

		return $objects;
	}


	/**
	 * @throws db_exception
	 * @return Generator|static[]
	 */
	static public function byQueryIterator( $f_szSqlQuery, array $f_args = [], $limit = null ) {
		$limit or $limit = static::ITERATOR_LIMIT;

		$page = 0;
		$fetch = function() use ($f_szSqlQuery, $f_args, &$page, $limit) {
			$offset = $page++ * $limit;
			return static::byQuery("$f_szSqlQuery LIMIT $limit OFFSET $offset", $f_args);
		};

		while ( count($objects = $fetch()) ) {
			foreach ( $objects as $object ) {
				yield $object;
			}
		}
	}


	/**
	 * Returns 0 or more records - in assoc array
	 *
	 * @return static[]
	 */
	static public function findManyByField( $conditions, $field, ...$args ) {
		$query = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::fetch($query, $field);
	}


	/**
	 * Returns 0 or more records
	 *
	 * @return static[]
	 */
	static public function findMany( $conditions, ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::fetch($conditions);
	}


	/**
	 * Returns records by PK search
	 *
	 * @return static[]
	 */
	static public function findManyByPK( $ids, $byField = false ) {
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
	 * @return static
	 */
	static public function any( $conditions = '1', ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);
		return static::findFirst("$conditions ORDER BY RAND()");
	}


	/**
	 * @return int
	 */
	static public function count( $conditions, ...$args ) {
		$conditions = static::$_db->prepAndReplaceQMarks($conditions, $args);

		$szSqlQuery = static::getQuery($conditions);
		return static::$_db->count_rows($szSqlQuery);
	}



	/**
	 *
	 */
	static public function saveOrder( $ids, $column, $conditions = array() ) {
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
	 * @return static|false
	 */
	static public function insert( array $data ) {
		static::presave($data);

		if ( static::$_db->insert(static::$_table, $data) ) {
			$id = static::$_db->insert_id();
			if ( static::$_pk && $id ) {
				$data[static::$_pk] = $id;
			}
			$object = new static($data, false);
			if ( static::$_pk && $id  ) {
				static::_oneToCache(static::class, $id, $object);
			}
			return $object;
		}

		return false;
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

		// remove cached getters/relations
		$this->clean();

		// populate object
		return $this->fill($data[0]);
	}


	/**
	 * Remove all properties
	 */
	public function clean() {
		$this->_got = [];
	}



	/**
	 *
	 */
	public function init() {
		$this->initId();
		$this->initCache();
	}

	/**
	 *
	 */
	public function initId() {
		if ( isset($this->id) ) {
			$this->id = (int) $this->id;
		}
	}

	/**
	 *
	 */
	public function initCache() {
		$this->_got = [];
		$this->_cache = [];
	}

	protected function initTimes( ...$fields) {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) && is_string($this->$field) ) {
				$this->$field = substr($this->$field, 0 ,5);
			}
		}
	}

	protected function initFloats( ...$fields) {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) ) {
				$this->$field = (float) $this->$field;
			}
		}
	}

	protected function initInts( ...$fields) {
		foreach ( $fields as $field ) {
			if ( isset($this->$field) ) {
				$this->$field = (int) $this->$field;
			}
		}
	}



	static public function presave( array &$data ) {
		self::presaveId($data);
		self::presaveNullables($data);
	}

	static public function presaveNullables( array &$data ) {
		foreach ( static::$_nullables as $column ) {
			if ( isset($data[$column]) && ( $data[$column] === '' || $data[$column] === [] ) ) {
				$data[$column] = null;
			}
		}
	}

	static public function presaveId( array &$data ) {
		unset($data['id']);
	}

	static public function presaveTrim( array &$data ) {
		foreach ( $data as $column => $value ) {
			if ( is_string($value) ) {
				$data[$column] = trim($value);
			}
		}
	}

	static public function presaveCSVs( array &$data, ...$fields ) {
		foreach ( $fields as $field ) {
			if ( isset($data[$field]) && is_array($data[$field]) ) {
				$data[$field] = implode(',', $data[$field]);
			}
		}
	}

	static public function presaveFloats( array &$data, ...$fields ) {
		foreach ( $fields as $field ) {
			if ( isset($data[$field]) ) {
				$data[$field] = (float) $data[$field];
			}
		}
	}



	protected function _cache( $type, $key, callable $callback ) {
		if ( !isset($this->_cache[$type][$key]) ) {
			$value = $callback($this);
			$this->_cache[$type][$key] = isset($value) ? $value : false;
		}

		return $this->_cache[$type][$key];
	}


	public function toggle( $field, $value = null ) {
		$newValue = (int)( $value === null ? !$this->$field : (bool)$value );
		return $this->update(array($field => $newValue));
	}


	public function extractOnly( $props ) {
		$data = [];
		foreach ( $props as $item ) {
			$data[$item] = $this->$item;
		}

		return $data;
	}


	/**
	 * @return bool
	 */
	function update( $updates ) {
		if ( is_array($updates) ) {
			static::presave($updates);

			$this->fill($updates);
		}

		$pk = $this::$_pk;
		$conditions = array($pk => $this->$pk);
		return static::$_db->update($this::$_table, $updates, $conditions);
	}


	/**
	 * @return array
	 */
	public function makeArray() {
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
	 * Copies and/or replaces data from $data into $this
	 * @return static
	 */
	public function fill( $data ) {
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
	 * @return int
	 */
	public function getPKValue() {
		$key = $this::$_pk;
		if ( $key ) {
			return $this->$key;
		}
	}



	/**
	 * ArrayAccess -- isset(obj[x])
	 */
	public function offsetExists($offset) {
		return property_exists($this, $offset) || $this->existsMagicProperty($offset);
	}


	/**
	 * ArrayAccess -- obj[x]
	 */
	public function offsetGet($offset) {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		return $this->$offset;
	}


	/**
	 * ArrayAccess -- obj[x] = y
	 */
	public function offsetSet($offset, $value) {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		$this->$offset = $value;
	}


	/**
	 * ArrayAccess -- unset(obj[x])
	 */
	public function offsetUnset($offset) {
		self::$allowArrayAccess or debug_exit('ArrayAccess ' . get_class($this) . '->' . $offset);

		unset($this->$offset);
	}


}
