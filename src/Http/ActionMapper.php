<?php

namespace Framework\Http;

use Framework\Annotations\Route;
use Framework\Http\Controller as BaseController;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-type Source 'hooks_array'|'annotations'
 * @phpstan-type Mapping array<Hook>
 */
class ActionMapper {

	/** @var Mapping */
	protected array $mapping;

	/** @var Source */
	protected string $source;

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

		return $this->mapping = $this->createMapping();
	}

	/**
	 * @return Source
	 */
	public function getSource() : string {
		return $this->source;
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
	protected function createMappingFromHooksArray() : array {
		$methods = ['get', 'post'];
		$methodKeys = array_flip($methods);

		$hooks = [];
		foreach ( $this->app->getRawHooks() as $path => $hook ) {
			if ( is_array($hook) ) {
				if ( isset($hook[0]) ) {
					$options = $hook;
					$hook = $hook[0];
					unset($options[0]);
					$name = $options['name'] ?? null;
					unset($options['name']);

					$hookObject = Hook::withOptions($path, $hook, $options);
					$name ? ($hooks[$name] = $hookObject) : ($hooks[] = $hookObject);
				}
				else {
					$options = array_diff_key($hook, $methodKeys);
					$name = $options['name'] ?? null;
					unset($options['name']);
					foreach ( $methods as $method ) {
						if ( isset($hook[$method]) ) {
							$hookObject = Hook::withMethod($path, $hook[$method], $method, $options);
							$name ? ($hooks[$name] = $hookObject) : ($hooks[] = $hookObject);
						}
					}
				}
			}
			else {
				$hooks[] = Hook::withAction($path, $hook);
			}
		}

		return $hooks;
	}

	/**
	 * @return Mapping
	 */
	protected function createMapping() : array {
// $t = microtime(true);
		$hooks = $this->createMappingFromHooksArray();
		$this->source = 'hooks_array';
		if ( !count($hooks) ) {
			$this->source = 'annotations';
			$hooks = $this->createMappingFromReflection();
		}

// dump(1000 * (microtime(true) - $t));
		return $hooks;
	}

	protected function getMappingFile() : string {
		return realpath(PROJECT_RUNTIME) . DIRECTORY_SEPARATOR . 'actions.php';
	}

}
