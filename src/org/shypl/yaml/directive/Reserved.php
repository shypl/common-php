<?php
namespace org\shypl\yaml\directive;

class Reserved extends Directive
{
	/**
	 * @param string $name
	 * @param array  $params
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct($name, array $params = null)
	{
		if ($name === 'YAML' || $name === 'TAG') {
			throw new \InvalidArgumentException("Reserved directive can not be named \"$name\"");
		}
		parent::__construct($name, $params);
	}
}