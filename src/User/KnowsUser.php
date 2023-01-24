<?php

namespace Framework\User;

use App\Services\Aro\AppActiveRecordObject;
use InvalidArgumentException;

trait KnowsUser {

	static public ?UserInterface $user = null;

	abstract static public function access( string $zone, AppActiveRecordObject $object = null ) : bool;

	abstract static public function logincheck() : bool;

	static public function id() : int {
		if ( !self::$user ) {
			throw new InvalidArgumentException("Login required");
		}

		return self::$user->id;
	}

	static public function idOr( ?int $alt ) : ?int {
		return self::$user ? self::$user->id : $alt;
	}

	static public function idOrFail( ?int $alt ) {
		if ( $uid = self::idOr($alt) ) {
			return $uid;
		}

		throw new InvalidArgumentException("Login required");
	}

}
