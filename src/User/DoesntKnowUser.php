<?php

namespace Framework\User;

use App\Services\Aro\AppActiveRecordObject;

trait DoesntKnowUser {

	abstract static public function access( string $zone, AppActiveRecordObject $object = null ) : bool;

	abstract static public function logincheck() : bool;

	static public function id() : int {
		return 0;
	}

	static public function idOr( ?int $alt ) : ?int {
		return 0;
	}

	static public function idOrFail( ?int $alt ) {
		return 0;
	}

}
