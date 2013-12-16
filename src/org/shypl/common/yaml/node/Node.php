<?php
namespace org\shypl\common\yaml\node;

abstract class Node
{
	/**
	 * @var array
	 */
	static protected $_defaultTags = array(
		''
	);
	
	/**
	 * @var string
	 */
	protected $_tagHandle;

	/**
	 * @var string
	 */
	protected $_tagSuffix;

	/**
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 */
	public function __construct($tagSuffix, $tagHandle = '!!')
	{
		$this->_tagHandle = $tagHandle;
		$this->_tagSuffix = $tagSuffix;
	}

	/**
	 * @return string
	 */
	public function getHandle()
	{
		return $this->_tagHandle;
	}

	/**
	 * @return string
	 */
	public function getSuffix()
	{
		return $this->_tagSuffix;
	}

	abstract public function getData();
}