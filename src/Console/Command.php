<?php

namespace Framework\Console;

use db_generic;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-consistent-constructor
 */
abstract class Command extends BaseCommand {

	protected db_generic $db;

	public function __construct( ?string $name = null ) {
		parent::__construct($name);
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) : void {
		parent::initialize($input, $output);

		$this->db = $GLOBALS['db'];
	}

	/**
	 * @param AssocArray $params
	 */
	static public function runWithParams( array $params = [] ) : BufferedOutput {
		$command = new static();
		$output = new BufferedOutput();

		ob_start();
		$command->run(new ArrayInput($params), $output);
		$echo = trim(ob_get_clean());

		$stdout = trim($output->fetch());

		$output->write($stdout);
		if ( $stdout and $echo ) $output->writeln("\n====\n====\n");
		$output->write($echo);

		return $output;
	}

}
