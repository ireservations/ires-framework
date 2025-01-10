<?php

namespace Framework\User;

use Framework\Http\Session;
use Framework\Tpl\HtmlString;

trait ConveysMessages {

	static public function error( string|HtmlString $msg, bool $encode = false ) : void {
		static::message($msg, 'error', $encode);
	}

	static public function warning( string|HtmlString $msg, bool $encode = false ) : void {
		static::message($msg, 'warning', $encode);
	}

	static public function info( string|HtmlString $msg, bool $encode = false ) : void {
		static::message($msg, 'info', $encode);
	}

	static public function success( string|HtmlString $msg, bool $encode = false ) : void {
		static::message($msg, 'success', $encode);
	}

	static public function message( string|HtmlString $msg, string $type = 'info', bool $encode = false ) : void {
		Session::required();

		if ( $encode ) {
			$msg = escapehtml($msg);
		}

		Session::push('messages', [
			'msg' => $msg,
			'type' => $type,
		]);
	}

	/**
	 * @return list<string>
	 */
	static public function messages( bool $reset = true ) : array {
		$messages = Session::get('messages', []);

		if ( $reset && count($messages) ) {
			Session::set('messages', []);
		}

		return $messages;
	}

}
