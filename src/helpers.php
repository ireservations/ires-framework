<?php

use App\Services\Http\AppController;
use App\Services\Session\User;
use Framework\Aro\ActiveRecordObject;
use Framework\Http\Request;
use Framework\Locale\Multilang;
use Framework\Tpl\HtmlString;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

function route(string $route, mixed ...$args) : string {
	return AppController::route($route, ...$args);
}

/**
 * @param AssocArray $options
 */
function trans( string $key, array $options = [] ) : string {
	/** @var Multilang<array-key> $g_language */
	global $g_language;

	return $g_language->translate($key, $options);
}

/**
 * @template T
 * @param array<array-key, T> $arr
 * @return ($arr is non-empty-array ? T : T|null)
 */
function array_first( array $arr ) : mixed {
	if ( count($arr) == 0 ) return null;

	reset($arr);
	return current($arr);
}

/**
 * @template T
 * @param array<array-key, T> $arr
 * @return ($arr is non-empty-array ? T : T|null)
 */
function array_last( $arr ) {
	if ( count($arr) == 0 ) return null;

	reset($arr);
	return end($arr);
}

/**
 * @param null|array<array-key, mixed> $array
 * @param-out array<array-key, mixed> $array
 * @param string|list<int|string> $path
 */
function array_set( ?array &$array, string|array $path, mixed $value ) : void {
	if ( !is_array($path) ) $path = array_filter(preg_split('#[\.\[\]]+#', $path));

	$container = &$array;
	foreach ( $path as $name ) {
		if ( !isset($container[$name]) || !is_array($container[$name]) ) {
			$container[$name] = [];
		}

		$container = &$container[$name];
	}

	$container = $value; // @phpstan-ignore paramOut.type,rudie.UnusedVariablesRule
}

/**
 * @param array<array-key, mixed>|object $source
 * @param string|list<int|string> $path
 */
function array_get( array|object $source, string|array $path ) : mixed {
	if ( !is_array($path) ) {
		$path = array_filter(preg_split('#[\.\[\]]+#', $path), function(string $part) {
			return strlen($part) > 0;
		});
	}

	$value = $source;
	foreach ( $path as $name ) {
		$value = is_object($value) ? ($value->$name ?? null) : ($value[$name] ?? null);
		if ( $value === null ) {
			return null;
		}
	}

	return $value;
}

/**
 * @param array<array-key, mixed> $array1
 * @param array<array-key, mixed> $array2
 * @return array<array-key, mixed>
 */
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
 * @template TAroModel of ActiveRecordObject
 * @param array<array-key, TAroModel> $objects
 * @param null|aro-dot-property<TAroModel> $label
 * @param null|aro-dot-property<TAroModel> $key
 * @return array<array-key, int|float|string>
 */
function aro_options( array $objects, ?string $label = null, ?string $key = null, bool $sort = false ) : array {
	$options = array();
	foreach ( $objects AS $object ) {
		$keyValue = $key ? array_get($object, $key) : $object->getPKValue();
		$labelValue = $label ? array_get($object, $label) : ($object instanceof Stringable ? strval($object) : $object->getPKValue());

		$options[$keyValue] = $labelValue;
	}

	$sort and natcasesort($options);

	return $options;
}

/**
 * @template TAroModel of ActiveRecordObject
 * @param array<TAroModel> $objects
 * @param aro-dot-property<TAroModel> $column
 * @return array<array-key, TAroModel>
 */
function aro_sort( array $objects, string $column ) : array {
	usort($objects, function(ActiveRecordObject $a, ActiveRecordObject $b) use ($column) {
		$a = array_get($a, $column);
		$b = array_get($b, $column);
		return strnatcasecmp($a, $b);
	});
	return $objects;
}

/**
 * @template TAroModel of ActiveRecordObject
 * @param array<TAroModel> $objects
 * @param null|aro-property<TAroModel> $column
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

/**
 * @param array<array-key, mixed> $array
 * @return list<mixed>
 */
function array_flatten( array $array ) : array {
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

/**
 * @param array<array-key, AssocArray|object> $array
 * @return array<array-key, mixed>
 */
function array_pluck( array $array, int|string $column, null|true|int|string $indexColumn = null ) : array {
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

function filter_xss( ?string $html ) : string {
	$allowed = 'p,br,hr,h1,h2,h3,h4,ul,ol,li,strong,b,em,i,u,strike,span,blockquote,a,img,table,tr,td,th,iframe,select,option,code,pre';
	$html = strip_tags($html ?? '', '<' . implode('><', explode(',', $allowed)) . '>');
	$html = preg_replace('#\s(on[a-z]+\s*=)#i', ' x$1', $html);
	return $html;
}

/**
 * @param mixed $string
 * @return string
 */
function escapehtml( $string ) {
	if ( $string instanceof HtmlString ) {
		return $string;
	}

	$encoded = @htmlspecialchars((string) $string, ENT_COMPAT, 'UTF-8');

	// Encoding error =(
	if ( $string && !$encoded ) {
		$encoded = @htmlspecialchars($string, ENT_COMPAT, 'ISO-8859-1');
	}

	return new HtmlString($encoded);
}

function timetostr( string $format, ?int $utc = null, null|int|string $language = null ) : string {
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

function debug_exit( string $title, mixed $more = '' ) : void {
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
		/** @var list<string> $g_arrErrorMessages */
		global $g_arrErrorMessages;

		if ( $more instanceof Throwable ) {
			$trace = _debug_backtrace($more->getTrace());
			$more = $more->getMessage() . ' in ' . $more->getFile() . ':' . $more->getLine();
		}
		else {
			$trace = _debug_backtrace();
		}

		$more = $more ? "\n\n" . print_r($more, true) : '';
		$g_arrErrorMessages[] = $title . $more . "\n\n" . print_r($trace, true);
	}
}

/**
 * @param list<AssocArray> $trace
 * @return list<string>
 */
function _debug_backtrace( array $trace = [] ) : array {
	if ( !$trace ) {
		$trace = debug_backtrace();
		$trace = array_slice($trace, 1);
	}

	$trace = array_map(function($item) {
		$type = !empty($item['object']) ? '->' : '::';
		$class = !empty($item['class']) ? strval($item['class']) . $type : '';
		$line = isset($item['line']) ? ' (' . strval($item['line']) . ')' : '';
		return $class . strval($item['function'] ?? '') . $line;
	}, $trace);
	return $trace;
}

function dpm( mixed $data, string $name = '' ) : void {
	if ( $name ) $name = $name . ' => ';
	User::message($name . kprint_r_out($data));
}

function kprint_r_out( mixed $data ) : string {
	$cloner = new VarCloner();
	$dumper = new HtmlDumper();
	return (string) $dumper->dump($cloner->cloneVar($data), true);
}

function kprint_r( mixed $data ) : void {
	VarDumper::dump($data);
}

function watchdog( string $name, mixed $data, string $logFile = 'watchdog' ) : int {
	$header = Request::host() . Request::fullUri() . ' - ' . $name . ' - ' . date('Y-m-d H:i:s') . ' - ' . User::idOr(0) . ' - ' . (Request::ip() ?: 'local') . ' - ' . strval($_SERVER['UNIQUE_ID'] ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? '?');
	return (int) @file_put_contents(
		RUNTIME_LOGS . "/$logFile.log",
		"$header:\n" . trim(print_r($data, true)) . "\n\n\n\n\n\n\n\n\n",
		FILE_APPEND
	);
}
