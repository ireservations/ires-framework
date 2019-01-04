<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use App\Services\Http\AppController;
use ReflectionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileActionsCommand extends Command {

	protected function configure() {
		$this->setName('ide:actions');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Find controllers
		$files = $this->allFiles(PROJECT_LOGIC);

		$prefix = realpath(PROJECT_LOGIC) . '/';

		$actions = $specialActions = $exceptions = [];
		foreach ( $files as $file ) {
			$controllerFile = substr($file, strlen($prefix));
			$controller = preg_replace('#Controller\.php$#', '', $controllerFile);
			if ( $controller == $controllerFile ) continue;

			$controller = str_replace('\\', '/', $controller);
			if ( !($ctrlrPath = array_search(strtolower($controller), AppController::$mapping)) ) {
				$ctrlrPath = strtolower($controller);
			}
			$class = 'App\\Controllers\\' . str_replace('/', '\\', $controller) . 'Controller';
			if ( $ctrlrPath === 'home' ) {
				$ctrlrPath = '';
			}

			try {
				/** @var AppController $ctrlr */
				$ctrlr = new $class('');
				$hooks = $ctrlr->getHooks();

				foreach ( $hooks as $hook ) {
					$path = '/' . trim($ctrlrPath . $hook->path, '/');
					$actions[] = $path;

					if ( $hook->method != 'all' || count($hook->args) ) {
						$specialActions[] = $path;
					}
				}
			}
			catch ( ReflectionException $ex) {
				$exceptions[] = $ex->getMessage();
			}
		}

		sort($actions);
		sort($specialActions);

		echo "\n" . count($actions) . " actions:\n\n";
		echo implode("\n", $actions) . "\n\n";
		echo "^ " . count($actions) . " actions\n";

		echo "\n" . count($specialActions) . " special actions:\n\n";
		echo implode("\n", $specialActions) . "\n\n";
		echo "^ " . count($specialActions) . " special actions\n";

		if ( $exceptions ) {
			echo "\nerrors:\n";
			print_r($exceptions);
		}
	}

	function allFiles( $dir ) {
		$files = array();
		foreach ( glob($dir . '/*') as $file ) {
			if ( is_dir($file) ) {
				$files = array_merge($files, $this->allFiles($file));
			}
			else {
				$files[] = realpath($file);
			}
		}
		return $files;
	}

}
