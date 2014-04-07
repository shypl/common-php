<?php
namespace org\shypl\jsy;

class Encoder
{
	/**
	 * @var array
	 */
	static private $_escapes = array(
		'\\' => '\\\\',
		"\n" => '\n',
		"\r" => '\r',
		"\t" => '\t',
		'\'' => '\\\''
	);

	/**
	 * @var array
	 */
	static private $_escapes2 = array(
		'{' => '\{',
		'}' => '\}',
		'[' => '\[',
		']' => '\]',
		':' => '\:',
		',' => '\,'
	);

	###

	/**
	 * @param mixed $value
	 *
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	public function encode($value)
	{
		switch (true) {

			case is_null($value):
				return 'N';

			case is_bool($value):
				return $value ? 'T' : 'F';

			case is_numeric($value):
				if ($value < 1 && $value > 0) {
					$value = substr($value, 1);
				}
				else if ($value < 0 && $value > -1) {
					$value = '-'.substr($value, 2);
				}
				return (string)$value;

			case is_string($value):
				return self::_encodeString($value);

			case is_array($value):
				return self::_encodeArray($value);

			case is_object($value):
				if (method_exists($value, 'toArray')) {
					/** @noinspection PhpUndefinedMethodInspection */
					return self::_encodeArray($value->toArray());
				}
				if (method_exists($value, '__toArray')) {
					/** @noinspection PhpUndefinedMethodInspection */
					return self::_encodeArray($value->__toArray());
				}
				if (method_exists($value, '__toString')) {
					return $value->__toString();
				}
				throw new \InvalidArgumentException('Can\'t encode object instance of '.get_class($value));

			default:
				throw new \InvalidArgumentException('Can\'t encode value of type '.gettype($value));
		}
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	private function _encodeString($value)
	{
		if ($value === 'N' || $value === 'T' || $value === 'F') {
			return '\\'.$value;
		}

		$value = strtr($value, self::$_escapes);

		$value1 = '\''.str_replace('\'', '\\\'', $value).'\'';
		$value2 = strtr($value, self::$_escapes2);

		return strlen($value1) < strlen($value2) ? $value1 : $value2;
	}

	/**
	 * @param array $value
	 *
	 * @return string
	 */
	private function _encodeArray($value)
	{
		$len = count($value);

		if ($len === 0) {
			return '[]';
		}

		if (array_keys($value) === range(0, $len - 1, 1)) {
			$value = array_map(array($this, 'encode'), $value);
			return '['.join(',', $value).']';
		}

		$result = array();
		foreach ($value as $k => $v) {
			$item = self::_encodeString($k);
			if ($v !== true) {
				$item .= ':' . self::encode($v);
			}
			$result[] = $item;
		}



		return '{' . join(',', $result) . '}';
	}
}