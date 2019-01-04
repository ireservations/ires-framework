<?php

namespace Framework\Common;

trait KnowsDates {

	public static function getDates( $startDate, $endDate, $delta = 1 ) {
		$date = $startDate;
		$dates = [];
		while ( $date <= $endDate ) {
			$dates[] = $date;
			$date = date('Y-m-d', strtotime("+$delta days", static::mktime($date)));
		}
		return $dates;
	}


	public static function getFirstDateOfWeekday( $startDate, $weekdays ) {
		$weekdays = (array)$weekdays;

		$iUtc = is_int($startDate) ? $startDate : static::mktime($startDate);

		$tries = 0;
		while ( $tries++ < 7 ) {
			$iToday = (int)date('w', $iUtc);
			if ( in_array($iToday, $weekdays) ) {
				return date('Y-m-d', $iUtc);
			}
			$iUtc = strtotime('+1 day', $iUtc);
		}

		return false;
	}


	public static function getPeriod( $f_bWeekend, $f_bPeak ) {
		return 'week' . ( $f_bWeekend ? 'end' : '' ) . ( $f_bPeak ? '_peak' : '' );
	}


	public static function isWeekend( $f_szDate ) {
		$iToday = (int)date('w', static::mktime($f_szDate));
		return ( 0 == $iToday || 6 == $iToday );
	}


	public static function mktime( $f_szDate, $f_szTime = null ) {
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


	public static function mondayAndSundayOfWeek( $time ) {
		$time = is_numeric($time) ? (int)$time : static::mktime($time);

		$iToday = (int)date('w', $time);

		$iWeekday = $iToday-1; // maandag = 0
		if ( 0 > $iWeekday ) {
			$iWeekday = 6; // zondag = 6
		}

		$monday = date('Y-m-d', strtotime('-'.$iWeekday.' days', $time));
		$sunday = date('Y-m-d', strtotime('+'.(6-$iWeekday).' days', $time));

		return array($monday, $sunday);
	}


	public static function nextFirstOfMonth( $date ) {
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
