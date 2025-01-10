<?php

namespace Framework\Http;

use App\Services\Aro\AppActiveRecordObject;
use App\Services\Session\User;
use Framework\Annotations\Access;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\RedirectException;
use InvalidArgumentException;

trait ChecksAccess {

	/** @var array<string, ?int> */
	protected array $actionAcl = [];

	/**
	 * @param string|list<string> $zones
	 */
	protected function aclAdd( string|array $zones, ?int $arg = null ) : void {
		$zones = (array) $zones;

		foreach ( $zones AS $zone ) {
			$this->actionAcl[$zone] = $arg;
		}
	}

	/**
	 * @param string|list<string> $zones
	 */
	protected function aclRemove( string|array $zones ) : void {
		$zones = (array) $zones;

		foreach ( $zones AS $zone ) {
			unset($this->actionAcl[$zone]);
		}
	}

	protected function aclAlterAction() : void {
		$attributes = $this->actionReflection->getAttributes(Access::class);
		foreach ( $attributes AS $attribute ) {
			$access = $attribute->newInstance();
			$zone = $access->name;

			if ( $zone[0] == '-' ) {
				$this->aclRemove(ltrim($zone, '+-'));
			}
			else {
				$this->aclAdd(ltrim($zone, '+-'), $access->arg);
			}
		}
	}

	protected function aclAlterController() : void {
		foreach ( $this->ctrlrOptions['accessZones'] ?? [] as $zone ) {
			if ( $zone[0] == '-' ) {
				$this->aclRemove(ltrim($zone, '+-'));
			}
			else {
				$this->aclAdd(ltrim($zone, '+-'), null);
			}
		}
	}

	protected function aclCheck() : void {
		if ( ($this->ctrlrOptions['access'] ?? true) === false ) {
			return;
		}

		foreach ( $this->actionAcl AS $zone => $arg ) {
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

	protected function aclObject( int $arg ) : ?AppActiveRecordObject {
		if ( !array_key_exists($arg, $this->actionArgs) ) {
			throw new InvalidArgumentException(strval($arg));
		}

		return $this->actionArgs[$arg];
	}

	protected function aclLoginRedirect( string $zone ) : void {
		throw new RedirectException(User::loginRedirectUrl($zone));
	}

}
