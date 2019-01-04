<?php

namespace Framework\Tpl;

use App\Services\Tpl\AppTemplate;
use Framework\Http\Request;

class Template {

	static public $layout = 'framework';

	static protected $instance;

	/** @return AppTemplate */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new AppTemplate;
		}
		return self::$instance;
	}

	public $smarty;

	function __construct() {
		$this->smarty = new Smarty;
	}


	/**
	 * Display a smarty template
	 */
	function display( $template, $layout = null, $fetch = false ) {
		$layout or $layout = static::$layout;

		$this->beforeDisplay();

		$szContent = trim($this->fetch($template));
		$szTitle = trim($this->smarty->getTemplateVars('title'));

		$this->smarty->assign('szDocumentTitle', strip_tags($szTitle));
		$this->smarty->assign('szDocumentBody', $szContent);

		// Display
		$method = $fetch ? 'fetch' : 'display';
		return $this->smarty->$method($layout);
	}


	/**
	 * Fetch a parsed template as string
	 */
	function fetch( $template ) {
		global $db;

		$this->smarty->assign('g_szRequestUri', Request::uri());

		$this->smarty->assign('db', $db);

		$fParseTime = number_format(microtime(true) - UTC_START, 4, ".", ",");
		$this->smarty->assign("fParseTime", $fParseTime);

		$this->beforeFetch();

		return trim($this->smarty->fetch($template));
	}


	/**
	 *
	 */
	function response( $template, $layout = null ) {
		$popup = Request::ajax();
		$mobile = Request::mobileVersion();
		$fetch = $popup || $mobile;
		$function = $fetch ? 'fetch' : 'display';

		$html = $this->$function($template, $layout);
		$html = $this->afterResponse($html);

		return $html;
	}


	/**
	 *
	 */
	function frame( $template, $layout = null ) {
		if ( Request::ajax() ) {
			return $this->fetch($template . '_frame');
		}

		return $this->response($template, $layout);
	}


	/**
	 * Assign last second vars
	 */
	function beforeDisplay() {
	}


	/**
	 * Assign last second vars
	 */
	function beforeFetch() {
	}


	/**
	 * Change response() output
	 */
	function afterResponse( $html ) {
		return $html;
	}


	/**
	 * Assign vars to the smarty object
	 */
	function assign( $key, $val = null ) {
		if ( is_array($key) ) {
			foreach ( $key AS $k => $v ) {
				$this->smarty->assign($k, $v);
			}

			return;
		}

		$this->smarty->assign($key, $val);
	}

}
