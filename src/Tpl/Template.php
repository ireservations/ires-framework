<?php

namespace Framework\Tpl;

use App\Services\Tpl\AppTemplate;
use Framework\Aro\ActiveRecordObject;
use Framework\Http\Request;

class Template {

	static public string $layout = 'framework';

	static protected AppTemplate $instance;

	public static function instance() : AppTemplate {
		return self::$instance ??= new AppTemplate;
	}

	public Smarty $smarty;

	public function __construct() {
		$this->smarty = new Smarty;
	}


	/**
	 * Display a smarty template
	 */
	public function display( string $template, ?string $layout = null, bool $fetch = false ) : ?string {
		if ( !$layout ) $layout = static::$layout;

		$this->beforeDisplay();

		$szContent = trim($this->fetch($template));
		$szTitle = trim($this->smarty->getTemplateVars('title') ?? '');

		$this->smarty->assign('szDocumentTitle', strip_tags($szTitle));
		$this->smarty->assign('szDocumentBody', $szContent);

		// Display
		$method = $fetch ? 'fetch' : 'display';
		return $this->smarty->$method($layout);
	}


	/**
	 * Fetch a parsed template as string
	 */
	public function fetch( string $template ) : string {
		$db = ActiveRecordObject::getDbObject();

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
	public function response( string $template, ?string $layout = null ) : string {
		$function = Request::ajax() ? 'fetch' : 'display';

		$html = $this->$function($template, $layout) ?? '';
		$html = $this->afterResponse($html);

		return $html;
	}


	/**
	 *
	 */
	public function frame( string $template, ?string $layout = null ) : string {
		if ( Request::ajax() ) {
			return $this->fetch($template . '_frame');
		}

		return $this->response($template, $layout);
	}


	/**
	 * Assign last second vars
	 */
	public function beforeDisplay() : void {
	}


	/**
	 * Assign last second vars
	 */
	public function beforeFetch() : void {
	}


	/**
	 * Change response() output
	 */
	public function afterResponse( string $html ) : string {
		return $html;
	}


	/**
	 * Assign vars to the smarty object
	 *
	 * @param string|AssocArray $key
	 */
	public function assign( string|array $key, mixed $val = null ) : void {
		if ( is_array($key) ) {
			foreach ( $key AS $k => $v ) {
				$this->smarty->assign($k, $v);
			}

			return;
		}

		$this->smarty->assign($key, $val);
	}

}
