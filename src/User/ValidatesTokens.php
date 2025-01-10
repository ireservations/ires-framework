<?php

namespace Framework\User;

trait ValidatesTokens {

	public static ?string $salt = null;

	public static function makeToken( ?string $name ) : string {
		if ( !self::$salt ) {
			return sha1(strval(rand()));
		}

		return sha1(self::$salt . ':' . $name);
	}

	public static function checkToken( string $name, ?string $token ) : bool {
		return $token !== null && $token === self::makeToken($name);
	}

}
