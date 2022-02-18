<?php

namespace Framework\Tpl;

use Smarty as BaseSmarty;

class Smarty extends BaseSmarty {

	public $_tpl_extension = '.tpl.html';

	public function __construct() {
		parent::__construct();

		// Overwrite template & compile dirs to our needs
		$this->compile_dir = PROJECT_RUNTIME . '/tpl_c';
		$this->template_dir = [PROJECT_SMARTY_TPLS];

		// Overwrite delimiters to our wantings
		$this->left_delimiter = '<?';
		$this->right_delimiter = '?>';

		// Compilation settings
		$this->compile_check = true; // check filemtime()
		$this->force_compile = false; // ignore existing compiled

		// Custom plugins
		$this->plugins_dir = [
			PROJECT_INC_SMARTY . '/plugins',
			PROJECT_VENDORS . '/smarty/smarty/libs/plugins',
		];
		$this->default_template_handler_func = function( $type, $name, $content, $timestamp, Smarty $smarty ) {
			if ( file_exists($name . $this->_tpl_extension) ) {
				return $name . $this->_tpl_extension;
			}
			return $this->template_dir[0] . $name . $this->_tpl_extension;
		};

		// Errors & debug
		$this->setErrorReporting(error_reporting() & ~E_NOTICE);
		$this->muteUndefinedOrNullWarnings();
	}

}
