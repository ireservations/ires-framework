<?php

namespace Framework\Http;

use App\Controllers\HomeController;
use App\Services\Aro\AppActiveRecordObject;
use App\Services\Http\AppController;
use App\Services\Session\User;
use App\Services\Tpl\AppTemplate;
use db_duplicate_exception;
use db_exception;
use db_foreignkey_exception;
use db_generic;
use Framework\Annotations\AroToken;
use Framework\Annotations\Loaders;
use Framework\Aro\ActiveRecordException;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\InvalidInputException;
use Framework\Http\Exception\InvalidTokenException;
use Framework\Http\Exception\NotFoundException;
use Framework\Http\Exception\ResponseException;
use Framework\Http\Exception\ServerException;
use Framework\Http\Response\HtmlResponse;
use Framework\Http\Response\JsonResponse;
use Framework\Http\Response\RedirectResponse;
use Framework\Http\Response\Response;
use Framework\Http\Response\TextResponse;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

abstract class Controller {

	use ChecksAccess;

	/** @var array<string, string> */
	static public array $action_path_wildcards = array(
		'INT'		=> '(\d+)',
		'STRING'	=> '([^/]+)',
		'DATE'		=> '(today|tomorrow|\d\d\d\d\-\d\d?\-\d\d?)',
		'TIME'		=> '(\d\d?:\d\d)',
	);
	/** @var array<string, string> */
	static public array $action_path_wildcard_aliases = array(
		'#' => 'INT',
		'*' => 'STRING',
	);

	static ControllerMapper $_controllerMapper;
	/** @var array<class-string<self>, ActionMapper> */
	static array $_actionMappers = [];

	protected string $fullRequestUri = '';
	protected string $actionPath = '';

	/** @var AssocArray */
	protected $m_arrHooks = [];
	/** @var AssocArray */
	protected const HOOKS = [];
	/** @var list<Hook> */
	protected array $hookObjects;

	/** @var AssocArray */
	protected array $ctrlrOptions = [
		'access' => true,
		'accessZones' => [],
	];
	/** @var list<scalar> */
	protected array $ctrlrArgs = [];

	protected ?ReflectionMethod $actionReflection = null;
	/** @var AssocArray */
	protected array $actionOptions = [];
	/** @var list<int|string|AppActiveRecordObject> */
	protected array $actionArgs = [];
	protected string $actionCallback = '';

	protected bool $disallowIframes = true;

	protected db_generic $db;
	protected ?AppTemplate $tpl = null;


	/**
	 * E v a l u a t e s   a n d   r e t u r n s   t h e   r e q u e s t   U R I
	 */
	static public function getRequestUri() : string {
		$uri = Request::uri();

		if ( function_exists('apache_setenv') ) {
			apache_setenv('RURI', $uri);
		}

		return $uri;
	}

	static public function route( string $name, mixed ...$allArgs ) : string {
		$nameParts = explode('.', $name, 2);
		if ( count($nameParts) < 2 ) {
			throw new RuntimeException(sprintf("Too few parts for route name '%s'", $name));
		}

		$ctrlrMapping = static::getControllerMapper()->getMapping();
		if ( !isset($ctrlrMapping[ $nameParts[0] ]) ) {
			throw new RuntimeException(sprintf("No controller found for route name '%s'", $name));
		}

		$compiledCtrlr = $ctrlrMapping[ $nameParts[0] ];
		$ctrlrClass = $compiledCtrlr->class;

		$actionMapper = static::$_actionMappers[$ctrlrClass] ??= (new $ctrlrClass(''))->getActionMapper();
		$actionMapping = $actionMapper->getMapping();
		if ( !isset($actionMapping[ $nameParts[1] ]) ) {
			throw new RuntimeException(sprintf("No action found for route name '%s'", $name));
		}

		$hook = $actionMapping[ $nameParts[1] ];
		$fullPath = '/' . trim($compiledCtrlr->path . $hook->path, '/');
		$fullPath = strtr($fullPath, static::$action_path_wildcard_aliases);

		$regex = '#\b(' . implode('|', array_keys(static::$action_path_wildcards)) . ')\b#';
		$fullPath = preg_replace_callback($regex, function() use ($name, &$allArgs) {
			if ( !count($allArgs) ) {
				throw new RuntimeException(sprintf("Too few args for route name '%s'", $name));
			}

			$arg = array_shift($allArgs);
			if ( $arg instanceof AppActiveRecordObject ) $arg = $arg->getPKValue();
			return $arg;
		}, $fullPath);

		if ( count($allArgs) ) {
			throw new RuntimeException(sprintf("Too many args for route name '%s'", $name));
		}

		return $fullPath;
	}

	static protected function matchControllerRoute( CompiledController $compiledCtrlr, string $fullUri ) : ?ControllerMatch {
		$route = strtr($compiledCtrlr->path, static::$action_path_wildcard_aliases);

		$regex = strtr($route, static::$action_path_wildcards);
		$regex = "#^$regex(/.*|$)#";

		if ( !preg_match($regex, $fullUri, $ctrlrArgs) ) {
			return null;
		}

		array_shift($ctrlrArgs);
		$actionPath = trim(array_pop($ctrlrArgs), '/');

		$ctrlrArgs = self::matchParams($route, $ctrlrArgs);
		if ( !is_array($ctrlrArgs) ) {
			return null;
		}

		return new ControllerMatch($compiledCtrlr, $ctrlrArgs, $actionPath);
	}

	/**
	 * @param ?array{} $params
	 * @param-out ?array<mixed> $params
	 */
	static protected function matchActionRoute( string $route, string $uri, ?array &$params ) : bool {
		$params = [];

		$route = strtr($route, static::$action_path_wildcard_aliases);

		$regex = str_replace('.', '\.', $route);
		$regex = strtr($regex, static::$action_path_wildcards);
		$regex = "#^$regex$#";

		if ( preg_match($regex, $uri, $params) ) {
			array_shift($params);

			$params = self::matchParams($route, $params);

			return $params !== null;
		}

		return false;
	}

	/**
	 * @param list<scalar> $params
	 * @return null|list<mixed>
	 */
	static protected function matchParams( string $route, array $params ) : ?array {
		preg_match_all('#(' . implode('|', array_keys(static::$action_path_wildcards)) . ')#', $route, $matches);
		$wildcards = $matches[1];

		if ( count($wildcards) != count($params) ) {
			return $params;
		}

		foreach ( $params as $i => $param ) {
			$params[$i] = static::matchParam($wildcards[$i], $param);
			if ( $params[$i] === null ) {
				return null;
			}
		}

		return $params;
	}

	static protected function matchParam( string $type, string $value ) : ?string {
		return $value;
	}


	public function getActionMapper() : ActionMapper {
		return new ActionMapper($this);
	}

	static public function getControllerMapper() : ControllerMapper {
		return static::$_controllerMapper ??= new ControllerMapper();
	}


	/**
	 * 1 .   T h e   M V C   s t a r t e r
	 *
	 * @param AssocArray $addCtrlrOptions
	 */
	static public function makeApplication( string $fullUri, array $addCtrlrOptions = [] ) : AppController {
// if ( count($addCtrlrOptions) ) debug_exit('Constructing application with $addCtrlrOptions', $addCtrlrOptions);

		$ctrlrMatch = static::findController($fullUri);
		$compiledCtrlr = $ctrlrMatch->compiledCtrlr;
		$ctrlrOptions = array_merge_recursive_distinct($compiledCtrlr->options, $addCtrlrOptions);
		$ctrlrClass = $compiledCtrlr->class;

		$application = new $ctrlrClass($ctrlrMatch->actionPath, $ctrlrMatch->ctrlrArgs, $ctrlrOptions);
		$application->fullRequestUri = '/' . trim($fullUri, '/');

		return $application;
	}


	static protected function findController( string $fullUri ) : ControllerMatch {
// $t = microtime(true);
		$fullUri = trim($fullUri, '/');

		$mapping = static::getControllerMapper()->getMapping();

		$homeCtrlr = $mapping['home'] ?? new CompiledController('', HomeController::class);

		if ( $fullUri === '' ) {
			return new ControllerMatch($homeCtrlr, [], '');
		}

		foreach ( $mapping as $compiledCtrlr ) {
			if ( $match = self::matchControllerRoute($compiledCtrlr, $fullUri) ) {
// dump(1000 * (microtime(true) - $t));
				return $match;
			}
		}

		return new ControllerMatch($homeCtrlr, [], $fullUri);
	}


	/**
	 * 2 .   L o a d   t h e   a p p l i c a t i o n
	 *
	 * @param list<int|string> $ctrlrArgs
	 * @param AssocArray $ctrlrOptions
	 */
	public function __construct( string $actionPath, array $ctrlrArgs = [], array $ctrlrOptions = [] ) {
		$this->ctrlrOptions = array_merge_recursive_distinct($this->ctrlrOptions, $ctrlrOptions);

		$this->ctrlrArgs = $ctrlrArgs;

		$this->actionPath = '/' . trim($actionPath, '/');
	}


	/**
	 * 3a .   R u n   t h e   a p p l i c a t i o n
	 */

	public function executeToExit() : void {
		$response = $this->executeToResponse();

		$response->printHeaders();
		$response->printContent();
		exit;
	}

	public function executeToResponse(bool $strict = false) : Response {
		try {
			$result = $this->executeAction();
			return $this->resultToResponse($result, $strict);
		}
		catch ( Throwable $ex ) {
			return $this->exceptionToResponse($ex);
		}
	}

	public function executeToStrictResponse() : Response {
		return $this->executeToResponse(true);
	}

	public function executeToResult() : mixed {
		try {
			return $this->executeAction();
		}
		catch ( Throwable $ex ) {
			return $ex;
		}
	}


	/**
	 * 3b .   R u n   t h e   a p p l i c a t i o n
	 */
	protected function executeAction() : mixed {
		$this->__preload();

// $t = microtime(true);
		$requestMethods = ['all', strtolower(Request::method())];

		foreach ( $this->getHooks() AS $hook ) {
			if ( !self::matchActionRoute($hook->path, $this->actionPath, $actionArgs) ) {
				continue;
			}

			if ( !in_array($hook->method, $requestMethods) || !is_callable(array($this, $hook->action)) ) {
				continue;
			}
// dump(1000 * (microtime(true) - $t));

			$this->actionOptions = $hook->options;

			$this->actionReflection = $method = new ReflectionMethod($this, $hook->action);
			$loaders = count($attributes = $method->getAttributes(Loaders::class)) ? $attributes[0]->newInstance()->methods : [];

			$this->actionCallback = $hook->action;

			$this->__loaded();

			// Objectize ARO params
			foreach ( $method->getParameters() AS $i => $param ) {
				$required = !$param->isOptional();
				$type = null;
				if ( ($tp = $param->getType()) instanceof ReflectionNamedType && !$tp->isBuiltin() ) {
					$type = $tp->getName();
				}

				// Missing arg
				if ( !isset($actionArgs[$i]) ) {
					if ( $required ) {
						break 2;
					}
					else {
						$actionArgs[$i] = $param->getDefaultValue();
					}
				}
				// Load arg
				elseif ( $type ) {
					$loader = $loaders[$i] ?? 'load';
					$id = $actionArgs[$i];

					// Object loaded
					if ( $object = call_user_func([$type, $loader], $id) ) {
						$actionArgs[$i] = $object;
					}
					// Object not found
					else {
						break 2;
					}
				}
			}

			$this->actionArgs = $actionArgs;

			// Action access
			$this->aclAlterAction();

			$this->__start();

			return call_user_func_array([$this, $hook->action], $this->actionArgs);
		}

		return $this->notFound();
	}


	/**
	 * @return AssocArray
	 */
	public function getRawHooks() : array {
		return static::HOOKS ?: $this->m_arrHooks;
	}

	/**
	 * @return array<Hook>
	 */
	protected function getHooks() : array {
		return $this->hookObjects ??= $this->getActionMapper()->getMapping();
	}


	protected function resultToResponse( mixed $result, bool $strict = false ) : Response {
		if ( $result instanceof Response ) {
			return $result;
		}

		if ( is_null($result) || is_scalar($result) ) {
			$result = trim($result ?? '');
			if ( strlen($result) && $result[0] == '<' ) {
				return new HtmlResponse($result);
			}

			return new TextResponse($result, $strict ? 400 : 200);
		}

		if ( is_array($result) ) {
			return new JsonResponse($result);
		}

		return new TextResponse("Invalid Controller result (1)", 500);
	}


	/**
	 *
	 */
	protected function exceptionToResponse( Throwable $ex ) : Response {
		if ( $ex instanceof db_duplicate_exception ) {
			if ( Request::semidebug() ) {
				debug_exit("db_duplicate_exception: " . escapehtml($ex->getMessage()), $ex);
			}

			$message = 'This record seems to exist. Check input.';
			$response = new TextResponse($message, 400);
		}

		elseif ( $ex instanceof db_foreignkey_exception ) {
			if ( Request::semidebug() ) {
				debug_exit("db_foreignkey_exception: " . escapehtml($ex->getMessage()), $ex);
			}

			$table = $ex->getTable() ? ' (' . $ex->getTable() . ')' : '';
			$message = "You can't " . $ex->getAction() . " this record, because it still has dependencies$table.";
			$response = new TextResponse($message, 400);
		}

		elseif ( $ex instanceof db_exception ) {
			debug_exit("db_exception: " . escapehtml($ex->getMessage()), $ex);
			$response = new TextResponse('Database error. Contact admin.', 500);
		}

		elseif ( $ex instanceof InvalidInputException ) {
			$message = $ex->getFullMessage();
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
			$response = new TextResponse($ex->getFullMessage(), 404);
		}

		elseif ( $ex instanceof AccessDeniedException ) {
			$response = new TextResponse($ex->getFullMessage(), 403);
		}

		elseif ( $ex instanceof RuntimeException ) {
			debug_exit("Uncaught " . get_class($ex) . ": " . escapehtml($ex->getMessage()) . ' on ' . basename($ex->getFile()) . ':' . $ex->getLine(), $ex);

			$response = new TextResponse($ex->getMessage(), 500);
		}

		elseif ( $ex instanceof ServerException ) {
			$response = new TextResponse("SERVER ERROR: " . $ex->getMessage(), 500);
		}

		else {
			debug_exit("Uncaught " . get_class($ex) . ": " . escapehtml($ex->getMessage()) . ' on ' . basename($ex->getFile()) . ':' . $ex->getLine(), $ex);

			$response = new TextResponse("UNKNOWN ERROR: " . get_class($ex), 500);
		}

		return $response;
	}


	/**
	 * @param AssocArray $options
	 */
	protected function redirect( string $location, array $options = [] ) : RedirectResponse {
		return new RedirectResponse($location, $options);
	}

	protected function forward( string $uri ) : self {
		return static::makeApplication($uri);
	}


	protected function notFound( string $message = '' ) : never {
		if ( $message ) $message = " - $message";
		throw new NotFoundException(Request::method() . ' ' . $this->fullRequestUri . $message);
	}

	protected function accessDenied( string $message = '' ) : never {
		if ( $message ) $message = " - $message";
		throw new AccessDeniedException($this->fullRequestUri . $message);
	}

	/**
	 * @param string|array<array-key, string> $error
	 */
	protected function invalidInput( string|array $error ) : never {
		if ( is_array($error) ) {
			throw new InvalidInputException(null, $error);
		}

		throw new InvalidInputException($error);
	}


	protected function assignTpl() : void {
		$this->tpl = AppTemplate::instance();
	}

	protected function maybeDisallowIframes() : void {
		if ( $this->tpl ) {
			$this->tpl->assign('disallowIframes', $this->disallowIframes);
		}

		if ( $this->disallowIframes ) {
			@header('X-Frame-Options: SAMEORIGIN');
		}
	}


	protected function __preload() : void {
		$this->db = $GLOBALS['db'];

		$this->assignTpl();
	}

	protected function __loaded() : void {
		// Controller access
		$this->aclAlterController();
	}

	protected function __start() : void {
		$this->aclCheck();
		$this->validateAroToken();

		$this->maybeDisallowIframes();
	}


	/**
	 * @template TAroClass of AppActiveRecordObject
	 * @param class-string<TAroClass> $aroType
	 * @return TAroClass
	 */
	protected function requireControllerArgOfType( int $n, string $aroType ) : AppActiveRecordObject {
		if ( empty($this->ctrlrArgs[$n]) || !($object = $aroType::find($this->ctrlrArgs[$n])) ) {
			return $this->notFound();
		}

		return $object;
	}


	protected function getFallbackAroTokenObject() : ?AppActiveRecordObject {
		return null;
	}

	protected function validateAroToken() : void {
		$attributes = $this->actionReflection->getAttributes(AroToken::class);
		foreach ( $attributes as $attribute ) {
			$token = $attribute->newInstance();
			$object = $token->arg === null ? $this->getFallbackAroTokenObject() : ($this->actionArgs[$token->arg] ?? null);
			if ( $object ) {
				$this->checkToken($object);
			}
			else {
				throw new InvalidTokenException('<object missing>');
			}
		}
	}

	protected function checkToken( AppActiveRecordObject $source ) : void {
		if ( !empty($_REQUEST['_token']) ) {
			if ( $source->checkToken($_REQUEST['_token']) ) {
				return;
			}
		}

		$this->failToken($source);
	}

	protected function failToken( AppActiveRecordObject $source ) : never {
		$name = (new ReflectionClass($source))->getShortName();
		throw new InvalidTokenException($name);
	}


	protected function checkSessionToken( string $name ) : void {
		if ( !empty($_REQUEST['_token']) ) {
			if ( User::checkToken($name, $_REQUEST['_token']) ) {
				return;
			}
		}

		$this->failSessionToken($name);
	}

	protected function failSessionToken( string $name ) : never {
		throw new InvalidTokenException($name);
	}



	/**
	 * Helper: API: Easy error JSON response
	 *
	 * @param AssocArray $data
	 */
	protected function jsonError( string $error, array $data = [] ) : JsonResponse {
		$data += ['error' => $error];
		return new JsonResponse($data, 400);
	}


	/**
	 * Helper: API: facilicate easy PHP -> JSON(P)
	 *
	 * @param AssocArray $data
	 */
	protected function json( array $data, bool $jsonp = false ) : JsonResponse {
		$jsonp = $jsonp && isset($_GET['jsonp']) ? $_GET['jsonp'] : '';
		return new JsonResponse($data, null, $jsonp);
	}

}
