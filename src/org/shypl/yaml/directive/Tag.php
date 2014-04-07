<?php
namespace org\shypl\yaml\directive;

/** @noinspection PhpDocSignatureInspection */
class Tag extends Directive
{
	/**
	 * @param string $handle
	 * @param string $prefix
	 */
	public function __construct($handle, $prefix)
	{
		#TODO check $handle, $prefix
		parent::__construct('TAG', array($handle, $prefix));
	}

	/**
	 * @return string
	 */
	public function getPrefix()
	{
		return $this->_params[1];
	}
}