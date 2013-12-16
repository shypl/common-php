<?php
namespace org\shypl\common\sys;

use org\shypl\common\http\HttpHeader;
use org\shypl\common\http\HttpRequest;
use RuntimeException;

class ServerHttpRequest extends HttpRequest
{
	/**
	 * @var ServerHttpRequest
	 */
	static private $_instance;

	/**
	 * @return ServerHttpRequest
	 */
	static public function instance()
	{
		if (self::$_instance) {
			return self::$_instance;
		}

		new ServerHttpRequest();

		return self::$_instance;
	}

	/**
	 * @var string
	 */
	private $_rootPath;

	/**
	 * @var int
	 */
	private $_rootPathOffset;

	/**
	 * @var array
	 */
	private $_pathParts;

	/**
	 * @throws RuntimeException
	 */
	public function __construct()
	{
		if (self::$_instance) {
			throw new RuntimeException('ServerHttpRequest already initialized');
		}

		if (PHP_SAPI === 'cli') {
			throw new RuntimeException("Method not available in CLI mode");
		}

		parent::__construct(
			(stripos($_SERVER['SERVER_PROTOCOL'], 'https') === false ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
			$_SERVER['REQUEST_METHOD']);

		self::$_instance = $this;

		foreach ($_SERVER as $name => $value) {
			if (strpos($name, 'HTTP') === 0) {
				$this->addHeader(new HttpHeader(str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($name, 5))))), $value));
			}
		}

		$path = $this->_path;

		$this->_pathParts = $path === '' ? array() : explode('/', trim($path, '/'));

		$tmp = $_SERVER['SCRIPT_NAME'];
		$tmp = substr($tmp, 0, strrpos($tmp, '/'));
		$this->_rootPathOffset = substr_count($tmp, '/');
		$this->_rootPath = '/' . trim($tmp, '/');

		foreach ($_POST as $key => $value) {
			$this->_params[$key] = $value;
		}
	}

	/**
	 * @param int  $index
	 * @param bool $atRoot
	 *
	 * @return string
	 */
	public function pathPart($index, $atRoot = true)
	{
		if ($atRoot) {
			$index += $this->_rootPathOffset;
		}

		return isset($this->_pathParts[$index]) ? $this->_pathParts[$index] : null;
	}
}