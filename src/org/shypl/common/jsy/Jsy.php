<?php
namespace org\shypl\common\jsy;

class Jsy
{
	/**
	 * @var \org\shypl\common\jsy\Encoder
	 */
	static private $_encoder;

	/**
	 * @var \org\shypl\common\jsy\Decoder
	 */
	static private $_decoder;

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	static public function encode($value)
	{
		if (self::$_encoder === null) {
			self::$_encoder = new Encoder();
		}
		return self::$_encoder->encode($value);
	}

	/**
	 * @param mixed $string
	 *
	 * @return mixed
	 */
	static public function decode($string)
	{
		if (self::$_decoder === null) {
			self::$_decoder = new Decoder();
		}
		return self::$_decoder->decode($string);
	}
}