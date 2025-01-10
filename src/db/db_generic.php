<?php


/**
 * @phpstan-type Record array<string, ?scalar>
 * @phpstan-type Where string|list<string>|array<array-key, mixed>
 * @phpstan-type Args list<mixed>
 */
abstract class db_generic {

	protected string $m_columnDelimiter = '';

	public string $db_name = '';
	public string $error = '';
	public int $errno = 0;

	public int $num_queries = 0;
	public bool $log_queries = false;
	public ?Closure $query_logger = null;
	/** @var list<string> */
	public array $queries = array();
	protected int $transaction = 0;



	abstract public function connected() : bool;

	abstract public function close() : bool;



	abstract public function begin() : void;

	abstract public function commit() : void;

	abstract public function rollback() : void;

	/**
	 * @template T
	 * @param (Closure(self): T) $callable
	 * @return T
	 */
	public function transaction( Closure $callable ) : mixed {
		try {
			if ($this->transaction == 0) {
				$this->begin();
			}

			$result = call_user_func($callable, $this);

			if ($this->transaction == 1) {
				$this->commit();
			}
			$this->transaction--;

			return $result;
		}
		catch ( Throwable $ex ) {
			$this->rollback();
			$this->transaction--;

			throw $ex;
		}
	}

	protected function isRetryableException( db_exception $ex ) : bool {
		return stripos($ex->getMessage(), 'deadlock found') !== false;
	}



	abstract public function escape( mixed $value ) : string;



	abstract public function insert_id() : int;

	abstract public function affected_rows() : int;



	/**
	 * @return true|object
	 */
	abstract public function query( string $query );

	/**
	 * @param bool|Args $first
	 * @param Args $args
	 * @return ($first is true ? ?Record : list<Record>)
	 */
	abstract public function fetch( string $query, bool|array $first = false, array $args = [] ) : ?array;

	/**
	 * @param Args $args
	 * @return array<int|string, ?scalar>
	 */
	abstract public function fetch_fields( string $query, array $args = [] ) : array;

	/**
	 * @param Args $args
	 * @return ?scalar
	 */
	abstract public function fetch_one( string $query, array $args = [] ) : mixed;

	/**
	 * @param Args $args
	 * @return array<int|string, Record>
	 */
	abstract public function fetch_by_field( string $query, string $field, array $args = [] ) : array;

	/**
	 * @param Args $args
	 * @return array<int|string, list<Record>>
	 */
	abstract public function groupfetch_by_field( string $query, string $field, array $args = [] ) : array;



	static public function prettifyQuery( string $sql ) : string {
		return trim(preg_replace('#\s+#', ' ', $sql));
	}



	/**
	 * @param bool|string $a
	 * @param null|bool|string $b
	 * @param null|bool $c
	 */
	public function like( $a, $b = null, $c = null ) : string {
		$args = func_get_args();
		if ( !is_bool($args[0]) ) {
			array_unshift($args, false);
		}
		if ( !isset($args[2]) ) {
			array_push($args, false);
		}

		list($before, $string, $after) = $args;
		return ($before ? '%' : '') . strtr($string, array(
			'_' => '\\_',
			'%' => '\\%',
		)) . ($after ? '%' : '');
	}

	/**
	 * @param Where $conditions
	 */
	public function stringify( string|array $conditions ) : string {
		return $this->stringifyConditions($conditions);
	}

	public function qmarks( string $str, mixed ...$args ) : string {
		return $this->replaceQMarks($str, $args);
	}

	/**
	 * @param Args $args
	 */
	public function replaceQMarks( string $str, array $args ) : string {
		if ( !$args ) return $str;

		$str = preg_replace_callback('#\?+#', function() use (&$args) {
			if ( count($args) ) {
				$arg = array_shift($args);

				if ( is_array($arg) ) {
					return implode(', ', array_map(array($this, 'escapeAndQuote'), $arg));
				}

				return $this->escapeAndQuote($arg);
			}

			return '?';
		}, $str);

		if ( $args ) {
			debug_exit('Left-over query args in replaceQMarks(): ' . var_export($args, true));
		}

		return $str;
	}

	/**
	 * @param Where $str
	 * @param Args $args
	 */
	public function prepAndReplaceQMarks( string|array $str, array $args ) : string {
		if ( is_array($str) ) {
			return $this->stringifyConditions($str);
		}

		if ( $args ) {
			$str = $this->replaceQMarks($str, $args);
		}

		return $str;
	}

	protected function escapeAndQuoteColumn( string $column ) : string {
		if ( $delimiter = $this->m_columnDelimiter ) {
			$column = str_replace($delimiter, '', $column);
		}
		return $delimiter . str_replace('.', $delimiter . '.' . $delimiter, $column) . $delimiter;
	}

	/**
	 * @param Where $conditions
	 */
	public function stringifyConditions( string|array $conditions, string $operator = 'AND' ) : string {
		if ( is_scalar($conditions) ) {
			return (string) $conditions;
		}

		$cond = array();
		foreach ( $conditions AS $field => $value ) {
			if ( is_array($value) ) {
				$values = array_map(array($this, 'escapeAndQuote'), $value);
				$field = $this->escapeAndQuoteColumn($field);
				$cond[] = $field . ' IN (' . implode(', ', $values) . ')';
			}
			else if ( is_int($field) ) {
				$cond[] = $value;
			}
			else {
				$comp = null === $value ? ' IS NULL' : ' = ' . $this->escapeAndQuote($value);
				$field = $this->escapeAndQuoteColumn($field);
				$cond[] = $field . $comp;
			}
		}
		$cond = implode(' ' . $operator . ' ', $cond);

		return $cond;
	}



	/**
	 * @param Where $where
	 * @return null|int|float|string
	 */
	public function select_one( string $table, string $field, string|array $where = '1', mixed ...$args ) : mixed {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$query = 'SELECT ' . $field . ' FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1';
		return $this->fetch_one($query);
	}

	/**
	 * @param Where $where
	 * @return array<int|string, Record>
	 */
	public function select_by_field( string $table, string $field, string|array $where = '1', mixed ...$args ) : array {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch_by_field($sql, $field);
	}

	public function escapeAndQuote( mixed $value ) : string {
		if ( $value === true ) {
			return "'1'";
		}
		else if ( $value === false ) {
			return "'0'";
		}
		else if ( $value === null ) {
			return 'NULL';
		}

		return "'" . $this->escape($value) . "'";
	}

	/**
	 * @param Where $where
	 * @return list<Record>
	 */
	public function select( string $table, string|array $where = '1', mixed ...$args ) : array {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch($sql);
	}

	/**
	 * @param Where $where
	 * @return ?Record
	 */
	public function select_first( string $table, string|array $where = '1', mixed ...$args ) : ?array {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch($sql, true);
	}

	/**
	 * @param Args $args
	 * @return ?Record
	 */
	public function fetch_first( string $sql, array $args = [] ) : ?array {
		$sql = $this->prepAndReplaceQMarks($sql, $args);
		return $this->fetch($sql, true);
	}

	/**
	 * @param Where $where
	 */
	public function max( string $table, string $field, string|array $where = '1', mixed ...$args ) : int|string {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->select_one($table, 'MAX(' . $field . ')', $where);
	}

	/**
	 * @param Where $where
	 */
	public function min( string $table, string $field, string|array $where = '1', mixed ...$args) : int|string {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->select_one($table, 'MIN(' . $field . ')', $where);
	}

	/**
	 * @param Where $where
	 */
	public function count( string $table, string|array $where = '1', mixed ...$args ) : int {
		$where = $this->prepAndReplaceQMarks( $where, $args);
		$count = $this->select_one($table, 'COUNT(1)', $where);
		return $count !== false ? (int) $count : false;
	}

	public function count_rows( string $query ) : int {
		$query = trim(rtrim($query, ';'));
		$n = 0;
		$query = preg_replace_callback('#(\S+\.\*)#', function($m) use (&$n) {
			return '1 as x' . (++$n);
		}, $query);
		return (int) $this->fetch_one("SELECT COUNT(1) num FROM ($query) x");
	}

	/**
	 * @param Where $where
	 * @return array<int, list<Record>>
	 */
	public function groupselect_by_field( string $table, string $field, string|array $where = '1', mixed ...$args ) : array {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->groupfetch_by_field($sql, $field);
	}

	/**
	 * @param Where $where
	 * @return array<int|string, ?scalar>
	 */
	public function select_fields( string $table, string $fields, string|array $where = '1', mixed ...$args ) : array {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->fetch_fields('SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $where);
	}

	/**
	 * @param AssocArray $data
	 */
	public function replace_into( string $table, array $data ) : bool {
		$keys = array_map([$this, 'escapeAndQuoteColumn'], array_keys($data));
		$values = array_map([$this, 'escapeAndQuote'], $data);

		$sql = 'REPLACE INTO ' . $table . ' (' . implode(',', $keys) . ') VALUES (' . implode(',', $values) . ')';
		return $this->query($sql);
	}

	/**
	 * @param AssocArray $data
	 */
	public function insert( string $table, array $data ) : bool {
		$keys = array_map([$this, 'escapeAndQuoteColumn'], array_keys($data));
		$values = array_map([$this, 'escapeAndQuote'], $data);

		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
		return $this->query($sql);
	}

	/**
	 * @param array<int|string, ?scalar> $update
	 * @param Where $where
	 */
	public function update( string $table, $update, string|array $where = '1', mixed ...$args ) : bool {
		$where = $this->prepAndReplaceQMarks($where, $args);

		if ( !is_string($update) ) {
			$u = '';
			foreach ( (array)$update AS $k => $v ) {
				if ( is_int($k) ) {
					$u .= ',' . $v;
				}
				else {
					$u .= ',' . $this->escapeAndQuoteColumn($k) . ' = ' . $this->escapeAndQuote($v);
				}
			}
			$update = substr($u, 1);
		}

		$query = 'UPDATE ' . $table . ' SET ' . $update . ' WHERE ' . $where;
		return $this->query($query);
	}

	/**
	 * @param Where $where
	 */
	public function delete( string $table, string|array $where, mixed ...$args ) : bool {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->query('DELETE FROM ' . $table . ' WHERE ' . $where);
	}

}
