<?php

namespace Framework\User;

use App\Services\Aro\AppActiveRecordObject;
use InvalidArgumentException;

trait KnowsUser {

	/** @var AppActiveRecordObject */
	public static $user;

	public static function id() {
		if ( !self::$user ) {
			throw new InvalidArgumentException("Login required");
		}

		return self::$user->id;
	}

	public static function idOr( $alt ) {
		return self::$user ? self::$user->id : $alt;
	}

	public static function idOrFail( $alt ) {
		if ( $uid = self::idOr($alt) ) {
			return $uid;
		}

		throw new InvalidArgumentException("Login required");
	}

}
