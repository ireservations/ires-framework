<?php

namespace Framework\Http;

use Framework\Annotations\Route;
use Framework\Http\Controller as BaseController;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-type Mapping array<Hook>
 */
class ActionMapper {

	/** @var Mapping */
	protected array $mapping;

	public function __construct(
		protected BaseController $app,
	) {}

	/**
	 * @return Mapping
	 */
	public function getMapping() : array {
		if ( isset($this->mapping) ) {
			return $this->mapping;
		}

		// $file = self::getMappingFile();
		// if ( file_exists($file) ) {
		// 	return include $file;
		// }

		return $this->mapping = $this->createMapping();
	}

	/**
	 * @return Mapping
	 */
	protected function createMappingFromReflection() : array {
		$reflClass = new ReflectionClass($this->app);

		$hooks = [];
		foreach ( $reflClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method ) {
			foreach ( $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $reflAttr ) {
				$route = $reflAttr->newInstance();
				$hook = Hook::withMethod($route->path, $method->getName(), $route->method, $route->options);
				if ( $route->name ) {
					$hooks[$route->name] = $hook;
				}
				else {
					$hooks[] = $hook;
				}
			}
		}

		return $hooks;
	}

	/**
	 * @return Mapping
	 */
	protected function createMapping() : array {
// $t = microtime(true);
		$methods = ['get', 'post'];
		$methodKeys = array_flip($methods);

		$hooks = [];
		foreach ( $this->app->getRawHooks() as $path => $hook ) {
			if ( is_array($hook) ) {
				if ( isset($hook[0]) ) {
					$args = $hook;
					$hook = $hook[0];
					unset($args[0]);
					$name = $args['name'] ?? null;
					unset($args['name']);

					$hookObject = Hook::withArgs($path, $hook, $args);
					$name ? ($hooks[$name] = $hookObject) : ($hooks[] = $hookObject);
				}
				else {
					$args = array_diff_key($hook, $methodKeys);
					$name = $args['name'] ?? null;
					unset($args['name']);
					foreach ( $methods as $method ) {
						if ( isset($hook[$method]) ) {
							$hookObject = Hook::withMethod($path, $hook[$method], $method, $args);
							$name ? ($hooks[$name] = $hookObject) : ($hooks[] = $hookObject);
						}
					}
				}
			}
			else {
				$hooks[] = Hook::withAction($path, $hook);
			}
		}

		if ( count($hooks) ) {
// dump(1000 * (microtime(true) - $t));
			return $hooks;
		}

		$hooks = $this->createMappingFromReflection();
// dump(1000 * (microtime(true) - $t));
		return $hooks;
	}

	// public function saveMapping( array $mapping ) : void {
	// }

	protected function getMappingFile() : string {
		return realpath(PROJECT_RUNTIME) . DIRECTORY_SEPARATOR . 'actions.php';
	}

}
