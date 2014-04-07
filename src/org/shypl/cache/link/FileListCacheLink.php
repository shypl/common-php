<?php
namespace org\shypl\cache\link;

class FileListCacheLink extends CacheLink
{
	/**
	 * @throws \RuntimeException
	 * @return bool
	 */
	public function check()
	{
		$files = $this->_loadMetadata();

		if (!$files) {
			return false;
		}

		$changed = false;
		foreach ($files as $file => $time) {
			if (!file_exists($file) || filemtime($file) !== $time) {
				$changed = true;
				break;
			}
		}

		if ($changed) {
			$this->_metadata = array();
			return false;
		}

		return true;
	}

	/**
	 * @return mixed
	 */
	protected function _compile()
	{
		list ($files, $data) = parent::_compile();

		$this->_metadata = array();
		foreach ($files as $file) {
			$this->_metadata[$file] = filemtime($file);
		}

		return $data;
	}

	/**
	 * @return array
	 */
	protected function _prepareCompileArgs()
	{
		$args = parent::_prepareCompileArgs();
		$args[] = $this->_name;
		return $args;
	}

}