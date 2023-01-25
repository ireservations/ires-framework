<?php

namespace Framework\Http;

use Framework\Annotations\Access;
use Framework\Annotations\Controller;
use Generator;
use ReflectionClass;

class ControllerMapper {

	static protected array $beforeClasses;

	public function getMapping() : array {
		$file = self::getMappingFile();
		if ( file_exists($file) ) {
			return include $file;
		}

		return $this->createMapping();
	}

	public function createMapping() : array {
		$controllers = $this->getAllControllerClasses(PROJECT_LOGIC);
		$prefixes = [];
		foreach ( $controllers as $controllerRefl ) {
			$attributes = $controllerRefl->getAttributes(Controller::class);
			foreach ( $attributes as $attribute ) {
				$ctrlr = $attribute->newInstance();
				$prefixes[$ctrlr->prefix] = [
					$controllerRefl->getName(),
					[
						'accessZones' => $this->getAccessZones($controllerRefl),
					],
				];
			}
		}
		uksort($prefixes, fn($a, $b) => strlen($b) <=> strlen($a));
		return $prefixes;
	}

	public function saveMapping( array $mapping ) : void {
		$file = self::getMappingFile();
		$mapping = $this->createMapping();
		$code = "<?php\n\nreturn " . var_export($mapping, true) . ";\n";
		file_put_contents($file, $code);
	}

	protected function getAccessZones( ReflectionClass $reflection ) : array {
		$zones = [];
		while ( $reflection ) {
			$attributes = $reflection->getAttributes(Access::class);
			foreach ( array_reverse($attributes) as $attribute ) {
				$access = $attribute->newInstance();
				$zones[] = $access->name;
			}
			$reflection = $reflection->getParentClass();
		}

		return array_reverse($zones);
	}

	protected function getMappingFile() : string {
		return realpath(PROJECT_RUNTIME) . DIRECTORY_SEPARATOR . 'controllers.php';
	}

	protected function getAllControllerClasses( string $dir ) : Generator {
		self::$beforeClasses ??= get_declared_classes();
		foreach ( $this->getAllPhpFiles($dir) as $file ) {
			include_once $file;
		}
		$newClasses = get_declared_classes();
		$addedClasses = array_diff($newClasses, self::$beforeClasses);

		foreach ( $addedClasses as $class ) {
			$refl = new ReflectionClass($class);
			if ($refl->isAbstract() || $refl->isAnonymous() || !preg_match('#Controller$#', $class)) {
				continue;
			}

			if (count($refl->getAttributes(Controller::class))) {
				yield $refl;
			}
		}
	}

	protected function getAllPhpFiles( string $dir ) : Generator {
		foreach ( glob("$dir/*") as $file ) {
			if ( is_dir($file) ) {
				yield from $this->getAllPhpFiles($file);
			}
			elseif (substr($file, -4) === '.php') {
				yield realpath($file);
			}
		}
	}

}
