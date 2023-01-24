<?php

namespace Framework\Console\Commands;

use Framework\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends Command {

	protected $file;

	protected function configure() {
		$this->setName('version');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->file = SCRIPT_ROOT . '/VERSION';

		$curVersion = $this->getCurrentVersion();
		$newVersion = $this->makeNewVersion($curVersion);

		$this->saveVersion($newVersion);

		$output->writeln($newVersion);

		return 0;
	}

	protected function getCurrentVersion() {
		return trim(file_get_contents($this->file));
	}

	protected function makeNewVersion( $curVersion ) {
		list($numbers, $name) = explode('-', $curVersion . '-');

		$numbers = array_map(function($component) {
			return (int) $component;
		}, explode('.', $numbers));

		$numbers[count($numbers) - 1]++;
		$numbers = implode('.', $numbers);

		$version = $numbers . ($name ? "-$name" : '');
		return $version;
	}

	protected function saveVersion( $version ) {
		file_put_contents($this->file, $version);
	}

}
