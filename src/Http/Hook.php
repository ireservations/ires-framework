<?php

namespace Framework\Http;

final class Hook {

	public function __construct(
		public string $path,
		public string $action,
		public string $method = 'all',
		/** @var AssocArray */
		public array $options = [],
	) {}

	/**
	 * @param AssocArray $options
	 * @return static
	 */
	static public function withOptions( string $path, string $action, array $options ) {
		return new static($path, $action, options: $options);
	}

	/**
	 * @param AssocArray $options
	 * @return static
	 */
	static public function withMethod( string $path, string $action, string $method, array $options = [] ) {
		return new static($path, $action, method: strtolower($method), options: $options);
	}

	/**
	 * @return static
	 */
	static public function withAction( string $path, string $action ) {
		return new static($path, $action);
	}

}
