<?php

namespace Framework\Http\Exception;

use Exception;

class NotFoundException extends Exception implements FullMessageException {

	public function getFullMessage() : string {
		return "Not found: " . $this->getMessage();
	}

}
