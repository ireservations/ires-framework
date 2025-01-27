<?php

namespace Framework\Tpl;

use App\Services\Http\AppController;
use Smarty as BaseSmarty;
use Smarty_Internal_Template;
use Smarty_Security;

class Smarty extends BaseSmarty {

	public string $_tpl_extension = '.tpl.html';

	public function __construct() {
		parent::__construct();

		// Overwrite template & compile dirs to our needs
		$this->compile_dir = PROJECT_RUNTIME . '/tpl_c';
		$this->template_dir = [PROJECT_SMARTY_TPLS];

		// Overwrite delimiters to our wantings
		$this->left_delimiter = '<?';
		$this->right_delimiter = '?>';

		// Compilation settings
		$this->compile_check = BaseSmarty::COMPILECHECK_ON; // check filemtime()
		$this->force_compile = false; // ignore existing compiled

		// Custom plugins
		$this->plugins_dir = [
			PROJECT_INC_SMARTY . '/plugins',
			PROJECT_VENDORS . '/smarty/smarty/libs/plugins',
		];

		// Route smarty function: {route name='invoices.edit' args=[123]}
		$this->registerPlugin('function', 'route', function(array $params, Smarty_Internal_Template $smarty) {
			$args = is_array($params['args'] ?? []) ? ($params['args'] ?? []) : [$params['args']];
			$path = AppController::route($params['name'], ...$args);
			if ( !empty($params['assign']) ) {
				$smarty->assign($params['assign'], $path);
				return '';
			}
			return $path;
		});

		// Route real function: {route('invoices.edit', 123)}
		$this->registerPlugin('modifier', 'route', route(...));

		// Template name/ext
		$this->default_template_handler_func = function(string $type, string $name, ?string $content, ?int $modified, self $smarty) : string {
			if ( file_exists($name . $this->_tpl_extension) ) {
				return $name . $this->_tpl_extension;
			}
			return strval($this->template_dir[0]) . $name . $this->_tpl_extension;
		};

		// Errors & debug
		$this->setErrorReporting(error_reporting() & ~E_NOTICE);
		$this->muteUndefinedOrNullWarnings();
	}

}
