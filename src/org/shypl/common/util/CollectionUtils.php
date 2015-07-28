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
}