<?php

namespace Framework\Http\Exception;

use Exception;

class AccessDeniedException extends Exception implements FullMessageException {

	public function getFullMessage() : string {
		return "Access denied: " . $this->getMessage();
	}

}
