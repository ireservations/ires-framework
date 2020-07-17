<?php

namespace Framework\Http;

use Framework\Annotations\Access;
use Framework\Http\Exception\AccessDeniedException;
use Framework\Http\Exception\RedirectException;
use User;

trait ChecksAccess {

	protected $m_szHook = '';

	protected $acl = [];

	/** @return Hook[] */
	abstract protected function getHooks();

	protected function aclAdd( $zones, $hooks = null, $arg = -1 ) {
		if ( $hooks === null ) {
			$hooks = [];
			foreach ( $this->getHooks() as $hook ) {
				$hooks[] = $hook->action;
			}
		}

		$zones = (array) $zones;
		$hooks = is_array($hooks) ? array_unique($hooks) : [$hooks];

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


	/**
	 * @param Access[] $accesses
	 */
	protected function aclAlterAnnotations( $hook, array $accesses ) {
		foreach ( $accesses AS $access ) {
			$zone = $access->value;
			$arg = $access->arg;

			// aclRemove
			if ( $zone[0] == '-' ) {
				$this->aclRemove(ltrim($zone, '+-'), $hook);
			}
			// aclAdd
			else {
				$this->aclAdd(ltrim($zone, '+-'), $hook, $arg);
			}
		}

	} // END aclAlter() */


	protected function aclCheck() {
		if ( ($this->m_arrRunOptions['access'] ?? true) === false ) {
			return;
		}

		if ( !empty($this->acl[$this->m_szHook]) ) {
			foreach ( $this->acl[$this->m_szHook] AS $zone => $arg ) {
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


	protected function aclAccess( $zone, $arg = -1 ) {
		if ( $arg >= 0 && !array_key_exists($arg, $this->m_arrActionArgs) ) {
			throw new \InvalidArgumentException("Can't check access '{$zone}' for hook '{$this->m_szHook}', because missing arg '{$arg}'");
		}

		$object = $arg >= 0 ? $this->m_arrActionArgs[$arg] : null;
		return User::access($zone, $object);

	} // END aclAccess() */


	protected function aclLoginRedirect( $zone ) {
		throw new RedirectException(User::loginRedirectUrl($zone));

	} // END aclLoginRedirect() */

}
