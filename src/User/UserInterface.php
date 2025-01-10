<?php

namespace Framework\User;

use App\Services\Aro\AppActiveRecordObject;

/**
 * @property int $id
 *
 * @phpstan-require-extends AppActiveRecordObject
 */
interface UserInterface {

	public function __toString() : string;

}
