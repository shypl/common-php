<?php
namespace org\shypl\common\net;

class HttpRequest {
	const METHOD_GET = 'GET';
	const METHOD_HEAD = 'HEAD';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_PATCH = 'PATCH';
	const METHOD_DELETE = 'DELETE';
	const METHOD_TRACE = 'TRACE';
	const METHOD_CONNECT = 'CONNECT';

	/**
	 * @return HttpRequest
	 */
	public static function factoryFromGlobals() {
		$url = new Url(
			(isset($_SERVER['HTTP_SCHEME'])
				? $_SERVER['HTTP_SCHEME']
				: (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 443 == $_SERVER['SERVER_PORT']) ? 'https' : 'http'))
			. '://'
			. $_SERVER['HTTP_HOST']
			. $_SERVER['REQUEST_URI']);

		$parameters = [];
		parse_str($url->getQuery(), $parameters);

		foreach ($_POST as $k => $v) {
			$parameters[$k] = $v;
		}

		return new HttpRequest($_SERVER['REQUEST_METHOD'], $url, getallheaders(), $_COOKIE, $parameters);
	}

	private $method;
	private $url;
	private $headers;
	private $cookies;
	private $parameters;

	/**
	 * @param string $method
	 * @param Url    $url
	 * @param array  $headers
	 * @param array  $cookies
	 * @param array  $parameters
	 */
	public function __construct($method, Url $url, array $headers, array $cookies, array $parameters) {
		$this->method = $method;
		$this->url = $url;
		$this->headers = $headers;
		$this->cookies = $cookies;
		$this->parameters = $parameters;
	}

	/**
	 * @return string
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * @return Url
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * @return array
	 */
	public function getCookies() {
		return $this->cookies;
	}

	/**
	 * @return array
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function getHeader($name) {
		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function getCookie($name) {
		return isset($this->cookies[$name]) ? $this->cookies[$name] : null;
	}

	/**
	 * @param string $name
	 *
	 * @return string|array
	 */
	public function getParameter($name) {
		return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
	}

	/**
	 * @param string $method
	 *
	 * @return bool
	 */
	public function isMethod($method) {
		return $this->method === $method;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasHeader($name) {
		return isset($this->headers[$name]);
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasCookie($name) {
		return isset($this->cookies[$name]);
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasParameter($name) {
		return isset($this->parameters[$name]);
	}
}