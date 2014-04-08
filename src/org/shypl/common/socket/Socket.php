<?php
namespace org\shypl\common\socket;

class Socket
{
	const PROTOCOL_IP = 'ip';
	const PROTOCOL_ICMP = 'icmp';
	const PROTOCOL_UDP = 'udp';
	const PROTOCOL_TCP = 'tcp';
	/**
	 * @var resource resource
	 */
	private $res;

	/**
	 * @param int    $domain
	 * @param int    $type
	 * @param string $protocol
	 */
	public function __construct($domain, $type, $protocol)
	{
		try {
			$this->res = socket_create($domain, $type, $this->defineProtocol($protocol));
		}
		catch (\ErrorException $e) {
			throw new SocketException("Can not create socket", 0, $e);
		}
	}

	/**
	 * @param string $name
	 *
	 * @return int
	 * @throws SocketException
	 */
	private function defineProtocol($name)
	{
		$protocol = getprotobyname($name);
		if ($protocol === false) {
			throw new SocketException('Undefined protocol ' . $name);
		}
		return $protocol;
	}

	/**
	 * @param string $address
	 * @param int    $port
	 */
	public function connect($address, $port = 0)
	{
		try {
			$this->checkSocketError(socket_connect($this->res, $address, $port));
		}
		catch (\ErrorException $e) {
			throw new SocketException("Can not connect socket", 0, $e);
		}
	}

	/**
	 * @param $result
	 *
	 * @throws SocketException
	 */
	private function checkSocketError($result)
	{
		if ($result === false) {
			throw new SocketException(socket_strerror(socket_last_error($this->res)));
		}
	}

	public function close()
	{
		socket_close($this->res);
	}

	/**
	 * @param int $byte
	 */
	public function writeByte($byte)
	{
		$this->write(pack('c', $byte));
	}

	/**
	 * @param $string
	 */
	public function write($string)
	{
		try {
			$size = mb_strlen($string, 'ASCII');

			do {
				$sent = socket_write($this->res, $string);
				$this->checkSocketError($sent);
				if ($sent < $size) {
					$string = mb_substr($string, $sent, null, 'ASCII');
					$size -= $sent;
				}
				else {
					break;
				}
			}
			while (true);
		}
		catch (\ErrorException $e) {
			throw new SocketException("Can not write to socket", 0, $e);
		}
	}

	/**
	 * @param int $length
	 * @param int $type
	 *
	 * @return string
	 * @throws SocketException
	 */
	public function read($length, $type = PHP_BINARY_READ)
	{
		try {
			$string = socket_read($this->res, $length, $type);
			$this->checkSocketError($string);
			return $string;
		}
		catch (\ErrorException $e) {
			throw new SocketException("Can not read from socket", 0, $e);
		}
	}
}