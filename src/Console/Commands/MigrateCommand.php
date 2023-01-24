<?php

namespace Framework\Console\Commands;

use db_exception;
use Framework\Console\Command;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command {

	protected function configure() {
		$this->setName('migrate');
		$this->addOption('test', null, InputOption::VALUE_NONE);
		$this->addOption('table', null, InputOption::VALUE_NONE);
		$this->addOption('undo', null, InputOption::VALUE_NONE);
		$this->addOption('status', null, InputOption::VALUE_NONE);
		$this->addOption('create', null, InputOption::VALUE_REQUIRED);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$table = $input->getOption('table');
		$undo = $input->getOption('undo');
		$status = $input->getOption('status');
		$create = $input->getOption('create');

		if ( $table ) {
			return $this->executeTable($input, $output);
		}

		if ( $create ) {
			return $this->executeCreate($input, $output);
		}

		if ( $undo ) {
			return $this->executeUndo($input, $output);
		}

		if ( $status ) {
			return $this->executeStatus($input, $output);
		}

		return $this->executeMigrate($input, $output);
	}

	protected function executeCreate( InputInterface $input, OutputInterface $output ) {
		echo "\n";

		$create = $input->getOption('create');

		$updates = $this->getUpdates();
		if ( count($updates) ) {
			$numbers = array_map(function($name) {
				return (int) $name;
			}, array_keys($updates));
			rsort($numbers, SORT_NUMERIC);
			$number = $numbers[0] + 1;
		}
		else {
			$number = 1;
		}

		$filename = "auto-$number-$create.php";
		$filepath = SCRIPT_ROOT . "/_updates/$filename";
		file_put_contents($filepath, trim('
<?php

/** @var \db_generic $db */
global $db;

$db->query("

");
		') . "\n\n");

		echo "$filename\n";

		return 0;
	}

	protected function executeStatus( InputInterface $input, OutputInterface $output ) {
		echo "\n";

		$done = $this->getDoneUpdates();

		$done = count($done);
		$todo = count($this->getNewUpdates());

		echo "$todo migrations TO DO.\n";
		echo "$done migrations done.\n";

		return 0;
	}

	protected function executeUndo( InputInterface $input, OutputInterface $output ) {
		$test = $input->getOption('test');

		echo "\n";

		$done = $this->getDoneUpdates();
		$undo = array_pop($done);

		if ( !$undo ) {
			echo "No migrations to undo.\n";
			return;
		}

		if ( $test ) {
			echo "Will undo migration `$undo`.\n";
			return;
		}

		$this->saveUndone($undo);
		echo "Migration `$undo` undone.\n";

		return 0;
	}

	protected function executeTable( InputInterface $input, OutputInterface $output ) {
		$test = $input->getOption('test');

		echo "\n";

		if ( $this->tableExists() ) {
			$count = count($this->getDoneUpdates());
			echo "Migrations table already exists, with $count migrations.\n";
			return;
		}

		if ( $test ) {
			echo "Migrations table does NOT exist yet.\n";
			return;
		}

		$this->createTable();

		return 0;
	}

	protected function executeMigrate( InputInterface $input, OutputInterface $output ) {
		$test = $input->getOption('test');

		$_start = microtime(1);

		require PROJECT_INCLUDE . '/inc.update.php';

		echo "\n";

		if ( !$test ) {
			echo "#### #### UPDATES ({$this->db->db_name}) #### ####\n\n";
		}

		if ( !$this->tableExists() ) {
			$this->createTable();
			echo "\n";
		}

		$updates = $this->getNewUpdates();
		echo count($updates) . " updates\n\n";

		try {
			foreach ( $updates AS $name => $file ) {
				echo '==== ' . $name . " ====\n\n";

				if ( $test ) {
					echo "\n";
					continue;
				}

				$db = $this->db;
				// $db is used in the update
				require $file;

				echo "~~ success ~~\n";

				// update status
				$this->saveDone($name);

				echo "\n\n";
			}
		}
		catch ( Exception $ex ) {
			$message = 'UPDATE FAILED';
			$border = str_repeat('~', strlen($message) + 6);
			echo $border . "\n~~ $message ~~\n" . $border . "\n\n";
			echo $ex . "\n";
			echo "\n";
		}

		echo number_format(microtime(1) - $_start, 2, '.', '') . ' s';

		echo "\n\n";

		return 0;
	}

	protected function saveDone( $migration ) {
		$this->db->insert('migrations', ['name' => $migration]);
	}

	protected function saveUndone( $migration ) {
		$this->db->delete('migrations', ['name' => $migration]);
	}

	protected function getUpdates() {
		$files = glob(SCRIPT_ROOT . '/_updates/auto-*');

		$updates = [];
		foreach ( $files as $file ) {
			if ( preg_match('#^auto-(\d+-.+)\.php$#', basename($file), $match) ) {
				$updates[ $match[1] ] = $file;
			}
		}

		uksort($updates, 'strnatcmp');

		return $updates;
	}

	protected function getDoneUpdates() {
		return $this->db->select_fields('migrations', 'name', '1 ORDER BY ran_on ASC');
	}

	protected function getNewUpdates() {
		$done = $this->getDoneUpdates();

		$updates = $this->getUpdates();
		foreach ( $updates as $name => $file ) {
			if ( isset($done[$name]) ) {
				unset($updates[$name]);
			}
		}

		return $updates;
	}

	protected function tableExists() {
		try {
			$this->db->count('migrations', '1');
			return true;
		}
		catch ( db_exception $ex ) {}
		return false;
	}

	protected function createTable() {
		echo "Creating migrations table...\n";
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `migrations` (
				`name` varchar(255) NOT NULL,
				`ran_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`name`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		");
		echo "Migrations table created.\n";
	}

}
