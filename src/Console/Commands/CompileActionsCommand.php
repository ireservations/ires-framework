<?php

namespace Framework\Console\Commands;

use App\Services\Http\AppController;
use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileActionsCommand extends Command {

	protected function configure() {
		$this->setName('compile:actions');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$mapper = AppController::getControllerMapper();
		$mapping = $mapper->createMapping();
		$mapper->saveMapping($mapping);
		echo "Controller mapping saved.\n";
	}

}
