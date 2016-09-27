<?php
namespace org\shypl\common\util;

use stdClass;

final class CollectionUtils {
	/**
	 * @param array $array
	 *
	 * @return stdClass
	 */
	public static function convertArrayToObject(array $array) {
		$object = new stdClass();
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$value = self::convertArrayToObject($value);
			}
			$object->$key = $value;
		}
		return $object;
	}

	/**
	 * @param stdClass $object
	 *
	 * @return array
	 */
	public static function convertObjectToArray(stdClass $object) {
		$array = array();
		foreach ($object as $key => $value) {
			if ($value instanceof stdClass) {
				$value = self::convertObjectToArray($value);
			}
			$array->$key = $value;
		}
		return $array;
	}
}