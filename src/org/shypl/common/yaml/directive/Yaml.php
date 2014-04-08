<?php
namespace org\shypl\common\yaml\directive;

class Yaml extends Directive
{
	/**
	 * @param string $version
	 */
	public function __construct($version)
	{
		#TODO check $version
		parent::__construct('YAML', array($version));
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return $this->_params[0];
	}
}