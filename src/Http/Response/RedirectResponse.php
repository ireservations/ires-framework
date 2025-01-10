<?php

namespace Framework\Http\Response;

use App\Services\Session\User;

class RedirectResponse extends Response {

	/**
	 * @param AssocArray $options
	 */
	public function __construct( string $url, array $options = [] ) {
		parent::__construct(User::redirectUrl($url, $options));
	}

	public function printHeaders() {
		$this->printDebugHeaders();

		header('Location: ' . strval($this->data));
	}

	public function printContent() {
		printf("Redirecting to %s\n", $this->data);
	}

}
