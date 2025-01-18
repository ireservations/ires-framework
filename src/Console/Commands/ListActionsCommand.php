<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Framework\Http\Controller;
use Framework\Http\Hook;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListActionsCommand extends Command {

	protected function configure() {
		$this->setName('list:actions');
		$this->addOption('controllers', null, InputOption::VALUE_NONE);
		$this->addOption('grep', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$verbose = $output->isVerbose();

		$ctrlrMapper = Controller::getControllerMapper();
		$controllers = $ctrlrMapper->createMapping();

		$actions = $otherPublicMethods = $actionsPerController = $sourcePerController = $errors = [];
		foreach ( $controllers as $compiledCtrlr ) {
			try {
				$class = $compiledCtrlr->class;
				$ctrlr = new $class('');
				$actionMapper = $ctrlr->getActionMapper();
				$hooks = $actionMapper->getMapping();
				$actionsPerController[$class] = count(array_unique(array_map(function(Hook $hook) {
					return $hook->action;
				}, $hooks)));
				$sourcePerController[$class] = $actionMapper->getSource();

				$actionMethods = [];
				foreach ( $hooks as $hook ) {
					$actionMethods[] = $hook->action;
					$path = '/' . trim($compiledCtrlr->path . $hook->path, '/');

					$special = [];
					$reflMethod = new ReflectionMethod($class, $hook->action);
					if ( !$reflMethod->isPublic() ) {
						$special[] = ' !! NOT PUBLIC !! ';
					}
					if ( count($hook->args) ) {
						$special[] = 'ARGS';
					}
					$special = count($special) ? '(' . implode(' + ', $special) . ')' : '';
					$verbs = $hook->method == 'all' ? ['GET', 'POST'] : [strtoupper($hook->method)];
					foreach ($verbs as $verb) {
						$fullAction = sprintf('% 4s  %s  %s', $verb, $path, $special);
						if ( in_array($fullAction, $actions) ) {
							$errors[] = 'DOUBLE: ' . $fullAction;
						}
						$actions[] = $fullAction;
					}
				}

				foreach ( (new ReflectionClass($class))->getMethods() as $reflMethod ) {
					if (
						!$reflMethod->isStatic() &&
						$reflMethod->isPublic() &&
						!in_array($reflMethod->getName(), $actionMethods) &&
						!$reflMethod->getDeclaringClass()->isAbstract()
					) {
						$otherPublicMethods[] = sprintf('%s::%s()', $class, $reflMethod->getName());
					}
				}
			}
			catch ( ReflectionException $ex) {
				$errors[] = $ex->getMessage();
			}
		}

		if ( $input->getOption('controllers') ) {
			$sources = array_count_values($sourcePerController);
			arsort($sources);
			$mostSource = key($sources);

			echo "\nMethods per controller (" . count($actionsPerController) . "):\n\n";
			$ctrlrPrefix = $this->findCommonNamespace(array_keys($actionsPerController));
			uksort($actionsPerController, function(string $a, string $b) {
				// strtolower - group `members\AbcContr` with `MembersContr`
				// str_replace - `members\Abc` comes after `MembersContr` but before `memberships\Abc`
				return str_replace('\\', 'd', strtolower($a)) <=> str_replace('\\', 'd', strtolower($b));
			});
			foreach ( $actionsPerController as $ctrlrClass => $numActions ) {
				$source = $sourcePerController[$ctrlrClass];
				$source = $source == $mostSource ? '' : '(' . $source . ')';
				printf("% 4d  %s  %s\n", $numActions, substr($ctrlrClass, strlen($ctrlrPrefix)), $source);
			}
			echo "\n";

			$this->showOtherPublicMethods($otherPublicMethods);

			return 0;
		}

		$showActions = $actions;
		if ( count($greps = $input->getOption('grep')) ) {
			$filter = function($action) use ($greps) {
				foreach ( $greps as $grep ) {
					if ( strpos($action, $grep) === false ) {
						return false;
					}
				}
				return true;
			};
			$showActions = array_filter($showActions, $filter);
		}

		sort($showActions);
		usort($showActions, function(string $a, string $b) {
			return substr($a, 6) <=> substr($b, 6);
		});

		echo "\n";
		echo "v " . count($showActions) . " / " . count($actions) . " actions:\n\n";
		echo " " . implode("\n ", $showActions) . "\n\n";
		echo "^ " . count($showActions) . " / " . count($actions) . " actions\n\n";

		if ( $verbose ) {
			echo "Actions per source type:\n";
			$sources = [];
			foreach ( $actionsPerController as $ctrlrClass => $numActions ) {
				$source = $sourcePerController[$ctrlrClass];
				$sources[$source] ??= 0;
				$sources[$source] += $numActions;
			}
			print_r($sources);
			echo "\n";
		}

		$this->showOtherPublicMethods($otherPublicMethods);

		if ( $errors ) {
			echo "Errors:\n";
			print_r($errors);
			echo "\n";
		}

		return 0;
	}

	/**
	 * @param list<string> $methods
	 */
	private function showOtherPublicMethods(array $methods) : void {
		if ( count($methods) ) {
			echo "Other public methods:\n";
			print_r($methods);
			echo "\n";
		}
	}

	/**
	 * @param list<string> $names
	 */
	private function findCommonNamespace(array $names) : string {
		$potentials = explode('\\', $names[0]);
		for ( $i = count($potentials); $i > 0; $i-- ) {
			$attempt = implode('\\', array_slice($potentials, 0, $i));
			foreach ( $names as $name ) {
				if ( !str_starts_with($name, $attempt) ) {
					continue 2;
				}
			}

			return "$attempt\\";
		}

		return '';
	}

}
