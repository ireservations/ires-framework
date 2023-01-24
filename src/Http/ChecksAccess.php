<?php

namespace Framework\Http;

use App\Services\Aro\AppActiveRecordObject;
use Framework\Annotations\Access;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\RedirectException;
use InvalidArgumentException;
use User;

trait ChecksAccess {

	protected function aclAdd( string|array $zones, null|string|array $hooks = null, ?int $arg = null ) : void {
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
	}

	protected function aclRemove( string|array $zones, string|array $hooks ) : void {
		$zones = (array) $zones;
		$hooks = (array) $hooks;

		foreach ( $hooks AS $hook ) {
			foreach ( $zones AS $zone ) {
				unset($this->acl[$hook][$zone]);
			}
		}
	}

	protected function aclAlterAnnotations( string $hook, array $attributes ) : void {
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
	}

	protected function aclCheck() : void {
		if ( ($this->runOptions['access'] ?? true) === false ) {
			return;
		}

		foreach ( $this->acl[$this->actionCallback] ?? [] AS $zone => $arg ) {
			if ( !$this->aclAccess($zone, $arg) ) {
				$this->aclExit($zone);
			}
		}
	}

	protected function aclExit( string $zone ) : void {
		if ( !User::logincheck() ) {
			if ( !Request::ajax() && Request::method() != 'POST' ) {
				$this->aclLoginRedirect($zone);
				return;
			}
		}

		throw new AccessDeniedException($zone);
	}

	protected function aclAccess( string $zone, ?int $arg = null ) : bool {
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
	}

	protected function aclObject( int $arg ) : AppActiveRecordObject {
		if ( !array_key_exists($arg, $this->actionArgs) ) {
			throw new InvalidArgumentException($arg);
		}

		return $this->actionArgs[$arg];
	}

	protected function aclLoginRedirect( string $zone ) : void {
		throw new RedirectException(User::loginRedirectUrl($zone));
	}

}
