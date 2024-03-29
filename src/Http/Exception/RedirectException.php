<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\RedirectResponse;

class RedirectException extends ResponseException {

	public function __construct( string $url, array $options = [] ) {
		parent::__construct(new RedirectResponse($url, $options));
	}

}
