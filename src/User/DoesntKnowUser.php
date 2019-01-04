<?php

namespace Framework\User;

trait DoesntKnowUser {

	public static function id() {
		return 0;
	}

	public static function idOr( $alt ) {
		return 0;
	}

	public static function idOrFail( $alt ) {
		return 0;
	}

}
