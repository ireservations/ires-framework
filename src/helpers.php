<?php

use Framework\Tpl\HtmlString;
use Framework\Http\Request;
use Framework\Locale\Multilang;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

function trans( $key, array $options = [] ) {
	/** @var Multilang $g_language */
	global $g_language;

	return $g_language->translate($key, $options);
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

function array_get( $array, $path ) {
	is_array($path) or $path = array_filter(preg_split('#[\.\[\]]+#', $path));

	$value = $array;
	foreach ( $path as $name ) {
		if ( !isset($value[$name]) ) {
			return null;
		}

		$value = is_object($value) ? $value->$name : $value[$name];
	}

	return $value;
}

function filter_xss( $html ) {
	$allowed = 'p,br,hr,h1,h2,h3,h4,ul,ol,li,strong,b,em,i,u,strike,span,blockquote,a,img,table,tr,td,th,iframe,select,option,code';
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

		echo '<h2>' . $title . "</h2>\n\n";

		if ( $more instanceof Throwable ) {
			$trace = $more->getTrace();
			$more = '';
		}
		else {
			$trace = array_slice(debug_backtrace(), 1);
			$more = print_r($more, true);
		}

		watchdog('debug_exit', "$title\n\n$more\n\n" . print_r(_debug_backtrace($trace), true));

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

function watchdog( $name, $data ) {
	return @file_put_contents(
		RUNTIME_LOGS . '/watchdog.log',
		Request::fullUri() . ' - ' . $name . ' - ' . date('Y-m-d H:i:s') . ":\n" . trim(print_r($data, 1)) . "\n\n\n\n\n\n\n\n\n",
		FILE_APPEND
	);
}
