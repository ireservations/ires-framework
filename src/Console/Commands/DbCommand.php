<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DbCommand extends Command {

	protected function configure() {
		parent::configure();

		$this->setName('db');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$process = new Process(['mysql', '-u' . SQL_USER, '-p' . SQL_PASS, SQL_DB]);
		$process->setTimeout(null);
		$process->setTty(true);
		$process->mustRun(function($type, $buffer) use ($output) {
			$output->write($buffer);
		});

		return 0;
	}

}
