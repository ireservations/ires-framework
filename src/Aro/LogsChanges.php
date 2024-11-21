<?php

namespace Framework\Aro;

trait LogsChanges {

	abstract function getPKValue();
	// abstract static function presave( array &$data );

	/**
	 * @param array<string, null|array{?scalar, ?scalar}> $changes
	 */
	abstract public function _logChangesLog( string $type, array $changes ) : void;

	/**
	 * @param array<int|string, bool|string> $fields
	 * @param array<array-key, mixed> $updates
	 */
	public function _logChanges( string $type, array $fields, array $updates ) : void {
		static::presave($updates);

		$changes = [];
		foreach ( $fields as $field => $withValue ) {
			if ( is_int($field) ) {
				$field = $withValue;
				$withValue = true;
			}

			if ( array_key_exists($field, $updates) && $updates[$field] != $this->$field ) {
				$old = $this->$field;
				$new = $updates[$field];
				$changes[$field] = $withValue ? [$old, $new] : null;
			}
		}

		if ( count($changes) > 0 ) {
			$this->_logChangesLog($type, $changes);
		}
	}

}
