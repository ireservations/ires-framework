<?php

namespace Framework\Http;

use Framework\Annotations\Access;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\RedirectException;
use InvalidArgumentException;
use User;

trait ChecksAccess {

	protected string $actionCallback = '';

	protected $acl = [];

	/** @return Hook[] */
	abstract protected function getHooks() : array;

	protected function aclAdd( $zones, $hooks = null, ?int $arg = null ) {
		if ( $hooks === null ) {
			$hooks = array_column($this->getHooks(), 'action');
		}

		$zones = (array) $zones;
		$hooks = (array) $hooks;

		foreach ( $hooks AS $hook ) {
			foreach ( $zones AS $zone ) {
				$this->acl[$hook][$zone] = $arg;
			}
		}

	} // END aclAdd() */


	protected function aclRemove( $zones, $hooks ) {
		$zones = (array)$zones;
		$hooks = (array)$hooks;

		foreach ( $hooks AS $hook ) {
			foreach ( $zones AS $zone ) {
				unset($this->acl[$hook][$zone]);
			}
		}

	} // END aclRemove() */


	protected function aclAlterAnnotations( string $hook, array $attributes ) {
		foreach ( $attributes AS $attribute ) {
			$access = $attribute->newInstance();
			$zone = $access->name;

			// aclRemove
			if ( $zone[0] == '-' ) {
				$this->aclRemove(ltrim($zone, '+-'), $hook);
			}
			// aclAdd
			else {
				$this->aclAdd(ltrim($zone, '+-'), $hook, $access->arg);
			}
		}

	} // END aclAlterAnnotations() */


	protected function aclCheck() {
		if ( ($this->runOptions['access'] ?? true) === false ) {
			return;
		}

		if ( !empty($this->acl[$this->actionCallback]) ) {
			foreach ( $this->acl[$this->actionCallback] AS $zone => $arg ) {
				if ( !$this->aclAccess($zone, $arg) ) {
					return $this->aclExit($zone);
				}
			}
		}

	} // END aclCheck() */


	protected function aclExit( $zone ) {
		if ( !User::logincheck() ) {
			if ( !Request::ajax() && Request::method() != 'POST' ) {
				return $this->aclLoginRedirect($zone);
			}
		}

		throw new AccessDeniedException($zone);

	} // END aclExit() */


	protected function aclAccess( string $zone, ?int $arg = null ) {
		if ( $arg === null ) {
			return User::access($zone);
		}

		try {
			$object = $this->aclObject($arg);
		}
		catch ( InvalidArgumentException $ex ) {
			$arg = $ex->getMessage();
			throw new InvalidArgumentException(sprintf("Can't check access '%s' for hook '%s', because missing arg '%d'", $zone, $this->actionCallback, $arg));
		}

		return User::access($zone, $object);

	} // END aclAccess() */


	protected function aclObject( $arg ) {
		if ( !array_key_exists($arg, $this->actionArgs) ) {
			throw new InvalidArgumentException($arg);
		}

		return $this->actionArgs[$arg];

	} // END aclObject() */


	protected function aclLoginRedirect( $zone ) {
		throw new RedirectException(User::loginRedirectUrl($zone));

	} // END aclLoginRedirect() */

}
