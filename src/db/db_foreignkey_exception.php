<?php

class db_foreignkey_exception extends db_exception {

	protected ?string $table = null;

	public function __construct(
		string $message,
		protected string $action,
	) {
		parent::__construct($message);

		if ( preg_match('#`([^`]+)`, CONSTRAINT `#', $message, $match) ) {
			$this->table = $match[1];
		}
	}

	public function getAction() : string {
		return $this->action;
	}

	public function getTable() : ?string {
		return $this->table;
	}

}
