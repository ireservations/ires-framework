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
		touch(sprintf('%s/compileviews.tmp', PROJECT_SMARTY_TPLS));
		$cmd = sprintf('rm -f %s/*.php', PROJECT_SMARTY_TPLS);
		shell_exec($cmd);

		$tpl = AppTemplate::instance();
		$tpl->smarty->compileAllTemplates($tpl->smarty->_tpl_extension, true, 99, 999);

		return 0;
	}

}
