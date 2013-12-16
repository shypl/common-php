<?php
namespace org\shypl\common\util;

class ArrayUtils
{
	/**
	 * @return array
	 */
	static public function merge()
	{
		$arrays = func_get_args();
		$base = array_shift($arrays);

		foreach ($arrays as $array) {
			foreach ($array as $key => $value) {
				if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
					if (self::isList($base[$key]) && self::isList($value)) {
						$base[$key] = $value;
					}
					else {
						$base[$key] = ArrayUtils::merge($base[$key], $value);
					}
				}
				else {
					$base[$key] = $value;
				}
			}
		}

		return $base;
	}

	/**
	 * @param array $array
	 * @param array $keys
	 *
	 * @return array
	 */
	static public function extract(array $array, array $keys)
	{
		return array_intersect_key($array, array_fill_keys($keys, null));
	}

	/**
	 * @param array $value
	 *
	 * @return bool
	 */
	static public function isList(array $value)
	{
		return array_keys($value) === range(0, count($value) - 1, 1);
	}

	/**
	 * @param array $value
	 *
	 * @return bool
	 */
	static public function isHash(array $value)
	{
		return !self::isList($value);
	}

	/**
	 * @param array $array
	 * @param array $list
	 */
	static public function pushMany(array &$array, array $list)
	{
		if (!empty($list)) {
			foreach ($list as $value) {
				$array[] = $value;
			}
		}
	}

	/**
	 * @param array $array
	 * @param bool  $key
	 *
	 * @return mixed
	 */
	static public function rand(array $array, $key = false)
	{
		$k = NumberUtils::rand(count($array));
		return $key ? $k : $array[$k];
	}

	/**
	 * @param array $array
	 * @param bool  $returnWeight
	 *
	 * @return int
	 */
	static public function randByWeight(array $array, $returnWeight = false)
	{
		$sum = 0;
		$rnd = NumberUtils::rand(array_sum($array));

		$i = null;
		$w = null;

		foreach ($array as $i => $w) {
			$sum += $w;
			if ($sum >= $rnd) {
				return $returnWeight ? $w : $i;
			}
		}

		return $returnWeight ? $w : $i;
	}
}
