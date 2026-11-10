<?php

namespace Framework\Tpl;

use App\Services\Http\AppController;
use Smarty\Smarty as BaseSmarty;
use Smarty\Template;

class Smarty extends BaseSmarty {

	public string $_tpl_extension = '.tpl.html';

	public function __construct() {
		parent::__construct();

		// Overwrite template & compile dirs to our needs
		$this->setCompileDir(PROJECT_RUNTIME . '/tpl_c');
		$this->setTemplateDir([PROJECT_SMARTY_TPLS]);

		// Overwrite delimiters to our wantings
		$this->setLeftDelimiter('<?');
		$this->setRightDelimiter('?>');

		// Compilation settings
		$this->compile_check = BaseSmarty::COMPILECHECK_ON; // check filemtime()
		$this->force_compile = false; // ignore existing compiled

		// Custom plugins
		$this->addPluginDirs([PROJECT_INC_SMARTY . '/plugins']);

		// Route smarty function: {route name='invoices.edit' args=[123]}
		// $this->registerPlugin('function', 'route', function(array $params, Template $smarty) {
		// 	$args = is_array($params['args'] ?? []) ? ($params['args'] ?? []) : [$params['args']];
		// 	$path = AppController::route($params['name'], ...$args);
		// 	if ( !empty($params['assign']) ) {
		// 		$smarty->assign($params['assign'], $path);
		// 		return '';
		// 	}
		// 	return $path;
		// });

		// Route real function: {route('invoices.edit', 123)}
		$this->registerPlugin('modifier', 'route', route(...));

		// Template name/ext
		$this->default_template_handler_func = function(string $type, string $name, ?string $content, ?int $modified, self $smarty) : string {
			if ( file_exists($name . $this->_tpl_extension) ) {
				return $name . $this->_tpl_extension;
			}
			return strval($this->getTemplateDir(0)) . $name . $this->_tpl_extension;
		};

		// Errors & debug
		$this->setErrorReporting(error_reporting() & ~E_NOTICE);
		$this->muteUndefinedOrNullWarnings();
	}

	/**
	 * @param list<string> $dirs
	 */
	protected function addPluginDirs( array $dirs ) : void {
		$extensions = $this->getExtensions();
		array_unshift($extensions, new PluginsDirExtension($dirs));
		$this->setExtensions($extensions);
	}

}
