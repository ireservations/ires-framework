<?php

use Framework\Http\Exception\ServerException;

abstract class db_generic {

	protected $m_columnDelimiter = '';

	public $db_name = '';
	public $error = '';
	public $errno = 0;

	public $num_queries = 0;
	public $log_queries = false;
	public $query_logger;
	public $queries = array();
	public $times = [];

	public $log_errors = true;
	public $except = false;



	/** @return bool */
	abstract public function connected();

	abstract public function close();



	abstract public function begin();

	abstract public function commit();

	abstract public function rollback();

	public function transaction( Closure $callable, int $attempts = 1 ) : mixed {
		$attempts = max(1, $attempts);

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			try {
				$this->begin();
				$result = call_user_func($callable, $this);
				$this->commit();
				return $result;
			}
			catch ( db_exception $ex ) {
				$this->rollback();

				if ( $attempt >= $attempts || !$this->isRetryableException($ex) ) {
					throw $ex;
				}
			}
			catch ( Throwable $ex ) {
				$this->rollback();

				throw $ex;
			}
		}

		throw new ServerException("Unknown transaction state in db_generic");
	}

	protected function isRetryableException( db_exception $ex ) : bool {
		return stripos($ex->getMessage(), 'deadlock found') !== false;
	}



	/** @return string */
	abstract public function escape( $value );



	/** @return int */
	abstract public function insert_id();

	/** @return int */
	abstract public function affected_rows();



	/** @return bool|object */
	abstract public function query( $query );

	/**
	 * @param bool|array $first
	 * @return array[]
	 */
	abstract public function fetch( $query, $first = false, $args = [] );

	/** @return array */
	abstract public function fetch_fields( $query, $args = [] );

	/** @return string */
	abstract public function fetch_one( $query, $args = [] );

	/** @return array */
	abstract public function fetch_by_field( $query, $field, $args = [] );

	/** @return array */
	abstract public function groupfetch_by_field( $query, $field, $args = [] );



	/** @return string */
	static public function prettifyQuery( $sql ) {
		return trim(preg_replace('#\s+#', ' ', $sql));
	}



	/** @return string */
	public function like( $string ) {
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

	/** @return string */
	public function stringify( $conditions ) {
		return $this->stringifyConditions($conditions);
	}

	/** @return string */
	public function qmarks( $str, ...$args ) {
		return $this->replaceQMarks($str, $args);
	}

	/** @return string */
	public function replaceQMarks( $str, array $args ) {
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
			debug_exit('Left-over query args in replaceQMarks(): ' . var_export($args, 1));
		}

		return $str;
	}

	/** @return string */
	function prepAndReplaceQMarks( $str, array $args ) {
		if ( is_array($str) ) {
			return $this->stringifyConditions($str);
		}

		if ( $args ) {
			$str = $this->replaceQMarks($str, $args);
		}

		return $str;
	}

	/** @return string */
	function escapeAndQuoteColumn( $column ) {
		if ( $delimiter = $this->m_columnDelimiter ) {
			$column = str_replace($delimiter, '', $column);
		}
		return $delimiter . str_replace('.', $delimiter . '.' . $delimiter, $column) . $delimiter;
	}

	/** @return string */
	function stringifyConditions( $conditions, $operator = 'AND' ) {
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
	 * @param string|array $where
	 * @return string
	 */
	public function select_one( $table, $field, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$query = 'SELECT ' . $field . ' FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1';
		return $this->fetch_one($query);
	}

	/** @return array[] */
	public function select_by_field( $table, $field, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch_by_field($sql, $field);
	}

	/** @return string */
	public function escapeAndQuote( $value ) {
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

	/** @return array[] */
	public function select( $table, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch($sql);
	}

	/**
	 * @param string|array $where
	 * @return array
	 */
	public function select_first( $table, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->fetch($sql, true);
	}

	/** @return array */
	public function fetch_first( $sql, $args = [] ) {
		$sql = $this->prepAndReplaceQMarks($sql, $args);
		return $this->fetch($sql, true);
	}

	/** @return int */
	public function max( $table, $field, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->select_one($table, 'MAX(' . $field . ')', $where);
	}

	/** @return int */
	public function min( $table, $field, $where = '1', ...$args) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->select_one($table, 'MIN(' . $field . ')', $where);
	}

	/**
	 * @param string|array $where
	 * @return int
	 */
	public function count( $table, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks( $where, $args);
		$count = $this->select_one($table, 'COUNT(1)', $where);
		return $count !== false ? (int) $count : false;
	}

	/** @return int */
	public function count_rows( $query ) {
		$query = trim(rtrim($query, ';'));
		$n = 0;
		$query = preg_replace_callback('#(\S+\.\*)#', function($m) use (&$n) {
			return '1 as x' . (++$n);
		}, $query);
		$count = $this->fetch_one("SELECT COUNT(1) num FROM ($query) x");
		return $count !== false ? (int) $count : false;
	}

	/** @return array */
	public function groupselect_by_field( $table, $field, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
		return $this->groupfetch_by_field($sql, $field);
	}

	/**
	 * @param string|array $where
	 * @return array
	 */
	public function select_fields( $table, $fields, $where = '1', ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->fetch_fields('SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $where);
	}

	/** @return bool */
	public function replace_into( $table, array $data ) {
		$keys = array_map([$this, 'escapeAndQuoteColumn'], array_keys($data));
		$values = array_map([$this, 'escapeAndQuote'], $data);

		$sql = 'REPLACE INTO ' . $table . ' (' . implode(',', $keys) . ') VALUES (' . implode(',', $values) . ')';
		return $this->query($sql);
	}

	/** @return bool */
	public function insert( $table, array $data ) {
		$keys = array_map([$this, 'escapeAndQuoteColumn'], array_keys($data));
		$values = array_map([$this, 'escapeAndQuote'], $data);

		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
		return $this->query($sql);
	}

	/**
	 * @param string|array $where
	 * @return bool
	 */
	public function update( $table, $update, $where = '1', ...$args ) {
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

	/** @return bool */
	public function delete( $table, $where, ...$args ) {
		$where = $this->prepAndReplaceQMarks($where, $args);
		return $this->query('DELETE FROM ' . $table . ' WHERE ' . $where);
	}

}
