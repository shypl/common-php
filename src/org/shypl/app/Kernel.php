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
	private static $_inited;
	/**
	 * @var string
	 */
	private static $_path;
	/**
	 * @var bool
	 */
	private static $_cliMode;
	/**
	 * @var string
	 */
	private static $_env;
	/**
	 * @var bool
	 */
	private static $_devMode;
	/**
	 * @var FileCache
	 */
	private static $_cache;
	/**
	 * @var array
	 */
	private static $_configs = array();

	/**
	 * @param string $path
	 */
	public static function init($path)
	{
		if (self::$_inited) {
			throw new RuntimeException();
		}
		self::$_inited = true;

		ini_set('display_errors', true);

		self::$_path = realpath($path);

		if (!self::$_path || !is_dir(self::$_path)) {
			throw new \InvalidArgumentException('Directory "' . $path . '" not found');
		}

		require(__DIR__ . '/../trace.php');

		self::$_cliMode = PHP_SAPI === 'cli';
		self::$_env = file_get_contents(self::path('env'));
		self::$_devMode = self::$_env !== self::ENV_PROD;

		ErrorHandler::init(self::$_devMode, self::path('log/error-{date}.log'));
		ClassLoader::init(self::path('cache/classes.php'));

		self::$_cache = new FileCache(self::path('cache'));
	}

	/**
	 * @return bool
	 */
	public static function isCliMode()
	{
		return self::$_cliMode;
	}

	/**
	 * @return bool
	 */
	public static function isDevMode()
	{
		return self::$_devMode;
	}

	/**
	 * @param string $target
	 *
	 * @return string
	 */
	public static function path($target)
	{
		return self::$_path . '/' . $target;
	}

	/**
	 * @param string $name
	 *
	 * @return Config
	 */
	public static function config($name)
	{
		if (!isset(self::$_configs[$name])) {
			$file = self::path('config/' . self::$_env . '/' . $name . '.yml');
			if (!file_exists($file)) {
				$file = self::path('config/' . $name . '.yml');
			}
			return
				self::$_configs[$name] = new Config(self::$_cache, $file, Config::COMPILER_YAML, self::$_devMode, true);
		}

		return self::$_configs[$name];
	}

	/**
	 * @return FileCache
	 */
	public static function cache()
	{
		return self::$_cache;
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