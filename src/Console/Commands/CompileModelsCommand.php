<?php

namespace Framework\Console\Commands;

use App\Services\Aro\AppActiveRecordObject;
use db_exception;
use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;
use Framework\Console\Command;
use PhpParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompileModelsCommand extends Command {

	protected function configure() {
		$this->setName('ide:models');
		$this->addOption('class', null, InputOption::VALUE_OPTIONAL);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$onlyClass = $input->getOption('class');

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

			$parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::ONLY_PHP7, new CompileModels\MinimalPhpLexer());
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

		/** @var ActiveRecordObject $className */
		$totalProperties = 0;
		foreach ( get_declared_classes() as $className ) {
			$class = new ReflectionClass($className);

			if ( $class->isAbstract() ) continue;
			if ( !in_array(AppActiveRecordObject::class, class_parents($class->getName())) ) continue;
			if ( $onlyClass && $onlyClass !== $class->getShortName() ) continue;

			$props = $class->getStaticProperties();
			if ( !isset($props['_table']) || !trim($props['_table'], '_ ') ) continue;

			$query = call_user_func([$class->getName(), 'getQuery'], '');

			$classOutput = strtr($_classOutput, [
				'__CLASS__' => $class->getShortName(),
			]);

			// Explicit properties
			$exProps = $this->getClassDocProperties($class);
			$allVars = $exProps;

			if ( $onlyClass ) {
				echo "Explicit (skip):\n";
				ksort($allVars);
				print_r(array_keys($allVars));
				echo "\n";
			}

			$localImports = $imports[$class->getShortName()] ?? null;

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
					if ( $type == 'object' && get_class($value) != 'stdClass' ) {
						$type = '\\' . get_class($value);
					}
					elseif ( $type == 'double' ) {
						$type = 'float';
					}
					elseif ( $type == 'NULL' ) {
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
						if ( $type ) {
							if ( $type[0] != strtolower($type[0]) ) {
								$type = $localImports->getResolvedClassName(new PhpParser\Node\Name($type))->toCodeString();
							}
							elseif ( $type[0] == '\\' ) {
								$explicitGetters[$class->getShortName()][$name] = $type;
							}
						}

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

		echo "Over-explicit return types: " . array_sum(array_map('count', $explicitGetters)) . "\n";
		$explicitGetters and print_r($explicitGetters);

		if ( $onlyClass ) {
			return;
		}

		$this->write($modelsNamespaceOutput);

		echo "\nDone\n";
	}

	protected function write( $code ) {
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

	protected function getMethodReturnType( ReflectionMethod $method ) {
		return $this->getCommentType($method->getDocComment(), 'return');
	}

	protected function gePropertyType( ReflectionProperty $method ) {
		return $this->getCommentType($method->getDocComment(), 'var');
	}

	protected function getCommentType( $comment, $atName ) {
		if ( $comment ) {
			if ( preg_match('#@' . $atName . ' +([^ ]+)#', $comment, $match) ) {
				return trim($match[1]);
			}
		}

		return '';
	}

	protected function getClassDocProperties( ReflectionClass $class ) {
		$props = [];

		$comment = trim($class->getDocComment());
		if ( $comment ) {
			if ( preg_match_all('#@property\s+([^\s]+)\s+\$?([^\s]+)#', $comment, $matches) ) {
				$props = array_combine($matches[2], $matches[1]);
			}
		}

		if ( $parent = $class->getParentClass() ) {
			return array_merge($props, $this->getClassDocProperties($parent));
		}

		return $props;
	}

	protected function getClassPublicProperties( ReflectionClass $class ) {
		$props = [];
		foreach ( $class->getProperties() as $prop ) {
			if ( !$prop->isStatic() && $prop->isPublic() ) {
				$props[ $prop->getName() ] = $this->gePropertyType($prop);
			}
		}

		return $props;
	}

	protected function getTemplate( $name ) {
		return file_get_contents(dirname(__DIR__) . "/templates/{$name}.php.txt");
	}

	protected function getOutputDir() {
		return defined('PROJECT_IDE_OUTPUT') ? PROJECT_IDE_OUTPUT : SCRIPT_ROOT;
	}

}
