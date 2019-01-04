<?php

namespace Framework\Http;

class Hook {

	public $path = '';
	public $method = 'all';
	public $action = '';
	public $args = [];

	public function __construct( $path ) {
		$this->path = $path;
	}

	static public function withArgs( $path, $action, array $args ) {
		$hook = new static($path);
		$hook->action = $action;
		$hook->args = $args;

		return $hook;
	}

	static public function withMethod( $path, $method, $action ) {
		$hook = new static($path);
		$hook->method = strtolower($method);
		$hook->action = $action;

		return $hook;
	}

	static public function withAction( $path, $action ) {
		$hook = new static($path);
		$hook->action = $action;

		return $hook;
	}

}
