<?php

namespace Framework\Common;

trait ValidatesInput {

	static public string $dateFormat = 'y-m-d';

	/**
	 * checkFloat()
	 *
	 * @param-out float|string $number
	 * @return float|false
	 */
	public static function checkFloat( mixed &$number ) {
		$number = (string) $number;

		$a = str_replace(' ', '', str_replace(',', '.', $number));
		if ( !is_numeric($a) ) {
			return false;
		}

		return $number = (float) $a;

	} // END checkFloat() */


	/**
	 * checkInt()
	 *
	 * @param-out int|string $number
	 * @return int|false
	 */
	public static function checkInt( mixed &$number, bool $positive = false ) {
		$number = (string) $number;

		$a = str_replace(' ', '', str_replace(',', '.', $number));
		if ( (string) (int) $a !== $a ) {
			return false;
		}

		$int = (int) $a;

		if ( $positive && $int <= 0 ) {
			return false;
		}

		return $number = $int;

	} // END checkInt() */


	/**
	 * checkTime()
	 * Check input and reformat to system format HH:MM
	 *
	 * Valid times are: 7:20, 18, 18:0, 12:51, 0:0, 24:0, 4:30pm (=16:30), 12am (=24:00 or 00:00), 19pm (=19:00), 19:4am (=19:40), 1:2:3 (=01:20), etc
	 *
	 * @param-out string $time
	 * @param bool $f_bNoMaxHours If true, 24:00 and 26:00 are valid times. If false they are converted to 00:00 and 02:00
	 * @return non-falsy-string|false
	 */
	public static function checkTime( mixed &$time, bool $f_bNoMaxHours = true ) {
		$time = (string) $time;

		if ( !preg_match('/^(\d\d?)(?:(?:\:|\.)(\d\d?))?(?:(?:\:|\.)\d\d?)?(?: ?(am|pm))?$/', strtolower($time), $parrMatch) ) {
			return false;
		}

		$parrMatch[] = '';
		$parrMatch[] = '';

		list(, $hours, $minutes, $ampm) = $parrMatch;

		$hours = (int) $hours;
		if ( ('pm' == $ampm && 12 > $hours) || ('am' == $ampm && 12 == $hours) ) {
			$hours += 12;
		}

		if ( !$f_bNoMaxHours && 24 <= $hours ) {
			$hours = $hours % 24;
		}

		// Reformat to HH:MM
		return $time = str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_RIGHT);

	} // END checkTime() */


	/**
	 * checkDate()
	 * Check input and reformat to system format YYYY-MM-DD
	 *
	 * All combinations of y, m, d are valid: y-m-d (system), d-m-y (Dutch), m/d/y, m.d.y (American), d.m.y (German?) etc
	 *
	 * Special: "today", "tomorrow"
	 *
	 * @param-out string $date
	 * @return non-falsy-string|false
	 */
	static public function checkDate( mixed &$date, ?string $format = null ) {
		$date = (string) $date;

		if ( $date === 'today' ) {
			return $date = date('Y-m-d');
		}
		else if ( $date === 'tomorrow' ) {
			return $date = date('Y-m-d', strtotime('+1 day'));
		}

		$defaultFormat = 'y-m-d';
		$format || $format = self::$dateFormat;

		// Use named groups in regex to identify Year, Month & Day
		$regexp = '#^' . strtr(preg_quote($format, '#'), array(
			'y' => '(?P<year>(?:1|2)\d{3})',
			'm' => '(?P<month>\d\d?)',
			'd' => '(?P<day>\d\d?)',
		)) . '$#';

		// Match input
		if ( preg_match($regexp, $date, $parrMatch) ) {
			// Reformat input to YYYY-MM-DD
			$szDate = implode('-', [
				$parrMatch['year'],
				str_pad($parrMatch['month'], 2, '0', STR_PAD_LEFT),
				str_pad($parrMatch['day'], 2, '0', STR_PAD_LEFT)
			]);

			return $date = $szDate;
		}

		// No match for $format

		// Try default format?
		if ( $format != $defaultFormat ) {
			// Use exact same routine with different, default format
			return self::checkDate($date, $defaultFormat);
		}

		// Invalid input: does not look like a date
		return false;

	} // END checkDate() */

}
