<?php

namespace Framework\Aro;

trait LogsChanges {

	abstract function getPKValue();
	abstract static function presave( array &$data );

	abstract public function _logChangesLog( $type, $changes );

	public function _logChanges( $type, $fields, $updates ) {
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
