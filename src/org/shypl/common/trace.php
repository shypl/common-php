<?php

function traced()
{
	$args = func_get_args();
	call_user_func_array('trace', $args);
	exit();
}

function trace()
{
	if (PHP_SAPI !== 'cli' && !headers_sent()) {
		header("Content-Type: text/plain; charset=UTF-8");
	}

	$args = func_get_args();

	$result = '';
	foreach ($args as $var) {
		if (is_bool($var)) {
			$result .= $var ? '[TRUE]' : '[FALSE]';
		}
		else if (is_null($var)) {
			$result .= '[NULL]';
		}
		else if (is_string($var)) {
			if ($var === '') {
				$result .= '[empty string]';
			}
			else {
				for ($i = 0, $l = mb_strlen($var, 'UTF8'); $i < $l; $i++) {
					$result .= trace_convertChar(mb_substr($var, $i, 1, 'UTF8'));
				}
			}
		}
		else {
			$result .= print_r($var, true);
		}
		if (count($args) > 0) {
			$result .= "\t";
		}
	}
	$result .= "\n";

	if (PHP_SAPI === 'cli' && defined('STDERR') && is_resource(STDERR)) {
		fwrite(STDERR, $result);
	}
	else {
		echo $result;
	}
}

/**
 * @param $char
 *
 * @return string
 */
function trace_convertChar($char)
{
	switch ($char) {
		case "\n":
			return '\n';
		case "\r":
			return '\r';
		case "\t":
			return '\t';
	}

	if (mb_strlen($char, 'ASCII') === 1) {
		$ord = ord($char);
		if ($ord < 32 || $ord > 126) {
			return strtoupper(sprintf('\%02x', $ord));
		}
	}
	return $char;
}
