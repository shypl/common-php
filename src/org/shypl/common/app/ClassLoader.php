<?php
namespace org\shypl\common\app;

use InvalidArgumentException;
use RuntimeException;

final class ClassLoader
{
	/**
	 * @var ClassLoader
	 */
	static private $instance;

	/**
	 * @param string $cacheFile
	 *
	 * @return ClassLoader
	 * @throws \RuntimeException
	 */
	static public function init($cacheFile = null)
	{
		if (null !== self::$instance) {
			throw new RuntimeException('ClassLoader already initialized');
		}
		self::$instance = new ClassLoader($cacheFile);
		return self::$instance;
	}

	/**
	 * @return ClassLoader
	 * @throws \RuntimeException
	 */
	static public function instance()
	{
		if (null === self::$instance) {
			throw new RuntimeException('ClassLoader is not initialized');
		}
		return self::$instance;
	}

	/**
	 * @var string
	 */
	private $cacheFile;

	/**
	 * @var array
	 */
	private $cache = array();

	/**
	 * @var array
	 */
	private $paths = array();

	/**
	 * @param string $cacheFile
	 */
	private function __construct($cacheFile = null)
	{
		$this->loadIncludePaths();
		$this->addPath(dirname(dirname(dirname(dirname(__DIR__)))));

		// cache
		if ($cacheFile !== null) {
			$this->cacheFile = $cacheFile;
			$this->loadCache();
		}

		// register auto load
		spl_autoload_register(array($this, 'load'), true, true);
	}

	/**
	 *
	 */
	private function loadIncludePaths()
	{
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
			if ($path != '.' && $path != '') {
				$this->addPath0($path, false);
			}
		}
	}

	/**
	 * @param string $path
	 * @param bool   $setInclude
	 *
	 * @throws \InvalidArgumentException
	 */
	private function addPath0($path, $setInclude)
	{
		$phar = strpos($path, 'phar://') === 0;

		if ($phar) {
			$path = substr($path, 7);
		}

		try {
			$realPath = realpath($path);
		}
		catch (\Exception $e) {
			return;
		}

		if ($realPath) {
			if (!$phar && is_file($realPath)) {
				$phar = true;
			}

			if ($phar) {
				$realPath = 'phar://' . $realPath;
			}

			if (!in_array($realPath, $this->paths)) {
				$this->paths[] = $realPath;
			}

			if ($setInclude) {
				set_include_path($realPath . PATH_SEPARATOR . get_include_path());
			}
		}
	}

	/**
	 * @param string $path
	 */
	public function addPath($path)
	{
		$this->addPath0($path, true);
	}

	/**
	 *
	 */
	private function loadCache()
	{
		clearstatcache();

		if ($this->cacheFile !== null) {
			if (file_exists($this->cacheFile)) {
				/** @noinspection PhpIncludeInspection */
				$this->cache = include($this->cacheFile);
			}
			if (!is_array($this->cache)) {
				$this->cache = array();
			}
		}
		else {
			$this->cache = array();
		}
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

		if ($this->checkExists($class)) {
			return true;
		}

		if ($this->loadFromCache($class)) {
			return true;
		}

		foreach ($this->paths as $path) {
			if ($this->loadFile($class, $path . '/' . strtr($class, array('\\' => '/', '_' => '/')))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private function checkExists($class)
	{
		return class_exists($class) || interface_exists($class);
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private function loadFromCache($class)
	{
		if (isset($this->cache[$class])) {
			$file = $this->cache[$class];
			if (file_exists($file)) {
				/** @noinspection PhpIncludeInspection */
				include $file;
				if ($this->checkExists($class)) {
					return true;
				}
			}
			unset($this->cache[$class]);
			$this->saveCache();
		}
		return false;
	}

	/**
	 * @throws \RuntimeException
	 */
	private function saveCache()
	{
		if ($this->cacheFile !== null) {
			clearstatcache();

			if (is_writable(dirname($this->cacheFile))) {
				$new = !file_exists($this->cacheFile);
				$data = '<?php' . "\n" . 'return ' . var_export($this->cache, true) . ';';
				$file = fopen($this->cacheFile, 'c');

				if (flock($file, LOCK_EX | LOCK_NB)) {
					ftruncate($file, 0);
					fwrite($file, $data);
					fflush($file);
					if ($new) {
						chmod($this->cacheFile, 0664);
					}
					flock($file, LOCK_UN);
				}

				fclose($file);
			}
			else {
				throw new \RuntimeException('Can not write cache file:  ' . $this->cacheFile);
			}
		}
	}

	/**
	 * @param string $class
	 * @param string $path
	 *
	 * @return bool
	 */
	private function loadFile($class, $path)
	{
		$file = $path . '.php';
		if (file_exists($file)) {
			/** @noinspection PhpIncludeInspection */
			include $file;
			if ($this->checkExists($class)) {
				$this->addToCache($class, $file);
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $class
	 * @param string $file
	 */
	private function addToCache($class, $file)
	{
		$this->cache[$class] = $file;
		$this->saveCache();
	}
}