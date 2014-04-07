<?php
namespace org\shypl\yaml\node;

class Sequence extends Node
{
	/**
	 * @var array
	 */
	protected $_items = array();

	/**
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 */
	public function __construct($tagSuffix = 'sec', $tagHandle = '!!')
	{
		parent::__construct($tagSuffix, $tagHandle);
	}

	/**
	 * @param \org\shypl\yaml\node\Node $node
	 *
	 * @return int
	 */
	public function addItem(Node $node)
	{
		$this->_items[] = $node;
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