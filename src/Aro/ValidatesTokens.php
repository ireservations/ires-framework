<?php

namespace Framework\Aro;

use User;

trait ValidatesTokens {

	abstract function getPKValue();

	public function tokenId() {
		return $this->getPKValue();
	}

	public function checkToken( ?string $token ) : bool {
		return User::checkToken(get_class($this) . ':' . $this->tokenId(), $token);
	}

	public function token() {
		return User::makeToken(get_class($this) . ':' . $this->tokenId());
	}

	public function _token() {
		return '_token=' . $this->token();
	}

}
