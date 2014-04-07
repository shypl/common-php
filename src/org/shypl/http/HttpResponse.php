<?php
namespace org\shypl\http;

class HttpResponse extends HttpMessage
{
	/**
	 * @param string $content
	 * @param string $encoding
	 *
	 * @return HttpResponse
	 */
	static public function factoryText($content = null, $encoding = 'UTF-8')
	{
		$response = new HttpResponse();
		$response->addHeader(new HttpHeader(HttpHeader::CONTENT_TYPE, 'text/plain; charset=' . $encoding));
		$response->setBody($content);
		return $response;
	}

	/**
	 * @param string $content
	 * @param string $encoding
	 *
	 * @return HttpResponse
	 */
	static public function factoryHtml($content = null, $encoding = 'UTF-8')
	{

		$response = new HttpResponse();
		$response->addHeader(new HttpHeader(HttpHeader::CONTENT_TYPE, 'text/html; charset=' . $encoding));
		$response->setBody($content);
		return $response;
	}

	/**
	 * @param string $content
	 * @param string $encoding
	 *
	 * @return HttpResponse
	 */
	static public function factoryXml($content = null, $encoding = 'UTF-8')
	{

		$response = new HttpResponse();
		$response->addHeader(new HttpHeader(HttpHeader::CONTENT_TYPE, 'application/xml; charset=' . $encoding));
		$response->setBody($content);
		return $response;
	}

	/**
	 * @param string $content
	 * @param string $encoding
	 *
	 * @return HttpResponse
	 */
	static public function factoryJson($content = null, $encoding = 'UTF-8')
	{

		$response = new HttpResponse();
		$response->addHeader(new HttpHeader(HttpHeader::CONTENT_TYPE, 'application/json; charset=' . $encoding));
		$response->setBody($content);
		return $response;
	}

	/**
	 * @param int    $code
	 * @param string $body
	 *
	 * @return HttpResponse
	 */
	static public function factoryByCode($code, $body = null)
	{
		$response = new HttpResponse();
		$response->setCode($code);
		$response->setBody($body);
		return $response;
	}

	/**
	 * @param string $url
	 *
	 * @return HttpResponse
	 */
	static public function factoryRedirect($url)
	{
		$response = new HttpResponse();
		$response->setCode(301);
		$response->addHeader(new HttpHeader(HttpHeader::LOCATION, $url));
		return $response;
	}

	###

	/**
	 * @var int
	 */
	private $_code = 200;

	/**
	 * @var HttpHeader[]
	 */
	private $_headers = array();

//	/**
//	 * @var array
//	 */
//	private $_cookies = array();

	/**
	 * @var string
	 */
	private $_body;

	/**
	 * @param int $code
	 */
	public function setCode($code)
	{
		$this->_code = $code;
	}

//	/**
//	 * @param string $name
//	 * @param string $value
//	 * @param int    $expire
//	 * @param string $path
//	 * @param string $domain
//	 * @param bool   $secure
//	 * @param bool   $httpOnly
//	 */
//	public function setCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = false, $httpOnly = false)
//	{
//		$this->_cookies[$name] = array($value, $expire, $path, $domain, $secure, $httpOnly);
//	}

	/**
	 * @param string $value
	 */
	public function setBody($value)
	{
		$this->_body = $value;
	}

	/**
	 * @return string
	 */
	public function getBody()
	{
		return $this->_body;
	}

	/**
	 */
	public function send()
	{
		header('HTTP/1.1 ' . $this->_code);

		foreach ($this->_headers as $header) {
			header($header->toString());
		}

//		foreach ($this->_cookies as $name => $cookie) {
//			setcookie($name, $cookie[0], $cookie[1], $cookie[2], $cookie[3], $cookie[4], $cookie[5]);
//		}

		if ($this->_body !== null) {
			echo $this->_body;
		}

		return $this;
	}
}