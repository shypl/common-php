<?php
namespace org\shypl\util;

class NumberUtils
{
	/**
	 * @param int $value
	 * @param int $offset
	 *
	 * @return int
	 */
	static public function rand($value, $offset = 0)
	{
		return (mt_rand() % $value) + $offset;
	}

	/**
	 * @param int $low
	 * @param int $high
	 *
	 * @return int
	 */
	static public function randRange($low, $high)
	{
		return self::rand($high - $low + 1, $low);
	}

	/**
	 * @return bool
	 */
	static public function randBool()
	{
		return self::rand(2) === 0;
	}

	/**
	 * @param $value
	 *
	 * @return float|int
	 */
	static public function parseInt($value)
	{
		if (gettype($value) === 'integer') {
			return $value;
		}

		if (preg_match('/^-?[1-9]\d*$/', $value)) {
			$value = (float) $value;
			if ($value <= PHP_INT_MAX) {
				return (int) $value;
			}
			return $value;
		}

		return 0;
	}
}
