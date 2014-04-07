<?php
namespace org\shypl\data;

class AssocData implements \Countable, \Iterator, \ArrayAccess
{
	/**
	 * @static
	 *
	 * @param array $map
	 * @param bool  $readOnly
	 *
	 * @return AssocData
	 */
	static public function factoryMap(array $map, $readOnly = false)
	{
		$object = new self();
		foreach ($map as $key => $value) {
			$object->set($key, $value);
		}
		if ($readOnly) {
			$object->setReadOnly();
		}
		return $object;
	}

	/**
	 * @param array|AssocData $data
	 * @param bool            $readOnly
	 *
	 * @return AssocData
	 */
	static public function obtain($data, $readOnly = false)
	{
		return new AssocData($data instanceof AssocData ? $data->toArray() : $data, $readOnly);
	}

	###

	/**
	 * @var array
	 */
	private $_data = array();

	/**
	 * Allow modifications to configuration data
	 *
	 * @var bool
	 */
	private $_readOnly;

	/**
	 * Position of iterator
	 *
	 * @var int
	 */
	private $_iteratorPosition = 0;

	/**
	 * Number of elements in data
	 *
	 * @var int
	 */
	private $_count = 0;

	/**
	 * AssocData provides a simple interface to access elements of a
	 * multidimensional array
	 *
	 * AssocData also implements Countable, Iterator and ArrayAccess to
	 * facilitate easy access to the data.
	 * The data are read-only unless $readOnly is set to false on construction.
	 *
	 * @param array $data
	 * @param bool  $readOnly
	 */
	public function __construct(array $data = null, $readOnly = false)
	{
		$this->_readOnly = (bool)$readOnly;
		$this->_data = (array)$data;
		$this->_count = count($data);
	}

	/**
	 * Prepare and check key
	 *
	 * @param string $key
	 *
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	private function _prepareKey($key)
	{
		$key = trim($key);
		if ($key === '') {
			throw new \InvalidArgumentException('$key can\'t be equal empty string');
		}
		return $key;
	}

	/**
	 * Throw exception if object is read only
	 *
	 * @throws \LogicException
	 */
	private function _checkReadOnly()
	{
		if ($this->_readOnly) {
			throw new \LogicException('This AssocData object is read only');
		}
	}

	/**
	 * @param bool $is
	 */
	public function setReadOnly($is = true)
	{
		$this->_readOnly = $is;
	}

	/**
	 * @return bool
	 */
	public function isReadOnly()
	{
		return $this->_readOnly;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function exist($key)
	{
		if (strpos($key, '.')) {
			$value = $this->_data;
			foreach (explode('.', $key) as $key) {
				if (array_key_exists($key, $value)) {
					$value = $value[$key];
				}
				else {
					return false;
				}
			}
			return true;
		}

		return array_key_exists($key, $this->_data);
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		if (strpos($key, '.')) {
			$value = $this->_data;
			foreach (explode('.', $key) as $key) {
				if (isset($value[$key])) {
					$value = $value[$key];
				}
				else {
					return false;
				}
			}
			return true;
		}

		return isset($this->_data[$key]);
	}

	/**
	 * Get element value
	 *
	 * Return $default if there is no element set.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @param bool   $asArray
	 *
	 * @return mixed|AssocData
	 */
	public function get($key, $default = null, $asArray = false)
	{
		if (false === strpos($key, '.')) {

			$key = $this->_prepareKey($key);

			if (isset($this->_data[$key])) {
				if (is_array($this->_data[$key]) && !$asArray) {
					$this->_data[$key] = new self($this->_data[$key], $this->_readOnly);
				}
				else if ($asArray && $this->_data[$key] instanceof self) {
					/** @noinspection PhpUndefinedMethodInspection */
					return $this->_data[$key]->toArray();
				}
				return $this->_data[$key];
			}

			return $default;
		}

		$data =& $this->_data;
		$keys = explode('.', $key);
		$last = count($keys) - 1;

		foreach ($keys as $i => $key) {

			$key = $this->_prepareKey($key);

			if (is_scalar($data) || !isset($data[$key])) {
				return $default;
			}
			if ($i === $last) {
				if (is_array($data[$key]) && !$asArray) {
					$data[$key] = new self($data[$key], $this->_readOnly);
				}
				else if ($asArray && $data[$key] instanceof self) {
					/** @noinspection PhpUndefinedMethodInspection */
					return $data[$key]->toArray();
				}
				return $data[$key];
			}

			$data =& $data[$key];
		}

		return $default;
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return array
	 */
	public function getArray($key, $default = null)
	{
		return $this->get($key, $default, true);
	}

	/**
	 * Set element value
	 *
	 * Only allow setting of a elements if $readOnly was set to false on
	 * construction. Otherwise, throw an exception.
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return null
	 */
	public function set($key, $value)
	{
		$this->_checkReadOnly();

		if (false === strpos($key, '.')) {

			$key = $this->_prepareKey($key);

			$not = !array_key_exists($key, $this->_data);
			if ($not || $this->_data[$key] !== $value) {
				$this->_data[$key] = $value;
			}
			if ($not) {
				$this->_count = count($this->_data);
			}

			return;
		}

		$data =& $this->_data;
		$keys = explode('.', $key);
		$last = count($keys) - 1;

		foreach ($keys as $i => $key) {

			$key = $this->_prepareKey($key);

			if ($i === $last) {
				$data[$key] = $value;
				break;
			}
			if (!array_key_exists($key, $data) || !is_array($data[$key]) || !($data[$key] instanceof self)) {
				$data[$key] = array();
			}

			$data =& $data[$key];
		}
	}

	/**
	 * Set element value
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return null
	 */
	public function setNotExist($key, $value)
	{
		if (!$this->has($key)) {
			$this->set($key, $value);
		}
	}

	/**
	 * Remove element
	 *
	 * Only allow if $readOnly was set to false on construction. Otherwise, throw an exception.
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function remove($key)
	{
		$this->_checkReadOnly();

		if (false === strpos($key, '.')) {

			$key = $this->_prepareKey($key);

			if (array_key_exists($key, $this->_data)) {
				unset($this->_data[$key]);
				$this->_count = count($this->_data);
			}
			return;
		}

		$data =& $this->_data;
		$keys = explode('.', $key);
		$last = count($keys) - 1;

		foreach ($keys as $i => $key) {
			$key = $this->_prepareKey($key);

			if ($i === $last) {
				unset($data[$key]);
				return;
			}

			if (!array_key_exists($key, $data) && !is_array($data[$key]) && !($data[$key] instanceof self)) {
				return;
			}

			$data =& $data[$key];
		}
	}

	/**
	 * Return an associative array of the stored data
	 *
	 * @return array
	 */
	public function toArray()
	{
		$array = $this->_data;
		foreach ($array as $key => $value) {
			if ($value instanceof self) {
				/**
				 * @var $value AssocData
				 */
				$array[$key] = $value->toArray();
			}
		}
		return $array;
	}

	/**
	 * @param array $array
	 * @param bool  $replace
	 * @param bool  $new
	 *
	 * @return AssocData
	 */
	public function merge(array $array, $replace = false, $new = false)
	{
		if ($new) {
			$source = clone $this;
			$source->_readOnly = false;
		}
		else {
			$this->_checkReadOnly();
			$source = $this;
		}

		foreach ($array as $key => $value) {

			if (array_key_exists($key, $source->_data)) {

				if (is_array($value) && $source->get($key) instanceof self) {
					$source->get($key)->merge($value, $replace);
				}
				else if ($replace) {
					$source->_data[$key] = $value;
				}

			}
			else {
				$source->_data[$key] = $value;
			}
		}

		return $source;
	}

	/**
	 * Deep clone of this instance to ensure that nested AssocData are also cloned
	 */
	public function __clone()
	{
		$this->_readOnly = false;
		foreach ($this->_data as $key => $value) {
			if ($value instanceof self) {
				$this->_data[$key] = clone $value;
			}
		}
	}

	/**
	 * Support setting properties
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Support reading properties
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->get($key);
	}

	/**
	 * Support isset()
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function __isset($key)
	{
		return $this->has($key);
	}

	/**
	 * Support unset()
	 *
	 * @param string $key
	 */
	public function __unset($key)
	{
		$this->remove($key);
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return 'AssocData';
	}

	### implements

	/**
	 * Defined by Countable interface
	 *
	 * @return int
	 */
	public function count()
	{
		return $this->_count;
	}

	/**
	 * Defined by Iterator interface
	 *
	 * @return mixed
	 */
	public function current()
	{
		$item = current($this->_data);
		if (is_array($item)) {
			$this->_data[$this->key()] = $item = new self($item, $this->_readOnly);
		}
		return $item;
	}

	/**
	 * Defined by Iterator interface
	 *
	 * @return mixed
	 */
	public function key()
	{
		return key($this->_data);
	}

	/**
	 * Defined by Iterator interface
	 */
	public function next()
	{
		next($this->_data);
		++$this->_iteratorPosition;
	}

	/**
	 * Defined by Iterator interface
	 */
	public function rewind()
	{
		reset($this->_data);
		$this->_iteratorPosition = 0;
	}

	/**
	 * Defined by Iterator interface
	 *
	 * @return boolean
	 */
	public function valid()
	{
		return $this->_iteratorPosition < $this->_count;
	}

	/**
	 * Defined by ArrayAccess interface
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->has($key);
	}

	/**
	 * Defined by ArrayAccess interface
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->get($key);
	}

	/**
	 * Defined by ArrayAccess interface
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function offsetSet($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Defined by ArrayAccess interface
	 *
	 * @param string $key
	 */
	public function offsetUnset($key)
	{
		$this->remove($key);
	}
}