<?php

namespace Framework\Http;

use Attribute;
use Framework\Annotations\Controller;
use Framework\Annotations\ControllerAnnotationInterface;
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
// $t = microtime(1);
		$controllers = $this->getAllControllerClasses(PROJECT_LOGIC);
		$prefixes = [];
		foreach ( $controllers as $controllerRefl ) {
			$attributes = $controllerRefl->getAttributes(Controller::class);
			$values = null;
			foreach ( $attributes as $attribute ) {
				$ctrlr = $attribute->newInstance();
				$values ??= $this->getAttributeValues($controllerRefl);
				$prefixes[$ctrlr->prefix] = [
					$controllerRefl->getName(),
					$values,
				];
			}
		}
		uksort($prefixes, fn($a, $b) => strlen($b) <=> strlen($a));
// dump(1000 * (microtime(1) - $t));
		return $prefixes;
	}

	public function saveMapping( array $mapping ) : void {
		$file = self::getMappingFile();
		$mapping = $this->createMapping();
		$code = "<?php\n\nreturn " . var_export($mapping, true) . ";\n";
		file_put_contents($file, $code);
	}

	protected function getAttributeValues( ReflectionClass $reflection ) : array {
		$attributes = $this->getAttributes($reflection);
		$values = [];
		foreach ( $attributes as $attribute ) {
			$instance = $attribute->newInstance();
			if ( $instance instanceof ControllerAnnotationInterface ) {
				if ( $instance->controllerIsMultiple() ) {
					$values[$instance->controllerName()][] = $instance->controllerSingleValue();
				}
				else {
					$values[$instance->controllerName()] = $instance->controllerSingleValue();
				}
			}
		}

		return $values;
	}

	protected function getAttributes( ReflectionClass $reflection ) : array {
		$reflections = [$reflection];
		while ( $reflection = $reflection->getParentClass() ) {
			$reflections[] = $reflection;
		}

		$attributes = [];
		foreach ( array_reverse($reflections) as $reflection ) {
			$attributes = [...$attributes, ...$reflection->getAttributes()];
		}

		return $attributes;
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
