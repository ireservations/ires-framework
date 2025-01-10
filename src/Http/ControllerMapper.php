<?php

namespace Framework\Http;

use Framework\Annotations\Controller;
use Framework\Annotations\ControllerAnnotationInterface;
use Framework\Http\Controller as BaseController;
use Generator;
use ReflectionAttribute;
use ReflectionClass;

/**
 * @phpstan-type AttributeValues AssocArray
 * @phpstan-type Mapping array<string, array{class-string<BaseController>, AttributeValues}>
 */
class ControllerMapper {

	/** @var list<class-string> */
	static protected array $beforeClasses;

	/**
	 * @return Mapping
	 */
	public function getMapping() : array {
		$file = self::getMappingFile();
		if ( file_exists($file) ) {
			return include $file;
		}

		return $this->createMapping();
	}

	/**
	 * @return Mapping
	 */
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

	/**
	 * @param Mapping $mapping
	 */
	public function saveMapping( array $mapping ) : void {
		$file = self::getMappingFile();
		$mapping = $this->createMapping();
		$code = "<?php\n\nreturn " . var_export($mapping, true) . ";\n";
		file_put_contents($file, $code);
	}

	/**
	 * @return AttributeValues
	 */
	protected function getAttributeValues( ReflectionClass $reflection ) : array {
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
