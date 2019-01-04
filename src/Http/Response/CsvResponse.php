<?php

namespace Framework\Http\Response;

class CsvResponse extends TextResponse {

	protected $contentType = 'text/csv';

	public function __construct( $data, $filename ) {
		parent::__construct($this->stringify($data));

		$this->downloadFilename = $filename;
	}

	protected function stringify( $data ) {
		if ( is_scalar($data) ) {
			return $data;
		}

		return implode("\n", array_map(function(array $line) {
			return '"' . implode('", "', $line) . '"';
		}, $data));
	}

}
