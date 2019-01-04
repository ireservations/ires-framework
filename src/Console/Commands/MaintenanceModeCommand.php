<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceModeCommand extends Command {

	protected $up;

	public function __construct( $name ) {
		parent::__construct($name);

		$this->up = $name === 'up';
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		call_user_func([$this, $this->up ? 'bringUp' : 'bringDown'], $output);
	}

	protected function bringUp( OutputInterface $output ) {
		if ( file_exists($file = PROJECT_PUBLIC . '/OFFLINE') ) {
			unlink($file);
			$output->writeln('App online');
		}
		else {
			$output->writeln('App already online');
		}
	}

	protected function bringDown( OutputInterface $output ) {
		file_put_contents(PROJECT_PUBLIC . '/OFFLINE', "We are updating. We'll be back very soon.");
		$output->writeln('App offline');
	}

}
