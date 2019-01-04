<?php

namespace Framework\Common;

trait KnowsToday {

	static function today() {
		return TODAY;
	}

	static function getTodayishOverlayHours() {
		return 4;
	}

	static function datetype( $date, $today = TODAY ) {
		return $date == TODAY ? 'today' : ( $date > TODAY ? 'future' : 'past' );
	}

	static function todayish( $overlapHours = null ) {
		$overlapHours or $overlapHours = self::getTodayishOverlayHours();
		return date('Y-m-d', strtotime('-' . $overlapHours . ' hours'));
	}

	static function dayIsToday( $szDate, $overlapHours = null ) {
		return TODAY == $szDate || static::todayish($overlapHours) == $szDate;
	}

}
