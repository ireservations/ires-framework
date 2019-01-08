<?php

class db_mysqli extends db_generic {

	protected $m_columnDelimiter = '`';

	/** @var mysqli $dbCon */
	protected $dbCon;

	public function __construct( $f_szHost, $f_szUser = '', $f_szPass = '', $f_szDb = '' ) {
		$this->db_name = $f_szDb;
		$this->dbCon = new mysqli($f_szHost, $f_szUser, $f_szPass, $f_szDb);
		if ( !$this->dbCon->connect_errno ) {
			list($names, $collate) = explode(':', defined('SQL_CHARSET') ? SQL_CHARSET : 'utf8:utf8_general_ci');
			$this->dbCon->query("SET NAMES '$names' COLLATE '$collate'");
		}
	}



	public function connected() {
		return (is_object($this->dbCon) && 0 === $this->dbCon->connect_errno);
	}

	public function close() {
		return $this->dbCon->close();
	}



	public function begin() {
		// 'start transaction': don't auto commit all following SQL UNTIL transaction end
		return $this->dbCon->autocommit(false);
	}

	public function commit() {
		$this->dbCon->commit();
		// end transaction: auto commit all following SQL
		return $this->dbCon->autocommit(true);
	}

	public function rollback() {
		$this->dbCon->rollBack();
		// end transaction: auto commit all following SQL
		return $this->dbCon->autocommit(true);
	}



	public function escape( $value ) {
		return $this->dbCon->real_escape_string($value);
	}



	public function insert_id() {
		return $this->dbCon->insert_id;
	}

	public function affected_rows() {
		return $this->dbCon->affected_rows;
	}



	public function query( $query, $resultmode = MYSQLI_STORE_RESULT ) {
		$_start = microtime(1);

		$r = $this->dbCon->query($query, $resultmode);
		$this->error = $r ? '' : $this->dbCon->error;
		$this->errno = $r ? 0 : $this->dbCon->errno;
		$this->num_queries++;

		if ( $this->log_queries ) {
			$_time = round((microtime(1) - $_start) * 1000);
			$this->queries[] = '[' . $_time . ' ms] ' . self::prettifyQuery($query);
		}

		if ( false === $r ) {
			if ( error_reporting() ) {
				if ( $this->except ) {
					$duplicate = strpos($this->error, 'Duplicate entry ') === 0;
					$foreignKey = preg_match('#^Cannot (add|delete) or update a (child|parent) row: a foreign key constraint fails#', $this->error);
					if ( $duplicate ) {
						throw new db_duplicate_exception($this->error . ' -- ' . $query);
					}
					elseif ( $foreignKey) {
						throw new db_foreignkey_exception($this->error . ' -- ' . $query);
					}
					else {
						throw new db_exception($this->error . ' -- ' . $query);
					}
				}
				elseif ( $this->log_errors ) {
					debug_exit("SQL ERROR: " . $this->error, $query);
				}
			}
		}

		return $r;
	}

	public function fetch( $query, $first = false, $args = [] ) {
		if ( is_array($first) ) {
			$args = $first;
			$first = false;
		}

		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);
		if ( !is_object($r) ) {
			return false;
		}

		if ( $first ) {
			return $r->fetch_assoc();
		}

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[] = $l;
		}
		return $a;
	}

	public function fetch_fields( $query, $args = [] ) {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);
		if ( !is_object($r) ) {
			return false;
		}

		$a = array();
		while ( $l = $r->fetch_row() ) {
			$key = $l[0];
			$value = count($l) > 1 ? $l[1] : $key;
			$a[$key] = $value;
		}

		return $a;
	}

	public function fetch_one( $query, $args = [] ) {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);

		if ( !is_object($r) || 0 >= $r->num_rows ) {
			return false;
		}

		$row = $r->fetch_row();

		return $row[0];
	}

	public function fetch_by_field( $query, $field, $args = [] ) {
		$query = $this->replaceQMarks($query, $args);

		$r = $this->query($query);
		if ( !is_object($r) ) {
			return false;
		}

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[ $l[$field] ] = $l;
		}

		return $a;
	}

	public function groupfetch_by_field( $query, $field, $args = [] ) {
		$query = $this->prepAndReplaceQMarks($query, $args);

		$r = $this->query($query);
		if ( !is_object($r) ) {
			return false;
		}

		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[ $l[$field] ][] = $l;
		}

		return $a;
	}

}
