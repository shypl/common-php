<?php
namespace org\shypl\common\core;

use InvalidArgumentException;
use org\shypl\common\util\CollectionUtils;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

require_once 'ErrorHandler.php';
require_once 'ClassLoader.php';

class App {
	private static $inited;
	private static $path;
	private static $envName;
	private static $cliMode;
	private static $devMode;

	/**
	 * @param string $path
	 */
	public static function init($path) {
		if (self::$inited) {
			throw new RuntimeException();
		}

		ini_set('display_errors', true);

		self::$path = realpath($path);

		if (!self::$path || !is_dir(self::$path)) {
			throw new \InvalidArgumentException('Directory "' . $path . '" not found');
		}

		require_once __DIR__ . '/../../../../dev.php';

		self::$envName = self::defineEvnName();
		self::$cliMode = self::defineCliMode();
		self::$devMode = self::defineDevMode();

		self::initErrorHandler();
		self::initClassLoader();
	}

	/**
	 * @return string
	 */
	public static function getEnvName() {
		return self::$envName;
	}

	/**
	 * @return bool
	 */
	public static function isCliMode() {
		return self::$cliMode;
	}

	/**
	 * @return bool
	 */
	public static function isDevMode() {
		return self::$devMode;
	}

	/**
	 * @param string $target
	 *
	 * @return string
	 */
	public static function pathTo($target) {
		return self::$path . '/' . $target;
	}

	/**
	 * @param string $target
	 *
	 * @return string
	 */
	public static function pathToConfig($target) {
		$file = self::pathTo('private/config/' . self::$envName . '/' . $target);
		if (!file_exists($file)) {
			$file = self::pathTo('private/config/' . $target);
			if (!file_exists($file)) {
				throw new RuntimeException('Config is not exists (' . $target . ')');
			}
		}
		return $file;
	}

	/**
	 * @param string $name
	 * @param bool   $asObject
	 *
	 * @return array
	 */
	public static function config($name, $asObject = false) {
		$data = Yaml::parse(file_get_contents(self::pathToConfig($name . '.yml')), true, false, true);
		return $asObject && is_array($data) ? CollectionUtils::convertArrayToObject($data) : $data;
	}

	/**
	 * @param string       $file
	 * @param string|array $message
	 */
	public static function log($file, $message) {
		$path = self::pathTo('private/log/' . str_replace('{date}', date('Ymd'), $file) . '.log');

		if (is_array($message)) {
			$message = join("\n", $message);
		}

		$message = str_replace('{date}', date('Y-m-d H:i:s'), $message);

		$new = !file_exists($path);
		if ((is_writable($path) || is_writable(dirname($path))) && ($handle = fopen($path, 'a'))) {

			while (!flock($handle, LOCK_EX | LOCK_NB)) {
				usleep(10);
			}

			fwrite($handle, $message . "\n");
			fflush($handle);
			if ($new) {
				chmod($path, 0666);
			}
			flock($handle, LOCK_UN);
			fclose($handle);
		}
		else {
			throw new RuntimeException('Can not write log file "' . $path . '"');
		}
	}

	/**
	 * @return string
	 */
	private static function defineEvnName() {
		$file = self::pathTo('private/config/env');
		if (file_exists($file)) {
			return file_get_contents($file);
		}
		return 'dev';
	}

	/**
	 * @return bool
	 */
	private static function defineCliMode() {
		return PHP_SAPI === 'cli';
	}

	/**
	 * @return bool
	 */
	private static function defineDevMode() {
		return !(self::$envName === 'prod' || self::$envName === 'production');
	}

	private static function initErrorHandler() {
		ErrorHandler::init(self::$devMode, is_dir(self::pathTo('private/log')) ? self::pathTo('private/log/error-{date}.log') : null);
	}

	private static function initClassLoader() {
		ClassLoader::init(is_dir(self::pathTo('private/cache')) ? self::pathTo('private/cache/classes.php') : null);
	}
}