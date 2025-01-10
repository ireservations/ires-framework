<?php

namespace Framework\Common;

trait KnowsWeekdays {

	static public int $weekdayShortLength = 3;

	/** @var array<int, string> */
	static public array $weekdaysFull = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

	/**
	 * @param-out string $weekday
	 */
	public static function checkWeekday( mixed &$weekday ) : bool {
		$enFull = self::$weekdaysFull;
		$enShort = array_map(function($day) {
			return substr($day, 0, self::$weekdayShortLength);
		}, $enFull);

		$weekday = strtolower(trim((string) $weekday));

		if ( in_array($weekday, $enFull) ) {
			return true;
		}
		elseif ( in_array($weekday, $enShort) ) {
			$weekday = $enFull[ array_search($weekday, $enShort) ];
			return true;
		}

		$curFull = array_map('strtolower', self::getWeekdays());
		$length = (int) t('WEEKDAY_SHORT_LENGTH') ?: 3;
		$curShort = array_map('strtolower', self::getWeekdays($length));

		if ( in_array($weekday, $curFull) ) {
			$weekday = $enFull[ array_search($weekday, $curFull) ];
			return true;
		}
		elseif ( in_array($weekday, $curShort) ) {
			$weekday = $enFull[ array_search($weekday, $curShort) ];
			return true;
		}

		return false;
	}


	public static function getWeekday( int $f_iWeekday, int $length = 0 ) : string {
		// 0 = SUNDAY
		$szWeekday = trans('WEEKDAY_' . ($f_iWeekday % 7));

		if ( 2 <= $length ) {
			$szWeekday = substr($szWeekday, 0, $length);
		}

		return $szWeekday;
	}


	/**
	 * @return array<int, string>
	 */
	public static function getWeekdays( int $length = 0, bool $ucfirst = true ) : array {
		$options = compact('ucfirst');

		// 0 = SUNDAY
		$arrWeekdays = array(
			1 => trans('WEEKDAY_MONDAY', $options),
			2 => trans('WEEKDAY_TUESDAY', $options),
			3 => trans('WEEKDAY_WEDNESDAY', $options),
			4 => trans('WEEKDAY_THURSDAY', $options),
			5 => trans('WEEKDAY_FRIDAY', $options),
			6 => trans('WEEKDAY_SATURDAY', $options),
			0 => trans('WEEKDAY_SUNDAY', $options),
		);

		if ( 2 <= $length ) {
			foreach ( $arrWeekdays AS $k => $szDay ) {
				$arrWeekdays[$k] = substr($szDay, 0, $length);
			}
		}

		return $arrWeekdays;
	}

}
