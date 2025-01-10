<?php

namespace Framework\Aro;

use App\Services\Session\User;

trait ValidatesTokens {

	abstract function getPKValue();

	/**
	 * @return int|string
	 */
	public function tokenId() {
		return $this->getPKValue();
	}

	public function checkToken( ?string $token ) : bool {
		return User::checkToken(get_class($this) . ':' . $this->tokenId(), $token);
	}

	public function token() : string {
		return User::makeToken(get_class($this) . ':' . $this->tokenId());
	}

	public function _token() : string {
		return '_token=' . $this->token();
	}

}
