<?php
namespace org\shypl\common\yaml\node;

/** @noinspection PhpDocSignatureInspection */
class Scalar extends Node
{
	/**
	 * @var string
	 */
	protected $_data;

	/**
	 * @param string $data
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 */
	public function __construct($data = null, $tagSuffix = 'str', $tagHandle = '!!')
	{
		parent::__construct($tagSuffix, $tagHandle);

		if (null !== $data) {
			$this->setData($data);
		}
	}

	/**
	 * @return string
	 */
	public function getData()
	{
		return $this->_data;
	}

	/**
	 * @param string $content
	 *
	 * @return void
	 */
	public function setData($content)
	{
		$this->_data = $content;
	}
}