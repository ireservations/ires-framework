<?php

namespace Framework\Console\Commands;

use App\Services\Aro\AppActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;
use Framework\Console\Command;
use Framework\Console\Commands\CompileModels\IncludesTablesAttribute;
use Framework\Console\Commands\CompileModels\SchemaFieldDefinition;
use Framework\Console\Commands\CompileModels\SchemaParser;
use PhpParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use db_exception;

class CompileModelsCommand extends Command {

	/** @var array<string, array<string, SchemaFieldDefinition>> */
	protected array $schemaTables = [];
	protected PhpParser\NameContext $localImports;

	protected function configure() {
		$this->setName('compile:models');

		$this->addOption('class', null, InputOption::VALUE_REQUIRED, 'Debug one model class');
		$this->addOption('dump', null, InputOption::VALUE_REQUIRED, 'Database structure file for input for better column types');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$onlyClass = $input->getOption('class');

		if ( $dumpFile = $input->getOption('dump') ) {
			// $dumpSql = file_get_contents($dumpFile);
			$this->schemaTables = (new SchemaParser($dumpFile))->getAllColumns();
		}

		$_modelsNamespaceOutput = $this->getTemplate('Models');
		$_classOutput = $this->getTemplate('Models.class');
		$_propertyOutput = $this->getTemplate('Models.class.property');

		// Find all models
		$models = glob(PROJECT_ARO . '/*.php');
		$classesOutput = [];
		$missingReturnType = $explicitGetters = [];

		/** @var PhpParser\NameContext[] $imports */
		$imports = [];
		foreach ( $models as $modelFile ) {
			require_once $modelFile;

			$modelModel = substr(basename($modelFile), 0, -4);
			$code = file_get_contents($modelFile);

			$parser = (new PhpParser\ParserFactory)->createForHostVersion();
			try {
				$ast = $parser->parse($code);
				$nameResolver = new PhpParser\NodeVisitor\NameResolver();
				$nodeTraverser = new PhpParser\NodeTraverser();
				$nodeTraverser->addVisitor($nameResolver);
				$nodeTraverser->traverse($ast);
				$imports[$modelModel] = $nameResolver->getNameContext();
			}
			catch ( PhpParser\Error $error ) {}
		}

		$totalProperties = 0;
		$gettypes = [];
		$nullStrings = [];
		foreach ( get_declared_classes() as $className ) {
			$class = new ReflectionClass($className);

			if ( $class->isAbstract() ) continue;
			if ( !in_array(AppActiveRecordObject::class, class_parents($class->getName())) ) continue;
			if ( $onlyClass && $onlyClass !== $class->getShortName() ) continue;

			$props = $class->getStaticProperties();
			if ( !isset($props['_table']) || !trim($props['_table'], '_ ') ) continue;
			$dbTable = $props['_table'];
			$dbTables = [$dbTable];

			$attributes = $class->getAttributes(IncludesTablesAttribute::class);
			if ( count($attributes) ) {
				$attribute = $attributes[0]->newInstance();
				$dbTables = [...$dbTables, ...$attribute->getTables()];
			}

			/** @var ReflectionClass<AppActiveRecordObject> $class */
			$query = call_user_func([$class->getName(), 'getQuery'], '');

			$classOutput = strtr($_classOutput, [
				'__CLASS__' => $class->getShortName(),
			]);

			$this->localImports = $imports[$class->getShortName()] ?? null;

			// Explicit properties
			$exPropsSelf = $this->getClassDocProperties($class, false);
			// $exPropsSelf += $this->getClassPublicProperties($class);
			$exPropsParents = $this->getClassDocProperties($class->getParentClass(), true);
			$allVars = $exPropsParents + $exPropsSelf + $this->getClassPublicProperties($class);

			$propertiesOutput = [];
			foreach ( $exPropsSelf as $name => $type ) {
				$propertiesOutput[$name] = strtr($_propertyOutput, [
					'__TYPE__' => $type,
					'__NAME__' => $name,
				]);
			}

			if ( $onlyClass ) {
				echo "Explicit parents (skip):\n";
				ksort($exPropsParents);
				print_r($exPropsParents);
				echo "\n";

				echo "Explicit self (add):\n";
				ksort($exPropsSelf);
				print_r($exPropsSelf);
				echo "\n";
			}

			$classOutput = strtr($classOutput, [
				'__EXPLICIT_PROPERTIES__' => rtrim(implode($propertiesOutput)) ?: ' *',
			]);

			// Columns
			try {
				$result = $this->db->fetch_first($query . ' LIMIT 1');
			}
			catch ( db_exception $ex ) {
				echo "DB ERROR: " . $ex->getMessage() . "\n\n";
				$result = [];
			}
			/** @var AppActiveRecordObject $object */
			$object = new $className($result, true);
			$propertiesOutput = [];
			foreach ( get_object_vars($object) as $name => $value ) {
				if ( !isset($allVars[$name]) ) {
					$allVars[$name] = 1;
					$totalProperties++;

					$type = gettype($value);
					$gettypes[] = $type;
					if ( $type == 'object' && get_class($value) != 'stdClass' ) {
						$type = '\\' . get_class($value);
					}
					elseif ( in_array($type, ['array']) ) {
						// Keep non-scalar type from init()
					}
					elseif ( $dbType = $this->getDbType($dbTables, $name) ) {
						$type = $dbType->getNullablePhpType();
					}
					elseif ( $type == 'double' ) {
						$type = 'float';
					}
					elseif ( $type == 'NULL' ) {
						$nullStrings[] = "$className - $name";
						$type = 'string';
					}
					$propertiesOutput[$name] = strtr($_propertyOutput, [
						'__TYPE__' => $type,
						'__NAME__' => $name,
					]);
				}
			}

			if ( $onlyClass ) {
				echo "Columns:\n";
				ksort($propertiesOutput);
				print_r(array_keys($propertiesOutput));
				echo "\n";
			}

			$classOutput = strtr($classOutput, [
				'__COLUMN_PROPERTIES__' => rtrim(implode($propertiesOutput)),
			]);

			// Relationships
			$propertiesOutput = [];
			foreach ( $class->getMethods() as $method ) {
				if ( !$method->isStatic() && strpos($method->getName(), 'relate_') === 0 ) {
					$name = substr($method->getName(), 7);

					if ( !isset($allVars[$name]) ) {
						$allVars[$name] = 1;
						$totalProperties++;

						$method->setAccessible(true);
						/** @var ActiveRecordRelationship $relation */
						$relation = $method->invoke($object);

						$propertiesOutput[$name] = strtr($_propertyOutput, [
							'__TYPE__' => $relation->getReturnType(),
							'__NAME__' => $name,
						]);
					}
				}
			}

			if ( $onlyClass ) {
				echo "Relationships:\n";
				ksort($propertiesOutput);
				print_r(array_keys($propertiesOutput));
				echo "\n";
			}

			$classOutput = strtr($classOutput, [
				'__RELATIONSHIP_PROPERTIES__' => rtrim(implode($propertiesOutput)),
			]);

			// Getters
			$propertiesOutput = [];
			foreach ( $class->getMethods() as $method ) {
				if ( !$method->isStatic() && strpos($method->getName(), 'get_') === 0 ) {
					$name = substr($method->getName(), 4);

					if ( !isset($allVars[$name]) ) {
						$allVars[$name] = 1;
						$totalProperties++;

						$type = $this->getMethodReturnType($method);

						$propertiesOutput[$name] = strtr($_propertyOutput, [
							'__TYPE__' => $type ?: 'mixed',
							'__NAME__' => $name,
						]);

						if ( !$type ) {
							$missingReturnType[$class->getName()][] = $method->getName();
						}
					}
				}
			}

			if ( $onlyClass ) {
				echo "Getters:\n";
				ksort($propertiesOutput);
				print_r(array_keys($propertiesOutput));
				echo "\n";
			}

			$classOutput = strtr($classOutput, [
				'__GETTER_PROPERTIES__' => rtrim(implode($propertiesOutput)),
			]);

			$classesOutput[] = $classOutput;
		}

		$modelsNamespaceOutput = strtr($_modelsNamespaceOutput, [
			'__CLASSES__' => str_replace("\n", "\n\t", implode("\n\n", $classesOutput)),
		]);

		echo "Total properties: $totalProperties\n\n";

		echo "Missing return types: " . array_sum(array_map('count', $missingReturnType)) . "\n";
		$missingReturnType and print_r($missingReturnType);

		echo "NULL-strings: " . count($nullStrings) . "\n";
		$nullStrings and print_r($nullStrings);

		// echo "Over-explicit return types: " . array_sum(array_map('count', $explicitGetters)) . "\n";
		// $explicitGetters and print_r($explicitGetters);

		if ( $onlyClass ) {
			return 0;
		}

		$this->write($modelsNamespaceOutput);

		echo "\nDone\n";

		return 0;
	}

	/**
	 * @param list<string> $dbTables
	 */
	protected function getDbType( array $dbTables, string $column ) : ?SchemaFieldDefinition {
		foreach ( $dbTables as $dbTable ) {
			if ( isset($this->schemaTables[$dbTable][$column]) ) {
				return $this->schemaTables[$dbTable][$column];
			}
		}

		return null;
	}

	protected function write( string $code ) : void {
		$dir = $this->getOutputDir();

		if ( !$dir ) {
			echo "I need a target dir (full).\n";
			exit(1);
		}

		$filepath = rtrim($dir, '\\/') . "/Models.php";
		$written = file_put_contents($filepath, $code);
		if ( !$written ) {
			echo "I can't seem to write to `{$filepath}`.\n";
			exit(1);
		}

		echo "\nWrote " . number_format($written) . " bytes to " . $filepath . "\n";
	}

	protected function getMethodReturnType( ReflectionMethod $method ) : string {
		$return = $origReturn = $this->getCommentType($method->getDocComment(), 'return');
		if ( $return ) {
			if ( strpos($return, '<') !== false ||  strpos($return, '{') !== false || strpos($return, '|') !== false ) {
				// echo $method->getName() . ":\n";
				return $return;
			}

			$nullable = $return[0] == '?' ? '?' : '';
			$return = ltrim($return, '?');
			$nested = preg_match('#([\[\]]+)$#', $return, $match) ? $match[1] : '';
			$return = rtrim($return, '[]');

			if ( in_array($return, ['self', 'static', 'parent', 'array', 'int', 'float', 'string', 'non-falsy-string', 'bool']) ) {
				return $nullable . $return . $nested;
			}

			if ( $return[0] == '\\' ) {
				// echo $method->getName() . ":\n";
				return $nullable . $return . $nested;
			}

			$namespacedReturn = $this->localImports->getResolvedClassName(new PhpParser\Node\Name($return))->toCodeString();
			return $nullable . $namespacedReturn . $nested;
		}

		$type = $method->getReturnType();

		return $this->getStringType($type) ?? '';
	}

	protected function getStringType( ReflectionType $type ) : ?string {
		if ( $type instanceof ReflectionUnionType ) {
			$types = array_map(function(ReflectionNamedType $type) {
				return $type->isBuiltin() ? $type->getName() : $this->unnamespaceModelClass($type->getName());
			}, $type->getTypes());
			return implode('|', $types);
		}

		if ( $type instanceof ReflectionNamedType ) {
			$nullable = $type->allowsNull() ? '?' : '';
			if ( $type->isBuiltin() ) {
				return $nullable . $type->getName();
			}

			return $nullable . $this->unnamespaceModelClass($type->getName());
		}

		return null;
	}

	protected function unnamespaceModelClass( string $className ) : string {
		$className = '\\' . $className;
		// $className = preg_replace('#^\\\\App\\\\Models\\\\#', '', $className);
		return $className;
	}

	protected function getPropertyType( ReflectionProperty $property ) : string {
		if ( $type = $this->getStringType($property->getType()) ) {
			return $type;
		}
		return $this->getCommentType($property->getDocComment(), 'var');
	}

	protected function getCommentType( ?string $comment, string $atName ) : string {
		if ( $comment ) {
			if ( preg_match('#@' . $atName . ' (.+)#', $comment, $match) ) {
				return trim($match[1], ' */');
			}
		}

		return '';
	}

	/**
	 * @return array<string, string>
	 */
	protected function getClassDocProperties( ?ReflectionClass $class, bool $includeParents ) : array {
		$props = [];

		$comment = trim($class->getDocComment());
		if ( $comment ) {
			if ( preg_match_all('#@property\s+([^\s]+)\s+\$?([^\s]+)#', $comment, $matches) ) {
				$props = array_combine($matches[2], $matches[1]);
			}
		}

		if ( $includeParents && ($parent = $class->getParentClass()) ) {
			return array_merge($props, $this->getClassDocProperties($parent, $includeParents));
		}

		return $props;
	}

	/**
	 * @return array<string, string>
	 */
	protected function getClassPublicProperties( ReflectionClass $class ) : array {
		$props = [];
		foreach ( $class->getProperties() as $prop ) {
			if ( !$prop->isStatic() && $prop->isPublic() ) {
				$props[ $prop->getName() ] = $this->getPropertyType($prop);
			}
		}

		return $props;
	}

	protected function getTemplate( string $name ) : string {
		return file_get_contents(dirname(__DIR__) . "/templates/{$name}.php.txt");
	}

	protected function getOutputDir() : string {
		return defined('PROJECT_IDE_OUTPUT') ? PROJECT_IDE_OUTPUT : SCRIPT_ROOT;
	}

}
