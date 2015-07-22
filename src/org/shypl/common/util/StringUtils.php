<?php
namespace org\shypl\common\util;

final class StringUtils {
	/**
	 * @param string $string
	 * @param string $needle
	 * @param int    $offset
	 *
	 * @return int
	 */
	public static function bytePos($string, $needle, $offset = 0) {
		return mb_strpos($string, $needle, $offset, 'ASCII');
	}

	/**
	 * @param string $string
	 * @param bool   $hex
	 *
	 * @return array
	 */
	public static function toBytes($string, $hex = false) {
		$bytes = [];
		for ($i = 0, $l = self::byteLen($string); $i < $l; $i++) {
			$byte = ord(self::byteCut($string, $i, 1));
			if ($hex) {
				$byte = ($byte < 0x10 ? '0' : '') . dechex($byte);
			}
			$bytes[] = $byte;
		}

		return $bytes;
	}

	/**
	 * @param string $string
	 *
	 * @return int
	 */
	public static function byteLen($string) {
		return mb_strlen($string, 'ASCII');
	}

	/**
	 * @param string   $string
	 * @param int      $offset
	 * @param int|null $length
	 *
	 * @return string
	 */
	public static function byteCut($string, $offset, $length = null) {
		if (null === $length) {
			$length = self::byteLen($string);
		}
		return mb_substr($string, $offset, $length, 'ASCII');
	}

	/**
	 * @param string $string
	 * @param bool   $lowFirst
	 *
	 * @return string
	 */
	public static function toCamelCase($string, $lowFirst = false) {
		$string = trim($string);
		if ($string === '') {
			return '';
		}

		$string = str_replace(['_', '-'], ' ', $string);
		$string = ucwords($string);
		$string = str_replace(' ', '', $string);

		if ($lowFirst) {
			$string{0} = strtolower($string{0});
		}

		return $string;
	}

	/**
	 * @param string $string
	 * @param string $sep
	 *
	 * @return string
	 */
	public static function toEmphasisCase($string, $sep = '_') {
		$string = preg_replace('/[A-Z\d]/', $sep . '$0', $string);
		$string = ltrim($string, $sep);
		$string = strtolower($string);
		return $string;
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public static function toPlural($string) {
		$last = substr($string, -1);
		if ($last === 'y') {
			$string = substr($string, 0, -1) . 'ies';
		}
		else if ($last !== 's') {
			$string .= 's';
		}

		return $string;
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public static function toSingular($string) {
		if (substr($string, -3) === 'ies') {
			return substr($string, 0, -3) . 'y';
		}
		if (substr($string, -1) === 's') {
			return substr($string, 0, -1);
		}

		return $string;
	}

	/**
	 * @param int      $number
	 * @param string[] $labels
	 *
	 * @return string
	 */
	public static function getNumberLabel($number, array $labels) {
		if ($number > 10 && $number < 15) {
			return $labels[2];
		}

		$last = (int)substr($number, -1);

		if ($last == 1) {
			return $labels[0];
		}

		if ($last != 0 && $last < 5) {
			return $labels[1];
		}

		return $labels[2];
	}

	/**
	 * @param number $number
	 * @param int    $decimals
	 * @param string $decPoint
	 * @param string $thousandsSep
	 *
	 * @return string
	 */
	public static function formatNumber($number, $decimals = 0, $decPoint = '.', $thousandsSep = ' ') {
		return number_format($number, $decimals, $decPoint, $thousandsSep);
	}
}