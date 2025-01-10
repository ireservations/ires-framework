<?php

namespace Framework\Http\Exception;

use Framework\Http\Response\RedirectResponse;

class RedirectException extends ResponseException {

	/**
	 * @param AssocArray $options
	 */
	public function __construct( string $url, array $options = [] ) {
		parent::__construct(new RedirectResponse($url, $options));
	}

}
