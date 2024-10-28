<?php

namespace Framework\Http;

use Framework\Http\Exception\InvalidInputException;

trait VetsInput {

	/** @var array<string, mixed> */
	protected array $vettedInput = [];

	/**
	 *
	 */
	protected function mf_AddVettedInput( $f_arrSource, $f_arrVars ) {
		$this->vettedInput += array_map(function($input) {
			return is_scalar($input) ? trim($input) : array_map(function($input) {
				return is_scalar($input) ? trim($input) : $input;
			}, $input);
		}, array_intersect_key($f_arrSource, array_flip($f_arrVars)));

	} // END mf_AddVettedInput() */


	/**
	 *
	 */
	protected function mf_AddVettedPostInput( $f_arrVars ) {
		$this->mf_AddVettedInput($_POST, $f_arrVars);

	} // END mf_AddVettedPostInput() */


	/**
	 * C h e c k   f o r   P O S T   v a r s
	 */
	protected function mf_RequirePostVars( $f_arrVars, $f_bRequireContent = false ) {
		$this->mf_RequireVars($_POST, $f_arrVars, $f_bRequireContent);

	} // END mf_RequirePostVars() */


	/**
	 * C h e c k   f o r   G E T   v a r s
	 */
	protected function mf_RequireGetVars( $f_arrVars, $f_bRequireContent = false ) {
		$this->mf_RequireVars($_GET, $f_arrVars, $f_bRequireContent);

	} // END mf_RequireGetVars() */


	/**
	 * C h e c k   f o r   m u l t i p l e   v a r s
	 */
	protected function mf_RequireVars( $f_arrSource, $f_arrVars, $f_bRequireContent = false ) {
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

	} // END mf_RequireVars() */


	/**
	 * C h e c k   f o r   s i n g l e   v a r
	 */
	protected function mf_RequireVar( $source, $varName, $requireContent, &$value = null ) {
		if ( isset($source[$varName]) ) {
			$value = is_array($source[$varName]) ? $source[$varName] : trim($source[$varName]);
			$length = is_array($value) ? count($value) : strlen($value);
			if ( !$requireContent || $length > 0 ) {
				return true;
			}
		}

		return false;

	} // END mf_RequireVar() */

}
