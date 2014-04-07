<?php
namespace org\shypl\app;

class HttpRequest
{
	/**
	 * @var string
	 */
	private $method;
	/**
	 * @var string
	 */
	private $scheme;
	/**
	 * @var string
	 */
	private $host;
	/**
	 * @var string
	 */
	private $path;
	/**
	 * @var array
	 */
	private $pathParts;
	/**
	 * @var string
	 */
	private $query;
	/**
	 * @var string
	 */
	private $rootPath;
	/**
	 * @var int
	 */
	private $rootIndex;
	/**
	 * @var array
	 */
	private $params = array();
	/**
	 * @var array
	 */
	private $cookies = array();

	/**
	 *
	 */
	public function __construct()
	{
		// method
		$this->method = $_SERVER['REQUEST_METHOD'];

		// scheme
		$this->scheme = isset($_SERVER['HTTP_SCHEME'])
			? $_SERVER['HTTP_SCHEME']
			: (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 443 == $_SERVER['SERVER_PORT'])
				? 'https' : 'http');

		// host
		$this->host = $_SERVER['HTTP_HOST'];

		// path
		$this->path = trim(parse_url(preg_replace('/\/\/+/', '/', $_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
		$this->pathParts = empty($this->path) ? array() : explode('/', $this->path);

		// query
		$this->query = $_SERVER['QUERY_STRING'];

		// root
		$tmp = $_SERVER['SCRIPT_NAME'];
		$this->rootPath = substr($tmp, 0, strrpos($tmp, '/'));
		$this->rootIndex = substr_count($this->rootPath, '/');

		// params
		foreach ($_GET as $name => $value) {
			$this->params[$name] = $value;
		}
		foreach ($_POST as $name => $value) {
			$this->params[$name] = $value;
		}

		// cookies
		foreach ($_COOKIE as $key => $value) {
			$this->cookies[$key] = $value;
		}
	}

	/**
	 * @return string
	 */
	public function method()
	{
		return $this->method;
	}

	/**
	 * @return string
	 */
	public function scheme()
	{
		return $this->scheme;
	}

	/**
	 * @return string
	 */
	public function host()
	{
		return $this->host;
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
			return $this->rootPath;
		}

		$path = $atRoot ? $this->rootPath : '';
		$from = $atRoot ? $this->rootIndex : 0;
		$to = $from + $toPart;

		for ($i = $from; $i <= $to; ++$i) {
			$path .= '/' . $this->pathParts[$i];
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
			$part += $this->rootIndex;
		}

		return isset($this->pathParts[$part]) ? $this->pathParts[$part] : null;
	}

	/**
	 * @return string
	 */
	public function query()
	{
		return $this->query;
	}

	/**
	 * @return bool
	 */
	public function isPost()
	{
		return $this->method === 'POST';
	}

	/**
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function param($name, $default = null)
	{
		return isset($this->params[$name]) ? $this->params[$name] : $default;
	}

	/**
	 * @return array
	 */
	public function params()
	{
		return $this->params;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasParam($name)
	{
		return isset($this->params[$name]);
	}

	/**
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function cookie($name, $default = null)
	{
		return isset($this->cookies[$name]) ? $this->cookies[$name] : $default;
	}
}