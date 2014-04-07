<?php
namespace org\shypl\sys;

use InvalidArgumentException;
use RuntimeException;

final class ClassLoader
{
	/**
	 * @var ClassLoader
	 */
	static private $_instance;

	/**
	 * @param string $cacheFile
	 *
	 * @return ClassLoader
	 * @throws \RuntimeException
	 */
	static public function init($cacheFile = null)
	{
		if (null !== self::$_instance) {
			throw new RuntimeException('ClassLoader already initialized');
		}
		self::$_instance = new ClassLoader($cacheFile);
		return self::$_instance;
	}

	/**
	 * @return ClassLoader
	 * @throws \RuntimeException
	 */
	static public function instance()
	{
		if (null === self::$_instance) {
			throw new RuntimeException('ClassLoader is not initialized');
		}
		return self::$_instance;
	}

	/**
	 * @var string
	 */
	private $_cacheFile;

	/**
	 * @var array
	 */
	private $_cache = array();

	/**
	 * @var array
	 */
	private $_paths = array();

	/**
	 * @param string $cacheFile
	 */
	private function __construct($cacheFile = null)
	{
		$this->_loadIncludePaths();
		$this->addPath(dirname(dirname(dirname(dirname(__DIR__)))));

		// cache
		if ($cacheFile !== null) {
			$this->_cacheFile = $cacheFile;
			$this->_loadCache();
		}

		// register auto load
		spl_autoload_register(array($this, 'load'), true, true);
	}

	/**
	 * @param string $path
	 */
	public function addPath($path)
	{
		$this->_addPath($path, true);
	}

	/**
	 * @param array $paths
	 */
	public function addPaths(array $paths)
	{
		foreach ($paths as $path) {
			$this->addPath($path);
		}
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	public function load($class)
	{
		foreach (spl_autoload_functions() as $callback) {
			if ($callback[0] === $this) {
				continue;
			}
			if (call_user_func($callback, $class)) {
				return true;
			}
		}

		if ($this->_checkExists($class)) {
			return true;
		}

		if ($this->_loadFromCache($class)) {
			return true;
		}

		foreach ($this->_paths as $path) {
			if ($this->_loadFile($class, $path . '/' . strtr($class, array('\\' => '/')))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $path
	 * @param bool   $setInclude
	 *
	 * @throws \InvalidArgumentException
	 */
	private function _addPath($path, $setInclude)
	{
		$phar = strpos($path, 'phar://') === 0;

		if ($phar) {
			$path = substr($path, 7);
		}

		$realPath = realpath($path);
		if (!$realPath) {
			throw new InvalidArgumentException('Path is not exists: ' . $path);
		}

		if (!$phar && is_file($realPath)) {
			$phar = true;
		}

		if ($phar) {
			$realPath = 'phar://' . $realPath;
		}

		if (!in_array($realPath, $this->_paths)) {
			$this->_paths[] = $realPath;
		}

		if ($setInclude) {
			set_include_path($realPath . PATH_SEPARATOR . get_include_path());
		}
	}

	/**
	 *
	 */
	private function _loadIncludePaths()
	{
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
			if ($path != '.') {
				$this->_addPath($path, false);
			}
		}
	}

	/**
	 *
	 */
	private function _loadCache()
	{
		clearstatcache();

		if ($this->_cacheFile !== null) {
			if (file_exists($this->_cacheFile)) {
				/** @noinspection PhpIncludeInspection */
				$this->_cache = include($this->_cacheFile);
			}
			if (!is_array($this->_cache)) {
				$this->_cache = array();
			}
		}
		else {
			$this->_cache = array();
		}
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private function _checkExists($class)
	{
		return class_exists($class) || interface_exists($class);
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private function _loadFromCache($class)
	{
		if (isset($this->_cache[$class])) {
			$file = $this->_cache[$class];
			if (file_exists($file)) {
				/** @noinspection PhpIncludeInspection */
				include $file;
				if ($this->_checkExists($class)) {
					return true;
				}
			}
			unset($this->_cache[$class]);
			$this->_saveCache();
		}
		return false;
	}

	/**
	 * @throws \RuntimeException
	 */
	private function _saveCache()
	{
		if ($this->_cacheFile !== null) {
			clearstatcache();

			if (is_writable(dirname($this->_cacheFile))) {
				$new = !file_exists($this->_cacheFile);
				$data = '<?php' . "\n" . 'return ' . var_export($this->_cache, true) . ';';
				$file = fopen($this->_cacheFile, 'c');

				if (flock($file, LOCK_EX | LOCK_NB)) {
					ftruncate($file, 0);
					fwrite($file, $data);
					fflush($file);
					if ($new) {
						chmod($this->_cacheFile, 0664);
					}
					flock($file, LOCK_UN);
				}

				fclose($file);

			}
			else {
				throw new \RuntimeException('Can not write cache file:  ' . $this->_cacheFile);
			}
		}
	}

	/**
	 * @param string $class
	 * @param string $path
	 *
	 * @return bool
	 */
	private function _loadFile($class, $path)
	{
		$file = $path . '.php';
		if (file_exists($file)) {
			/** @noinspection PhpIncludeInspection */
			include $file;
			if ($this->_checkExists($class)) {
				$this->_addToCache($class, $file);
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $class
	 * @param string $file
	 */
	private function _addToCache($class, $file)
	{
		$this->_cache[$class] = $file;
		$this->_saveCache();
	}
}