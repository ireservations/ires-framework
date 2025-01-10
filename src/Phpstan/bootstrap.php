<?php

namespace App\Services\Aro {
	class AppActiveRecordObject extends \Framework\Aro\ActiveRecordObject {
		use \Framework\Aro\LogsChanges;

		public function _logChangesLog( string $type, array $changes ) : void {} // @phpstan-ignore missingType.iterableValue
	}
}

namespace App\Services\Session {
	class User {
		use \Framework\User\ConveysMessages;
		use \Framework\User\KnowsUser;
		use \Framework\User\Redirects;
		use \Framework\User\ValidatesTokens;

		static public function access( string $zone, ?\App\Services\Aro\AppActiveRecordObject $object = null ) : bool {
			return (bool) rand(0, 1);
		}

		static public function logincheck() : bool {
			return (bool) rand(0, 1);
		}
	}
}

namespace App\Services\Tpl {
	class AppTemplate extends \Framework\Tpl\Template {
	}
}

namespace App\Services\Http {
	class AppController extends \Framework\Http\Controller {
		use \Framework\Http\VetsInput;
	}
}

namespace App\Controllers {
	class HomeController extends \App\Services\Http\AppController {
	}
}

namespace {
	define('SCRIPT_ROOT', dirname(__DIR__));
	define('PROJECT_RUNTIME', SCRIPT_ROOT . '/source/runtime');
	define('PROJECT_INCLUDE', SCRIPT_ROOT . '/source/include');

	define('DEBUG', true);
	define('HTTP_HOST', 'project.localhost');
	define('PROJECT_ARO', SCRIPT_ROOT . '/source/Models');
	define('PROJECT_CONFIG', SCRIPT_ROOT . '/_config');
	define('PROJECT_CRONJOBS', SCRIPT_ROOT . '/source/cronjobs');
	define('PROJECT_INC_SMARTY', PROJECT_INCLUDE . '/smarty');
	define('PROJECT_LOGIC', SCRIPT_ROOT . '/source/Controllers');
	define('PROJECT_PUBLIC', SCRIPT_ROOT . '/public_html');
	define('PROJECT_RESOURCES', SCRIPT_ROOT . '/source/resources');
	define('PROJECT_SMARTY_TPLS', SCRIPT_ROOT . '/source/views');
	define('PROJECT_VENDORS', SCRIPT_ROOT . '/vendor');
	define('RUNTIME_LOGS', PROJECT_RUNTIME . '/logs');
	define('SEMIDEBUG_IPS', ['1.1.1.1/0']);
	define('SESSION_NAME', 'ires_project');
	define('SQL_DB', 'abc');
	define('SQL_PASS', 'abc');
	define('SQL_USER', 'abc');
	define('UTC_START', microtime(true));
}
