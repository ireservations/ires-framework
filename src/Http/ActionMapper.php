<?php

namespace Framework\Http;

use Framework\Http\Controller as BaseController;
use ReflectionClass;
use ReflectionMethod;

class ActionMapper {

	public function __construct(
		protected BaseController $app,
	) {}

	/**
	 * @return list<Hook>
	 */
	public function getMapping() : array {
		// $file = self::getMappingFile();
		// if ( file_exists($file) ) {
		// 	return include $file;
		// }

		return $this->createMapping();
	}

	/**
	 * @return list<Hook>
	 */
	protected function createMappingFromReflection() : array {
		$reflClass = new ReflectionClass($this->app);

		$hooks = [];
		foreach ( $reflClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method ) {
			foreach ( $method->getAttributes() as $attr ) {
				$verb = explode('\\', $attr->getName());
				$verb = $verb[count($verb) - 1];
				if ( in_array($verb, ['All', 'Get', 'Post']) ) {
					$hooks[] = Hook::withMethod($attr->getArguments()[0], $method->getName(), strtolower($verb));
				}
			}
		}

		return $hooks;
	}

	/**
	 * @return list<Hook>
	 */
	protected function createMapping() : array {
		$methods = ['get', 'post'];
		$methodKeys = array_flip($methods);

		$hooks = [];
		foreach ( $this->app->getRawHooks() as $path => $hook ) {
			if ( is_array($hook) ) {
				if ( isset($hook[0]) ) {
					$args = $hook;
					$hook = $hook[0];
					unset($args[0]);

					$hooks[] = Hook::withArgs($path, $hook, $args);
				}
				else {
					foreach ( $methods as $method ) {
						if ( isset($hook[$method]) ) {
							$args = array_diff_key($hook, $methodKeys);
							$hooks[] = Hook::withMethod($path, $hook[$method], $method, $args);
						}
					}
				}
			}
			else {
				$hooks[] = Hook::withAction($path, $hook);
			}
		}

		if ( !count($hooks) ) {
			return $this->createMappingFromReflection();
		}

		return $hooks;
	}

	// public function saveMapping( array $mapping ) : void {
	// }

	protected function getMappingFile() : string {
		return realpath(PROJECT_RUNTIME) . DIRECTORY_SEPARATOR . 'actions.php';
	}

}
