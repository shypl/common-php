<?php
namespace org\shypl\util;

class Serialization
{
	/**
	 * @var bool
	 */
	static public $igbinary;

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	static public function encode($value)
	{
		/** @noinspection PhpUndefinedFunctionInspection */
		return self::$igbinary ? igbinary_serialize($value) : serialize($value);
	}

	/**
	 * @param string $string
	 *
	 * @return mixed
	 */
	static public function decode($string)
	{
		/** @noinspection PhpUndefinedFunctionInspection */
		return self::$igbinary ? igbinary_unserialize($string) : unserialize($string);
	}
}

Serialization::$igbinary = function_exists('igbinary_unserialize');