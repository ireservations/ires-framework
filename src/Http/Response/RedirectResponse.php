<?php

namespace Framework\Http\Response;

use User;

class RedirectResponse extends Response {

	public function __construct( $url, array $options = [] ) {
		parent::__construct(User::redirectUrl($url, $options));
	}

	public function printHeaders() {
		$this->printDebugHeaders();

		header('Location: ' . $this->data);
	}

	public function printContent() {
		echo "Redirecting to {$this->data}";
	}

}
