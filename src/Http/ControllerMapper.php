<?php

namespace Framework\Http;

use Framework\Annotations\Controller;
use Framework\Annotations\ControllerAnnotationInterface;
use Generator;
use ReflectionAttribute;
use ReflectionClass;

/**
 * @phpstan-type Mapping array<CompiledController>
 */
class ControllerMapper {

	/** @var list<class-string> */
	static protected array $beforeClasses;

	/** @var Mapping */
	protected array $mapping;

	/**
	 * @return Mapping
	 */
	public function getMapping() : array {
		if ( isset($this->mapping) ) {
			return $this->mapping;
		}

		$file = self::getMappingFile();
		if ( file_exists($file) ) {
			return $this->mapping = include $file;
		}

		return $this->mapping = $this->createMapping();
	}

	/**
	 * @return Mapping
	 */
	public function createMapping() : array {
// $t = microtime(true);
		$controllers = $this->getAllControllerClasses(PROJECT_LOGIC);

		$prefixes = [];
		foreach ( $controllers as $controllerRefl ) {
			$ctrlrOptions = $this->getControllerOptions($controllerRefl);

			$attributes = $controllerRefl->getAttributes(Controller::class);
			foreach ( $attributes as $reflAttribute ) {
				$ctrlr = $reflAttribute->newInstance();
				$compiled = new CompiledController(
					$ctrlr->prefix,
					$controllerRefl->getName(),
					$ctrlrOptions,
				);
				if ( $ctrlr->name ) {
					$prefixes[$ctrlr->name] = $compiled;
				}
				else {
					$prefixes[] = $compiled;
				}
			}
		}

		uasort($prefixes, function(CompiledController $a, CompiledController $b) {
			return strlen($b->path) <=> strlen($a->path);
		});

// dump(1000 * (microtime(true) - $t));
		return $prefixes;
	}

	/**
	 * @param Mapping $mapping
	 */
	public function saveMapping( array $mapping ) : void {
		// $items = [];
		// foreach ( $mapping as $index => $info ) {
		// 	$value = implode(",\n", array_map(fn(mixed $sub) => var_export($sub, true), $info));
		// 	$item = sprintf("%s => new CompiledController(\n%s\n),", is_int($index) ? $index : "'$index'", $value);
		// 	$items[] = $item;
		// }
		// $code = "<?php\n\nuse " . CompiledController::class . ";\n\nreturn [\n" . implode("\n\n", $items) . "\n];\n";

		// $code = "<?php\n\nreturn unserialize('" . serialize($mapping) . "');\n";

		$code = "<?php\n\nreturn " . var_export($mapping, true) . ";\n";

		$file = self::getMappingFile();
		file_put_contents($file, $code);
	}

	/**
	 * @return AssocArray
	 */
	protected function getControllerOptions( ReflectionClass $reflection ) : array {
		$attributes = $this->getAttributes($reflection);
		$values = [];
		foreach ( $attributes as $attribute ) {
			$instance = $attribute->newInstance();
			if ( $instance instanceof ControllerAnnotationInterface ) {
				$name = $instance->controllerName();
				if ( $instance->controllerIsMultiple() ) {
					$values[$name][] = $instance->controllerSingleValue(); // @phpstan-ignore offsetAccess.nonOffsetAccessible
				}
				else {
					$values[$name] = $instance->controllerSingleValue();
				}
			}
		}

		return $values;
	}

	/**
	 * @return list<ReflectionAttribute>
	 */
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

	/**
	 * @return Generator<int, ReflectionClass>
	 */
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
