<?php
namespace org\shypl\common\yaml\directive;

abstract class Directive
{
	/**
	 * @var string
	 */
	protected $_name;

	/**
	 * @var array
	 */
	protected $_params = array();

	/**
	 * @param string $name
	 * @param array  $params
	 */
	public function __construct($name, array $params = array())
	{
		$this->_name = $name;
		foreach ($params as $value) {
			$this->_checkParam($value);
			$this->_params[] = $value;
		}
	}

	/**
	 * @param string $param
	 */
	protected function _checkParam($param)
	{
		#TODO
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 * @return array
	 */
	public function getParams()
	{
		return $this->_params;
	}

	/**
	 * @param int $index
	 *
	 * @return string
	 */
	public function getParam($index = 0)
	{
		return $this->_params[$index];
	}
}