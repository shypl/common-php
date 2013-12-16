<?php
namespace org\shypl\common\sys;

use org\shypl\common\cache\FileCache;
use org\shypl\common\cache\ICache;
use org\shypl\common\data\config\Config;

class Kernel
{
	/**
	 * @var string
	 */
	private $_root;

	/**
	 * @var bool
	 */
	private $_cli;

	/**
	 * @var string
	 */
	private $_env;

	/**
	 * @var bool
	 */
	private $_dev;

	/**
	 * @var ICache
	 */
	private $_cache;

	/**
	 * @var array
	 */
	private $_configs = array();

	/**
	 * @param string $path
	 */
	public function __construct($path)
	{
		ini_set('display_errors', true);

		$this->_root = realpath($path);

		if (!$this->_root || !is_dir($this->_root)) {
			throw new \InvalidArgumentException('Directory "' . $path . '" not found');
		}

		require(__DIR__ . '/../trace.php');

		$this->_cli = PHP_SAPI === 'cli';
		$this->_env = file_get_contents($this->path('env'));
		$this->_dev = $this->_env !== 'prod';

		$this->_initClassLoader();
		$this->_initErrorHandler();
		$this->_initCache();
		$this->_init();
	}

	/**
	 * @return bool
	 */
	public function dev()
	{
		return $this->_dev;
	}

	/**
	 * @param string $relativePath
	 *
	 * @return string
	 */
	public function path($relativePath)
	{
		return $this->_root . '/' . $relativePath;
	}

	/**
	 * @param string $name
	 *
	 * @return Config
	 */
	public function config($name)
	{
		if (!isset($this->_configs[$name])) {
			$file = $this->path('config/' . $this->_env . '/' . $name . '.yml');
			if (!file_exists($file)) {
				$file = $this->path('config/' . $name . '.yml');
			}
			return $this->_configs[$name] = new Config($this->_cache, $file, Config::COMPILER_YAML, $this->_dev, true);
		}

		return $this->_configs[$name];
	}

	/**
	 * @param string       $name
	 * @param string|array $msg
	 */
	public function log($name, $msg)
	{
		$file = $this->path('log/' . str_replace('{date}', date('Ymd'), $name) . '.log');

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

		} else {
			throw new \RuntimeException('Can not write log file "' . $file . '"');
		}
	}

	protected function _init()
	{
	}

	private function _initClassLoader()
	{
		require(__DIR__ . '/ClassLoader.php');
		ClassLoader::init();
	}

	private function _initErrorHandler()
	{
		$handler = ErrorHandler::init(true, $this->path('log/error-{date}.log'));
		if ($this->_env == 'prod') {
			$handler->setDisplayErrors(false);
		}
	}

	private function _initCache()
	{
		$this->_cache = new FileCache($this->path('cache'));
	}
}