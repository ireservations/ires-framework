<?php

namespace Framework\Http;

use App\Services\Http\AppController;

class ActionMapper {

	public function __construct(
		protected AppController $app,
	) {}

	public function getMapping() : array {
		// $file = self::getMappingFile();
		// if ( file_exists($file) ) {
		// 	return include $file;
		// }

		return $this->createMapping();
	}

	protected function createMapping() : array {
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
					foreach ( $hook as $method => $action ) {
						$hooks[] = Hook::withMethod($path, $action, $method);
					}
				}
			}
			else {
				$hooks[] = Hook::withAction($path, $hook);
			}
		}

		return $hooks;
	}

	// public function saveMapping( array $mapping ) : void {
	// }

	protected function getMappingFile() : string {
		return realpath(PROJECT_RUNTIME) . DIRECTORY_SEPARATOR . 'actions.php';
	}

}
