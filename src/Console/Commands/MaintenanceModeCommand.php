<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceModeCommand extends Command {

	protected bool $up;

	public function __construct( ?string $name = null ) {
		parent::__construct($name);

		$this->up = $name === 'up';
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		call_user_func([$this, $this->up ? 'bringUp' : 'bringDown'], $output);

		return 0;
	}

	protected function bringUp( OutputInterface $output ) : void {
		@unlink(PROJECT_PUBLIC . '/OFFLINE');

		$this->printStatus($output, true);
	}

	protected function bringDown( OutputInterface $output ) : void {
		@file_put_contents(PROJECT_PUBLIC . '/OFFLINE', "We are updating. We'll be back very soon.");

		$this->printStatus($output, false);
	}

	protected function printStatus( OutputInterface $output, bool $expectOnline ) : void {
		usleep(200000);

		$online = !file_exists(PROJECT_PUBLIC . '/OFFLINE');
		$error = $online != $expectOnline ? ' !! ' : '';
		$still = $error ? ' still' : '';

		if ( $online ) {
			$output->writeln("{$error}App$still online{$error}");
		}
		else {
			$output->writeln("{$error}App$still offline{$error}");
		}
	}

}
