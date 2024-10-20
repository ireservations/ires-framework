<?php

use App\Services\Aro\AppActiveRecordObject;
use App\Services\Session\User;
use Framework\Http\Request;
use Framework\Locale\Multilang;
use Framework\Tpl\HtmlString;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

function trans( $key, array $options = [] ) {
	/** @var Multilang $g_language */
	global $g_language;

	return $g_language->translate($key, $options);
}

function array_first( $arr ) {
	if ( count($arr) == 0 ) return null;

	reset($arr);
	return current($arr);
}

function array_last( $arr ) {
	if ( count($arr) == 0 ) return null;

	reset($arr);
	return end($arr);
}

function array_set( &$array, $path, $value ) {
	is_array($path) or $path = array_filter(preg_split('#[\.\[\]]+#', $path));

	$container = &$array;
	foreach ( $path as $name ) {
		if ( !isset($container[$name]) || !is_array($container[$name]) ) {
			$container[$name] = [];
		}

		$container = &$container[$name];
	}

	$container = $value;
}

function array_get( $source, $path ) {
	is_array($path) or $path = array_filter(preg_split('#[\.\[\]]+#', $path));

	$value = $source;
	foreach ( $path as $name ) {
		$value = is_object($value) ? ($value->$name ?? null) : ($value[$name] ?? null);
		if ( $value === null ) {
			return null;
		}
	}

	return $value;
}

function array_merge_recursive_distinct( array $array1, array $array2 ) : array {
	$merged = $array1;

	foreach ( $array2 as $key => $value ) {
		if ( is_array($value) && isset($merged[$key]) && is_array($merged[$key]) ) {
			$merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
		}
		else {
			$merged[$key] = $value;
		}
	}

	return $merged;
}

/**
 * @param array<array-key, AppActiveRecordObject> $objects
 * @return array<array-key, string>
 */
function aro_options( array $objects, ?string $label = null, ?string $key = null, bool $sort = false ) : array {
	$options = array();
	foreach ( $objects AS $object ) {
		$keyValue = $key ? array_get($object, $key) : $object->getPKValue();
		$labelValue = $label ? array_get($object, $label) : (string) $object;

		$options[$keyValue] = $labelValue;
	}

	$sort and natcasesort($options);

	return $options;
}

/**
 * @template TAroModel of AppActiveRecordObject
 * @param array<array-key, TAroModel> $objects
 * @return array<array-key, TAroModel>
 */
function aro_sort( array $objects, string $column ) : array {
	usort($objects, function(AppActiveRecordObject $a, AppActiveRecordObject $b) use ($column) {
		$a = array_get($a, $column);
		$b = array_get($b, $column);
		return strnatcasecmp($a, $b);
	});
	return $objects;
}

/**
 * @template TAroModel of AppActiveRecordObject
 * @param array<array-key, TAroModel> $objects
 * @return array<array-key, TAroModel>
 */
function aro_key( array $objects, ?string $column = null ) : array {
	$keyed = [];
	foreach ( $objects as $object ) {
		$key = $column ? $object->$column : $object->getPKValue();
		$keyed[$key] = $object;
	}
	return $keyed;
}

function array_flatten( $array ) {
	$items = [];
	foreach ( $array as $item ) {
		if ( is_array($item) ) {
			$items = array_merge($items, array_flatten($item));
		}
		else {
			$items[] = $item;
		}
	}

	return $items;
}

function array_pluck( $array, $column, $indexColumn = null ) {
	$out = [];
	foreach ( $array as $key => $item ) {
		$value = array_get($item, $column);
		if ( $indexColumn === null ) {
			$out[] = $value;
		}
		elseif ( $indexColumn === true ) {
			$out[$key] = $value;
		}
		else {
			$key = array_get($item, $indexColumn);
			$out[$key] = $value;
		}
	}

	return $out;
}

function filter_xss( $html ) {
	$allowed = 'p,br,hr,h1,h2,h3,h4,ul,ol,li,strong,b,em,i,u,strike,span,blockquote,a,img,table,tr,td,th,iframe,select,option,code,pre';
	$html = strip_tags($html, '<' . implode('><', explode(',', $allowed)) . '>');
	$html = preg_replace('#\s(on[a-z]+\s*=)#i', ' x$1', $html);
	return $html;
}

function escapehtml( $string ) {
	if ( $string instanceof HtmlString ) {
		return $string;
	}

	$encoded = @htmlspecialchars($string, ENT_COMPAT, 'UTF-8');

	// Encoding error =(
	if ( $string && !$encoded ) {
		$encoded = @htmlspecialchars($string, ENT_COMPAT, 'ISO-8859-1');
	}

	return new HtmlString($encoded);
}

function timetostr( $format, $utc = null, $language = null ) {
	$_format = preg_replace('/(?<!\\\\)([DlMF])/', '~~~@$1~~~', $format);

	$date = $utc ? date($_format, $utc) : date($_format);
	if ( $format == $_format ) {
		return $date;
	}

	$options = ['language' => $language, 'ucfirst' => false];
	$date = preg_replace_callback('/~~~@(.*?)~~~/', function($match) use ($options) {
		return trans('DATE__' . strtoupper($match[1]), $options);
	}, $date);
	return $date;
}

function debug_exit( $title, $more = '' ) {
	if ( Request::debug() ) {
		@ob_end_clean();
		@header("HTTP/1.1 500 debug_exit");
		@header('Content-type: text/html; charset=utf-8');
		@header('Content-Disposition: inline');

		echo '<h2>' . $title . "</h2>\n\n";

		if ( $more instanceof Throwable ) {
			$trace = $more->getTrace();
			$more = '';
		}
		else {
			$trace = array_slice(debug_backtrace(), 1);
			$more = print_r($more, true);
		}

		if ( Request::ajax() || Request::cli() || !ini_get('html_errors') ) {
			echo "<pre>$more\n";
			print_r(_debug_backtrace($trace));
			echo '</pre>';
		}
		else {
			echo "<pre>$more</pre>";
			kprint_r($trace);
		}

		exit;
	}
	else {
		global $g_arrErrorMessages;

		if ( $more instanceof Throwable ) {
			$trace = _debug_backtrace($more->getTrace());
			$more = $more->getMessage() . ' in ' . $more->getFile() . ':' . $more->getLine();
		}
		else {
			$trace = _debug_backtrace();
		}

		$more = $more ? "\n\n" . print_r($more, true) : '';
		$g_arrErrorMessages[] = $title . $more . "\n\n" . print_r($trace, 1);
	}
}

function _debug_backtrace( array $trace = [] ) {
	if ( !$trace ) {
		$trace = debug_backtrace();
		$trace = array_slice($trace, 1);
	}

	$trace = array_map(function($item) {
		$type = @$item['object'] ? '->' : '::';
		$class = @$item['class'] ? $item['class'] . $type : '';
		$line = @$item['line'] ? ' (' . $item['line'] . ')' : '';
		return $class . @$item['function'] . $line;
	}, $trace);
	return $trace;
}

function dpm( $data, $name = '' ) {
	$name and $name = $name . ' => ';
	return User::message($name . kprint_r_out($data));
}

function kprint_r_out( $data ) {
	$cloner = new VarCloner();
	$dumper = new HtmlDumper();
	return $dumper->dump($cloner->cloneVar($data), true);
}

function kprint_r( $data ) {
	VarDumper::dump($data);
}

function watchdog( $name, $data, $logFile = 'watchdog' ) {
	$header = Request::host() . Request::fullUri() . ' - ' . $name . ' - ' . date('Y-m-d H:i:s') . ' - ' . User::idOr(0) . ' - ' . (Request::ip() ?: 'local') . ' - ' . ($_SERVER['UNIQUE_ID'] ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? '?');
	return @file_put_contents(
		RUNTIME_LOGS . "/$logFile.log",
		"$header:\n" . trim(print_r($data, 1)) . "\n\n\n\n\n\n\n\n\n",
		FILE_APPEND
	);
}
