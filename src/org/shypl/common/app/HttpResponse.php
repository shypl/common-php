<?php
namespace org\shypl\common\app;

class HttpResponse
{
	const TYPE_TEXT = 'text/plain';
	const TYPE_HTML = 'text/html';
	const TYPE_XML = 'application/xml';
	const TYPE_JSON = 'application/json';

	/**
	 * @param string $type
	 * @param mixed  $content
	 * @param int    $code
	 * @param string $encoding
	 *
	 * @return HttpResponse
	 */
	static public function factory($type, $content = null, $code = 200, $encoding = 'UTF-8')
	{
		if ($type === self::TYPE_JSON) {
			$content = json_encode($content);
		}

		$response = new HttpResponse($content, $code);
		$response->setHeader('Content-Type', $type . '; charset=' . $encoding);
		return $response;
	}

	/**
	 * @param string $name
	 * @param string $value
	 */
	public function setHeader($name, $value)
	{
		$this->headers[$name] = $value;
	}

	###

	/**
	 * @param string $url
	 *
	 * @return HttpResponse
	 */
	static public function factoryRedirect($url)
	{
		$response = new HttpResponse(null, 303);
		$response->setHeader('Location', $url);
		return $response;
	}

	/**
	 * @var int
	 */
	private $code;
	/**
	 * @var array
	 */
	private $headers = array();
	/**
	 * @var array
	 */
	private $cookies = array();
	/**
	 * @var string
	 */
	private $body;

	/**
	 * @param string $body
	 * @param int    $code
	 */
	public function __construct($body = null, $code = 200)
	{
		$this->body = $body;
		$this->code = $code;
	}

	/**
	 * @param int $code
	 */
	public function setCode($code)
	{
		$this->code = $code;
	}

	/**
	 * @return int
	 */
	public function code()
	{
		return $this->code;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @param int    $expire
	 * @param string $path
	 * @param string $domain
	 * @param bool   $secure
	 * @param bool   $httpOnly
	 */
	public function setCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = false, $httpOnly = false)
	{
		$this->cookies[$name] = array($value, $expire, $path, $domain, $secure, $httpOnly);
	}

	/**
	 * @param string $value
	 */
	public function setBody($value)
	{
		$this->body = $value;
	}

	/**
	 * @return string
	 */
	public function body()
	{
		return $this->body;
	}

	/**
	 */
	public function send()
	{
		header('HTTP/1.1 ' . $this->code);

		foreach ($this->headers as $name => $value) {
			header($name . ': ' . $value, true);
		}

		foreach ($this->cookies as $name => $cookie) {
			setcookie($name, $cookie[0], $cookie[1], $cookie[2], $cookie[3], $cookie[4], $cookie[5]);
		}

		if ($this->body !== null) {
			echo $this->body;
		}

		return $this;
	}
}