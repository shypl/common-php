<?php
namespace org\shypl\common\cache\link;

use org\shypl\common\cache\Cache;
use org\shypl\common\cache\ICache;

abstract class CacheLink
{
	/**
	 * @var mixed
	 */
	protected $_metadata;
	/**
	 * @var string
	 */
	protected $_name;
	/**
	 * @var ICache
	 */
	private $_cache;
	/**
	 * @var string
	 */
	private $_key;
	/**
	 * @var string
	 */
	private $_metadataKey;
	/**
	 * @var callable
	 */
	private $_compileCallback;
	/**
	 * @var array
	 */
	private $_compileArgs;
	/**
	 * @var bool
	 */
	private $_has = false;

	/**
	 * @param ICache     $cache
	 * @param string     $name
	 * @param callable   $compileCallback
	 * @param array|null $compileArgs
	 */
	public function __construct(ICache $cache, $name, $compileCallback, array $compileArgs = array())
	{
		$this->_cache = $cache;
		$this->_name = $name;
		$this->_key = 'link.' . md5($this->_name);
		$this->_metadataKey = $this->_key . '.metadata';
		$this->_compileCallback = $compileCallback;
		$this->_compileArgs = $compileArgs;
	}

	/**
	 * @param bool $check
	 *
	 * @return mixed
	 */
	public function get($check = false)
	{
		if ((!$check && ($this->_has = $this->_cache->has($this->_key))) || $this->check()) {
			return Cache::unserialize($this->_cache->get($this->_key));
		}

		$data = $this->_compile();

		$this->_cache->set($this->_key, Cache::serialize($data));
		$this->_cache->set($this->_metadataKey, $this->_metadata);

		return $data;
	}

	/**
	 * @return bool
	 */
	abstract public function check();

	/**
	 * @return mixed
	 */
	protected function _compile()
	{
		return call_user_func_array(
			$this->_compileCallback,
			array_merge($this->_prepareCompileArgs(), $this->_compileArgs)
		);
	}

	/**
	 * @return array
	 */
	protected function _prepareCompileArgs()
	{
		return array($this);
	}

	/**
	 * @return mixed
	 */
	protected function _loadMetadata()
	{
		return $this->_metadata = $this->_cache->get($this->_metadataKey);
	}

	/**
	 * @param mixed $data
	 */
	protected function _setMetadata($data)
	{
		$this->_metadata = $data;
	}
}