<?php
namespace org\shypl\common\http;

class HttpHeader
{
	const CONTENT_TYPE = 'Content-Type';

	const LOCATION = 'Location';

	/**
	 * @var string
	 */
	private $_name;

	/**
	 * @var string
	 */
	private $_value;

	/**
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value)
	{
		$this->_name = $name;
		$this->_value = $value;
	}

	/**
	 * @return string
	 */
	public function name()
	{
		return $this->_name;
	}

	/**
	 * @return string
	 */
	public function value()
	{
		return $this->_value;
	}

	/**
	 * @return string
	 */
	public function toString()
	{
		return $this->_name . ': ' . $this->_value;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->toString();
	}

}