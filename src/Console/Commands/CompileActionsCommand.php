<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Framework\Http\Controller;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileActionsCommand extends Command {

	protected function configure() {
		$this->setName('compile:actions');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$mapper = Controller::getControllerMapper();
		$mapping = $mapper->createMapping();
		$mapper->saveMapping($mapping);
		echo "Controller mapping saved.\n";

		return 0;
	}

}
