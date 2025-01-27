<?php

namespace Framework\Console\Commands;

use App\Services\Tpl\AppTemplate;
use Framework\Console\Command;
use Framework\Http\Controller;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileViewsCommand extends Command {

	protected function configure() {
		$this->setName('compile:views');
	}

	protected function execute( InputInterface $input, OutputInterface $output ) : int {
		$tpl = AppTemplate::instance();
		$compileDir = rtrim($tpl->smarty->compile_dir, '/');

		touch(sprintf('%s/compileviews.tmp.php', $compileDir));

		$cmd = sprintf('rm -f %s/*.php', $compileDir);
		shell_exec($cmd);

		$tpl->smarty->compileAllTemplates($tpl->smarty->_tpl_extension, true, 99, 999);

		shell_exec($cmd);

		return 0;
	}

}
