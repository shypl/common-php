<?php
namespace org\shypl\common\http;

class HttpMessage
{
	/**
	 * @var HttpHeader[]
	 */
	private $_headers = array();

	/**
	 * @param HttpHeader $header
	 */
	public function addHeader(HttpHeader $header)
	{
		$this->_headers[] = $header;
	}
}