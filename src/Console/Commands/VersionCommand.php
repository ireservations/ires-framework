<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends Command {

	protected string $file;

	protected function configure() {
		$this->setName('version');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$this->file = SCRIPT_ROOT . '/VERSION';

		$curVersion = $this->getCurrentVersion();
		$newVersion = $this->makeNewVersion($curVersion);

		$this->saveVersion($newVersion);

		$output->writeln($newVersion);

		return 0;
	}

	protected function getCurrentVersion() : string {
		return trim(file_get_contents($this->file));
	}

	protected function makeNewVersion( string $curVersion ) : string {
		list($numbers, $name) = explode('-', $curVersion . '-');

		$numbers = array_map(function($component) {
			return (int) $component;
		}, explode('.', $numbers));

		$numbers[count($numbers) - 1]++;
		$numbers = implode('.', $numbers);

		$version = $numbers . ($name ? "-$name" : '');
		return $version;
	}

	protected function saveVersion( string $version ) : void {
		file_put_contents($this->file, $version);
	}

}
