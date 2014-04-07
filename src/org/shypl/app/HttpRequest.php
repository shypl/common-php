<?php
namespace org\shypl\app;

class HttpRequest
{
	/**
	 * @var string
	 */
	private $_method;
	/**
	 * @var string
	 */
	private $_scheme;
	/**
	 * @var string
	 */
	private $_host;
	/**
	 * @var string
	 */
	private $_path;
	/**
	 * @var array
	 */
	private $_pathParts;
	/**
	 * @var string
	 */
	private $_query;
	/**
	 * @var string
	 */
	private $_rootPath;
	/**
	 * @var int
	 */
	private $_rootIndex;
	/**
	 * @var array
	 */
	private $_params = array();
	/**
	 * @var array
	 */
	private $_cookies = array();

	/**
	 * @throws RuntimeException
	 */
	public function __construct()
	{
		// method
		$this->_method = $_SERVER['REQUEST_METHOD'];

		// scheme
		$this->_scheme = isset($_SERVER['HTTP_SCHEME'])
			? $_SERVER['HTTP_SCHEME']
			: (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 443 == $_SERVER['SERVER_PORT'])
				? 'https' : 'http');

		// host
		$this->_host = $_SERVER['HTTP_HOST'];

		// path
		$this->_path = trim(parse_url(preg_replace('/\/\/+/', '/', $_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
		$this->_pathParts = empty($this->_path) ? array() : explode('/', $this->_path);

		// query
		$this->_query = $_SERVER['QUERY_STRING'];

		// root
		$tmp = $_SERVER['SCRIPT_NAME'];
		$this->_rootPath = substr($tmp, 0, strrpos($tmp, '/'));
		$this->_rootIndex = substr_count($this->_rootPath, '/');

		// params
		foreach ($_GET as $name => $value) {
			$this->_params[$name] = $value;
		}
		foreach ($_POST as $name => $value) {
			$this->_params[$name] = $value;
		}

		// cookies
		foreach ($_COOKIE as $key => $value) {
			$this->_cookies[$key] = $value;
		}
	}

	/**
	 * @return string
	 */
	public function method()
	{
		return $this->_method;
	}

	/**
	 * @return string
	 */
	public function scheme()
	{
		return $this->_scheme;
	}

	/**
	 * @return string
	 */
	public function host()
	{
		return $this->_host;
	}

	/**
	 * @param int  $toPart
	 * @param bool $atRoot
	 *
	 * @return string
	 */
	public function path($toPart = 0, $atRoot = true)
	{
		if ($toPart == 0 && $atRoot) {
			return $this->_rootPath;
		}

		$path = $atRoot ? $this->_rootPath : '';
		$from = $atRoot ? $this->_rootIndex : 0;
		$to = $from + $toPart;

		for ($i = $from; $i <= $to; ++$i) {
			$path .= '/' . $this->_pathParts[$i];
		}

		return $path;
	}

	/**
	 * @param int  $part
	 * @param bool $atRoot
	 *
	 * @return string
	 */
	public function pathPart($part, $atRoot = true)
	{
		if ($atRoot) {
			$part += $this->_rootIndex;
		}

		return isset($this->_pathParts[$part]) ? $this->_pathParts[$part] : null;
	}

	/**
	 * @return string
	 */
	public function query()
	{
		return $this->_query;
	}

	/**
	 * @return bool
	 */
	public function isPost()
	{
		return $this->_method === 'POST';
	}

	/**
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function param($name, $default = null)
	{
		return isset($this->_params[$name]) ? $this->_params[$name] : $default;
	}

	/**
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function cookie($name, $default = null)
	{
		return isset($this->_cookies[$name]) ? $this->_cookies[$name] : $default;
	}
}