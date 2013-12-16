<?php
namespace org\shypl\common\cache\link;

use org\shypl\common\cache\ICache;

class DirectoryCacheLink extends CacheLink
{
	/**
	 * @var string
	 */
	private $_mask;

	/**
	 * @var bool
	 */
	private $_recursive;

	/**
	 * @var array
	 */
	private $_files;

	/**
	 * @param ICache   $cache
	 * @param string   $name
	 * @param callable $compileCallback
	 * @param array    $compileArgs
	 * @param string   $mask
	 * @param bool     $recursive
	 *
	 * @throws \RuntimeException
	 */
	public function __construct(ICache $cache, $name, $compileCallback, array $compileArgs = null, $mask = '*', $recursive = true)
	{
		parent::__construct($cache, $name, $compileCallback, $compileArgs);
		$this->_mask = $mask;
		$this->_recursive = $recursive;
	}

	/**
	 * @throws \RuntimeException
	 * @return string
	 */
	protected function _getPath()
	{
		$path = realpath($this->_name);

		if (!$path || !is_dir($path)) {
			throw new \RuntimeException('Directory "'.$this->_name.'" not found');
		}
		if (!is_readable($path)) {
			throw new \RuntimeException('Directory "'.$this->_name.'" not readable');
		}

		return $path;
	}

	/**
	 * @return bool
	 */
	public function check()
	{
		$change = false;

		$old = $this->_loadMetadata();
		$new = $this->_findFiles($this->_getPath());
		if (!$old) {
			$change = true;
		}

		if (!$change) {
			foreach ($new as $file => $time) {
				if (!isset($old[$file]) || $old[$file] !== $time) {
					$change = true;
					break;
				}
				unset($old[$file]);
			}
		}

		if (!$change && !empty($old)) {
			$change = true;
		}

		if ($change) {
			$this->_metadata = $new;
			$this->_files = array_keys($new);
		}

		return !$change;
	}

	/**
	 * @param string $dir
	 * @param string $prefix
	 *
	 * @return array
	 */
	private function _findFiles($dir, $prefix = null)
	{
		$files = array();

		foreach (glob($dir . '/' . $this->_mask, GLOB_BRACE) as $path) {
			if (is_file($path)) {
				$files[$prefix . basename($path)] = filemtime($path);
			}
		}

		if ($this->_recursive) {
			foreach (glob($dir . '/*', GLOB_ONLYDIR) as $path) {
				$files = array_merge($files, $this->_findFiles($path, $prefix . basename($path) . '/'));
			}
		}

		return $files;
	}

	/**
	 * @return array
	 */
	protected function _prepareCompileArgs()
	{
		$args = parent::_prepareCompileArgs();
		$args[] = $this->_files;
		return $args;
	}
}