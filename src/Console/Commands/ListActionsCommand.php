<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use App\Services\Http\AppController;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListActionsCommand extends Command {

	protected function configure() {
		$this->setName('list:actions');
		$this->addOption('grep', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$ctrlrMapper = AppController::getControllerMapper();
		$controllers = $ctrlrMapper->createMapping();

		$actions = $exceptions = [];
		foreach ( $controllers as $ctrlrPath => [$class, $options] ) {
			try {
				$ctrlr = new $class('');
				$actionMapper = $ctrlr->getActionMapper();
				$hooks = $actionMapper->getMapping();

				foreach ( $hooks as $hook ) {
					$path = '/' . trim($ctrlrPath . $hook->path, '/');

					$special = [];
					$method = new ReflectionMethod($class, $hook->action);
					if ( !$method->isPublic() ) {
						$special[] = ' !! NOT PUBLIC !! ';
					}
					if ( count($hook->args) ) {
						$special[] = 'ARGS';
					}
					$special = count($special) ? '(' . implode(' + ', $special) . ')' : '';
					$verbs = $hook->method == 'all' ? ['GET', 'POST'] : [strtoupper($hook->method)];
					foreach ($verbs as $verb) {
						$actions[] = sprintf('% 4s  %s  %s', $verb, $path, $special);
					}
				}
			}
			catch ( ReflectionException $ex) {
				$exceptions[] = $ex->getMessage();
			}
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

		echo "\n" . count($showActions) . " / " . count($actions) . " actions:\n\n";
		echo implode("\n", $showActions) . "\n\n";
		echo "^ " . count($showActions) . " / " . count($actions) . " actions\n";

		if ( $exceptions ) {
			echo "\nerrors:\n";
			print_r($exceptions);
		}

		return 0;
	}

}
