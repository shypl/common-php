<?php
namespace org\shypl\common\cache\link;

class FileCacheLink extends CacheLink
{
	/**
	 * @throws \RuntimeException
	 * @return string
	 */
	protected function _getPath()
	{
		$path = realpath($this->_name);

		if (!$path || !is_file($path)) {
			throw new \RuntimeException('File "'.$this->_name.'" not found');
		}
		if (!is_readable($path)) {
			throw new \RuntimeException('File "'.$this->_name.'" not readable');
		}

		return $path;
	}

	/**
	 * @throws \RuntimeException
	 * @return bool
	 */
	public function check()
	{
		$time = filemtime($this->_getPath());
		$this->_loadMetadata();

		if ((int)$this->_metadata !== $time) {
			$this->_metadata = $time;
			return false;
		}

		return true;
	}

	/**
	 * @return array
	 */
	protected function _prepareCompileArgs()
	{
		$args = parent::_prepareCompileArgs();
		$args[] = $this->_getPath();
		return $args;
	}
}