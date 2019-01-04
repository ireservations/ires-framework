<?php

namespace Framework\User;

const TOKEN_RANDOM_SIZE = 10;

trait ValidatesTokens {

	/** @var string */
	public static $salt;

	public static function makeToken( $name, $rand = null ) {
		if ( !self::$salt ) {
			return sha1(rand());
		}

		if ( $rand === null ) {
			$rand = rand(1, TOKEN_RANDOM_SIZE);
		}

		return sha1(self::$salt . ':' . $name . ':' . $rand);
	}

	public static function checkToken( $name, $token ) {
		for ( $rand=1; $rand<=TOKEN_RANDOM_SIZE; $rand++ ) {
			if ( $token === self::makeToken($name, $rand) ) {
				return true;
			}
		}

		return false;
	}

}
