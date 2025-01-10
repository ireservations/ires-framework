<?php

namespace Framework\Http;

use Framework\Http\Exception\InvalidInputException;

trait VetsInput {

	protected const INPUT_OPTIONAL = false;
	protected const INPUT_REQUIRED = true;

	/** @var array<string, mixed> */
	protected array $vettedInput = [];

	/**
	 * @param AssocArray $f_arrSource
	 * @param list<string> $f_arrVars
	 */
	protected function mf_AddVettedInput( array $f_arrSource, array $f_arrVars ) : void {
		$this->vettedInput += array_map(function($input) {
			return is_scalar($input) ? trim($input) : array_map(function($input) {
				return is_scalar($input) ? trim($input) : $input;
			}, $input);
		}, array_intersect_key($f_arrSource, array_flip($f_arrVars)));
	}


	/**
	 * @param list<string> $f_arrVars
	 */
	protected function mf_AddVettedPostInput( array $f_arrVars ) : void {
		$this->mf_AddVettedInput($_POST, $f_arrVars);
	}


	/**
	 * @param list<string>|array<string, string> $f_arrVars
	 */
	protected function mf_RequirePostVars( array $f_arrVars, bool $f_bRequireContent = self::INPUT_OPTIONAL ) : void {
		$this->mf_RequireVars($_POST, $f_arrVars, $f_bRequireContent);
	}


	/**
	 * @param list<string>|array<string, string> $f_arrVars
	 */
	protected function mf_RequireGetVars( array $f_arrVars, bool $f_bRequireContent = self::INPUT_OPTIONAL ) : void {
		$this->mf_RequireVars($_GET, $f_arrVars, $f_bRequireContent);
	}


	/**
	 * @param AssocArray $f_arrSource
	 * @param list<string>|array<string, string> $f_arrVars
	 */
	protected function mf_RequireVars( array $f_arrSource, array $f_arrVars, bool $f_bRequireContent = self::INPUT_OPTIONAL ) : void {
		$arrMissing = array();
		foreach ( $f_arrVars AS $k => $f ) {
			$translate = true;
			if ( is_int($k) ) {
				$translate = false;
				$k = $f;
			}

			if ( $this->mf_RequireVar($f_arrSource, $k, $f_bRequireContent, $value) ) {
				$this->vettedInput[$k] = $value;
			}
			else {
				$label = $translate ? trans($f) : $f;
				$arrMissing[$k] = strip_tags($label);
			}
		}

		if ( $arrMissing ) {
			throw new InvalidInputException(null, $arrMissing);
		}
	}


	/**
	 * @param AssocArray $source
	 * @param mixed $value
	 * @param-out mixed $value
	 */
	protected function mf_RequireVar( array $source, string $varName, bool $requireContent, &$value = null ) : bool {
		if ( isset($source[$varName]) ) {
			$value = is_array($source[$varName]) ? $source[$varName] : trim($source[$varName]);
			$length = is_array($value) ? count($value) : strlen($value);
			if ( !$requireContent || $length > 0 ) {
				return true;
			}
		}

		return false;
	}

}
