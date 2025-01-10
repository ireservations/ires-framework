<?php

namespace Framework\Console\Commands;

use db_exception;
use Closure;
use Framework\Console\Command;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command {

	protected function configure() {
		$this->setName('migrate');
		$this->addOption('test', null, InputOption::VALUE_NONE, "Show new updates in order, but don't run them");
		$this->addOption('table', null, InputOption::VALUE_NONE, "Create migrations table");
		$this->addOption('convert-to-closures', null, InputOption::VALUE_NONE, "Convert old-style update files to new-style closures");
		$this->addOption('undo', null, InputOption::VALUE_NONE, "Undo last update (only deletes last migration record from table!)");
		$this->addOption('status', null, InputOption::VALUE_NONE, "Show summary of done vs todo updates");
		$this->addOption('create', null, InputOption::VALUE_REQUIRED, "Create a new update file");
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$table = $input->getOption('table');
		$undo = $input->getOption('undo');
		$status = $input->getOption('status');
		$create = $input->getOption('create');
		$convert = $input->getOption('convert-to-closures');

		if ( $table ) {
			return $this->executeTable($input, $output);
		}

		if ( $convert ) {
			return $this->executeConvertToClosures($input, $output);
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

	protected function executeConvertToClosures( InputInterface $input, OutputInterface $output ) : int {
		$test = $input->getOption('test');

		$files = $this->getUpdates();
		$codeTemplate = $this->getTemplate('update');

		$converted = 0;
		foreach ( $files as $name => $filepath ) {
			$code = file_get_contents($filepath);

			if ( str_contains($code, "\nreturn function(") ) continue;

			$code = trim(str_replace('<?php', '', $code));
			$code = trim(str_replace('global $db;', '', $code));
			$code = trim(str_replace('/*'.'* @var \db_generic $db */', '', $code));
			$code = trim(str_replace('/*'.'* @var db_generic $db */', '', $code));

			$uses = '';
			if ( preg_match_all('#use [\\w+\\\\]+;#', $code, $matches) ) {
				$uses = "\n" . implode("\n", $matches[0]) . "\n";

				foreach ( $matches[0] as $oneUse ) {
					$code = trim(str_replace($oneUse, '', $code));
				}
			}

			$innerCode = str_replace("\n", "\n\t", $code);
			$innerCode = str_replace("\n\t\n", "\n\n", $innerCode);
			$innerCode = str_replace("\n\t\n", "\n\n", $innerCode);

			$outerCode = str_replace('__CODE__', $innerCode, $codeTemplate);
			$outerCode = str_replace('__USES__', $uses, $outerCode);

			if ( $test ) {
				echo "\n\n\n\n\n\n\n$name\n\n$outerCode\n\n\n\n\n\n\n\n\n";
			}
			else {
				file_put_contents($filepath, $outerCode);
			}

			$converted++;
		}

		if ( !$test ) {
			echo "$converted / " . count($files) . " files updated. See dirty working dir.\n";
		}

		return 0;
	}

	protected function executeCreate( InputInterface $input, OutputInterface $output ) : int {
		echo "\n";

		$create = (string) $input->getOption('create');

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

		$codeTemplate = $this->getTemplate('update');
		$outerCode = str_replace('__CODE__', '', $codeTemplate);
		$outerCode = str_replace('__USES__', '', $outerCode);
		file_put_contents($filepath, $outerCode);

		echo "$filename\n";

		return 0;
	}

	protected function executeStatus( InputInterface $input, OutputInterface $output ) : int {
		echo "\n";

		$done = $this->getDoneUpdates();

		$done = count($done);
		$todo = count($this->getNewUpdates());

		echo "$todo migrations TO DO.\n";
		echo "$done migrations done.\n";

		return 0;
	}

	protected function executeUndo( InputInterface $input, OutputInterface $output ) : int {
		$test = $input->getOption('test');

		echo "\n";

		$done = $this->getDoneUpdates();
		$undo = array_pop($done);

		if ( !$undo ) {
			echo "No migrations to undo.\n";
			return 0;
		}

		if ( $test ) {
			echo "Will undo migration `$undo`.\n";
			return 0;
		}

		$this->saveUndone($undo);
		echo "Migration `$undo` undone.\n";

		return 0;
	}

	protected function executeTable( InputInterface $input, OutputInterface $output ) : int {
		$test = $input->getOption('test');

		echo "\n";

		if ( $this->tableExists() ) {
			$count = count($this->getDoneUpdates());
			echo "Migrations table already exists, with $count migrations.\n";
			return 0;
		}

		if ( $test ) {
			echo "Migrations table does NOT exist yet.\n";
			return 0;
		}

		$this->createTable();

		return 0;
	}

	protected function executeMigrate( InputInterface $input, OutputInterface $output ) : int {
		$test = $input->getOption('test');

		$_start = microtime(true);

		if ( file_exists($file = PROJECT_INCLUDE . '/inc.update.php') ) {
			require $file;
		}

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

				$db = $this->db; // $db is used in old-style update

				$callback = require $file;
				if ( $callback instanceof Closure ) {
					$callback($db);
				}

				echo "\n~~ success ~~\n";

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

		echo number_format(microtime(true) - $_start, 2, '.', '') . ' s';

		echo "\n\n";

		return 0;
	}

	protected function saveDone( string $migration ) : void {
		$this->db->insert('migrations', ['name' => $migration]);
	}

	protected function saveUndone( string $migration ) : void {
		$this->db->delete('migrations', ['name' => $migration]);
	}

	/**
	 * @return array<string, string>
	 */
	protected function getUpdates() : array {
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

	/**
	 * @return array<string, string>
	 */
	protected function getDoneUpdates() : array {
		return $this->db->select_fields('migrations', 'name', '1 ORDER BY ran_on ASC');
	}

	/**
	 * @return array<string, string>
	 */
	protected function getNewUpdates() : array {
		$done = $this->getDoneUpdates();

		$updates = $this->getUpdates();
		foreach ( $updates as $name => $file ) {
			if ( isset($done[$name]) ) {
				unset($updates[$name]);
			}
		}

		return $updates;
	}

	protected function tableExists() : bool {
		try {
			$this->db->count('migrations', '1');
			return true;
		}
		catch ( db_exception $ex ) {}
		return false;
	}

	protected function createTable() : void {
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

	protected function getTemplate( string $name ) : string {
		return file_get_contents(dirname(__DIR__) . "/templates/{$name}.php.txt");
	}

}
