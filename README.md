Assumes these classes exist:

- `App\Services\Aro\AppActiveRecordObject extends Framework\Aro\ActiveRecordObject`
- `App\Services\Session\User` (uses `Framework\User\DoesntKnowUser` or `Framework\User\KnowsUser`)
- `App\Services\Tpl\AppTemplate extends Framework\Tpl\Template`
- `App\Services\Http\AppController extends Framework\Annotations\Controller`
- `App\Controllers\HomeController extends App\Services\Http\AppController`
