<?php

namespace Framework\Http\Response;

use ArrayAccess;
use Framework\Aro\ActiveRecordObject;

/**
 * @implements ArrayAccess<string, mixed>
 */
abstract class Response implements ArrayAccess {

	protected const CHARSET_UTF8 = 'utf-8';

	protected const CODES = [
		200 => 'OK',
		300 => 'Redirecting',
		400 => 'Error',
		500 => 'Error',
	];

	public int $code;
	/** @var mixed */
	public $data;
	/** @var ?string */
	protected $contentType;
	/** @var ?string */
	protected $contentTypeCharset;
	/** @var ?string */
	protected $downloadFilename;

	/**
	 * @param mixed $data
	 */
	public function __construct( $data, ?int $code = null ) {
		$this->code = $code ?: 200;
		$this->data = $data;
	}

	/**
	 * @return $this
	 */
	public function contentType( string $type, null|bool|string $charset = null ) {
		$this->contentType = $type;

		if ( $charset === true ) {
			$this->contentTypeCharset = self::CHARSET_UTF8;
		}
		elseif ( is_string($charset) ) {
			$this->contentTypeCharset = $charset;
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function download( string $filename ) {
		$this->downloadFilename = $filename;
		return $this;
	}

	protected function printResponseCode() : void {
		$codeName = self::CODES[ floor($this->code / 100) * 100 ];
		@header("HTTP/1.1 {$this->code} $codeName");
	}

	protected function printContentType( ?string $contentType = null, ?string $contentTypeCharset = null, ?string $downloadFilename = null ) : void {
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

	/**
	 * @return void
	 */
	protected function printDebugHeaders() : void {
		$db = ActiveRecordObject::getDbObject();
		@header('X-Br-Queries: ' . $db->num_queries);
		@header('X-Br-Time: ' . number_format(microtime(true) - UTC_START, 4, '.', ''));
		@header('X-Br-Memory: ' . number_format(memory_get_peak_usage()/1e6, 1) . 'M');
	}

	/**
	 * @return void
	 */
	public function printHeaders() {
		$this->printResponseCode();
		$this->printDebugHeaders();
		$this->printContentType();
	}

	/**
	 * @return void
	 */
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
