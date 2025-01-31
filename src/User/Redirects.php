<?php

namespace Framework\User;

use Framework\Http\Request;

trait Redirects {

	public static function loginRedirectUrl( string $reason = '', string $frontpage = '' ) : string {
		$query = [
			'reason' => $reason,
			'goto' => $_SERVER['REQUEST_URI'],
		];
		return self::redirectUrl($frontpage, ['query' => $query]);
	}

	/**
	 * @param AssocArray $options
	 */
	public static function redirectUrl( string $location, array $options = [] ) : string {
		$options += [
			'external' => false,
			'query' => [],
		];

		$schemed = preg_match('#^https?://#', $location);
		if ( !$schemed ) {
			$location = Request::origin() . '/' . ltrim($location, '/');
		}
		elseif ( !preg_match('#^' . Request::origin() . '#', $location) ) {
			if ( !$options['external'] ) {
				debug_exit('External redirect not allowed', $location);

				$location = '/';
				$options['query'] = [];
			}
		}

		if ( $options['query'] ) {
			$separator = is_int(strpos($location, '?')) ? '&' : '?';
			$location .= $separator . http_build_query($options['query']);
		}

		return $location;
	}

}
