<?php

namespace Framework\Http\Response;

use ArrayAccess;

abstract class Response implements ArrayAccess {

	const CHARSET_UTF8 = 'utf-8';

	static protected $codes = [
		200 => 'OK',
		300 => 'Redirecting',
		400 => 'Error',
		500 => 'Error',
	];

	public int $code;
	public $data;
	/** @var ?string */
	protected $contentType;
	/** @var ?string */
	protected $contentTypeCharset;
	/** @var ?string */
	protected $downloadFilename;

	public function __construct( $data, $code = null ) {
		$this->code = $code ?: 200;
		$this->data = $data;
	}

	/** @return $this */
	public function contentType( $type, $charset = null ) {
		$this->contentType = $type;

		if ( $charset === true ) {
			$this->contentTypeCharset = self::CHARSET_UTF8;
		}
		elseif ( is_string($charset) ) {
			$this->contentTypeCharset = $charset;
		}

		return $this;
	}

	/** @return $this */
	public function download( $filename ) {
		$this->downloadFilename = $filename;
		return $this;
	}

	protected function printResponseCode() {
		$codeName = self::$codes[ floor($this->code / 100) * 100 ];
		@header("HTTP/1.1 {$this->code} $codeName");
	}

	protected function printContentType( $contentType = null, $contentTypeCharset = null, $downloadFilename = null ) {
		$contentType = $contentType ?? $this->contentType;
		$contentTypeCharset = $contentTypeCharset ?? $this->contentTypeCharset;
		$downloadFilename = $downloadFilename ?? $this->downloadFilename;

		if ( $contentType ) {
			$charset = $contentTypeCharset ? '; charset=' . $contentTypeCharset : '';
			@header('Content-type: ' . $contentType . $charset);
		}

		if ( $downloadFilename ) {
			header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
		}
	}

	protected function printDebugHeaders() {
		global $db;
		@header('X-Br-Queries: ' . $db->num_queries);
		@header('X-Br-Time: ' . number_format(microtime(true) - UTC_START, 4, '.', ''));
		@header('X-Br-Memory: ' . number_format(memory_get_peak_usage()/1e6, 1) . 'M');
	}

	public function printHeaders() {
		$this->printResponseCode();
		$this->printDebugHeaders();
		$this->printContentType();
	}

	abstract public function printContent();

	public function offsetExists( $name ) : bool {
		return isset($this->data[$name]);
	}

	public function offsetGet( $name ) : mixed {
		return $this->data[$name];
	}

	public function offsetSet( $name, $value ) : void {
	}

	public function offsetUnset( $name ) : void {
	}

}
