<?php

namespace Framework\User;

use App\Services\Aro\AppActiveRecordObject;

// @phpstan-ignore trait.unused
trait DoesntKnowUser {

	static public ?UserInterface $user = null;

	abstract static public function access( string $zone, ?AppActiveRecordObject $object = null ) : bool;

	abstract static public function logincheck() : bool;

	static public function id() : int {
		return 0;
	}

	static public function idOr( ?int $alt ) : ?int {
		return 0;
	}

	static public function idOrFail( ?int $alt ) : int {
		return 0;
	}

}
