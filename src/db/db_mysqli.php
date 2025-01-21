<?php

class db_mysqli extends db_generic {

	protected string $m_columnDelimiter = '`';

	protected ?mysqli $dbCon;

	public function __construct( string $host, string $user, string $pass, string $db ) {
		$this->db_name = $db;

		try {
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

			$this->dbCon = new mysqli($host, $user, $pass, $db);
			if ( $this->dbCon->connect_errno ) {
				throw new Exception($this->dbCon->connect_error, $this->dbCon->connect_errno);
			}

			// $this->dbCon->options(MYSQLI_SET_CHARSET_NAME, 'utf8');
			$this->dbCon->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

			$this->dbCon->query("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
		}
		catch ( Exception $ex ) {
			$this->dbCon = null;
		}
	}



	public function connected() : bool {
		return is_object($this->dbCon) && 0 === $this->dbCon->connect_errno;
	}

	public function close() : bool {
		return (bool) $this->dbCon->close();
	}



	public function begin() : void {
		if (!$this->dbCon->begin_transaction()) {
			throw new db_exception('BEGIN');
		}

		$this->transaction++;
		$this->queries[] = '[? ms] BEGIN';
	}

	public function commit() : void {
		if (!$this->dbCon->commit()) {
			throw new db_exception('COMMIT');
		}

		$this->transaction = max(0, $this->transaction - 1);
		$this->queries[] = '[? ms] COMMIT';
	}

	public function rollback() : void {
		if (!$this->dbCon->rollback()) {
			throw new db_exception('ROLLBACK');
		}

		$this->transaction = max(0, $this->transaction - 1);
		$this->queries[] = '[? ms] ROLLBACK';
	}



	public function escape( mixed $value ) : string {
		return $this->dbCon->real_escape_string($value);
	}



	public function insert_id() : int {
		return $this->dbCon->insert_id;
	}

	public function affected_rows() : int {
		return $this->dbCon->affected_rows;
	}



	/**
	 * @return true|mysqli_result
	 */
	public function query( string $query ) {
		$_start = microtime(true);

		$this->num_queries++;

		try {
			$result = $this->dbCon->query($query);
			$this->error = $result ? '' : $this->dbCon->error;
			$this->errno = $result ? 0 : $this->dbCon->errno;
		}
		catch ( mysqli_sql_exception $ex ) {
			$result = false;
			$this->error = $ex->getMessage();
			$this->errno = $ex->getCode();
		}

		if ( $this->log_queries ) {
			$_time = round((microtime(true) - $_start) * 1000);
			if ( $this->query_logger ) {
				call_user_func($this->query_logger, $query, $_time);
			}
			else {
				$this->queries[] = '[' . $_time . ' ms] ' . self::prettifyQuery($query);
			}
		}

		if ( false === $result ) {
			if ( error_reporting() ) {
				$duplicate = strpos($this->error, 'Duplicate entry ') === 0;
				$foreignKey = preg_match('#^Cannot (add|delete) or update a (child|parent) row: a foreign key constraint fails#', $this->error, $fkMatch);
				if ( $duplicate ) {
					throw new db_duplicate_exception($this->error . ' -- ' . $query);
				}
				elseif ( $foreignKey) {
					throw new db_foreignkey_exception($this->error . ' -- ' . $query, $fkMatch[1]);
				}
				else {
					throw new db_exception($this->error . ' -- ' . $query);
				}
			}
		}

		return $result;
	}

	public function fetch( string $query, bool|array $first = false, array $args = [] ) : ?array {
		if ( is_array($first) ) {
			$args = $first;
			$first = false;
		}

		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);

		if ( $first ) {
			return $r->fetch_assoc();
		}

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[] = $l;
		}
		return $a;
	}

	public function fetch_fields( string $query, array $args = [] ) : array {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);

		$a = array();
		while ( $l = $r->fetch_row() ) {
			$key = $l[0];
			$value = count($l) > 1 ? $l[1] : $key;
			$a[$key] = $value;
		}

		return $a;
	}

	public function fetch_one( string $query, array $args = [] ) : mixed {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);

		if ( $r->num_rows < 1 ) {
			return false;
		}

		$row = $r->fetch_row();

		return $row[0] ?? false;
	}

	public function fetch_by_field( string $query, string $field, array $args = [] ) : array {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[ $l[$field] ] = $l;
		}

		return $a;
	}

	public function groupfetch_by_field( string $query, string $field, array $args = [] ) : array {
		$query = $this->prepAndReplaceQMarks($query, $args);

		$r = $this->query($query);

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[ $l[$field] ][] = $l;
		}

		return $a;
	}

}
