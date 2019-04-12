<?php

class db_foreignkey_exception extends db_exception {

	protected $action;
	protected $table;

	public function __construct( $message, $action ) {
		parent::__construct($message);

		$this->action = $action;

		if ( preg_match('#`([^`]+)`, CONSTRAINT `#', $message, $match) ) {
			$this->table = $match[1];
		}
	}

	public function getAction() {
		return $this->action;
	}

	public function getTable() {
		return $this->table;
	}

}
