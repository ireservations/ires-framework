<?php

namespace Framework\Common;

trait ValidatesBankAccounts {

	static function isIBAN( $number ) {
		return
			// AB 12 ABCD 0123456789
			preg_match('/^([a-z]{2})(\d{2})([a-z]{4})(\d{8,12})$/i', $number) ||
			// AB 12 012345678901234567
			preg_match('/^([a-z]{2})(\d{2})(\d{14,20})$/i', $number);
	}

	static function ibantest( $iban ) {
		$iban = substr($iban, 4) . substr($iban, 0, 4);
		$iban = preg_replace_callback('/[a-z]/i', function($match) {
			$letter = strtoupper($match[0]);
			$ord = ord($letter);
			return $ord - 55; // A = 65
		}, $iban);
		$mod = bcmod($iban, 97);
		return $mod == 1;
	}

	static function eleventest( $number ) {
		$number = str_pad($number, 10, '0', STR_PAD_LEFT);

		$t = 0;
		for ( $i=0; $i<10; $i++ ) {
			$t += $number[$i] * ($i+1);
		}

		return $t % 11 == 0;
	}

}
