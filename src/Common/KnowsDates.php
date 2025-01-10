<?php

namespace Framework\Common;

trait KnowsDates {

	/**
	 * @return list<string>
	 */
	public static function getDates( string $startDate, string $endDate, int $delta = 1 ) : array {
		$date = $startDate;
		$dates = [];
		while ( $date <= $endDate ) {
			$dates[] = $date;
			$date = date('Y-m-d', strtotime("+$delta days", static::mktime($date)));
		}
		return $dates;
	}


	/**
	 * @param int|list<int> $weekdays
	 */
	public static function getFirstDateOfWeekday( int|string $startDate, $weekdays ) : string {
		$weekdays = (array) $weekdays;

		$iUtc = is_int($startDate) ? $startDate : static::mktime($startDate);

		$tries = 0;
		while ( $tries++ < 7 ) {
			$iToday = (int) date('w', $iUtc);
			if ( in_array($iToday, $weekdays) ) {
				return date('Y-m-d', $iUtc);
			}
			$iUtc = strtotime('+1 day', $iUtc);
		}

		return ''; // never
	}


	public static function isWeekend( string $f_szDate ) : bool {
		$iToday = (int) date('w', static::mktime($f_szDate));
		return 0 == $iToday || 6 == $iToday;
	}


	static public function today() : string {
		return date('Y-m-d');
	}


	static public function tomorrow() : string {
		return date('Y-m-d', strtotime('tomorrow'));
	}


	public static function mktime( ?string $f_szDate, ?string $f_szTime = null ) : int {
		if ( !$f_szDate ) {
			$f_szDate = date('Y-m-d');
		}

		if ( !$f_szTime ) {
			$f_szTime = '0:0';
		}

		if ( !(int)$f_szDate ) {
			return 0;
		}

		$d = explode('-', $f_szDate);
		$t = explode(':', $f_szTime);
		if ( 3 > count($d) || 2 > count($t) ) {
			return 0;
		}

		return mktime((int)$t[0], (int)$t[1], 0, (int)$d[1], (int)$d[2], (int)$d[0]);
	}


	/**
	 * @return array{string, string}
	 */
	public static function mondayAndSundayOfWeek( int|string $time ) : array {
		$time = is_int($time) ? $time : static::mktime($time);

		$iToday = (int)date('w', $time);

		$iWeekday = $iToday-1; // maandag = 0
		if ( 0 > $iWeekday ) {
			$iWeekday = 6; // zondag = 6
		}

		$monday = date('Y-m-d', strtotime('-'.$iWeekday.' days', $time));
		$sunday = date('Y-m-d', strtotime('+'.(6-$iWeekday).' days', $time));

		return array($monday, $sunday);
	}


	public static function nextFirstOfMonth( string $date ) : string {
		$utc = static::mktime($date);

		// 1st of month: return that
		if ( 1 == (int)date('d', $utc) ) {
			return $date;
		}

		// After 1st: return next 1st of month
		$first = date('Y-m-01', $utc);
		$first = static::mktime($first);
		$next = strtotime('+1 month', $first);
		$date = date('Y-m-01', $next);

		return $date;
	}

}
