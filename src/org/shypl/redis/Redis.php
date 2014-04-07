<?php
namespace org\shypl\redis;

use org\shypl\cache\Cache;
use org\shypl\util\NumberUtils;

class Redis extends Cache
{
	const PARAM_SOCKET = 'socket';
	const PARAM_HOST = 'host';
	const PARAM_PORT = 'port';
	const PARAM_DB = 'db';
	const PARAM_TIMEOUT = 'timeout';
	const PARAM_CLASS = 'class';
	const EOL = "\r\n";
	/**
	 * @var string
	 */
	private $_unixSocket;
	/**
	 * @var string
	 */
	private $_host = 'localhost';
	/**
	 * @var int
	 */
	private $_port = 6379;
	/**
	 * @var int
	 */
	private $_db = 0;
	/**
	 * @var resource
	 */
	private $_socket;
	/**
	 * @var bool
	 */
	private $_connected;

	/**
	 * @param array $config
	 */
	public function __construct(array $config = array())
	{
		if (isset($config[self::PARAM_SOCKET])) {
			$this->_unixSocket = $config[self::PARAM_SOCKET];
		}

		if (isset($config[self::PARAM_HOST])) {
			$this->_host = $config[self::PARAM_HOST];
		}

		if (isset($config[self::PARAM_PORT])) {
			$this->_port = $config[self::PARAM_PORT];
		}

		if (isset($config[self::PARAM_DB])) {
			$this->_db = $config[self::PARAM_DB];
		}
	}

	public function connect()
	{
		if (!$this->_connected) {

			$errorNumber = null;
			$errorMessage = null;

			if ($this->_unixSocket) {
				$socket = 'unix://' . $this->_unixSocket;
			}
			else {
				$socket = 'tcp://' . $this->_host . ':' . $this->_port;
			}

			$this->_socket = stream_socket_client($socket, $errorNumber, $errorMessage);

			if (!$this->_socket) {
				throw new RedisException($errorMessage . ' (' . $errorNumber . ')');
			}

			if (!stream_set_timeout($this->_socket, -1)) {
				throw new RedisException('Can not set timeout');
			}

			$this->_connected = true;

			if ($this->_db !== 0) {
				$this->select($this->_db);
			}
		}
	}

	public function disconnect()
	{
		if ($this->_connected) {
			fclose($this->_socket);

			$this->_socket = null;
			$this->_connected = false;
		}
	}

	/**
	 * @param array $args
	 *
	 * @return void
	 */
	private function _write(array $args)
	{
		$command = '*' . count($args) . self::EOL;
		foreach ($args as $arg) {
			$command .= '$' . strlen($arg) . self::EOL . $arg . self::EOL;
		}

		$this->connect();

		for ($written = 0, $len = strlen($command); $written < $len; $written += $write) {
			$write = fwrite($this->_socket, substr($command, $written));
			if ($write === false) {
				throw new RedisException('Failed to write entire command to stream');
			}
		}
	}

	/**
	 * @return mixed
	 */
	public function _read()
	{
		$reply = trim(fgets($this->_socket));

		switch (substr($reply, 0, 1)) {
			// error
			case '-':
				throw new RedisException(substr($reply, 1));

			// inline
			case '+':
				return substr($reply, 1);

			// bulk
			case '$':
				$size = NumberUtils::parseInt(substr($reply, 1));

				if ($size < 0) {
					return null;
				}

				$read = 0;
				$result = '';

				if ($size > 0) {
					do {
						$blockSize = $size - $read;
						if ($blockSize > 1024) {
							$blockSize = 1024;
						}
						$result .= fread($this->_socket, $blockSize);
						$read += $blockSize;
					}
					while ($read < $size);
				}
				fread($this->_socket, 2);

				return $result;

			// multi-bulk
			case '*':
				$count = NumberUtils::parseInt(substr($reply, 1));
				if ($count < 0) {
					return null;
				}

				$result = array();

				for ($i = 0; $i < $count; $i++) {
					$result[] = $this->_read();
				}

				return $result;

			// int
			case ':':
				return NumberUtils::parseInt(substr($reply, 1));

			case '':
				$this->disconnect();
				throw new DisconnectException();

			// undefined
			default:
				throw new RedisException('Undefined server response: "' . $reply . '"', 1);
		}
	}

	/**
	 * @param array|string $command
	 *
	 * @return mixed
	 */
	public function execute($command)
	{
		if (!is_array($command)) {
			$command = func_get_args();
		}

		$this->_write($command);

		try {
			$result = $this->_read();
		}
		catch (DisconnectException $e) {
			$this->connect();
			$result = $this->execute($command);
		}

		return $result;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		return $this->exists($key);
	}

	# keys

	/**
	 * Delete a key
	 *
	 * Removes the specified keys. A key is ignored if it does not exist.
	 *
	 * @param string|array $key
	 *
	 * @return int
	 */
	public function del($key)
	{
		if (is_array($key)) {
			if (empty($key)) {
				return 0;
			}
			array_unshift($key, 'DEL');
			return $this->execute($key);
		}
		return $this->execute('DEL', $key);
	}

	/**
	 * Determine if a key exists
	 *
	 * @param string $key
	 *
	 * @return bool True - if the key exists.
	 *              False - if the key does not exist
	 */
	public function exists($key)
	{
		return (bool)$this->execute('EXISTS', $key);
	}

	/**
	 * Set a key's time to live in seconds
	 *
	 * Set a timeout on $key. After the timeout has expired, the key will automatically be deleted. A key with an
	 * associated timeout is said to be volatile in Redis terminology.
	 *
	 * If $key is updated before the timeout has expired, then the timeout is removed as if the PERSIST command was
	 * invoked on $key.
	 *
	 * @param string $key
	 * @param int    $seconds
	 *
	 * @return bool True - if the timeout was set.
	 *              False - if $key does not exist or the timeout could not be set.
	 */
	public function expire($key, $seconds)
	{
		return (bool)$this->execute('EXPIRE', $key, $seconds);
	}

	/**
	 * Set the expiration for a key as a UNIX timestamp
	 *
	 * Set a timeout on $key. After the timeout has expired, the key will automatically be deleted. A key with an
	 * associated timeout is said to be volatile in Redis terminology.
	 *
	 * EXPIREAT has the same effect and semantic as EXPIRE, but instead of specifying the number of seconds
	 * representing the TTL (time to live), it takes an absolute UNIX timestamp (seconds since January 1, 1970).
	 *
	 * As in the case of EXPIRE command, if $key is updated before the timeout has expired, then the timeout is
	 * removed as if the PERSIST command was invoked on $key.
	 *
	 * @param string $key
	 * @param int    $timestamp
	 *
	 * @return bool True - if the timeout was set.
	 *              False - if $key does not exist or the timeout could not be set.
	 */
	public function expireat($key, $timestamp)
	{
		return (bool)$this->execute('EXPIREAT', $key, $timestamp);
	}

	/**
	 * Find all keys matching the given pattern
	 *
	 * Returns all keys matching $pattern.
	 *
	 * Supported glob-style patterns:
	 * - 'h?llo'
	 * - 'h*llo'
	 * - 'h[ae]llo'
	 *
	 * Use '\' to escape special characters if you want to match them verbatim.
	 *
	 * @param string $pattern
	 *
	 * @return array
	 */
	public function keys($pattern)
	{
		return $this->execute('KEYS', $pattern);
	}

	/**
	 * Remove the expiration from a key
	 *
	 * @param string $key
	 *
	 * @return bool True - if the timeout was removed.
	 *              False - if key does not exist or does not have an associated timeout.
	 */
	public function persist($key)
	{
		return (bool)$this->execute('PERSIST', $key);
	}

	/**
	 * Rename a key
	 *
	 * Renames $key to $newKey. It returns an error when the source and destination names are the same, or when $key
	 * does not exist. If $newKey already exists it is overwritten.
	 *
	 * @param string $key
	 * @param string $newKey
	 */
	public function rename($key, $newKey)
	{
		$this->execute('RENAME', $key, $newKey);
	}

	/**
	 * Sort the elements in a list, set or sorted set
	 *
	 * Returns or stores the elements contained in the list, set or sorted set at $key. By default, sorting is numeric
	 * and elements are compared by their value interpreted as double precision floating point number.
	 *
	 * options:
	 * - by     string          pattern
	 * - limit  int
	 * - offset int
	 * - get    string|array    pattern or an array of patterns
	 * - asc    bool
	 * - alpha  bool
	 * - store  string
	 *
	 * @param string $key
	 * @param array  $options
	 *
	 * @return array
	 */
	public function sort($key, array $options = null)
	{
		$command = array('SORT', $key);

		if (isset($options['by'])) {
			$command[] = 'BY';
			$command[] = $options['by'];
		}

		if (isset($options['limit']) || isset($options['offset'])) {
			$command[] = 'LIMIT';
			$command[] = isset($options['offset']) ? (int)$options['offset'] : 0;
			$command[] = isset($options['limit']) ? (int)$options['limit'] : 0;
		}

		if (isset($options['get'])) {
			if (is_array($options['get'])) {
				foreach ($options['get'] as $get) {
					$command[] = 'GET';
					$command[] = $get;
				}
			}
			else {
				$command[] = 'GET';
				$command[] = $options['get'];
			}
		}

		if (isset($options['asc'])) {
			$command[] = $options['asc'] ? 'ASC' : 'DESC';
		}

		if (isset($options['alpha']) && $options['alpha']) {
			$command[] = 'ALPHA';
		}

		if (isset($options['store'])) {
			$command[] = 'STORE';
			$command[] = $options['store'];
		}

		return $this->execute($command);
	}

	/**
	 * Get the time to live for a key
	 *
	 * Returns the remaining time to live of a key that has a timeout. This introspection capability allows a Redis
	 * client to check how many seconds a given key will continue to be part of the data set.
	 *
	 * @param string $key
	 *
	 * @return int TTL in seconds or -1 when key does not exist or does not have a timeout.
	 */
	public function ttl($key)
	{
		return $this->execute('TTL', $key);
	}

	# strings

	/**
	 * Decrement the integer value of a key by one
	 *
	 * @param string $key
	 *
	 * @return int The value of key after the increment.
	 */
	public function decr($key)
	{
		return $this->execute('DECR', $key);
	}

	/**
	 * Get the value of a key
	 *
	 * Get the value of $key. If the key does not exist the NULL is returned. An error is throw if the value stored
	 * at $key is not a scalar, because GET only handles scalar values.
	 *
	 * @param string $key
	 *
	 * @return mixed The value of key, or NULL when key does not exist.
	 */
	public function get($key)
	{
		return $this->execute('GET', $key);
	}

	/**
	 * Set the value of a key
	 *
	 * Set $key to hold the scalar $value. If $key already holds a value, it is overwritten, regardless of its type.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function set($key, $value)
	{
		$this->execute('SET', $key, $value);
	}

	/**
	 * Set the value and expiration of a key
	 *
	 * Set key to hold the string value and set key to timeout after a given number of seconds.
	 *
	 * @param string $key
	 * @param int    $seconds
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setex($key, $seconds, $value)
	{
		$this->execute('SETEX', $key, $seconds, $value);
	}

	/**
	 * Set the value of a key, only if the key does not exist
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return bool
	 */
	public function setnx($key, $value)
	{
		return (bool)$this->execute('SETNX', $key, $value);
	}

	/**
	 * Increment the integer value of a key by one
	 *
	 * @param string $key
	 *
	 * @return int The value of key after the increment.
	 */
	public function incr($key)
	{
		return $this->execute('INCR', $key);
	}

	/**
	 * Increment the integer value of a key by the given amount
	 *
	 * @param string $key
	 * @param int    $increment
	 *
	 * @return int The value of key after the increment.
	 */
	public function incrby($key, $increment)
	{
		return $this->execute('INCRBY', $key, $increment);
	}

	/**
	 * @param array $keys
	 * @param bool  $asMap
	 *
	 * @return array|mixed
	 */
	public function mget(array $keys, $asMap = false)
	{
		$command = $keys;
		array_unshift($command, 'MGET');
		$values = $this->execute($command);
		return $asMap ? array_combine($keys, $values) : $values;
	}

	# hashes

	/**
	 * Delete one or more hash fields
	 *
	 * Removes the specified fields from the hash stored at $key. Specified fields that do not exist within this hash
	 * are ignored. If $key does not exist, it is treated as an empty hash and this command returns 0.
	 *
	 * @param string       $key
	 * @param string|array $fields
	 *
	 * @return int The number of fields that were removed from the hash, not including specified but non existing
	 *             fields.
	 */
	public function hdel($key, $fields)
	{
		$command = array('HDEL', $key);

		if (is_array($fields)) {
			$command = array_merge($command, $fields);
		}
		else {
			$command[] = $fields;
		}

		return $this->execute($command);
	}

	/**
	 * Determine if a hash field exists
	 *
	 * @param string $key
	 * @param string $field
	 *
	 * @return bool True - if the hash contains $field.
	 *              False - if the hash does not contain $field, or $key does not exist.
	 */
	public function hexists($key, $field)
	{
		return (bool)$this->execute('HEXISTS', $key, $field);
	}

	/**
	 * Get the value of a hash field
	 *
	 * Returns the value associated with $field in the hash stored at $key.
	 *
	 * @param string $key
	 * @param string $field
	 *
	 * @return int|string The value associated with field, or NULL when field is not present in the hash or key does not
	 *                exist.
	 */
	public function hget($key, $field)
	{
		return $this->execute('HGET', $key, $field);
	}

	/**
	 * Get all the fields and values in a hash
	 *
	 * Returns all fields and values, as assoc array, of the hash stored at $key.
	 *
	 * @param string $key
	 *
	 * @return array Array of fields and their values stored in the hash, or an empty array when key does not exist.
	 */
	public function hgetall($key)
	{
		$result = array();
		$data = $this->execute('HGETALL', $key);
		while (false !== ($key = current($data))) {
			$result[$key] = next($data);
			next($data);
		}
		return $result;
	}

	/**
	 * Increment the integer value of a hash field by the given number
	 *
	 * Increments the number stored at $field in the hash stored at $key by $increment. If $key does not exist, a new
	 * key holding a hash is created. If $field does not exist the value is set to 0 before the operation is performed.
	 *
	 * @param string $key
	 * @param string $field
	 * @param int    $increment
	 *
	 * @return int The value at field after the increment operation.
	 */
	public function hincrby($key, $field, $increment)
	{
		return $this->execute('HINCRBY', $key, $field, $increment);
	}

	/**
	 * Get all the fields in a hash
	 *
	 * Returns all field names in the hash stored at key.
	 *
	 * @param string $key
	 *
	 * @return array List of fields in the hash, or an empty list when key does not exist.
	 */
	public function hkeys($key)
	{
		return $this->execute('HKEYS', $key);
	}

	/**
	 * Get the number of fields in a hash
	 *
	 * Returns the number of fields contained in the hash stored at $key.
	 *
	 * @param string $key
	 *
	 * @return int Number of fields in the hash, or 0 when $key does not exist.
	 */
	public function hlen($key)
	{
		return $this->execute('HLEN', $key);
	}

	/**
	 * Get the values of all the given hash fields
	 *
	 * Returns the values associated with the specified $fields in the hash stored at key.
	 * For every field that does not exist in the hash, a NULL value is returned. Because a non-existing keys are
	 * treated as empty hashes, running HMGET against a non-existing $key will return a list of NULL values.
	 *
	 * @param string $key
	 * @param array  $fields
	 * @param bool   $asList
	 *
	 * @return array List of values associated with the given $fields, in the same order as they are requested.
	 */
	public function hmget($key, array $fields, $asList = false)
	{
		$command = $fields;
		array_unshift($command, 'HMGET', $key);
		$r = $this->execute($command);
		return $asList ? $r : array_combine($fields, $r);
	}

	/**
	 * Set multiple hash fields to multiple values
	 *
	 * Sets the specified fields to their respective values in the hash stored at $key. This command overwrites any
	 * existing fields in the hash. If $key does not exist, a new key holding a hash is created.
	 *
	 * @param string $key
	 * @param array  $data
	 *
	 * @return void
	 */
	public function hmset($key, array $data)
	{
		$command = array('HMSET', $key);
		foreach ($data as $field => $value) {
			array_push($command, $field, $value);
		}

		$this->execute($command);
	}

	/**
	 * Set the string value of a hash field
	 *
	 * Sets $field in the hash stored at $key to $value. If $key does not exist, a new key holding a hash is created. If
	 * $field already exists in the hash, it is overwritten.
	 *
	 * @param string $key
	 * @param string $field
	 * @param string $value
	 *
	 * @return bool True - if $field is a new field in the hash and $value was set.
	 *              False - if $field already exists in the hash and the value was updated.
	 */
	public function hset($key, $field, $value)
	{
		return (bool)$this->execute('HSET', $key, $field, $value);
	}

	/**
	 * Set the value of a hash field, only if the field does not exist
	 *
	 * Sets $field in the hash stored at $key to $value, only if $field does not yet exist. If $key does not exist,
	 * a new $key holding a hash is created. If $field already exists, this operation has no effect.
	 *
	 * @param string $key
	 * @param string $field
	 * @param string $value
	 *
	 * @return bool True - if $field is a new field in the hash and $value was set.
	 *              False - if $field already exists in the hash and no operation was performed.
	 */
	public function hsetnx($key, $field, $value)
	{
		return (bool)$this->execute('HSETNX', $key, $field, $value);
	}

	/**
	 * Get all the values in a hash
	 *
	 * Returns all values in the hash stored at key.
	 *
	 * @param string $key
	 *
	 * @return array List of values in the hash, or an empty list when key does not exist.
	 */
	public function hvals($key)
	{
		return $this->execute('HVALS', $key);
	}

	# lists

	/**
	 * Get an element from a list by its index
	 *
	 * Returns the element at index $index in the list stored at $key. The index is zero-based, so 0 means the first
	 * element, 1 the second element and so on. Negative indices can be used to designate elements starting at the
	 * tail of the list. Here, -1 means the last element, -2 means the penultimate and so forth.
	 *
	 * When the value at $key is not a list, an error throws.
	 *
	 * @param string $key
	 * @param int    $index
	 *
	 * @return number|string The requested element, or NULL when $index is out of range.
	 */
	public function lindex($key, $index)
	{
		return $this->execute('LINDEX', $key, $index);
	}

	/**
	 * Remove and get the first element in a list
	 *
	 * Removes and returns the first element of the list stored at key.
	 *
	 * @param string $key
	 *
	 * @return string|int|null The value of the first element, or null when key does not exist.
	 */
	public function lpop($key)
	{
		return $this->execute('LPOP', $key);
	}

	/**
	 * Prepend one or multiple values to a list
	 *
	 * Insert all the specified values at the head of the list stored at $key. If $key does not exist, it is created
	 * as empty list before performing the push operations. When $key holds a value that is not a list, an error
	 * is returned.
	 *
	 * It is possible to push multiple elements using a single command call just specifying multiple arguments
	 * at the end of the command. Elements are inserted one after the other to the head of the list, from the leftmost
	 * element to the rightmost element. So for instance the command LPUSH my list a b c will result into a list
	 * containing c as first element, b as second element and a as third element.
	 *
	 * @param string       $key
	 * @param string|array $value
	 *
	 * @return int The length of the list after the push operations.
	 */
	public function lpush($key, $value)
	{
		$command = array('LPUSH', $key);
		if (is_array($value)) {
			$command = array_merge($command, $value);
		}
		else {
			$command[] = $value;
		}

		return $this->execute($command);
	}

	/**
	 * Get a range of elements from a list
	 *
	 * Returns the specified elements of the list stored at $key. The offsets $start and $stop are zero-based indexes,
	 * with 0 being the first element of the list (the head of the list), 1 being the next element and so on.
	 *
	 * These offsets can also be negative numbers indicating offsets starting at the end of the list. For example,
	 * -1 is the last element of the list, -2 the penultimate, and so on.
	 *
	 * Out of range indexes will not produce an error. If $start is larger than the end of the list, an empty list is
	 * returned. If $stop is larger than the actual end of the list, Redis will treat it like the last element of the
	 * list.
	 *
	 * @param string $key
	 * @param int    $start
	 * @param int    $stop
	 *
	 * @return array
	 */
	public function lrange($key, $start = 0, $stop = -1)
	{
		return $this->execute('LRANGE', $key, $start, $stop);
	}

	/**
	 * Trim a list to the specified range
	 *
	 * @param string $key
	 * @param int    $start
	 * @param int    $stop
	 */
	public function ltrim($key, $start = 0, $stop = -1)
	{
		$this->execute('LTRIM', $key, $start, $stop);
	}

	/**
	 * Append one or multiple values to a list
	 *
	 * @param string       $key
	 * @param string|array $value
	 *
	 * @return int The  length of the list after the push operation.
	 */
	public function rpush($key, $value)
	{
		$command = array('RPUSH', $key);
		if (is_array($value)) {
			$command = array_merge($command, $value);
		}
		else {
			$command[] = $value;
		}

		return $this->execute($command);
	}

	# sets

	/**
	 * Add one or more members to a set
	 *
	 * Add the specified members to the set stored at $key. Specified members that are already a member of this set
	 * are ignored. If $key does not exist, a new set is created before adding the specified members.
	 * An error throw when the value stored at $key is not a set.
	 *
	 * @param string       $key
	 * @param string|array $member
	 *
	 * @return int The number of elements that were added to the set, not including all the elements already present
	 *             into the set.
	 */
	public function sadd($key, $member)
	{
		$command = array('SADD', $key);
		if (is_array($member)) {
			$command = array_merge($command, $member);
		}
		else {
			$command[] = $member;
		}

		return $this->execute($command);
	}

	/**
	 * Get the number of members in a set
	 *
	 * Returns the set cardinality (number of elements) of the set stored at key$.
	 *
	 * @param string $key
	 *
	 * @return int The cardinality (number of elements) of the set, or 0 if $key does not exist.
	 */
	public function scard($key)
	{
		return $this->execute('SCARD', $key);
	}

	/**
	 * Subtract multiple sets
	 *
	 * Returns the members of the set resulting from the difference between the first set and all the successive sets.
	 *
	 * Keys that do not exist are considered to be empty sets.
	 *
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return array List with members of the resulting set.
	 */
	public function sdiff($key, $key2 = null)
	{
		if (is_array($key)) {
			$command = $key;
			array_unshift($command, 'SDIFF');
		}
		else {
			$command = array('SDIFF', $key);
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	/**
	 * Subtract multiple sets and store the resulting set in a key
	 *
	 * This command is equal to SDIFF, but instead of returning the resulting set, it is stored in $destination.
	 *
	 * If $destination already exists, it is overwritten.
	 *
	 * @param string       $destination
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return int The number of elements in the resulting set.
	 */
	public function sdiffstore($destination, $key, $key2 = null)
	{
		$command = array('SDIFFSTORE', $destination);

		if (is_array($key)) {
			$command = array_merge($command, $key);
		}
		else {
			$command[] = $key;
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	/**
	 * Intersect multiple sets
	 *
	 * Returns the members of the set resulting from the intersection of all the given sets.
	 *
	 * Keys that do not exist are considered to be empty sets. With one of the keys being an empty set, the resulting
	 * set is also empty (since set intersection with an empty set always results in an empty set).
	 *
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return array List with members of the resulting set.
	 */
	public function sinter($key, $key2 = null)
	{
		if (is_array($key)) {
			$command = $key;
			array_unshift($command, 'SINTER');
		}
		else {
			$command = array('SINTER', $key);
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	/**
	 * Intersect multiple sets and store the resulting set in a key
	 *
	 * This command is equal to SINTER, but instead of returning the resulting set, it is stored in destination.
	 *
	 * If $destination already exists, it is overwritten.
	 *
	 * @param string       $destination
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return int The number of elements in the resulting set.
	 */
	public function sinterstore($destination, $key, $key2 = null)
	{
		$command = array('SINTERSTORE', $destination);

		if (is_array($key)) {
			$command = array_merge($command, $key);
		}
		else {
			$command[] = $key;
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	/**
	 * Determine if a given value is a member of a set
	 *
	 * Returns if member is a $member of the set stored at $key.
	 *
	 * @param string $key
	 * @param string $member
	 *
	 * @return bool True - if the element is a member of the set.
	 *              False - if the element is not a member of the set, or if $key does not exist.
	 */
	public function sismember($key, $member)
	{
		return (bool)$this->execute('SISMEMBER', $key, $member);
	}

	/**
	 * Get all the members in a set
	 *
	 * Returns all the members of the set value stored at $key.
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	public function smembers($key)
	{
		return (array)$this->execute('SMEMBERS', $key);
	}

	/**
	 * Move a member from one set to another
	 *
	 * Move $member from the set at $source to the set at $destination. This operation is atomic. In every given
	 * moment the element will appear to be a member of $source or $destination for other clients.
	 *
	 * If the source set does not exist or does not contain the specified element, no operation is performed and 0 is
	 * returned. Otherwise, the element is removed from the source set and added to the destination set. When the
	 * specified element already exists in the destination set, it is only removed from the source set.
	 *
	 * An error throws if $source or $destination does not hold a set value.
	 *
	 * @param string             $source
	 * @param string             $destination
	 * @param bool|number|string $member
	 *
	 * @return bool True - if the element is moved.
	 *            False - if the element is not a member of $source and no operation was performed.
	 */
	public function smove($source, $destination, $member)
	{
		return (bool)$this->execute('SMOVE', $source, $destination, $member);
	}

	/**
	 * Remove and return a random member from a set
	 *
	 * Removes and returns a random element from the set value stored at $key.
	 *
	 * This operation is similar to SRANDMEMBER, that returns a random element from a set but does not remove it.
	 *
	 * @param string $key
	 *
	 * @return int|string The removed element, or NULL when key does not exist.
	 */
	public function spop($key)
	{
		return $this->execute('SPOP', $key);
	}

	/**
	 * Get a random member from a set
	 *
	 * Return a random element from the set value stored at $key.
	 *
	 * This operation is similar to SPOP, however while SPOP also removes the randomly selected element from the set,
	 * SRANDMEMBER will just return a random element without altering the original set in any way.
	 *
	 * @param string $key
	 *
	 * @return number|string The randomly selected element, or NULL when key does not exist.
	 */
	public function srandmember($key)
	{
		return $this->execute('SRANDMEMBER', $key);
	}

	/**
	 * Remove one or more members from a set
	 *
	 * Remove the specified members from the set stored at $key. Specified members that are not a member of this set
	 * are ignored. If $key does not exist, it is treated as an empty set and this command returns 0.
	 *
	 * An error throws when the value stored at key is not a set.
	 *
	 * @param string       $key
	 * @param string|array $member
	 *
	 * @return int The number of members that were removed from the set, not including non existing members.
	 */
	public function srem($key, $member)
	{
		$command = array('SREM', $key);
		if (is_array($member)) {
			$command = array_merge($command, $member);
		}
		else {
			$command[] = $member;
		}

		return $this->execute($command);
	}

	/**
	 * Add multiple sets
	 *
	 * Returns the members of the set resulting from the union of all the given sets.
	 *
	 * Keys that do not exist are considered to be empty sets.
	 *
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return array List with members of the resulting set.
	 */
	public function sunion($key, $key2 = null)
	{
		if (is_array($key)) {
			$command = $key;
			array_unshift($command, 'SUNION');
		}
		else {
			$command = array('SUNION', $key);
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	/**
	 * Add multiple sets and store the resulting set in a key
	 *
	 * This command is equal to SUNION, but instead of returning the resulting set, it is stored in $destination.
	 *
	 * If $destination already exists, it is overwritten.
	 *
	 * @param string       $destination
	 * @param string|array $key
	 * @param string       $key2
	 *
	 * @return int The number of elements in the resulting set.
	 */
	public function sunionstore($destination, $key, $key2 = null)
	{
		$command = array('SUNIONSTORE', $destination);

		if (is_array($key)) {
			$command = array_merge($command, $key);
		}
		else {
			$command[] = $key;
			if (null !== $key2) {
				$command[] = $key2;
			}
		}

		return $this->execute($command);
	}

	# sorted sets

	/**
	 * Add one or more members to a sorted set, or update its score if it already exists
	 *
	 * Adds all the specified members with the specified scores to the sorted set stored at $key. It is possible to
	 * specify multiple score/member pairs. If a specified member is already a member of the sorted set, the score is
	 * updated and the element reinserted at the right position to ensure the correct ordering. If $key does not exist,
	 * a new sorted set with the specified members as sole members is created, like if the sorted set was empty.
	 * If the key exists but does not hold a sorted set, an error is returned.
	 * The score values should be the string representation of a numeric value, and accepts double precision floating
	 * point numbers.
	 *
	 * @param string       $key
	 * @param string|array $member
	 * @param number       $score
	 *
	 * @return int The number of elements added to the sorted sets, not including elements already existing for which
	 *             the score was updated.
	 */
	public function zadd($key, $member, $score = null)
	{
		if (is_array($member)) {
			$command = array('ZADD', $key);
			foreach ($member as $m => $score) {
				array_push($command, $score, $m);
			}
		}
		else {
			$command = array('ZADD', $key, $score, $member);
		}

		return $this->execute($command);
	}

	/**
	 * Get the number of members in a sorted set
	 *
	 * Returns the sorted set cardinality (number of elements) of the sorted set stored at $key.
	 *
	 * @param string $key
	 *
	 * @return int The cardinality (number of elements) of the sorted set, or 0 if $key does not exist.
	 */
	public function zcard($key)
	{
		return $this->execute('ZCARD', $key);
	}

	/**
	 * Increment the score of a member in a sorted set
	 *
	 * @param string $key
	 * @param string $member
	 * @param number $increment
	 *
	 * @return int The new score of member (a double precision floating point number), represented as string.
	 */
	public function zincrby($key, $member, $increment)
	{
		return $this->execute('ZINCRBY', $key, $increment, $member);
	}

	/**
	 * Return a range of members in a sorted set, by index
	 *
	 * Returns the specified range of elements in the sorted set stored at $key. The elements are considered to be
	 * ordered from the lowest to the highest score. Lexicographical order is used for elements with equal score.
	 * See ZREVRANGE when you need the elements ordered from highest to lowest score (and descending lexicographical
	 * order for elements with equal score).
	 *
	 * Both $start and $stop are zero-based indexes, where 0 is the first element, 1 is the next element and so on.
	 * They can also be negative numbers indicating offsets from the end of the sorted set, with -1 being the last
	 * element of the sorted set, -2 the penultimate element and so on.
	 *
	 * Out of range indexes will not produce an error. If $start is larger than the largest index in the sorted set,
	 * or $start > $stop, an empty list is returned. If $stop is larger than the end of the sorted set Redis will
	 * treat it like it is the last element of the sorted set.
	 *
	 * It is possible to pass the $withScores option in order to return the scores of the elements together with the
	 * elements.
	 *
	 * @param string $key
	 * @param int    $start
	 * @param int    $stop
	 * @param bool   $withScores
	 *
	 * @return array List of elements in the specified range (optionally with their scores).
	 */
	public function zrange($key, $start = 0, $stop = -1, $withScores = false)
	{
		$command = array('ZRANGE', $key, $start, $stop);
		if ($withScores) {
			$command[] = 'WITHSCORES';
		}
		$list = $this->execute($command);
		if ($withScores) {
			$hash = array();
			for ($i = 0, $l = count($list); $i < $l; $i += 2) {
				$hash[$list[$i]] = $list[$i + 1];
			}
			return $hash;
		}
		return $list;
	}

	/**
	 * Determine the index of a member in a sorted set
	 *
	 * Returns the rank of member in the sorted set stored at key, with the scores ordered from low to high.
	 * The rank (or index) is 0-based, which means that the member with the lowest score has rank 0.
	 *
	 * @param string $key
	 * @param string $member
	 *
	 * @return int If member exists in the sorted set, Integer reply: the rank of member. If member does not exist in
	 * the sorted set or key does not exist, Bulk reply: nil.
	 */
	public function zrank($key, $member)
	{
		return $this->execute('ZRANK', $key, $member);
	}

	/**
	 * Remove one or more members from a sorted set
	 *
	 * Removes the specified members from the sorted set stored at $key. Non existing members are ignored.
	 *
	 * An error throws when $key exists and does not hold a sorted set.
	 *
	 * @param string                   $key
	 * @param bool|number|string|array $member
	 *
	 * @return int The number of members removed from the sorted set, not including non existing members.
	 */
	public function zrem($key, $member)
	{
		$command = array('ZREM', $key);
		if (is_array($member)) {
			$command = array_merge($command, $member);
		}
		else {
			$command[] = $member;
		}

		return $this->execute($command);
	}

	/**
	 * Remove all members in a sorted set within the given indexes
	 *
	 * Removes all elements in the sorted set stored at $key with rank between $start and $stop. Both $start and $stop
	 * are 0-based indexes with 0 being the element with the lowest score. These indexes can be negative numbers,
	 * where they indicate offsets starting at the element with the highest score. For example: -1 is the element
	 * with the highest score, -2 the element with the second highest score and so forth.
	 *
	 * @param string $key
	 * @param int    $start
	 * @param int    $stop
	 *
	 * @return int The number of elements removed.
	 */
	public function zremrangebyrank($key, $start, $stop)
	{
		return $this->execute('ZREMRANGEBYRANK', $key, $start, $stop);
	}

	/**
	 * Return a range of members in a sorted set, by index, with scores ordered from high to low
	 *
	 * Returns the specified range of elements in the sorted set stored at $key. The elements are considered to be
	 * ordered from the highest to the lowest score. Descending lexicographical order is used for elements with equal
	 * score.
	 *
	 * Apart from the reversed ordering, ZREVRANGE is similar to ZRANGE.
	 *
	 * @param string $key
	 * @param int    $start
	 * @param int    $stop
	 * @param bool   $withScores
	 *
	 * @return array List of elements in the specified range (optionally with their scores).
	 */
	public function zrevrange($key, $start = 0, $stop = -1, $withScores = false)
	{
		$command = array('ZREVRANGE', $key, $start, $stop);
		if ($withScores) {
			$command[] = 'WITHSCORES';
		}
		$list = $this->execute($command);
		if ($withScores) {
			$hash = array();
			for ($i = 0, $l = count($list); $i < $l; $i += 2) {
				$hash[$list[$i]] = $list[$i + 1];
			}
			return $hash;
		}
		return $list;
	}

	/**
	 * Determine the index of a member in a sorted set, with scores ordered from high to low
	 *
	 * Returns the rank of member in the sorted set stored at key, with the scores ordered from high to low.
	 * The rank (or index) is 0-based, which means that the member with the highest score has rank 0.
	 *
	 * @param string $key
	 * @param string $member
	 *
	 * @return int If member exists in the sorted set, Integer reply: the rank of member. If member does not exist in
	 * the sorted set or key does not exist, Bulk reply: nil.
	 */
	public function zrevrank($key, $member)
	{
		return $this->execute('ZREVRANK', $key, $member);
	}

	/**
	 * Get the score associated with the given member in a sorted set
	 *
	 * Returns the score of $member in the sorted set at $key.
	 *
	 * If member does not exist in the sorted set, or $key does not exist, NULL is returned.
	 *
	 * @param $key
	 * @param $member
	 *
	 * @return number The score of member.
	 */
	public function zscore($key, $member)
	{
		$v = $this->execute('ZSCORE', $key, $member);
		return $v === null ? null : (float)$v;
	}

	# pub/sub

	/**
	 * Post a message to a channel
	 *
	 * Posts a message to the given channel.
	 *
	 * @param string $channel
	 * @param string $message
	 *
	 * @return int The number of clients that received the message.
	 */
	public function publish($channel, $message)
	{
		return $this->execute('PUBLISH', $channel, $message);
	}

	# transactions

	/**
	 * Discard all commands issued after MULTI
	 *
	 * Flushes all previously queued commands in a transaction and restores the connection state to normal.
	 *
	 * If WATCH was used, DISCARD unwatched all keys.
	 *
	 * @return void
	 */
	public function discard()
	{
		$this->execute('DISCARD');
	}

	/**
	 * Execute all commands issued after MULTI
	 *
	 * Executes all previously queued commands in a transaction and restores the connection state to normal.
	 * When using WATCH, EXEC will execute commands only if the watched keys were not modified, allowing for a
	 * check-and-set mechanism.
	 *
	 * @return array Each element being the reply to each of the commands in the atomic transaction.
	 *               When using WATCH, EXEC can return a NULL reply if the execution was aborted.
	 */
	public function exec()
	{
		return $this->execute('EXEC');
	}

	/**
	 * Mark the start of a transaction block
	 *
	 * Marks the start of a transaction block. Subsequent commands will be queued for atomic execution using EXEC.
	 *
	 * @return void
	 */
	public function multi()
	{
		$this->execute('MULTI');
	}

	/**
	 * Forget about all watched keys
	 *
	 * Flushes all the previously watched keys for a transaction.
	 * If you call EXEC or DISCARD, there's no need to manually call UNWATCH.
	 *
	 * @return void
	 */
	public function unwatch()
	{
		$this->execute('UNWATCH');
	}

	/**
	 * Watch the given keys to determine execution of the MULTI/EXEC block
	 *
	 * Marks the given keys to be watched for conditional execution of a transaction.
	 *
	 * @param string|array $key
	 *
	 * @return void
	 */
	public function watch($key)
	{
		if (is_array($key)) {
			array_unshift($key, 'WATCH');
			$this->execute($key);
		}
		else {
			$this->execute('WATCH', $key);
		}
	}

	# connection

	/**
	 * Change the selected database for the current connection
	 *
	 * Select the DB with having the specified zero-based numeric index. New connections always use DB 0.
	 *
	 * @param int $index
	 */
	public function select($index)
	{
		$this->execute('SELECT', $index);
	}

	# server

	/**
	 * Remove all keys from the current database
	 *
	 * Delete all the keys of the currently selected DB. This command never fails.
	 */
	public function flushdb()
	{
		$this->execute('FLUSHDB');
	}

	/**
	 * Get information and statistics about the server
	 *
	 * @return array
	 */
	public function info()
	{
		$result = array();
		foreach (explode("\r\n", $this->execute('INFO')) as $line) {
			if ($line === '') {
				continue;
			}
			$p = strpos($line, ':');
			$result[substr($line, 0, $p)] = substr($line, $p + 1);
		}
		return $result;
	}
}