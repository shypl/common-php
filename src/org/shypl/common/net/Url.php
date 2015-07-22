<?php
namespace org\shypl\common\net;

class Url {
	private $scheme;
	private $host;
	private $port;
	private $user;
	private $password;
	private $path;
	private $query;
	private $fragment;

	/**
	 * @param string $value
	 */
	public function __construct($value) {
		$value = parse_url($value);

		$this->scheme = isset($value['scheme']) ? $value['scheme'] : null;
		$this->host = isset($value['host']) ? $value['host'] : null;
		$this->port = isset($value['port']) ? (int)$value['port'] : -1;
		$this->user = isset($value['user']) ? $value['user'] : null;
		$this->password = isset($value['pass']) ? $value['pass'] : null;
		$this->path = isset($value['path']) ? $value['path'] : null;
		$this->query = isset($value['query']) ? $value['query'] : null;
		$this->fragment = isset($value['fragment']) ? $value['fragment'] : null;
	}

	/**
	 * @return string
	 */
	public function getScheme() {
		return $this->scheme;
	}

	/**
	 * @return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @return string
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return null
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * @return string
	 */
	public function getFragment() {
		return $this->fragment;
	}
}