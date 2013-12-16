<?php
namespace org\shypl\common\cache\link;

class ClassCacheLink extends FileCacheLink
{
	/**
	 * @throws \RuntimeException
	 * @return string
	 */
	protected function _getPath()
	{
		$reflection = new \ReflectionClass($this->_name);
		return $reflection->getFileName();
	}


	/**
	 * @return array
	 */
	protected function _prepareCompileArgs()
	{
		$args = parent::_prepareCompileArgs();
		$args[] = new \ReflectionClass($this->_name);
		return $args;
	}
}