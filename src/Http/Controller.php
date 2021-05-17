<?php

namespace Framework\Http;

use db_foreignkey_exception;
use Framework\Annotations\Access;
use Framework\Annotations\Loaders;
use App\Controllers\HomeController;
use Framework\Annotations\MultipleIndexedReader;
use Framework\Aro\ActiveRecordException;
use App\Services\Aro\AppActiveRecordObject;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\InvalidInputException;
use Framework\Http\Exception\InvalidTokenException;
use Framework\Http\Exception\ResponseException;
use Framework\Http\Exception\ServerException;
use Framework\Http\Response\HtmlResponse;
use Framework\Http\Response\JsonResponse;
use Framework\Http\Exception\NotFoundException;
use Framework\Http\Response\RedirectResponse;
use Framework\Http\Response\Response;
use Framework\Http\Response\TextResponse;
use App\Services\Tpl\AppTemplate;
use db_duplicate_exception;
use db_exception;
use db_generic;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;
use User;

abstract class Controller {

	use ChecksAccess;

	const INPUT_OPTIONAL = false;
	const INPUT_REQUIRED = true;

	public static $action_path_wildcards = array(
		'INT'		=> '(\d+)',
		'STRING'	=> '([^/]+)',
		'DATE'		=> '(today|tomorrow|\d\d\d\d\-\d\d?\-\d\d?)',
		'TIME'		=> '(\d\d?:\d\d)',
	);
	public static $action_path_wildcard_aliases = array(
		'#' => 'INT',
		'*' => 'STRING',
	);
	public static $mapping = array();

	protected $m_szFullRequestUri		= '';
	protected $m_szRequestUri			= '';
	protected $m_szRequestUriMatch		= '';
	protected $m_arrHooks				= [];
	/** @var Hook[] */
	protected $m_arrHookObjects			= [];
	protected $m_szHook					= '';
	protected $m_arrCtrlrArgs			= [];
	protected $m_arrActionArgs			= [];
	protected $m_arrRunOptions			= [];
	protected $m_arrOptions				= [];
	protected $m_arrCtrlrAnnotations	= [];
	protected $m_arrActionAnnotations	= [];

	protected $disallowIframes		= true;
	protected $cspReporting			= true;
	protected $localCsfr			= true;
	protected $assignTemplate		= true;

	/** @var AppTemplate */
	protected $tpl;

	/** @var db_generic */
	protected $db;


	/**
	 * E v a l u a t e s   a n d   r e t u r n s   t h e   r e q u e s t   U R I
	 */
	public static function getRequestUri() {
		$uri = Request::uri();

		if ( function_exists('apache_setenv') ) {
			apache_setenv('RURI', $uri);
		}

		return $uri;
	}

	/**
	 *
	 */
	protected function annotateController( ReflectionClass $reflectee ) {
		AnnotationRegistry::registerLoader('class_exists');

		$reader = new MultipleIndexedReader(new AnnotationReader());
		$annotations = $reader->getClassAnnotations($reflectee);
		return $annotations;
	}


	/**
	 *
	 */
	protected function annotateAction( ReflectionMethod $reflectee ) {
		AnnotationRegistry::registerLoader('class_exists');

		$reader = new MultipleIndexedReader(new AnnotationReader());
		$annotations = $reader->getMethodAnnotations($reflectee);
		return $annotations;
	}


	static protected function matchControllerRoute( $route, $uri, &$params, &$path ) {
		$params = [];

		$route = strtr($route, static::$action_path_wildcard_aliases);

		$regex = strtr($route, static::$action_path_wildcards);
		$regex = "#^$regex(/.*|$)#";

		if ( preg_match($regex, $uri, $params) ) {
			array_shift($params);

			$path = trim(array_pop($params), '/');

			$params = self::matchParams($route, $params);

			return $params !== false;
		}
	}

	static protected function matchActionRoute( $route, $uri, &$params ) {
		$params = [];

		$route = strtr($route, static::$action_path_wildcard_aliases);

		$regex = strtr($route, static::$action_path_wildcards);
		$regex = "#^$regex$#";

		if ( preg_match($regex, $uri, $params) ) {
			array_shift($params);

			$params = self::matchParams($route, $params);

			return $params !== false;
		}
	}

	static protected function matchParams( $route, array $params ) {
		preg_match_all('#(' . implode('|', array_keys(static::$action_path_wildcards)) . ')#', $route, $matches);
		$wildcards = $matches[1];

		if ( count($wildcards) != count($params) ) {
			return $params;
		}

		foreach ( $params as $i => $param ) {
			$params[$i] = static::matchParam($wildcards[$i], $param);
			if ( $params[$i] === false ) {
				return false;
			}
		}

		return $params;
	}

	static protected function matchParam( $type, $value ) {
		return $value;
	}


	/**
	 * 1 .   T h e   M V C   s t a r t e r
	 * @return static
	 */
	public static function run( $f_szFullRequestUri, array $f_arrRunOptions = [] ) {
		if ( '/' == $f_szFullRequestUri ) {
			$application = new HomeController('/', [], $f_arrRunOptions);
		}
		else {
			list($szControllerClass, $szActionPath, $arrControllerArgs) = static::findController($f_szFullRequestUri);

			$application = new $szControllerClass($szActionPath, $arrControllerArgs, $f_arrRunOptions);
		}

		$application->m_szFullRequestUri = '/' . trim($f_szFullRequestUri, '/');
		return $application;
	}


	/**
	 *
	 */
	static public function findController( $uri ) {
		$uri = trim($uri, '/');

		foreach ( array_reverse(static::$mapping) as $prefix => $class) {
			if ( self::matchControllerRoute($prefix, $uri, $params, $path) ) {
				if ( $class = static::getControllerClass($class) ) {
					return [$class, $path, $params];
				}
			}
		}

		$components = explode('/', $uri);
		$class = array_shift($components);
		if ( $class = static::getControllerClass($class) ) {
			return [$class, trim(implode('/', $components), '/'), []];
		}

		return [HomeController::class, $uri, []];
	}


	/**
	 *
	 */
	static public function getControllerClass( $controller ) {
		$components = explode('/', $controller);

		$class = array_pop($components);
		$class = ucfirst($class) . 'Controller';
		$components[] = $class;

		$fullClass = 'App\\Controllers\\' . implode('\\', $components);

		if ( class_exists($fullClass, true) ) {
			return $fullClass;
		}
	}


	/**
	 * 2 .   L o a d   t h e   a p p l i c a t i o n
	 */
	public function __construct( $f_szUri, array $f_arrCtrlrArgs = [], array $f_arrRunOptions = [] ) {
		$this->m_arrRunOptions = $f_arrRunOptions + [];

		$this->m_arrCtrlrArgs = $f_arrCtrlrArgs;

		$this->m_szRequestUri = '/' . trim($f_szUri, '/');
	}


	/**
	 * 3a .   R u n   t h e   a p p l i c a t i o n
	 */
	public function exec( $f_bAutoExec = true ) {
		try {
			return $this->execPrivate($f_bAutoExec);
		}
		catch ( Throwable $ex ) {
			return $this->handleException($ex, $f_bAutoExec);
		}
	}


	/**
	 * 3b .   R u n   t h e   a p p l i c a t i o n
	 */
	protected function execPrivate( $f_bAutoExec = true ) {
		$this->__preload();

		$requestUri = $this->m_szRequestUri;
		$requestMethods = ['all', strtolower(Request::method())];

		foreach ( $this->getHooks() AS $hook ) {
			if ( self::matchActionRoute($hook->path, $requestUri, $arrArgs) ) {
				if ( in_array($hook->method, $requestMethods) && is_callable(array($this, $hook->action)) ) {
					$this->m_arrOptions = $hook->args;
					$this->m_szRequestUriMatch = $hook->path;

					$method = new ReflectionMethod($this, $hook->action);
					$this->m_arrActionAnnotations = $annotations = $this->annotateAction($method);
					$loaders = isset($annotations[Loaders::class]) ? $annotations[Loaders::class][0]->value : [];

					$this->m_szHook = $hook->action;

					$this->__loaded();

					// Objectize ARO params
					foreach ( $method->getParameters() AS $i => $param ) {
						$required = !$param->isOptional();
						$type = $param->getClass();

						// Missing arg
						if ( !isset($arrArgs[$i]) ) {
							if ( $required ) {
								break 2;
							}
							else {
								$arrArgs[$i] = $param->getDefaultValue();
							}
						}
						// Load arg
						elseif ( $type ) {
							$loader = @$loaders[$i] ?: 'load';
							$id = $arrArgs[$i];

							// Object loaded
							if ( $object = call_user_func([$type->name, $loader], $id) ) {
								$arrArgs[$i] = $object;
							}
							// Object not found
							else {
								break 2;
							}
						}
					}

					$this->m_arrActionArgs = $arrArgs;

					// Action access
					if ( isset($annotations[Access::class]) ) {
						$this->aclAlterAnnotations($hook->action, $annotations[Access::class]);
					}

					$this->__start();

					$r = call_user_func_array(array($this, $hook->action), $this->m_arrActionArgs);
					if ( $f_bAutoExec ) {
						$this->handleResult($r);
					}

					return $r;
				}
			}
		}

		// Not found
		$r = $this->notFound();
		if ( $f_bAutoExec ) {
			$this->handleResult($r);
		}

		return $r;
	}


	/**
	 *
	 */
	protected function makeHookRegex( $path ) {
		$path = strtr($path, self::$action_path_wildcard_aliases);
		$path = strtr($path, self::$action_path_wildcards);
		$path = '#^' . $path . '$#';

		return $path;
	}


	/**
	 * @return Hook[]
	 */
	public function getHooks() {
		if ( count($this->m_arrHookObjects) ) {
			return $this->m_arrHookObjects;
		}

		$hooks = [];
		foreach ( $this->m_arrHooks as $path => $hook ) {
			if ( is_array($hook) ) {
				if ( isset($hook[0]) ) {
					$args = $hook;
					$hook = $hook[0];
					unset($args[0]);

					$hooks[] = Hook::withArgs($path, $hook, $args);
				}
				else {
					foreach ( $hook as $method => $action ) {
						$hooks[] = Hook::withMethod($path, $method, $action);
					}
				}
			}
			else {
				$hooks[] = Hook::withAction($path, $hook);
			}
		}

		return $this->m_arrHookObjects = $hooks;
	}


	/**
	 *
	 */
	function handleResult( $result ) {
		if ( is_scalar($result) ) {
			$result = trim($result);
			$result = strlen($result) && $result[0] == '<' ? new HtmlResponse($result) : new TextResponse($result);
		}

		if ( is_array($result) ) {
			$result = new JsonResponse($result);
		}

		if ( $result instanceof Response ) {
			$result->printHeaders();
			$result->printContent();
			exit;
		}
	}


	/**
	 *
	 */
	protected function handleException( Throwable $ex, $f_bAutoExec = true ) {
		$response = null;

		if ( $ex instanceof db_duplicate_exception ) {
			debug_exit("db_duplicate_exception: " . escapehtml($ex->getMessage()), $ex);

			$message = 'This record seems to exist. Check input.';
			$response = new TextResponse($message, 400);
		}

		elseif ( $ex instanceof db_foreignkey_exception ) {
			debug_exit("db_foreignkey_exception: " . escapehtml($ex->getMessage()), $ex);

			$table = $ex->getTable() ? ": " . $ex->getTable() : '';
			$message = "You can't " . $ex->getAction() . " this record, because it still has dependencies$table.";
			$response = new TextResponse($message, 400);
		}

		elseif ( $ex instanceof db_exception ) {
			debug_exit("db_exception: " . escapehtml($ex->getMessage()), $ex);
			$response = new TextResponse('Database error. Contact admin.', 500);
		}

		elseif ( $ex instanceof InvalidInputException ) {
			$message = $ex->getMessage() ?: trans('INVALID_PARAMETERS');
			if ( $ex->getInvalids() ) {
				$message .= ":\n\n* " . implode("\n* ", $ex->getInvalids());
			}

			$response = new TextResponse($message, 400);
		}

		elseif ( $ex instanceof InvalidTokenException ) {
			$response = new TextResponse("Access denied: token: " . $ex->getMessage(), 403);
		}

		elseif ( $ex instanceof ActiveRecordException ) {
			debug_exit("ActiveRecordException: " . escapehtml($ex->getMessage()), $ex);

			$class = (new ReflectionClass($ex->class))->getShortName();
			$response = new TextResponse("Not found: " . $class, 404);
		}

		elseif ( $ex instanceof ResponseException ) {
			$response = $ex->getResponse();
		}

		elseif ( $ex instanceof NotFoundException ) {
			$response = new TextResponse("Not found: " . $ex->getMessage(), 404);
		}

		elseif ( $ex instanceof AccessDeniedException ) {
			$response = new TextResponse("Access denied: " . $ex->getMessage(), 403);
		}

		elseif ( $ex instanceof RuntimeException ) {
			$response = new TextResponse($ex->getMessage(), 500);
		}

		elseif ( $ex instanceof ServerException ) {
			$response = new TextResponse("SERVER ERROR: " . $ex->getMessage(), 500);
		}

		elseif ( $ex instanceof Throwable ) {
			debug_exit("Uncaught " . get_class($ex) . ": " . escapehtml($ex->getMessage()) . ' on ' . basename($ex->getFile()) . ':' . $ex->getLine(), $ex);

			$response = new TextResponse("UNKNOWN ERROR: " . $ex->getMessage(), 500);
		}

		if ( $response ) {
			return $f_bAutoExec ? $this->handleResult($response) : $response;
		}

		throw $ex;
	}


	/**
	 *
	 */
	protected function redirect( $location, array $options = [] ) {
		return new RedirectResponse($location, $options);
	}


	/** @return static */
	protected function forward( $uri ) {
		return static::run($uri);
	}


	protected function notFound( $message = '' ) {
		$message and $message = " - $message";
		throw new NotFoundException($this->m_szFullRequestUri . $message);
	}


	protected function accessDenied( $message = '' ) {
		$message and $message = " - $message";
		throw new AccessDeniedException($this->m_szFullRequestUri . $message);
	}


	protected function invalidInput( $error ) {
		if ( is_array($error) ) {
			throw new InvalidInputException(null, $error);
		}

		throw new InvalidInputException($error);
	}


	protected function __preload() {
		/** @var db_generic $db */
		global $db;
		$this->db = $db;

		if ( $this->assignTemplate ) {
			$this->tpl = AppTemplate::instance();
		}

		$this->m_arrCtrlrAnnotations = $this->annotateController(new ReflectionClass(get_class($this)));

		// Controller access
		if ( isset($this->m_arrCtrlrAnnotations[Access::class]) ) {
			foreach ( $this->m_arrCtrlrAnnotations[Access::class] as $access ) {
				$this->aclAdd(ltrim($access->value, '+-'));
			}
		}

		if ( $this->localCsfr ) {
			$this->aclAdd('LOCAL_CSRF');
		}
	}


	protected function __loaded() {
	}


	protected function __start() {
		$this->aclCheck();

		if ( $this->tpl ) {
			$this->tpl->assign('disallowIframes', $this->disallowIframes);
		}

		if ( $this->disallowIframes ) {
			@header('X-Frame-Options: SAMEORIGIN');
		}

		if ( $this->cspReporting ) {
			@header("Content-Security-Policy-Report-Only: script-src 'self' 'unsafe-inline' 'unsafe-eval'; report-uri /csp");
		}
	}


	/**
	 *
	 */
	protected function requireControllerArgOfType( $n, $type ) {
		/** @var AppActiveRecordObject $type */
		if ( empty($this->m_arrCtrlrArgs[$n]) || !($object = $type::find($this->m_arrCtrlrArgs[$n])) ) {
			return $this->notFound();
		}

		return $object;
	}



	protected function checkToken( AppActiveRecordObject $source ) {
		if ( !empty($_REQUEST['_token']) ) {
			if ( $source->checkToken($_REQUEST['_token']) ) {
				return;
			}
		}

		$this->failToken($source);
	}

	protected function failToken( AppActiveRecordObject $source ) {
		$name = (new ReflectionClass($source))->getShortName();
		throw new InvalidTokenException($name);
	}


	protected function checkSessionToken( $name ) {
		if ( !empty($_REQUEST['_token']) ) {
			if ( User::checkToken($name, $_REQUEST['_token']) ) {
				return;
			}
		}

		$this->failSessionToken($name);
	}

	protected function failSessionToken( $name ) {
		throw new InvalidTokenException($name);
	}



	/**
	 * Helper: API: Easy error JSON response
	 */
	public function jsonError( $error, array $data = [] ) {
		$data += ['error' => $error];
		return new JsonResponse($data, 400);
	}


	/**
	 * Helper: API: facilicate easy PHP -> JSON(P)
	 */
	public function json( array $data, $jsonp = false ) {
		$jsonp = $jsonp && isset($_GET['jsonp']) ? $_GET['jsonp'] : '';
		return new JsonResponse($data, null, $jsonp);
	}

}
