<?php

namespace Framework\Http\Response;

class CsvResponse extends TextResponse {

	protected $contentType = 'text/csv';

	/**
	 * @param mixed $data
	 */
	public function __construct( $data, string $filename ) {
		parent::__construct($this->stringify($data));

		$this->downloadFilename = $filename;
	}

	/**
	 * @param mixed $data
	 */
	protected function stringify( $data ) : string {
		if ( is_scalar($data) ) {
			return $data;
		}

		return implode("\n", array_map(function(array $line) {
			return '"' . implode('", "', $line) . '"';
		}, $data));
	}

}
