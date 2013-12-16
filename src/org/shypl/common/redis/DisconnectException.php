<?php
namespace org\shypl\common\redis;

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
