<?php

namespace Framework\Console\Commands;

use App\Services\Tpl\AppTemplate;
use FilesystemIterator;
use Framework\Console\Command;
use Framework\Errors\ErrorHandler;
use Framework\Tpl\Smarty;
use ParseError;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Smarty\Template\Compiled;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CompileViewsCommand extends Command {

	protected function configure() {
		$this->setName('compile:views');
		$this->setDescription('Compile templates into a temp dir and check the generated PHP for syntax errors');
		$this->addArgument('paths', InputArgument::IS_ARRAY, 'Template files or dirs (default: the template dirs)');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$smarty = AppTemplate::instance()->smarty;

		$paths = $input->getArgument('paths') ?: $smarty->getTemplateDir();
		$files = $this->findTemplates($paths, $smarty->_tpl_extension);

		$originalCompileDir = $smarty->getCompileDir();
		$originalThrow = ErrorHandler::$throwErrorException;

		$compileDir = sys_get_temp_dir() . '/compile-views-' . bin2hex(random_bytes(6));
		mkdir($compileDir);

		$smarty->setCompileDir($compileDir);
		ErrorHandler::$throwErrorException = true;

		$errors = 0;
		try {
			foreach ( $files as $file ) {
				$error = $this->compile($smarty, $file);
				if ( $error === null ) {
					if ( $output->isVerbose() ) {
						$output->writeln('OK     ' . $this->relativePath($file));
					}
					continue;
				}

				$errors++;
				$output->writeln('<error>ERROR</error>  ' . $this->relativePath($file));
				$output->writeln('       ' . $error);
			}
		}
		finally {
			$smarty->setCompileDir($originalCompileDir);
			ErrorHandler::$throwErrorException = $originalThrow;

			$this->removeDir($compileDir);
		}

		$output->writeln(sprintf('Compiled %d templates, %d with errors', count($files), $errors));

		return $errors ? self::FAILURE : self::SUCCESS;
	}

	protected function compile( Smarty $smarty, string $file ) : ?string {
		try {
			$template = $smarty->createTemplate($file);
			$template->compileTemplateSource();
		}
		catch ( Throwable $ex ) {
			return get_class($ex) . ': ' . $ex->getMessage();
		}

		$compiled = Compiled::load($template);
		$code = file_get_contents($compiled->filepath);

		try {
			PhpToken::tokenize($code, TOKEN_PARSE);
		}
		catch ( ParseError $ex ) {
			$lines = explode("\n", $code);
			$line = trim($lines[$ex->getLine() - 1] ?? '');
			return sprintf('Compiled PHP: %s on line %d: %s', $ex->getMessage(), $ex->getLine(), $line);
		}

		return null;
	}

	/**
	 * @param array<string> $paths
	 * @return list<string>
	 */
	protected function findTemplates( array $paths, string $extension ) : array {
		$files = [];
		foreach ( $paths as $path ) {
			if ( is_file($path) ) {
				$files[] = $path;
				continue;
			}

			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
			foreach ( $iterator as $file ) {
				/** @var SplFileInfo $file */
				if ( str_ends_with($file->getFilename(), $extension) ) {
					$files[] = $file->getPathname();
				}
			}
		}

		sort($files);

		return $files;
	}

	protected function relativePath( string $file ) : string {
		$cwd = getcwd() . '/';
		if ( str_starts_with($file, $cwd) ) {
			return substr($file, strlen($cwd));
		}

		return $file;
	}

	protected function removeDir( string $dir ) : void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ( $iterator as $file ) {
			/** @var SplFileInfo $file */
			if ( $file->isDir() ) {
				rmdir($file->getPathname());
			}
			else {
				unlink($file->getPathname());
			}
		}

		rmdir($dir);
	}

}
