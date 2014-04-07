<?php
namespace org\shypl\app;

use org\shypl\cache\FileCache;
use org\shypl\data\config\Config;
use RuntimeException;

require_once 'ErrorHandler.php';
require_once 'ClassLoader.php';

final class Kernel
{
	const ENV_LOCAL = 'local';
	const ENV_TEST = 'test';
	const ENV_PROD = 'prod';

	/**
	 * @var
	 */
	private static $inited;
	/**
	 * @var string
	 */
	private static $path;
	/**
	 * @var bool
	 */
	private static $cliMode;
	/**
	 * @var string
	 */
	private static $env;
	/**
	 * @var bool
	 */
	private static $devMode;
	/**
	 * @var FileCache
	 */
	private static $cache;
	/**
	 * @var array
	 */
	private static $configs = array();

	/**
	 * @param string $path
	 */
	public static function init($path)
	{
		if (self::$inited) {
			throw new RuntimeException();
		}
		self::$inited = true;

		ini_set('display_errors', true);

		self::$path = realpath($path);

		if (!self::$path || !is_dir(self::$path)) {
			throw new \InvalidArgumentException('Directory "' . $path . '" not found');
		}

		require(__DIR__ . '/../trace.php');

		self::$cliMode = PHP_SAPI === 'cli';
		self::$env = file_get_contents(self::path('env'));
		self::$devMode = self::$env !== self::ENV_PROD;

		ErrorHandler::init(self::$devMode, self::path('log/error-{date}.log'));
		ClassLoader::init(self::path('cache/classes.php'));

		self::$cache = new FileCache(self::path('cache'));
	}

	/**
	 * @return bool
	 */
	public static function isCliMode()
	{
		return self::$cliMode;
	}

	/**
	 * @return bool
	 */
	public static function isDevMode()
	{
		return self::$devMode;
	}

	/**
	 * @param string $target
	 *
	 * @return string
	 */
	public static function path($target)
	{
		return self::$path . '/' . $target;
	}

	/**
	 * @param string $name
	 *
	 * @return Config
	 */
	public static function config($name)
	{
		if (!isset(self::$configs[$name])) {
			$file = self::path('config/' . self::$env . '/' . $name . '.yml');
			if (!file_exists($file)) {
				$file = self::path('config/' . $name . '.yml');
			}
			return
				self::$configs[$name] = new Config(self::$cache, $file, Config::COMPILER_YAML, self::$devMode, true);
		}

		return self::$configs[$name];
	}

	/**
	 * @return FileCache
	 */
	public static function cache()
	{
		return self::$cache;
	}

	/**
	 * @return ClassLoader
	 */
	public static function classLoader()
	{
		return ClassLoader::instance();
	}

	/**
	 * @return ErrorHandler
	 */
	public static function errorHandler()
	{
		return ErrorHandler::instance();
	}

	/**
	 * @param string       $name
	 * @param string|array $msg
	 */
	public static function log($name, $msg)
	{
		$file = self::path('log/' . str_replace('{date}', date('Ymd'), $name) . '.log');

		if (is_array($msg)) {
			$msg = join("\n", $msg);
		}

		$msg = str_replace('{date}', date('Y-m-d H:i:s'), $msg);

		$new = !file_exists($file);
		if ((is_writable($file) || is_writable(dirname($file))) && ($handle = fopen($file, 'a'))) {

			while (!flock($handle, LOCK_EX | LOCK_NB)) {
				usleep(10);
			}

			fwrite($handle, $msg . "\n");
			fflush($handle);
			if ($new) {
				chmod($file, 0664);
			}
			flock($handle, LOCK_UN);
			fclose($handle);
		}
		else {
			throw new \RuntimeException('Can not write log file "' . $file . '"');
		}
	}
}