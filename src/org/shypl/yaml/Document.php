<?php
namespace org\shypl\yaml;

use org\shypl\yaml\directive;
use org\shypl\yaml\node\Mapping;
use org\shypl\yaml\node\Node;
use org\shypl\yaml\node\Scalar;
use org\shypl\yaml\node\Sequence;

class Document
{
	/**
	 * @param mixed  $data
	 * @param string $encoding
	 *
	 * @return \org\shypl\yaml\Document
	 */
	static public function create($data, $encoding = 'UTF-8')
	{
		/**
		 * Document
		 */
		$document = new static($encoding);

		$document->setRootNode(static::_createNode($document, $data));

		return $document;
	}

	/**
	 * @param \org\shypl\yaml\Document $document
	 * @param mixed              $data
	 *
	 * @return \org\shypl\yaml\node\Node
	 */
	static protected function _createNode(Document $document, $data)
	{
		if (is_array($data)) {
			$isAssoc = array_keys($data) !== range(0, count($data) - 1);
			if ($isAssoc) {
				$node = $document->createMappingNode();
			}
			else {
				$node = $document->createSequenceNode();
			}

			foreach ($data as $key => $value) {
				$value = static::_createNode($document, $value);
				if ($isAssoc) {
					$node->addItem(static::_createNode($document, $key), $value);
				}
				else {
					$node->addItem($value);
				}
			}

			return $node;
		}

		switch (true) {
			case null === $data:
				$suffix = 'null';
				break;

			case is_bool($data):
				$suffix = 'bool';
				break;

			case is_int($data):
				$suffix = 'int';
				break;

			case is_float($data):
				$suffix = 'float';
				break;

			case is_numeric($data):
				if (false === strpos($data, '.')) {
					$suffix = 'int';
				}
				else {
					$suffix = 'float';
				}
				break;

			default:
				$suffix = 'str';
				break;
		}

		return $document->createScalarNode($data, $suffix);
	}

	###

	/**
	 * @var string
	 */
	protected $_encoding;

	/**
	 * @var \org\shypl\yaml\directive\Yaml
	 */
	protected $_yamlDirective;

	/**
	 * @var int
	 */
	protected $_indent = 2;

	/**
	 * @var string
	 */
	protected $_break = "\n";

	/**
	 * @var \org\shypl\yaml\directive\Tag[]
	 */
	protected $_tagDirectives = array();

	/**
	 * @var \org\shypl\yaml\directive\Reserved[]
	 */
	protected $_reservedDirectives = array();

	/**
	 * @var \org\shypl\yaml\node\Node
	 */
	protected $_rootNode;

	/**
	 * @param string $encoding
	 * @param string $version
	 */
	public function __construct($encoding = 'UTF-8', $version = '1.2')
	{
		$this->setEncoding($encoding);
		$this->setVersion($version);
		$this->setTag('!', '!');
		$this->setTag('!!', 'tag:yaml.org,2002:');
	}

	/**
	 * @return mixed
	 */
	public function getData()
	{
		if (null === $this->_rootNode) {
			return null;
		}
		return $this->_getNodeData($this->_rootNode);
	}

	/**
	 * @return string
	 */
	public function getYaml()
	{
		#TODO

		$result = array();

		if ($this->_yamlDirective->getVersion() !== '1.2') {
			$result[] = '%YAML ' . $this->_yamlDirective->getVersion();
		}

		/*foreach ($this->_tagDirectives as $tag) {

		}

		foreach ($this->_reservedDirectives as $directive) {

		}*/

		if (count($result)) {
			$result[] = '---';
		}

		if (null !== $this->_rootNode) {
			//$result[] = $this->_rootNode->getYaml(0, $this->_indent, $this->_break);
		}

		return join($this->_break, $result);
	}

	/**
	 * @param \org\shypl\yaml\node\Node $node
	 *
	 * @return mixed
	 *
	 * @throws \OutOfBoundsException
	 */
	protected function _getNodeData(Node $node)
	{
		$tag = $this->_prepareNodeTag($node);
		$data = $node->getData();

		switch (true) {
			// Scalar
			case $node instanceof Scalar:
				switch ($tag) {
					// str
					case 'tag:yaml.org,2002:str':
						return $data;
					// int
					case 'tag:yaml.org,2002:int':
						if (0 === strpos($data, '0o')) {
							return octdec($data);
						}
						if (0 === strpos($data, '0x')) {
							return hexdec($data);
						}
						if ($data > PHP_INT_MAX) {
							return $data;
						}
						return (int)$data;
					// bool
					case 'tag:yaml.org,2002:bool':
						if ($data === 'false' || $data === 'False' || $data === 'FALSE') {
							return false;
						}
						return (bool)$data;
					// float
					case 'tag:yaml.org,2002:float':
						$data = mb_convert_case($data, MB_CASE_LOWER, $this->_encoding);
						if ($data === '-.inf') {
							return -INF;
						}
						if ($data === '.inf') {
							return INF;
						}
						if ($data === '.nan') {
							return NAN;
						}
						return (float)$data;
					// null
					case 'tag:yaml.org,2002:null':
						return null;
					//
					default:
						return $data;
				}

			// Sequence
			case $node instanceof Sequence:
				$result = array();
				/** @var $data array */
				foreach ($data as $node) {
					$result[] = $this->_getNodeData($node);
				}
				return $result;

			// Mapping
			case $node instanceof Mapping:
				$result = array();
				$count = 0;
				/** @var $data array */
				foreach ($data as $item) {
					if ($item[0] instanceof node\Sequence) {
						$key = '__seq_' . $count . '__';
					}
					elseif ($item[0] instanceof node\Mapping) {
						$key = '__map_' . $count . '__';
					}
					else {
						$key = $this->_getNodeData($item[0]);
					}
					$result[$key] = $this->_getNodeData($item[1]);
					++$count;
				}
				return $result;

			//
			default:
				throw new \OutOfBoundsException('Undefined tag node kind');
		}
	}

	/**
	 * @param \org\shypl\yaml\node\Node $node
	 *
	 * @return string
	 *
	 * @throws \OutOfBoundsException
	 */
	protected function _prepareNodeTag(Node $node)
	{
		$handle = $node->getHandle();
		$suffix = $node->getSuffix();

		if (!isset($this->_tagDirectives[$handle])) {
			throw new \OutOfBoundsException('Undefined tag handle ' . $handle);
		}

		return $this->_tagDirectives[$handle]->getPrefix() . $suffix;
	}

	/**
	 * @param string $encoding
	 */
	public function setEncoding($encoding)
	{
		#TODO добавить проверку на валидность
		$this->_encoding = strtoupper($encoding);
	}

	/**
	 * @return string
	 */
	public function getEncoding()
	{
		return $this->_encoding;
	}

	/**
	 * @param string $version
	 */
	public function setVersion($version)
	{
		$this->_yamlDirective = new directive\Yaml($version);
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return $this->_yamlDirective->getVersion();
	}

	/**
	 * @param string $handle
	 * @param string $prefix
	 */
	public function setTag($handle, $prefix)
	{
		$this->_tagDirectives[$handle] = new directive\Tag($handle, $prefix);
	}

	/**
	 * @param string $handle
	 *
	 * @return string
	 */
	public function getTagPrefix($handle)
	{
		return isset($this->_tagDirectives[$handle]) ? $this->_tagDirectives[$handle]->getPrefix() : null;
	}

	/**
	 * @param string $handle
	 *
	 * @return bool Success
	 */
	public function removeTag($handle)
	{
		if (isset($this->_tagDirectives[$handle])) {
			unset($this->_tagDirectives[$handle]);
			return true;
		}
		return false;
	}

	/**
	 * @param \org\shypl\yaml\directive\Reserved $directive
	 *
	 * @return int Number of directives
	 */
	public function addDirective(directive\Reserved $directive)
	{
		return array_push($this->_reservedDirectives, $directive);
	}

	/**
	 * @param int $index
	 *
	 * @return \org\shypl\yaml\directive\Reserved
	 */
	public function getDirective($index)
	{
		return isset($this->_reservedDirectives[$index]) ? $this->_reservedDirectives[$index] : null;
	}

	/**
	 * @param $name
	 *
	 * @return \org\shypl\yaml\directive\Reserved[]
	 */
	public function getDirectives($name)
	{
		$list = array();
		foreach ($this->_reservedDirectives as $dir) {
			if ($dir->getName() === $name) {
				$list[] = $dir;
			}
		}
		return $list;
	}

	/**
	 * @param int $index
	 *
	 * @return int Number of directives
	 */
	public function removeDirective($index)
	{
		array_splice($this->_reservedDirectives, $index, 1);
		return count($this->_reservedDirectives);
	}

	/**
	 * @param \org\shypl\yaml\node\Node $node
	 */
	public function setRootNode(Node $node)
	{
		$this->_rootNode = $node;
	}

	/**
	 * @return \org\shypl\yaml\node\Node
	 */
	public function getRootNode()
	{
		return $this->_rootNode;
	}

	/**
	 * @param string $data
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 *
	 * @return \org\shypl\yaml\node\Scalar
	 */
	public function createScalarNode($data = null, $tagSuffix = 'str', $tagHandle = '!!')
	{
		return new Scalar($data, $tagSuffix, $tagHandle);
	}

	/**
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 *
	 * @return \org\shypl\yaml\node\Sequence
	 */
	public function createSequenceNode($tagSuffix = 'seq', $tagHandle = '!!')
	{
		return new Sequence($tagSuffix, $tagHandle);
	}

	/**
	 * @param string $tagSuffix
	 * @param string $tagHandle
	 *
	 * @return \org\shypl\yaml\node\Mapping
	 */
	public function createMappingNode($tagSuffix = 'map', $tagHandle = '!!')
	{
		return new Mapping($tagSuffix, $tagHandle);
	}
}