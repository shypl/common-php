<?php
namespace org\shypl\cache;

class FileCache extends Cache
{

	/**
	 * @var string
	 */
	private $_path;

	/**
	 * @param string $path
	 *
	 * @throws \RuntimeException
	 */
	public function __construct($path)
	{
		if (!is_dir($path) || !is_writable($path)) {
			throw new \RuntimeException('Cache directory "'.$path.'" not writable');
		}
		$this->_path = $path;
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	private function _getFilePath($key)
	{
		if (substr($key, 0, 5) !== 'link.') {
			$key = md5($key);
		}
		return $this->_path . '/' . $key. '.cache';
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		clearstatcache();

		return file_exists($this->_getFilePath($key));
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		clearstatcache();

		$file = $this->_getFilePath($key);
		return file_exists($file)
			? self::unserialize(file_get_contents($file))
			: null;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function set($key, $value)
	{
		clearstatcache();

		$path = $this->_getFilePath($key);
		$new  = !file_exists($path);
		$file = fopen($path, 'c');

		if (flock($file, LOCK_EX)) {
			ftruncate($file, 0);
			fwrite($file, self::serialize($value));
			fflush($file);
			if ($new) {
				chmod($path, 0664);
			}
			flock($file, LOCK_UN);
		}

		fclose($file);
	}
}