<?php
namespace org\shypl\redis;

class DisconnectException extends RedisException
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct('Disconnecting from redis server');
	}
}
