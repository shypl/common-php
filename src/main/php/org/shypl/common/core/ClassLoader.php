<?php
namespace org\shypl\common\core;

use RuntimeException;

final class ClassLoader {
	private static $inited;
	private static $cacheFile;
	private static $cache = [];
	private static $includePaths = [];

	/**
	 * @param string $cacheFile
	 */
	public static function init($cacheFile = null) {
		if (self::$inited) {
			throw new RuntimeException('ClassLoader already initialized');
		}

		self::loadIncludePaths();
		self::addPath(dirname(dirname(dirname(dirname(__DIR__)))));

		// cache
		if ($cacheFile !== null) {
			self::$cacheFile = $cacheFile;
			self::loadCache();
		}

		// register auto load
		spl_autoload_register(__NAMESPACE__ . '\\ClassLoader::load', true, true);
	}

	/**
	 * @param string $path
	 */
	public static function addPath($path) {
		self::addIncludePath($path, true);
	}

	/**
	 * @param array $paths
	 */
	public static function addPaths(array $paths) {
		foreach ($paths as $path) {
			self::addPath($path);
		}
	}

	/**
	 * @return array
	 */
	public static function getPaths() {
		return self::$includePaths;
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	public static function load($class) {
		foreach (spl_autoload_functions() as $callback) {
			if ($callback[0] === 'org\\shypl\\common\\core\\ClassLoader' && $callback[1] === 'load') {
				continue;
			}
			if (call_user_func($callback, $class)) {
				return true;
			}
		}

		if (self::checkExists($class)) {
			return true;
		}

		if (self::loadFromCache($class)) {
			return true;
		}

		foreach (self::$includePaths as $path) {
			if (self::loadFromFile($class, $path . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class))) {
				return true;
			}
		}

		return false;
	}

	private static function loadIncludePaths() {
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
			if ($path != '.' && $path != '') {
				self::addIncludePath($path, false);
			}
		}
	}

	/**
	 * @param string $path
	 * @param bool   $setInclude
	 */
	private static function addIncludePath($path, $setInclude) {
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
			if (!$phar) {
				if (is_file($realPath)) {
					$phar = true;
				}
				else {
					foreach (glob($realPath . DIRECTORY_SEPARATOR . '*.phar') as $childPhar) {
						self::addIncludePath('phar://' . $childPhar, true);
					}
				}
			}

			if ($phar) {
				$realPath = 'phar://' . $realPath;
			}

			if (!in_array($realPath, self::$includePaths)) {
				self::$includePaths[] = $realPath;
			}

			if ($setInclude) {
				set_include_path($realPath . PATH_SEPARATOR . get_include_path());
			}
		}
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private static function checkExists($class) {
		return class_exists($class) || interface_exists($class);
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	private static function loadFromCache($class) {
		if (isset(self::$cache[$class])) {
			$file = self::$cache[$class];
			if (file_exists($file)) {
				/** @noinspection PhpIncludeInspection */
				include $file;
				if (self::checkExists($class)) {
					return true;
				}
			}
			unset(self::$cache[$class]);
			self::saveCache();
		}
		return false;
	}

	/**
	 * @param string $class
	 * @param string $path
	 *
	 * @return bool
	 */
	private static function loadFromFile($class, $path) {

		$file = $path . '.php';
		if (file_exists($file)) {
			/** @noinspection PhpIncludeInspection */
			include $file;
			if (self::checkExists($class)) {
				self::addToCache($class, $file);
				return true;
			}
		}
		return false;
	}

	private static function loadCache() {
		clearstatcache();

		if (self::$cacheFile !== null) {
			if (file_exists(self::$cacheFile)) {
				/** @noinspection PhpIncludeInspection */
				self::$cache = include self::$cacheFile;
			}
			if (!is_array(self::$cache)) {
				self::$cache = [];
			}
		}
		else {
			self::$cache = [];
		}
	}

	private static function saveCache() {
		if (self::$cacheFile !== null) {
			clearstatcache();

			if (is_writable(dirname(self::$cacheFile))) {
				$data = '<?php' . "\n" . 'return ' . var_export(self::$cache, true) . ';';

				$new = !file_exists(self::$cacheFile);
				$file = fopen(self::$cacheFile, 'c');

				if (flock($file, LOCK_EX | LOCK_NB)) {
					ftruncate($file, 0);
					fwrite($file, $data);
					fflush($file);
					if ($new) {
						chmod(self::$cacheFile, 0666);
					}
					flock($file, LOCK_UN);
				}

				fclose($file);
			}
			else {
				throw new RuntimeException('Can not write cache file:  ' . self::$cacheFile);
			}
		}
	}

	/**
	 * @param string $class
	 * @param string $file
	 */
	private static function addToCache($class, $file) {
		self::$cache[$class] = $file;
		self::saveCache();
	}
}