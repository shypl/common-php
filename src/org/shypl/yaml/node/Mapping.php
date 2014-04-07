<?php
namespace org\shypl\yaml\node;

class Mapping extends Node
{
	/**
	 * @var array
	 */
	protected $_items = array();

	/**
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 */
	public function __construct($tagSuffix = 'map', $tagHandle = '!!')
	{
		parent::__construct($tagSuffix, $tagHandle);
	}

	/**
	 * @param \org\shypl\yaml\node\Node $key
	 * @param \org\shypl\yaml\node\Node $value
	 *
	 * @return int
	 */
	public function addItem(Node $key, Node $value)
	{
		$this->_items[] = array($key, $value);
		return count($this->_items);
	}

	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->_items;
	}
}