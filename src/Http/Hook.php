<?php

namespace Framework\Http;

class Hook {

	public function __construct(
		public string $path,
		public string $action,
		public string $method = 'all',
		public array $args = [],
	) {}

	static public function withArgs( string $path, string $action, array $args ) {
		return new static($path, $action, args: $args);
	}

	static public function withMethod( string $path, string $action, string $method, array $args = [] ) {
		return new static($path, $action, method: strtolower($method), args: $args);
	}

	static public function withAction( string $path, string $action ) {
		return new static($path, $action);
	}

}
