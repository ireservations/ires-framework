<?php

namespace Framework\User;

use Framework\Http\Session;

trait ConveysMessages {

	static function error( $msg, $encode = false ) {
		static::message($msg, 'error', $encode);
	}

	static function warning( $msg, $encode = false ) {
		static::message($msg, 'warning', $encode);
	}

	static function info( $msg, $encode = false ) {
		static::message($msg, 'info', $encode);
	}

	static function success( $msg, $encode = false ) {
		static::message($msg, 'success', $encode);
	}

	static function message( $msg, $type = 'info', $encode = false ) {
		Session::required();

		if ( $encode ) {
			$msg = escapehtml($msg);
		}

		Session::push('messages', [
			'msg' => $msg,
			'type' => $type,
		]);
	}

	static function messages( $reset = true ) {
		$messages = Session::get('messages', []);

		if ( $reset ) {
			Session::set('messages', []);
		}

		return $messages;
	}

}
