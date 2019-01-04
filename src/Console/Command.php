<?php

namespace Framework\Console;

use db_generic;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends BaseCommand {

	/** @var db_generic */
	protected $db;

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		parent::initialize($input, $output);

		$this->db = $GLOBALS['db'];
	}

	static public function runWithParams( array $params = [] ) {
		$command = new static();
		$output = new BufferedOutput();

		ob_start();
		$command->run(new ArrayInput($params), $output);
		$echo = trim(ob_get_clean());

		$stdout = trim($output->fetch());

		$output->write($stdout);
		$stdout and $echo and $output->writeln("\n====\n====\n");
		$output->write($echo);

		return $output;
	}

}
