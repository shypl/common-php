<?php
namespace org\shypl\common\http;

class HttpRequest extends HttpMessage
{
	/**
	 * @var string
	 */
	protected $_method;

	/**
	 * @var string
	 */
	protected $_protocol;

	/**
	 * @var string
	 */
	protected $_host;

	/**
	 * @var string
	 */
	protected $_path;

	/**
	 * @var array
	 */
	protected $_params = array();

	/**
	 * @param string $url
	 * @param string $method
	 */
	public function __construct($url, $method = 'GET')
	{
		$url = parse_url($url);

		$this->_protocol = $url['scheme'];
		$this->_host     = $url['host'];
		$this->_path     = isset($url['path']) ? preg_replace('#//+#', '/', $url['path']) : '';

		if (isset($url['query'])) {
			parse_str($url['query'], $this->_params);
		}

		$this->_method = $method;
	}

	/**
	 * @return string
	 */
	public function host()
	{
		return $this->_host;
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
	public function path()
	{
		return $this->_path;
	}

	/**
	 * @return string
	 */
	public function protocol()
	{
		return $this->_protocol;
	}

	/**
	 * @param string $name
	 * @param string $default
	 *
	 * @return string|array
	 */
	public function param($name, $default = null)
	{
		return isset($this->_params[$name]) ? $this->_params[$name] : $default;
	}

	/**
	 * @return array
	 */
	public function params()
	{
		return $this->_params;
	}
}