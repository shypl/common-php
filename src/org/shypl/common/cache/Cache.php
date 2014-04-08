<?php
namespace org\shypl\common\cache;

use org\shypl\common\util\Serialization;

abstract class Cache implements ICache
{
	const DATA_NULL      = 0;
	const DATA_BOOL      = 1;
	const DATA_INT       = 2;
	const DATA_FLOAT     = 3;
	const DATA_STRING    = 4;
	const DATA_SERIALIZE = 5;

	/**
	 * @param mixed $data
	 *
	 * @return string
	 */
	static public function serialize($data)
	{
		switch (true) {
			case null === $data:
				$flag = self::DATA_NULL;
				break;

			case is_bool($data):
				$flag = self::DATA_BOOL;
				break;

			case is_int($data):
				$flag = self::DATA_INT;
				break;

			case is_float($data):
				$flag = self::DATA_FLOAT;
				break;

			case is_string($data):
				$flag = self::DATA_STRING;
				break;

			default:
				$flag = self::DATA_SERIALIZE;
				$data = Serialization::encode($data);
				break;
		}

		return $flag . $data;
	}

	/**
	 * @param string $data
	 *
	 * @return mixed
	 *
	 * @throws \RuntimeException
	 */
	static public function unserialize($data)
	{
		if (!is_string($data)) {
			return $data;
		}

		$flag = (int)substr($data, 0, 1);
		$data = substr($data, 1);

		switch ($flag) {
			case self::DATA_NULL:
				return null;

			case self::DATA_BOOL:
				return (bool)$data;

			case self::DATA_INT:
				return (int)$data;

			case self::DATA_FLOAT:
				return (float)$data;

			case self::DATA_STRING:
				return (string)$data;

			case self::DATA_SERIALIZE:
				return Serialization::decode($data);

			default:
				throw new \RuntimeException('Undefined flag "'.$flag.'"');
		}
	}
}