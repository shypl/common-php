<?php
namespace org\shypl\common\yaml;

/** @noinspection PhpDocSignatureInspection */
class Parser
{
	const MARK = 'mark';
	const MARK_SCALAR = 'scalar';
	const MARK_SEQUENCE = 'sequence';
	const MARK_MAPPING = 'mapping';
	const MARK_END = 'end';
	const MARK_NULL = 'null';
	const MARK_ALIAS = 'alias';
	const MARK_DIRECTIVE_YAML = 'directive_yaml';
	const MARK_DIRECTIVE_TAG = 'directive_tag';
	const MARK_DIRECTIVE_RESERVED = 'directive_reserved';
	const MARK_TAG = 'tag';
	const MARK_ANCHOR = 'anchor';
	const MARK_DOCUMENT_START = 'doc_start';
	const MARK_DOCUMENT_END = 'doc_end';
	const MARK_CONTENT_BREAK = 'break';
	const MARK_CONTENT_SPACE = 'space';
	const MARK_CONTENT_STRING = 'string';

	const CONTEXT_BLOCK_OUT = 1;
	const CONTEXT_BLOCK_IN = 2;
	const CONTEXT_BLOCK_KEY = 3;
	const CONTEXT_FLOW_OUT = 4;
	const CONTEXT_FLOW_IN = 5;
	const CONTEXT_FLOW_KEY = 6;

	const BLOCK_STRIP = 1;
	const BLOCK_KEEP = 2;
	const BLOCK_CLIP = 3;

	###

	/**
	 * @var bool
	 */
	static protected $_initialized = false;

	/**
	 * @var array
	 */
	static protected $_ptn = array(
		's-white'          => array(0x20, 0x09), // s-space | s-tab
		'nb-char'          => array( //c-printable  - b-char  - c-byte-order-mark
			0x9, array(-1, 0x20, 0x7E),
			0x85, array(-1, 0xA0, 0xD7FF), array(-1, 0xE000, 0xFEFE), array(-1, 0xFF00, 0xFFFD),
			array(-1, 0x10000, 0x10FFFF),
		),
		'nb-json'          => array(0x9, array(-1, 0x20, 0x10FFFF)), // #x9 | [#x20-#x10FFFF]
		'b-break'          => array(array(-2, 0x0D, 0x0A), 0x0D, 0x0A),
		#28 //( b-carriage-return b-line-feed ) | b-carriage-return | b-line-feed,
		'ns-dec-digit'     => array(-1, 0x30, 0x39) /* 0-9 */,
		'ns-word-char'     => array('ns-dec-digit', array(-1, 0x41, 0x5A), array(-1, 0x61, 0x7A), 0x2D),
		// ns-dec-digit  | ns-ascii-letter  | “-”
		'ns-hex-digit'     => array('ns-dec-digit', array(-1, 0x41, 0x46), array(-1, 0x61, 0x66)),
		// ns-dec-digit | [#x41-#x46] /* A-F */ | [#x61-#x66] /* a-f */
		'ns-uri-char'      => array(
			array(-2, 0x25, array(-3, 2, 'ns-hex-digit')), 'ns-word-char',
			// “%” ns-hex-digit  ns-hex-digit  | ns-word-char | “#” | “;” | “/” | “?” | “:” | “@” | “&” | “=” | “+” | “$” | “,” | “_” | “.” | “!” | “~” | “*” | “'” | “(” | “)” | “[” | “]”
			0x23, 0x3B, 0x2F, 0x3F, 0x3A, 0x40, 0x26, 0x3D, 0x2B, 0x24, 0x2C, 0x5F, 0x2E, 0x21, 0x7E, 0x2A, 0x27, 0x28,
			0x29, 0x5B, 0x5D
		),
		'ns-tag-char'      => array(
			array(-2, 0x25, array(-3, 2, 'ns-hex-digit')), 'ns-word-char', // ns-uri-char  - “!”  - c-flow-indicator
			0x23, 0x3B, 0x2F, 0x3F, 0x3A, 0x40, 0x26, 0x3D, 0x2B, 0x24, 0x5F, 0x2E, 0x7E, 0x2A, 0x27, 0x28, 0x29
		),
		'ns-char'          => array( // nb-char - s-white
			array(-1, 0x21, 0x7E),
			0x85, array(-1, 0xA0, 0xD7FF), array(-1, 0xE000, 0xFEFE), array(-1, 0xFF00, 0xFFFD),
			array(-1, 0x10000, 0x10FFFF),
		),
		'ns-anchor-char'   => array( // ns-char  - c-flow-indicator
			array(-1, 0x21, 0x2B), array(-1, 0x2D, 0x5A), 0x5C, array(-1, 0x5E, 0x7A), 0x7C, 0x7E,
			0x85, array(-1, 0xA0, 0xD7FF), array(-1, 0xE000, 0xFEFE), array(-1, 0xFF00, 0xFFFD),
			array(-1, 0x10000, 0x10FFFF),
		),
		'ns-plain-safe-in' => 'ns-anchor-char',
		'c-indicator'      => array(
			0x2D, 0x3F, 0x3A, 0x2C, 0x5B, 0x5D, 0x7B, 0x7D, 0x23, 0x26, 0x2A, 0x21,
			0x7C, 0x3E, 0x27, 0x22, 0x25, 0x40, 0x60
		),
		'c-ns-esc-char'    => array(
			-2, 0x5C, array(
				0x30, 0x61, 0x62, 0x64, 0x74, 0x09, 0x6E, 0x76, 0x66, 0x72, 0x65, 0x20, 0x22, 0x2F, 0x4E, 0x5F, 0x4C,
				0x50,
				array(
					-2, 0x78, array(-3, 2, array(array(-1, 0x30, 0x39), array(-1, 0x61, 0x66), array(-1, 0x41, 0x46)))
				),
				array(
					-2, 0x75, array(-3, 4, array(array(-1, 0x30, 0x39), array(-1, 0x61, 0x66), array(-1, 0x41, 0x46)))
				),
				array(
					-2, 0x55, array(-3, 6, array(array(-1, 0x30, 0x39), array(-1, 0x61, 0x66), array(-1, 0x41, 0x46)))
				)
			)
		)
	);

	/**
	 *
	 */
	static protected function _init()
	{
		if (self::$_initialized) {
			return;
		}
		self::$_initialized = true;
		self::_compilePatterns(self::$_ptn);
	}

	/**
	 * @param array $arr
	 */
	static protected function _compilePatterns(&$arr)
	{
		foreach ($arr as $k => $v) {
			if (is_string($v)) {
				$arr[$k] = self::$_ptn[$v];
			}
			elseif (is_array($v)) {
				self::_compilePatterns($arr[$k]);
			}
		}
	}

	###

	/**
	 * @var string
	 */
	protected $_documentClass;

	/**
	 * @var string
	 */
	protected $_encoding;

	/**
	 * @var array
	 */
	protected $_marks;

	/**
	 * @var mixed
	 */
	protected $_lastMarkContent = false;

	/**
	 * @var mixed
	 */
	protected $_lastMarkContentEnd;

	/**
	 * @var string
	 */
	protected $_source;

	/**
	 * @var array
	 */
	protected $_stream;

	/**
	 * @var int
	 */
	protected $_length;

	/**
	 *
	 * @var int
	 */
	protected $_cursor;

	/**
	 * @param string $encoding
	 * @param string $documentClass
	 */
	public function __construct($encoding = 'UTF-8', $documentClass = 'org\shypl\common\yaml\Document')
	{
		$this->_encoding = $encoding;
		$this->_documentClass = $documentClass;
	}

	/**
	 * @param string $source
	 * @param int    $offset
	 * @param int    $length
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return Document[]
	 */
	public function parse($source, $offset = 0, $length = null)
	{
		self::_init();

		$this->_source = $source;
		$this->_stream = $this->_convert($this->_source);
		$this->_length = count($this->_stream);
		$this->_cursor = 0;
		$this->_marks = array();

		$docs = array();

		try {
			$this->_ptn211();

			if ($this->_cursor < $this->_length) {
				throw new ParserException('Invalid syntax');
			}

			$this->_compareScalarMarks();
			$docs = $this->_analyseMarks($offset, $length, $docs);
		}
		catch (ParserException $e) {
			$pos = $this->_getCursorPosition();
			$e->setErrorPosition($pos[0], $pos[1]);
			throw $e;
		}

		$this->_source = null;
		$this->_stream = null;
		$this->_length = null;
		$this->_cursor = null;
		$this->_marks = null;
		$this->_lastMarkContent = false;
		$this->_lastMarkContentEnd = null;

		return $docs;
	}

	/**
	 * @param string $string
	 *
	 * @return array
	 */
	protected function _convert($string)
	{
		$encoding = mb_detect_encoding($string);
		if ($encoding && $encoding !== $this->_encoding) {
			$string = mb_convert_encoding($string, $this->_encoding, $encoding);
		}

		$stream = array();
		$length = mb_strlen($string, $this->_encoding);
		for ($i = 0; $i < $length; $i++) {
			$stream[] = hexdec(bin2hex(mb_substr($string, $i, 1, $this->_encoding)));
		}

		return $stream;
	}

	/**
	 * @param int $from
	 * @param int $to
	 *
	 * @return string
	 */
	protected function _getString($from, $to)
	{
		return mb_substr($this->_source, $from, $to - $from, $this->_encoding);
	}

	/**
	 * @param int $cursor
	 *
	 * @return array
	 */
	protected function _getCursorPosition($cursor = null)
	{
		if (null === $cursor) {
			$cursor = $this->_cursor;
		}

		$c = 0;
		$line = 1;
		$cell = 0;
		reset($this->_stream);
		do {
			$char = current($this->_stream);
			if ($char === 0x0A || $char === 0x0D) {
				++$line;
				$cell = 0;
				if ($char === 0x0D && (isset($this->_stream[$c + 1]) && $this->_stream[$c + 1] === 0x0A)) {
					next($this->_stream);
					$c++;
				}
			}
			else {
				++$cell;
			}
		}
		while (next($this->_stream) && $c++ < $cursor);
		return array($line, $cell);
	}

	/**
	 *
	 */
	protected function _compareScalarMarks()
	{
		$last = false;
		foreach ($this->_marks as $i => $mark) {
			$mn = is_array($mark) ? $mark[0] : $mark;
			if ($mn === self::MARK_SCALAR) {
				$last = $i;
				$this->_marks[$i] = array(self::MARK_SCALAR, array());
			}
			elseif ($mn === self::MARK_CONTENT_STRING || $mn === self::MARK_CONTENT_SPACE
				|| $mn === self::MARK_CONTENT_BREAK
			) {
				unset($this->_marks[$i]);
				switch ($mn) {
					case self::MARK_CONTENT_SPACE:
						$this->_marks[$last][1][] = ' ';
						break;
					case self::MARK_CONTENT_BREAK:
						$this->_marks[$last][1][] = "\n";
						break;
					case self::MARK_CONTENT_STRING:
						$this->_marks[$last][1][] = $this->_getString($mark[1], $mark[2]);
						break;
				}
			}
			elseif ($mn === self::MARK_END && $last) {
				$last = false;
				unset($this->_marks[$i]);
			}
			elseif ($mn === self::MARK_NULL) {
				$this->_marks[$i] = array(self::MARK_SCALAR, array('null'));
			}
		}
	}

	/**
	 * @param int $offset
	 * @param int $length
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return array
	 */
	protected function _analyseMarks($offset, $length)
	{
		$docs = array();
		$version = false;
		$directives = array();
		$tags = array();
		$isRoot = false;
		$tag = null; /** @var $tag array */
		$node = null;
		$collection = array();
		$mapKeys = array();
		$anchors = array();
		$anchor = false;
		$i = 0;

		/**
		 * @var $doc \org\shypl\common\yaml\Document
		 */
		$doc = null;

		foreach ($this->_marks as $mark) {
			$mn = is_array($mark) ? $mark[0] : $mark;

			if ($offset > 0) {
				if ($mn === self::MARK_DOCUMENT_END) {
					--$offset;
				}
				continue;
			}


			/// nodes
			if ($mn === self::MARK_SCALAR
				|| $mn === self::MARK_SEQUENCE
				|| $mn === self::MARK_MAPPING
				|| $mn === self::MARK_ALIAS
			) {
				$cln = end($collection);

				///
				switch ($mn) {
					// SCALAR
					case self::MARK_SCALAR:
						$content = join('', $mark[1]);
						if ($tag) {
							if (!$this->_checkTag($tag, $tags)) {
								$this->_cursor($tag[2]);
								throw new ParserException('Found undefined tag handle ' . $tag[0]);
							}
						}
						else {
							$tag = array('!!');
							if (preg_match('/^(null|NULL|Null|~)$/', $content)) {
								$tag[1] = 'null';
							}
							elseif (preg_match('/^(true|True|TRUE|false|False|FALSE)$/', $content)) {
								$tag[1] = 'bool';
							}
							elseif (preg_match('/^([\-\+]?[0-9]+|0o[0-7]+|0x[0-9a-fA-F]+)$/', $content))
							{
								$tag[1] = 'int';
							}
							elseif (preg_match('/^[\-\+]?(\.[0-9]+|[0-9]+(\.[0-9]*)?)([eE][\-\+]?[0-9]+)?$/', $content))
							{
								$tag[1] = 'float';
							}
							elseif (preg_match('/^[\-\+]?(\.inf|\.Inf|\.INF)$/', $content)) {
								$tag[1] = 'float';
							}
							elseif (preg_match('/^(\.nan|\.NaN|\.NAN)$/', $content)) {
								$tag[1] = 'float';
							}
							else {
								$tag[1] = 'str';
							}
						}

						if (!$doc) {
							throw new \RuntimeException('$doc is not object');
						}

						$node = $doc->createScalarNode($content, $tag[1] ? $tag[1] : 'str', $tag[0]);
						break;

					// SEQUENCE
					case self::MARK_SEQUENCE:
						if ($tag) {
							if (!$this->_checkTag($tag, $tags)) {
								$this->_cursor($tag[2]);
								throw new ParserException('Found undefined tag handle ' . $tag[0]);
							}
							$node = $doc->createSequenceNode($tag[1] ? $tag[1] : 'seq', $tag[0]);
						}
						else
						{
							$node = $doc->createSequenceNode();
						}
						$collection[] = $node;
						break;

					// MAPPING
					case self::MARK_MAPPING:
						if ($tag) {
							if (!$this->_checkTag($tag, $tags)) {
								$this->_cursor($tag[2]);
								throw new ParserException('Found undefined tag handle ' . $tag[0]);
							}
							$node = $doc->createMappingNode($tag[1] ? $tag[1] : 'map', $tag[0]);
						}
						else
						{
							$node = $doc->createMappingNode();
						}
						$collection[] = $node;
						break;

					// ALIAS
					case self::MARK_ALIAS:
						$alias = $this->_getString($mark[1], $mark[2]);
						if (!isset($anchors[$alias])) {
							$this->_cursor($mark[1]);
							throw new ParserException('Found undefined alias ' . $alias);
						}
						$node = clone $anchors[$alias];
						break;
				}

				// anchored node
				if ($anchor) {
					$anchors[$anchor] = $node;
					$anchor = false;
				}

				// collection
				if ($cln instanceof node\Sequence) {
					/**
					 * @var $cln \org\shypl\common\yaml\node\Sequence
					 */
					$cln->addItem($node);
				}
				elseif ($cln instanceof node\Mapping) {
					$key = end($mapKeys);
					if ($key && $key[1]) {
						/**
						 * @var $cln \org\shypl\common\yaml\node\Mapping
						 */
						$cln->addItem($key[0], $node);
						array_pop($mapKeys);
					}
					else
					{
						$mapKeys[] = array($node, $node instanceof node\Scalar);
					}
				}
					// is root node
				elseif (!$isRoot) {
					$isRoot = true;
					$doc->setRootNode($node);
				}

				$tag = null;

				continue;
			}

			switch ($mn) {
				// end collection
				case self::MARK_END:
					array_pop($collection);
					$c = count($mapKeys);
					if ($c) {
						$mapKeys[$c - 1][1] = true;
					}
					break;

				// doc start
				case self::MARK_DOCUMENT_START:

					$docs[$i] = $doc = new $this->_documentClass($this->_encoding);

					if (false !== $version) {
						$doc->setVersion($version);
					}

					if (!empty($tags)) {
						foreach ($tags as $handle => $prefix) {
							$doc->setTag($handle, $prefix);
						}
					}

					if (!empty($directives)) {
						foreach ($directives as $dir) {
							if (empty($dir[2])) {
								$params = null;
							}
							else {
								$params = array();
								foreach ($dir[2] as $param) {
									$params[] = $this->_getString($param[0] + 1, $param[1]);
								}
							}
							$doc->addDirective(new directive\Reserved(
									$this->_getString($dir[1][0], $dir[1][1]),
									$params
								)
							);
						}
					}

					break;

				// doc end
				case self::MARK_DOCUMENT_END:
					++$i;
					$version = false;
					$directives = array();
					$tags = array();
					$doc = null;
					$isRoot = false;
					$node = null;
					$collection = array();
					$anchors = array();
					$anchor = false;
					if ($length && count($docs) === $length) {
						return $docs;
					}
					break;

				// anchor
				case self::MARK_ANCHOR:
					$anchor = $this->_getString($mark[1], $mark[2]);
					if (isset($anchors[$anchor])) {
						$this->_cursor($mark[1]);
						throw new ParserException('Second occurrence');
					}
					$anchors[$anchor] = null;
					break;

				// tag
				case self::MARK_TAG:
					if (is_array($mark)) {
						if (count($mark) === 3) {
							$tag =
								array(
									null, $this->_convertUriString($this->_getString($mark[1], $mark[2])),
									$mark[1]
								);
						}
						elseif (count($mark) === 4)
						{
							$tag = array(
								$mark[1] == 1 ? '!' : '!!', $this->_getString($mark[2], $mark[3]), $mark[2]
							);
						}
						elseif (count($mark) === 5)
						{
							$tag = array(
								$this->_getString($mark[2], $mark[3]),
								$this->_convertUriString($this->_getString($mark[3], $mark[4])), $mark[2]
							);
						}
					}
					else
					{
						$tag = array('!!', null);
					}
					break;

				// yaml directive
				case self::MARK_DIRECTIVE_YAML:
					$version = $this->_getString($mark[1], $mark[2]);
					break;

				// tag directive
				case self::MARK_DIRECTIVE_TAG:
					$handle = $this->_getString($mark[1][0], $mark[1][1]);
					if (isset($tags[$handle])) {
						$this->_cursor($mark[1][0]);
						throw new ParserException("Duplicate tag handle '$handle'");
					}
					$tags[$handle] = $this->_getString($mark[2][0], $mark[2][1]);
					break;

				// reserved directive
				case self::MARK_DIRECTIVE_RESERVED:
					$directives[] = $mark;
					break;

				default:
					throw new ParserException("Undefined mark '$mn'");
			}
		}

		return $docs;
	}

	/**
	 * @param array $tag
	 * @param array $tags
	 *
	 * @return bool
	 */
	protected function _checkTag($tag, $tags)
	{
		return ($tag[0] === '!' || $tag[0] === '!!')
			? true
			: isset($tags[$tag[0]]);
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	protected function _convertUriString($string)
	{
		return preg_replace_callback('/%([0-9a-f]{2})/ie', function($v){ return chr(hexdec($v[1])); }, $string);
	}

	/**
	 * @param string $name
	 * @param mixed  $a1
	 * @param mixed  $a2
	 *
	 * @return int
	 */
	protected function _addMark($name = self::MARK, $a1 = null, $a2 = null)
	{
		if (null === $a1) {
			$mark = $name;
			$this->_lastMarkContent = false;
		}
		else {
			if ($name === self::MARK_TAG) {
				$mark = func_get_args();
			}
			else {
				if ($name === self::MARK_CONTENT_STRING) {
					if (false !== $this->_lastMarkContent && $this->_lastMarkContentEnd === $a1) {
						if ($a1 !== $a2) {
							$this->_marks[$this->_lastMarkContent][2] = $a2;
							$this->_lastMarkContentEnd = $a2;
						}
						return $this->_lastMarkContent;
					}
					else {
						$this->_lastMarkContent = count($this->_marks);
						$this->_lastMarkContentEnd = $a2;
					}
				}
				$mark = array($name, $a1, $a2);
			}
		}
		return array_push($this->_marks, $mark) - 1;
	}

	/**
	 * @param int  $index
	 * @param bool $one
	 */
	protected function _removeMark($index, $one = false)
	{
		if ($one) {
			array_splice($this->_marks, $index, 1);
		}
		else {
			array_splice($this->_marks, $index);
		}
	}

	/**
	 * @param int $set
	 *
	 * @return int
	 */
	protected function _cursor($set = null)
	{
		$c = $this->_cursor;
		if (null !== $set) {
			if ($set < 0) {
				$this->_cursor += $set;
			}
			else {
				$this->_cursor = $set;
			}
		}
		return $c;
	}

	/**
	 * @param int  $c
	 * @param bool $is
	 *
	 * @return bool
	 */
	protected function _check($c, $is)
	{
		if (!$is) {
			$this->_cursor($c);
		}
		return $is;
	}

	/**
	 * @param mixed    $ptn
	 * @param int      $length
	 *
	 * @return bool
	 */
	protected function _read($ptn, $length = null)
	{
		if ($this->_cursor > $this->_length) {
			return false;
		}

		if (is_string($ptn)) {
			$ptn = self::$_ptn[$ptn];
		}

		$c = $this->_cursor;
		if (is_array($ptn)) {
			if ($ptn[0] === -1) { // interval
				if (!$this->_readCharInterval($ptn[1], $ptn[2], $length)) {
					$this->_cursor = $c;
					return false;
				}
				return true;
			}
			if ($ptn[0] === -2) { // succession
				reset($ptn);
				while (next($ptn)) {
					if (!$this->_read(current($ptn), 1)) {
						$this->_cursor = $c;
						return false;
					}
				}
				return true;
			}
			if ($ptn[0] === -3) { // repetition
				$count = $ptn[1];
				while (0 < $count--) {
					if (!$this->_read($ptn[2], 1)) {
						$this->_cursor = $c;
						return false;
					}
				}
				return true;
			}
			// search
			reset($ptn);
			$result = false;
			$i = 0;
			do {
				$r = false;
				if ($this->_read(current($ptn), $length)) {
					reset($ptn);
					$result = true;
					$r = true;
					if (++$i === $length) {
						break;
					}
				}
			}
			while ($r || next($ptn));
			return $result;
		}
		// char
		if (!$this->_readChar($ptn, $length)) {
			$this->_cursor = $c;
			return false;
		}
		return true;
	}

	/**
	 * @param int $char
	 * @param int $length
	 *
	 * @return bool
	 */
	protected function _readChar($char, $length)
	{
		$result = false;
		$count = 0;
		while ($this->_cursor !== $this->_length && $this->_stream[$this->_cursor] === $char) {
			++$this->_cursor;
			if ($length) {
				++$count;
				if ($length === $count) {
					return true;
				}
			}
			$result = true;
		}
		return $length ? false : $result;
	}

	/**
	 * @param int $from
	 * @param int $to
	 * @param int $length
	 *
	 * @return bool
	 */
	protected function _readCharInterval($from, $to, $length)
	{
		if ($this->_cursor === $this->_length) {
			return false;
		}

		$result = $next = false;
		$count = 0;
		do {
			$char = $this->_stream[$this->_cursor];
			if ($char >= $from && $char <= $to) {
				++$this->_cursor;
				$next = $this->_cursor !== $this->_length;
				$result = true;
				if ($length) {
					++$count;
					if ($length === $count) {
						return true;
					}
				}
			}
			else {
				$next = false;
			}
		}
		while ($next);

		return $result;
	}

	/**
	 * @return bool
	 */
	protected function _isSOL()
	{
		if ($this->_cursor === 0) {
			return true;
		}
		$char = $this->_stream[$this->_cursor - 1];
		return $char === 0x0A || $char === 0x0D;
	}

	/**
	 * @return bool
	 */
	protected function _isEOL()
	{
		if ($this->_isEOF()) {
			return true;
		}
		$char = $this->_stream[$this->_cursor];
		return $char === 0x0A || $char === 0x0D;
	}

	/**
	 * @return bool
	 */
	protected function _isEOF()
	{
		return $this->_cursor >= $this->_length;
	}

	/**
	 * @return bool
	 */
	protected function _ptn29() # b-as-line-feed
	{
		// b-break
		$c = $this->_cursor();
		$r = $this->_read('b-break', 1);
		if ($r) {
			$this->_addMark(self::MARK_CONTENT_BREAK);
		}
		else {
			$this->_cursor($c);
		}
		return $r;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn63($n) # s-indent(n)
	{
		// s-space × n
		if ($n === 0) {
			return true;
		}
		return $this->_read(0x20, $n);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn64($n) # s-indent(<n)
	{
		// s-space × m  /* Where m < n */
		$c = $this->_cursor();
		$this->_read(0x20);
		$m = $this->_cursor($c) - $c;
		if ($m < $n) {
			return $this->_ptn63($m);
		}
		return false;
	}

	/**
	 * @param $n
	 *
	 * @return bool
	 */
	protected function _ptn65($n) # s-indent(<=n)
	{
		// s-space × m  /* Where m ≤ n */
		$c = $this->_cursor();
		$this->_read(0x20);
		$m = $this->_cursor($c) - $c;
		if ($m <= $n) {
			return $this->_ptn63($m);
		}
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _ptn66() # s-separate-in-line
	{
		// s-white+ | /* Start of line */
		return $this->_read('s-white') || $this->_isSOL();
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn67($n, $c) # s-line-prefix(n,c)
	{
		switch ($c) {
			case self::CONTEXT_BLOCK_OUT:
			case self::CONTEXT_BLOCK_IN:
				//s-block-line-prefix = s-indent(n)
				return $this->_ptn63($n);
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				return $this->_ptn69($n);
		}
		throw new ParserException('Invalid pattern param "c"');
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn69($n) # s-flow-line-prefix(n)
	{
		// s-indent(n)  s-separate-in-line?
		if (!$this->_ptn63($n)) {
			return false;
		}
		$this->_ptn66();
		return true;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn70($n, $z) # l-empty(n,c)
	{
		// ( s-line-prefix(n,c)  | s-indent(<n) ) b-as-line-feed
		$c = $this->_cursor();
		if (!$this->_ptn67($n, $z)) {
			if (!$this->_ptn64($n)) {
				return $this->_check($c, false);
			}
		}
		return $this->_check($c, $this->_ptn29($n));
	}

	/**
	 * @param int $n
	 * @param int $z
	 * @return bool
	 */
	protected function _ptn71($n, $z) # b-l-trimmed(n,c)
	{
		// b-non-content  l-empty(n,c)+
		$c = $this->_cursor();
		if (!$this->_read('b-break', 1)) {
			return false;
		}
		$r = false;
		while ($this->_ptn70($n, $z)) {
			$r = true;
		}
		return $this->_check($c, $r);
	}

	/**
	 * @return bool
	 */
	protected function _ptn72() # b-as-space
	{
		//b-break
		$r = $this->_read('b-break', 1);
		if ($r) {
			$this->_addMark(self::MARK_CONTENT_SPACE);
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn73($n, $c) # b-l-folded(n,c)
	{
		//b-l-trimmed(n,c)  | b-as-space
		return $this->_ptn71($n, $c) || $this->_ptn72();
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn74($n) # s-flow-folded(n)
	{
		// s-separate-in-line? b-l-folded(n,flow-in) s-flow-line-prefix(n)
		$i = $this->_addMark();
		$c = $this->_cursor();
		$this->_ptn66();
		$r = $this->_ptn73($n, self::CONTEXT_FLOW_IN) && $this->_ptn69($n);
		$this->_removeMark($i, $r);
		$this->_check($c, $r);
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn75() # c-nb-comment-text
	{
		// “#”  nb-char*
		if (!$this->_read(0x23, 1)) {
			return false;
		}
		$this->_read('nb-char');
		return true;
	}

	/**
	 * @return bool
	 */
	protected function _ptn76() # b-comment
	{
		// b-non-content | /* End of file */
		return $this->_read('b-break', 1) || $this->_isEOF();
	}

	/**
	 * @return bool
	 */
	protected function _ptn77() # s-b-comment
	{
		// ( s-separate-in-line  c-nb-comment-text? )? b-comment
		if ($this->_ptn66()) {
			$this->_ptn75();
		}
		return $this->_ptn76();
	}

	/**
	 * @return bool
	 */
	protected function _ptn78() # l-comment
	{
		// s-separate-in-line c-nb-comment-text? b-comment
		$c = $this->_cursor();
		$r = $this->_check($c, $this->_ptn66() && ($this->_ptn75() || true) && $this->_ptn76());
		if ($r && $this->_isEOF()) {
			return false;
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn79() # s-l-comments
	{
		// ( s-b-comment | /* Start of line */ ) l-comment*
		if ($this->_ptn77() || $this->_isSOL()) {
			//$c = $this->cursor();
			while ($this->_ptn78()) {
				;
			} //if ($c === $this->cursor()) return true;
			return true;
		}
		return false;
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn80($n, $c) # s-separate(n,c)
	{
		/*
		c = block-out > s-separate-lines(n)
		c = block-in  > s-separate-lines(n)
		c = flow-out  > s-separate-lines(n)
		c = flow-in   > s-separate-lines(n)
		c = block-key > s-separate-in-line
		c = flow-key  > s-separate-in-line
		 */
		switch ($c) {
			case self::CONTEXT_BLOCK_OUT:
			case self::CONTEXT_BLOCK_IN:
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				return $this->_ptn81($n);
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				return $this->_ptn66();
		}
		throw new ParserException('Invalid pattern param c = ' . $c);
	}

	/**
	 * @param int $n
	 * @return bool
	 */
	protected function _ptn81($n) # s-separate-lines(n)
	{
		// ( s-l-comments  s-flow-line-prefix(n) ) | s-separate-in-line
		if ($this->_check($this->_cursor(),
			$this->_ptn79() && $this->_ptn69($n)
		)
		) {
			return true;
		}
		return $this->_ptn66();
	}

	/**
	 * @return bool
	 */
	protected function _ptn82() # l-directive
	{
		// “%” ( ns-yaml-directive | ns-tag-directive | ns-reserved-directive ) s-l-comments
		return $this->_check($this->_cursor(),
			$this->_read(0x25, 1)
				&& ($this->_ptn86() | $this->_ptn88() | $this->_ptn83())
				&& $this->_ptn79()
		);
	}

	/**
	 * @return bool
	 */
	protected function _ptn83() # ns-reserved-directive
	{
		// ns-directive-name ( s-separate-in-line ns-directive-parameter )*
		$c = $this->_cursor();
		if (!$this->_read('ns-char')) { //ns-char+
			return false;
		}
		$n = array($c, $this->_cursor());
		$p = array();

		while (true) {
			$c = $this->_cursor();
			if (!$this->_ptn66()) {
				break;
			}
			if ($this->_check($c, $this->_read('ns-char'))) {
				$p[] = array($c, $this->_cursor());
			}
			else {
				break;
			}
		}

		$this->_addMark(self::MARK_DIRECTIVE_RESERVED, $n, $p);

		return true;
	}

	/**
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn86() # ns-yaml-directive
	{
		// “Y” “A” “M” “L” s-separate-in-line ns-yaml-version
		$c = $this->_cursor();
		if (!$this->_read(array(-2, 0x59, 0x41, 0x4d, 0x4c), 1)) {
			return false;
		}
		if (!$this->_ptn66()) {
			return $this->_check($c, false);
		}

		// ns-yaml-version = ns-dec-digit+ “.” ns-dec-digit+
		$c = $this->_cursor();
		$r = ($this->_read('ns-dec-digit') && $this->_read(0x2e) && $this->_read('ns-dec-digit'));
		if (!$r) {
			throw new ParserException('Invalid version param in YAML directive');
		}

		$this->_addMark(self::MARK_DIRECTIVE_YAML, $c, $this->_cursor());

		return true;
	}

	/**
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn88() # ns-tag-directive
	{
		// “T” “A” “G” s-separate-in-line c-tag-handle s-separate-in-line ns-tag-prefix
		$c = $this->_cursor();
		if (!$this->_read(array(-2, 0x54, 0x41, 0x47))) {
			return false;
		}
		if (!$this->_ptn66()) {
			return $this->_check($c, false);
		}

		$c = $this->_cursor();
		if (!$this->_ptn89()) {
			throw new ParserException('Invalid handle param in TAG directive');
		}
		$h = array($c, $this->_cursor());

		if (!$this->_ptn66()) {
			throw new ParserException('Invalid handle param in TAG directive');
		}

		$c = $this->_cursor();
		if (!$this->_ptn93()) {
			throw new ParserException('Invalid prefix param in TAG directive');
		}

		$this->_addMark(self::MARK_DIRECTIVE_TAG, $h, array($c, $this->_cursor()));

		return true;
	}

	/**
	 * @return bool
	 */
	protected function _ptn89() # c-tag-handle
	{
		// c-named-tag-handle | c-secondary-tag-handle | c-primary-tag-handle

		// c-named-tag-handle = “!”  ns-word-char+ “!”
		$c = $this->_cursor();
		$r = $this->_read(0x21, 1) && $this->_read('ns-word-char') && $this->_read(0x21, 1);
		if ($this->_check($c, $r)) {
			return 3;
		}

		// c-secondary-tag-handle ::=  “!”  “!”
		$r = $this->_read(0x21, 2);
		if ($this->_check($c, $r)) {
			return 2;
		}

		// c-primary-tag-handle = “!”
		$r = $this->_read(0x21, 1);
		if ($this->_check($c, $r)) {
			return 1;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	protected function _ptn93() # ns-tag-prefix
	{
		// c-ns-local-tag-prefix  | ns-global-tag-prefix

		// c-ns-local-tag-prefix = “!”  ns-uri-char*
		$c = $this->_cursor();
		if ($this->_read(0x21, 1)) {
			$this->_read('ns-uri-char');
			return true;
		}
		$this->_check($c, false);

		// ns-global-tag-prefix = ns-tag-char  ns-uri-char*

		if ($this->_read('ns-tag-char', 1)) {
			$this->_read('ns-uri-char');
			return true;
		}
		return $this->_check($c, false);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn96($n, $z) # c-ns-properties(n,c)
	{
		// ( c-ns-tag-property ( s-separate(n,c) c-ns-anchor-property )? ) | ( c-ns-anchor-property ( s-separate(n,c) c-ns-tag-property )? )
		$i = $this->_addMark();
		if ($this->_ptn97()) {
			$c = $this->_cursor();
			$r = $this->_ptn80($n, $z) && $this->_ptn101();
			$this->_check($c, $r);
			$this->_removeMark($i, true);
			return true;
		}
		if ($this->_ptn101()) {
			$c = $this->_cursor();
			$r = $this->_ptn80($n, $z) && $this->_ptn97();
			$this->_check($c, $r);
			$this->_removeMark($i, true);
			return true;
		}
		$this->_removeMark($i);
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _ptn97() # c-ns-tag-property
	{
		// c-verbatim-tag | c-ns-shorthand-tag | c-non-specific-tag
		return $this->_ptn98() || $this->_ptn99() || $this->_ptn100();
	}

	/**
	 * @return bool
	 */
	protected function _ptn98() # c-verbatim-tag
	{
		//“!”  “<” ns-uri-char+ “>”
		$c = $this->_cursor();
		$r = $this->_read(0x21, 1) && $this->_read(0x3C, 1)
			&& $this->_read('ns-uri-char')
			&& $this->_read(0x3E, 1);
		if ($this->_check($c, $r)) {
			$this->_addMark(self::MARK_TAG, $c + 2, $this->_cursor() - 1);
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn99() # c-ns-shorthand-tag
	{
		// c-tag-handle  ns-tag-char+
		$c = $this->_cursor();
		$h = $this->_ptn89();
		if (!$h) {
			return false;
		}
		$c2 = $this->_cursor();
		$r = $this->_check($c, $this->_read('ns-tag-char'));
		if ($r) {
			if ($h === 3) {
				$this->_addMark(self::MARK_TAG, false, $c, $c2, $this->_cursor());
			}
			else {
				$this->_addMark(self::MARK_TAG, $h, $c2, $this->_cursor());
			}
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn100() # c-non-specific-tag
	{
		if ($this->_read(0x21, 1)) {
			$this->_addMark(self::MARK_TAG);
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _ptn101() # c-ns-anchor-property
	{
		// “&”  ns-anchor-name
		if (!$this->_read(0x26, 1)) {
			return false;
		}
		$c = $this->_cursor();
		$r = $this->_read('ns-anchor-char');
		if ($this->_check($c, $r)) {
			$this->_addMark(self::MARK_ANCHOR, $c, $this->_cursor());
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn104() # c-ns-alias-node
	{
		// “*”  ns-anchor-name
		if (!$this->_read(0x2A, 1)) {
			return false;
		}
		$c = $this->_cursor();
		$r = $this->_read('ns-anchor-char');
		if ($r) {
			$this->_addMark(self::MARK_ALIAS, $c, $this->_cursor());
		}
		else {
			$this->_cursor($c);
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn106() # e-node
	{
		// e-scalar
		$this->_addMark(self::MARK_NULL);
		return true;
	}

	/**
	 * @return bool
	 */
	protected function _ptn107() # nb-double-char
	{
		if ($this->_read('c-ns-esc-char', 1)) {
			return true;
		}
		$c = $this->_cursor();
		if ($this->_read(0x5C, 1) || $this->_read(0x22, 1)) {
			$this->_cursor($c);
			return false;
		}
		return $this->_read('nb-json', 1);
	}

	/**
	 * @return bool
	 */
	protected function _ptn108() # ns-double-char
	{
		//nb-double-char  - s-white
		// nb-double-char ::= c-ns-esc-char  | ( nb-json  - “\”  - “"” )
		if ($this->_read('c-ns-esc-char', 1)) {
			return true;
		}
		$c = $this->_cursor();
		if ($this->_read(0x5C, 1) || $this->_read(0x22, 1) || $this->_read('s-white', 1)) {
			$this->_cursor($c);
			return false;
		}
		return $this->_read('nb-json', 1);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn109($n, $z) # c-double-quoted(n,c)
	{
		//“"”  nb-double-text(n,c)  “"”
		if (!$this->_read(0x22, 1)) {
			return false;
		}
		$c = $this->_cursor();
		$this->_addMark(self::MARK_SCALAR);
		$r = $this->_ptn110($n, $z) && $this->_read(0x22, 1);
		if ($r) {
			$this->_addMark(self::MARK_CONTENT_STRING, $c, $this->_cursor() - 1);
			$this->_addMark(self::MARK_END);
			return false; #!check this
		}
		else {
			return $this->_check($c, $r);
		}
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn110($n, $z) # nb-double-text(n,c)
	{
		//c = flow-out  > nb-double-multi-line(n)
		//c = flow-in   > nb-double-multi-line(n)
		//c = block-key > nb-double-one-line
		//c = flow-key  > nb-double-one-line
		switch ($z) {
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				return $this->_ptn116($n);
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				return $this->_ptn111();
		}
		throw new ParserException('Invalid pattern param "c"');
	}

	/**
	 * @return bool
	 */
	protected function _ptn111() # nb-double-one-line
	{
		// nb-double-char*
		$r = false;
		while ($this->_ptn107()) {
			$r = true;
		}
		return $r;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn112($n) # s-double-escaped(n)
	{
		// s-white* “\”  b-non-content l-empty(n,flow-in)* s-flow-line-prefix(n)
		$c = $this->_cursor();
		$r = $this->_check($c, ($this->_read('s-white') || true)
			&& $this->_read(0x5C, 1)
			&& $this->_read('b-break', 1)
		);
		if (!$r) {
			return false;
		}
		while ($this->_ptn70($n, self::CONTEXT_FLOW_IN)) {
			;
		}
		return $this->_check($c, $this->_ptn69($n));
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn113($n) # s-double-break(n)
	{
		// s-double-escaped(n)  | s-flow-folded(n)
		return $this->_ptn112($n) || $this->_ptn74($n);
	}

	/**
	 * @return bool
	 */
	protected function _ptn114() # nb-ns-double-in-line
	{
		//( s-white* ns-double-char )*
		while (true) {
			$c = $this->_cursor();
			$this->_read('s-white');
			if (!$this->_ptn108()) {
				$this->_cursor($c);
				break;
			}
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn115($n) # s-double-next-line(n)
	{
		//s-double-break(n) ( ns-double-char nb-ns-double-in-line ( s-double-next-line(n) | s-white* ) )?
		if (!$this->_ptn113($n)) {
			return false;
		}
		$this->_check($this->_cursor(), $this->_ptn108() && $this->_ptn114()
			&& ($this->_ptn115($n) || $this->_read('s-white') || true)
		);
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn116($n) # nb-double-multi-line(n)
	{
		// nb-ns-double-in-line ( s-double-next-line(n) | s-white* )
		return $this->_ptn114()
			&& ($this->_ptn115($n) || $this->_read('s-white') || true);
	}

	/**
	 * @return bool
	 */
	protected function _ptn118() # nb-single-char
	{
		//c-quoted-quote | ( nb-json  - “'” )
		if ($this->_read(0x27, 2)) {
			return true;
		}
		$c = $this->_cursor();
		if ($this->_read(0x27, 1)) {
			$this->_cursor($c);
			return false;
		}
		return $this->_read('nb-json', 1);
	}

	/**
	 * @return bool
	 */
	protected function _ptn119() # ns-single-char
	{
		// nb-single-char  - s-white
		//  nb-single-char ::= c-quoted-quote  | ( nb-json  - “'” )
		//  c-quoted-quote ::= “'”  “'”
		if ($this->_read(0x27, 2)) {
			return true;
		}
		$c = $this->_cursor();
		if ($this->_read(0x27, 1) || $this->_read('s-white', 1)) {
			$this->_cursor($c);
			return false;
		}
		return $this->_read('nb-json', 1);
	}

	/**
	 * @param int $n
	 * @param int $z
	 * @return bool
	 */
	protected function _ptn120($n, $z) # c-single-quoted(n,c)
	{
		// “'”  nb-single-text(n,c)  “'”
		if (!$this->_read(0x27, 1)) {
			return false;
		}
		$c = $this->_cursor();
		$i = $this->_addMark(self::MARK_SCALAR);
		$r = $this->_ptn121($n, $z) && $this->_read(0x27, 1);
		if ($r) {
			$this->_addMark(self::MARK_CONTENT_STRING, $c, $this->_cursor() - 1);
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i, false);
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn121($n, $z) # nb-single-text(n,c)
	{
		//c = flow-out  > nb-single-multi-line(n)
		//c = flow-in   > nb-single-multi-line(n)
		//c = block-key > nb-single-one-line
		//c = flow-key  > nb-single-one-line
		switch ($z) {
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				return $this->_ptn125($n);
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				return $this->_ptn122();
		}
		throw new ParserException('Invalid pattern param "c"');
	}

	/**
	 * @return bool
	 */
	protected function _ptn122() # nb-single-one-line
	{
		// nb-single-char*
		$r = false;
		while ($this->_ptn118()) {
			$r = true;
		}
		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn123() # nb-ns-single-in-line
	{
		// ( s-white* ns-single-char )*
		while (true) {
			$c = $this->_cursor();
			$this->_read('s-white');
			if (!$this->_ptn119()) {
				$this->_cursor($c);
				break;
			}
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn124($n) # s-single-next-line(n)
	{
		// s-flow-folded(n) ( ns-single-char nb-ns-single-in-line ( s-single-next-line(n) | s-white* ) )?
		if (!$this->_ptn74($n)) {
			return false;
		}
		$this->_check($this->_cursor(), $this->_ptn119() && $this->_ptn123()
			&& ($this->_ptn124($n) || $this->_read('s-white') || true)
		);
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn125($n) # nb-single-multi-line(n)
	{
		// nb-ns-single-in-line ( s-single-next-line(n) | s-white* )
		return $this->_ptn123()
			&& ($this->_ptn124($n) || $this->_read('s-white') || true);
	}

	/**
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn126($z) # ns-plain-first(c)
	{
		// ( ns-char - c-indicator ) | ( ( “?” | “:” | “-” )  /* Followed by an ns-plain-safe(c)) */ )
		$c = $this->_cursor();
		if ($this->_read('ns-char', 1)) {
			$c2 = $this->_cursor($c);
			if (!$this->_read('c-indicator', 1)) {
				$this->_cursor($c2);
				$this->_addMark(self::MARK_CONTENT_STRING, $c, $c2);
				return true;
			}
			$this->_cursor($c);
		}

		if ($this->_read(array(0x3F, 0x3A, 0x2D), 1)) {
			$c2 = $this->_cursor();
			$r = $this->_ptn127($z);
			if ($r) {
				$this->_cursor($c2);
				$this->_addMark(self::MARK_CONTENT_STRING, $c, $c2);
			}
			else {
				$this->_cursor($c);
			}
			return $r;
		}
		return false;
	}

	/**
	 * @param int $c
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn127($c) # ns-plain-safe(c)
	{
		//c = flow-out  > ns-plain-safe-out
		//c = flow-in   > ns-plain-safe-in
		//c = block-key > ns-plain-safe-out
		//c = flow-key  > ns-plain-safe-in
		switch ($c) {
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_BLOCK_KEY:
				//ns-char
				return $this->_read('ns-char', 1);
			case self::CONTEXT_FLOW_IN:
			case self::CONTEXT_FLOW_KEY:
				return $this->_read('ns-plain-safe-in', 1);
		}
		throw new ParserException('Invalid pattern param "c"');
	}

	/**
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn130($z) # ns-plain-char(c)
	{
		// ( ns-plain-safe(c)  - “:”  - “#” ) | ( /* An ns-char preceding */ “#” ) | ( “:” /* Followed by an ns-plain-safe(c) */ )
		$c = $this->_cursor();
		$r = $this->_ptn127($z);
		if ($r) {
			$ch = $this->_stream[$c];
			if ($ch !== 0x3A && $ch !== 0x23) {
				return true;
			}
			$this->_cursor($c);
		}
		if ($this->_read(0x23, 1)) {
			$this->_cursor($c - 1);
			if ($this->_read('ns-char', 1)) {
				$this->_cursor($c + 1);
				return true;
			}
			$this->_cursor($c);
		}
		if ($this->_read(0x3A, 1)) {
			if ($this->_ptn127($z)) {
				$this->_cursor($c + 1);
				return true;
			}
			$this->_cursor($c);
		}
		return false;
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn131($n, $c) # ns-plain(n,c)
	{
		//c = flow-out  > ns-plain-multi-line(n,c)
		//c = flow-in   > ns-plain-multi-line(n,c)
		//c = block-key > ns-plain-one-line(c)
		//c = flow-key  > ns-plain-one-line(c)
		switch ($c) {
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				return $this->_ptn135($n, $c);
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				return $this->_ptn133($c);
		}
		throw new ParserException('Invalid pattern param "c"');
	}

	/**
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn132($z) # nb-ns-plain-in-line(c)
	{
		// ( s-white* ns-plain-char(c) )*
		$c = $this->_cursor(-1);
		$r = ($this->_read(0x2D, 3) || $this->_read(0x2E, 3))
			&& ($this->_read(array(0x0A, 0x0D, 0x20, 0x09), 1) || $this->_isEOF());
		$this->_cursor($c);
		if ($r) {
			return false;
		}

		$c = $this->_cursor();
		while (true) {
			$c2 = $this->_cursor();
			$this->_read('s-white');
			if (!$this->_ptn130($z)) {
				$this->_cursor($c2);
				break;
			}
		}
		$this->_addMark(self::MARK_CONTENT_STRING, $c, $this->_cursor());
		return true;
	}

	/**
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn133($c) # ns-plain-one-line(c)
	{
		// ns-plain-first(c)  nb-ns-plain-in-line(c)
		$i = $this->_addMark();
		$r = $this->_ptn126($c) && $this->_ptn132($c);
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn134($n, $z) # s-ns-plain-next-line(n,c)
	{
		// s-flow-folded(n) ns-plain-char(c) nb-ns-plain-in-line(c)
		$i = $this->_addMark();
		$c = $this->_cursor();
		$r = $this->_ptn74($n) && $this->_ptn130($z) && $this->_ptn132($z);
		if ($r) {
			--$this->_marks[$this->_lastMarkContent][1];
		}
		$this->_check($c, $r);
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn135($n, $c) # ns-plain-multi-line(n,c)
	{
		//ns-plain-one-line(c) s-ns-plain-next-line(n,c)*
		if (!$this->_ptn133($c)) {
			return false;
		}
		while ($this->_ptn134($n, $c)) {
			;
		}
		return true;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn137($n, $z) # c-flow-sequence(n,c)
	{
		// “[”  s-separate(n,c)? ns-s-flow-seq-entries(n,in-flow(c))? “]”
		$c = $this->_cursor();
		if (!$this->_read(0x5B, 1)) {
			return false;
		}
		$i = $this->_addMark(self::MARK_SEQUENCE);
		$this->_ptn80($n, $z);
		switch ($z) { // in-flow(c)
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				$z = self::CONTEXT_FLOW_IN;
				break;
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				$z = self::CONTEXT_FLOW_KEY;
				break;
		}
		$this->_ptn138($n, $z);
		$r = $this->_read(0x5D, 1);
		if ($r) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i);
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn138($n, $z) # ns-s-flow-seq-entries(n,c)
	{
		// ns-flow-seq-entry(n,c)  s-separate(n,c)? ( “,” s-separate(n,c)? ns-s-flow-seq-entries(n,c)? )?
		if (!$this->_ptn139($n, $z)) {
			return false;
		}
		$this->_ptn80($n, $z);

		while ($this->_read(0x2C, 1)) {
			$this->_ptn80($n, $z);

			if (!$this->_ptn139($n, $z)) {
				break;
			}
			$this->_ptn80($n, $z);
		}

//		if ($this->_read(0x2C, 1)) {
//			$this->_ptn80($n, $z);
//			$this->_ptn138($n, $z);
//		}
		return true;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn139($n, $z) # ns-flow-seq-entry(n,c)
	{
		// ns-flow-pair(n,c)  | ns-flow-node(n,c)
		return $this->_ptn150($n, $z) || $this->_ptn161($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn140($n, $z) # c-flow-mapping(n,c)
	{
		// “{”  s-separate(n,c)? ns-s-flow-map-entries(n,in-flow(c))? “}”
		$c = $this->_cursor();
		if (!$this->_read(0x7B, 1)) {
			return false;
		}
		$i = $this->_addMark(self::MARK_MAPPING);
		$this->_ptn80($n, $z);
		switch ($z) { // in-flow(c)
			case self::CONTEXT_FLOW_OUT:
			case self::CONTEXT_FLOW_IN:
				$z = self::CONTEXT_FLOW_IN;
				break;
			case self::CONTEXT_BLOCK_KEY:
			case self::CONTEXT_FLOW_KEY:
				$z = self::CONTEXT_FLOW_KEY;
				break;
		}
		$this->_ptn141($n, $z);
		$r = $this->_read(0x7D, 1);
		if ($r) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i);
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn141($n, $z) # ns-s-flow-map-entries(n,c)
	{
		//ns-flow-map-entry(n,c)  s-separate(n,c)? ( “,” s-separate(n,c)? ns-s-flow-map-entries(n,c)? )?
		if (!$this->_ptn142($n, $z)) {
			return false;
		}
		$this->_ptn80($n, $z);
		if ($this->_read(0x2C, 1)) {
			$this->_ptn80($n, $z);
			$this->_ptn141($n, $z);
		}
		return true;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn142($n, $z) # ns-flow-map-entry(n,c)
	{
		// ( “?”  s-separate(n,c) ns-flow-map-explicit-entry(n,c) ) | ns-flow-map-implicit-entry(n,c)
		$c = $this->_cursor();
		$r = $this->_read(0x3F, 1)
			&& $this->_ptn80($n, $z)
			&& $this->_ptn143($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn144($n, $z);
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn143($n, $z) # ns-flow-map-explicit-entry(n,c)
	{
		// ns-flow-map-implicit-entry(n,c) | ( e-node /* Key */ e-node /* Value */ )
		if ($this->_ptn144($n, $z)) {
			return true;
		}
		return $this->_ptn106() && $this->_ptn106();
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn144($n, $z) # ns-flow-map-implicit-entry(n,c)
	{
		// ns-flow-map-yaml-key-entry(n,c) | c-ns-flow-map-empty-key-entry(n,c) | c-ns-flow-map-json-key-entry(n,c)
		return $this->_ptn145($n, $z) || $this->_ptn146($n, $z) || $this->_ptn148($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn145($n, $z) # ns-flow-map-yaml-key-entry(n,c)
	{
		// ns-flow-yaml-node(n,c) ( ( s-separate(n,c)? c-ns-flow-map-separate-value(n,c) ) | e-node )
		if (!$this->_ptn159($n, $z)) {
			return false;
		}
		$c = $this->_cursor();
		$this->_ptn80($n, $z);
		$r = $this->_ptn147($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn106();
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn146($n, $z) # ns-flow-map-yaml-key-entry(n,c)
	{
		// e-node /* Key */ c-ns-flow-map-separate-value(n,c)
		$c = $this->_cursor();
		$i = $this->_addMark();
		$this->_ptn106();
		$r = $this->_ptn147($n, $z);
		$this->_removeMark($i, $r);
		$this->_check($c, $r);
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn147($n, $z) # c-ns-flow-map-separate-value(n,c)
	{
		// “:”  /* Not followed by an ns-plain-safe(c) */ ( ( s-separate(n,c) ns-flow-node(n,c) ) | e-node /* Value */ )
		$c = $this->_cursor();
		if (!$this->_read(0x3A, 1)) {
			return false;
		}
		$c2 = $this->_cursor();
		$r = $this->_ptn127($z);
		if ($r) {
			$this->_cursor($c);
			return false;
		}
		$this->_cursor($c2);
		$c = $this->_cursor();
		$r = $this->_ptn80($n, $z) && $this->_ptn161($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn106();
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn148($n, $z) # c-ns-flow-map-json-key-entry(n,c)
	{
		// c-flow-json-node(n,c) ( ( s-separate(n,c)? c-ns-flow-map-adjacent-value(n,c) ) | e-node )
		if (!$this->_ptn160($n, $z)) {
			return false;
		}
		$c = $this->_cursor();
		$this->_ptn80($n, $z);
		$r = $this->_ptn149($n, $z);
		if (!$this->_check($c, $r)) {
			$r = $this->_ptn106();
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn149($n, $z) # c-ns-flow-map-adjacent-value(n,c)
	{
		// “:”  ( ( s-separate(n,c)? ns-flow-node(n,c) ) | e-node ) /* Value */
		if (!$this->_read(0x3A, 1)) {
			return false;
		}
		$c = $this->_cursor();
		$this->_ptn80($n, $z);
		$r = $this->_ptn161($n, $z);
		if (!$this->_check($c, $r)) {
			$r = $this->_ptn106();
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn150($n, $z) # ns-flow-pair(n,c)
	{
		//( “?”  s-separate(n,c) ns-flow-map-explicit-entry(n,c) ) | ns-flow-pair-entry(n,c)
		$i = $this->_addMark(self::MARK_MAPPING);
		$c = $this->_cursor();
		$r = $this->_read(0x3F, 1)
			&& $this->_ptn80($n, $z)
			&& $this->_ptn143($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn151($n, $z);
		}
		if (!$r) {
			$this->_removeMark($i);
		}
		else {
			$this->_addMark(self::MARK_END);
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn151($n, $z) # ns-flow-pair-entry(n,c)
	{
		// ns-flow-pair-yaml-key-entry(n,c) | c-ns-flow-map-empty-key-entry(n,c) | c-ns-flow-pair-json-key-entry(n,c)
		return $this->_ptn152($n, $z)
			|| $this->_ptn146($n, $z)
			|| $this->_ptn153($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn152($n, $z) # ns-flow-pair-yaml-key-entry(n,c)
	{
		// ns-s-implicit-yaml-key(flow-key) c-ns-flow-map-separate-value(n,c)
		return $this->_ptn154(self::CONTEXT_FLOW_KEY)
			&& $this->_ptn147($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn153($n, $z) # c-ns-flow-pair-json-key-entry(n,c)
	{
		// c-s-implicit-json-key(flow-key) c-ns-flow-map-adjacent-value(n,c)
		return $this->_ptn155(self::CONTEXT_FLOW_KEY)
			&& $this->_ptn149($n, $z);
	}

	/**
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn154($c) # ns-s-implicit-yaml-key(c)
	{
		// ns-flow-yaml-node(n/a,c)  s-separate-in-line? /* At most 1024 characters altogether */
		if (!$this->_ptn159(0, $c)) {
			return false;
		}
		$this->_ptn66();
		return true;
	}

	/**
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn155($c) # c-s-implicit-json-key(c)
	{
		// c-flow-json-node(n/a,c)  s-separate-in-line? /* At most 1024 characters altogether */
		if (!$this->_ptn160(0, $c)) {
			return false;
		}
		$this->_ptn66();
		return true;
	}

	/**
	 * @param int $n
	 * @param int $z
	 * @return bool
	 */
	protected function _ptn156($n, $z) # ns-flow-yaml-content(n,c)
	{
		// ns-plain(n,c)
		$i = $this->_addMark(self::MARK_SCALAR);
		$r = $this->_ptn131($n, $z);
		if ($r) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i, false);
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn157($n, $z) # c-flow-json-content(n,c)
	{
		//c-flow-sequence(n,c)  | c-flow-mapping(n,c) | c-single-quoted(n,c) | c-double-quoted(n,c)
		$i = $this->_addMark();
		$r = $this->_ptn137($n, $z) || $this->_ptn140($n, $z) || $this->_ptn120($n, $z) || $this->_ptn109($n, $z);
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn158($n, $z) # ns-flow-content(n,c)
	{
		// ns-flow-yaml-content(n,c)  | c-flow-json-content(n,c)
		return $this->_ptn156($n, $z) || $this->_ptn157($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn159($n, $z) # ns-flow-yaml-node(n,c)
	{
		// c-ns-alias-node | ns-flow-yaml-content(n,c) | ( c-ns-properties(n,c) ( ( s-separate(n,c) ns-flow-yaml-content(n,c) ) | e-scalar ) )
		if ($this->_ptn104()) {
			return true;
		}
		if ($this->_ptn156($n, $z)) {
			return true;
		}
		$i = $this->_addMark();
		if (!$this->_ptn96($n, $z)) {
			$this->_removeMark($i);
			return false;
		}
		$c = $this->_cursor();
		$r = $this->_ptn80($n, $z) && $this->_ptn156($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$this->_addMark(self::MARK_SCALAR, $c, $c);
			$r = true;
		}
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn160($n, $z) # c-flow-json-node(n,c)
	{
		// ( c-ns-properties(n,c)  s-separate(n,c) )? c-flow-json-content(n,c)
		$c = $this->_cursor();
		$i = $this->_addMark();
		$r = $this->_ptn96($n, $z) && $this->_ptn80($n, $z);
		if (!$r) {
			$this->_cursor($c);
		}
		$this->_removeMark($i, $r);
		return $this->_ptn157($n, $z);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn161($n, $z) # ns-flow-node(n,c)
	{
		// c-ns-alias-node | ns-flow-content(n,c) | ( c-ns-properties(n,c) ( ( s-separate(n,c) ns-flow-content(n,c) ) | e-scalar ) )
		if ($this->_ptn104()) {
			return true;
		}
		if ($this->_ptn158($n, $z)) {
			return true;
		}
		$i = $this->_addMark();
		if (!$this->_ptn96($n, $z)) {
			$this->_removeMark($i);
			return false;
		}
		$c = $this->_cursor();
		$r = $this->_ptn80($n, $z) && $this->_ptn158($n, $z);
		if (!$r) {
			$this->_cursor($c);
			$this->_addMark(self::MARK_SCALAR, $c, $c);
			$r = true;
		}
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return array
	 */
	protected function _ptn162() # c-b-block-header(m,t)
	{
		// ( ( c-indentation-indicator(m) c-chomping-indicator(t) ) | ( c-chomping-indicator(t) c-indentation-indicator(m) ) ) s-b-comment
		$m = $this->_ptn163();
		$t = false;
		if ($m !== false) {
			$t = $this->_ptn164();
			$r = ($t !== false);
		}
		else {
			$r = false;
		}

		if (!$r) {
			$t = $this->_ptn164();
			if ($t !== false) {
				$m = $this->_ptn163();
				$r = ($m !== false);
			}
			else {
				$r = false;
			}
		}

		if (!$r || !$this->_ptn77()) {
			throw new ParserException('Invalid syntax in header of block');
		}

		return array($m, $t);
	}

	/**
	 * @return bool
	 */
	protected function _ptn163() # c-indentation-indicator(m)
	{
		// ns-dec-digit > m = ns-dec-digit - #x30 /* Empty */ > m = auto-detect()
		$c = $this->_cursor();
		if ($this->_read(array(-1, 0x31, 0x39), 1)) {
			return (int)$this->_getString($c, $this->_cursor());
		}
		if ($this->_isEOL()) {
			$this->_read('b-break');
			$c2 = $this->_cursor();
			$this->_read(0x20);
			$m = $this->_cursor() - $c2;
			$this->_cursor($c);
			return $m;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _ptn164() # c-chomping-indicator(t)
	{
		// “-” > t = strip, “+” > t = keep, /* Empty */ > t = clip
		if ($this->_read(0x2D)) {
			return self::BLOCK_STRIP;
		}
		if ($this->_read(0x2B)) {
			return self::BLOCK_KEEP;
		}
		if ($this->_isEOL()) {
			return self::BLOCK_CLIP;
		}
		return false;
	}

	/**
	 * @param int $t
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn165($t) # b-chomped-last(t)
	{
		/* t = strip > b-non-content | /* End of file
		 * t = clip  > b-as-line-feed | /* End of file
		 * t = keep  > b-as-line-feed | /* End of file
		 */
		switch ($t) {
			case self::BLOCK_STRIP:
				return $this->_read('b-break', 1) || $this->_isEOF();
			case self::BLOCK_CLIP:
			case self::BLOCK_KEEP:
				return $this->_ptn29() || $this->_isEOF();
		}
		throw new ParserException('Invalid pattern param "t"');
	}

	/**
	 * @param int $n
	 * @param int $t
	 *
	 * @throws \org\shypl\common\yaml\ParserException
	 * @return bool
	 */
	protected function _ptn166($n, $t) # l-chomped-empty(n,t)
	{
		//t = strip > l-strip-empty(n)
		//t = clip  > l-strip-empty(n)
		//t = keep  > l-keep-empty(n)
		switch ($t) {
			case self::BLOCK_STRIP:
			case self::BLOCK_CLIP:
				return $this->_ptn167($n);
			case self::BLOCK_KEEP:
				return $this->_ptn168($n);
		}
		throw new ParserException('Invalid pattern param "t"');
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn167($n) # l-strip-empty(n)
	{
		// ( s-indent(≤n) b-non-content )* l-trail-comments(n)?
		while (true) {
			$c = $this->_cursor();
			if ($this->_ptn65($n)) {
				break;
			}
			if (!$this->_read('b-break', 1)) {
				$this->_cursor($c);
				break;
			}
		}
		$this->_ptn169($n);
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn168($n) # l-keep-empty(n)
	{
		// l-empty(n,block-in)* l-trail-comments(n)?
		while ($this->_ptn70($n, self::CONTEXT_BLOCK_IN)) {
			;
		}
		$this->_ptn169($n);
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn169($n) # l-trail-comments(n)
	{
		// s-indent(<n)  c-nb-comment-text  b-comment l-comment*
		$c = $this->_cursor();
		$r = $this->_ptn64($n) && $this->_ptn75() && $this->_ptn76();
		if (!$r) {
			$this->_cursor($c);
			return false;
		}
		while ($this->_ptn78()) {
			;
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn170($n) # c-l+literal(n)
	{
		// “|”  c-b-block-header(m,t) l-literal-content(n+m,t)
		if (!$this->_read(0x7C)) {
			return false;
		}
		list($m, $t) = $this->_ptn162();
		if ($m <= $n) {
			return false;
		}
		return $this->_ptn173($m, $t);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn171($n) # l-nb-literal-text(n)
	{
		// l-empty(n,block-in)* s-indent(n) nb-char+

		$c = $this->_cursor();
		while ($this->_ptn70($n, self::CONTEXT_BLOCK_IN)) {
			;
		}
		if (!$this->_ptn63($n)) {
			return $this->_check($c, false);
		}

		$c2 = $this->_cursor();
		if ($this->_read('nb-char')) {
			return $this->_addMark(self::MARK_CONTENT_STRING, $c2, $this->_cursor());
		}
		else
		{
			return $this->_check($c, false);
		}
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn172($n) # b-nb-literal-next(n)
	{
		// b-as-line-feed l-nb-literal-text(n)
		return $this->_check($this->_cursor(), $this->_ptn29() && $this->_ptn171($n));
	}

	/**
	 * @param int $n
	 * @param int $t
	 *
	 * @return bool
	 */
	protected function _ptn173($n, $t) # l-literal-content(n,t)
	{
		// ( l-nb-literal-text(n)  b-nb-literal-next(n)* b-chomped-last(t) )? l-chomped-empty(n,t)
		$i = $this->_addMark(self::MARK_SCALAR);
		$c = $this->_cursor();
		$r = $this->_ptn171($n);
		if ($r) {
			while ($this->_ptn172($n)) {
				;
			}
			$i2 = $this->_addMark();
			$r = $this->_ptn165($t);
			$this->_removeMark($i2);
		}
		if ($r) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_cursor($c);
			$this->_removeMark($i);
		}

		return $this->_check($c, $this->_ptn166($n, $t));
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn174($n) # c-l+folded(n)
	{
		// “>”  c-b-block-header(m,t) l-folded-content(n+m,t)
		if (!$this->_read(0x3E, 1)) {
			return false;
		}
		list($m, $t) = $this->_ptn162();
		if ($m <= $n) {
			return false;
		}
		return $this->_ptn182($m, $t);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn175($n) # s-nb-folded-text(n)
	{
		// s-indent(n)  ns-char  nb-char*
		$c = $this->_cursor();
		if (!$this->_ptn63($n)) {
			return false;
		}
		$c2 = $this->_cursor();
		$r = $this->_read('ns-char', 1);
		if (!$r) {
			$this->_cursor($c);
			return false;
		}
		$this->_read('nb-char');
		$this->_addMark(self::MARK_CONTENT_STRING, $c2, $this->_cursor());
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn176($n) # l-nb-folded-lines(n)
	{
		// s-nb-folded-text(n) ( b-l-folded(n,block-in) s-nb-folded-text(n) )*
		if (!$this->_ptn175($n)) {
			return false;
		}
		while (true) {
			$i = $this->_addMark();
			$c = $this->_cursor();
			$r = $this->_ptn73($n, self::CONTEXT_BLOCK_IN);
			if (!$r) {
				$this->_removeMark($i);
				break;
			}
			if (!$this->_ptn175($n)) {
				$this->_removeMark($i);
				$this->_cursor($c);
				break;
			}
			$this->_removeMark($i, true);
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn177($n) # s-nb-spaced-text(n)
	{
		// s-indent(n)  s-white  nb-char*
		$c = $this->_cursor();
		if (!$this->_ptn63($n)) {
			return false;
		}
		$c2 = $this->_cursor();
		$r = $this->_read('s-white', 1);
		if (!$r) {
			$this->_cursor($c);
			return false;
		}
		$this->_read('nb-char');
		$this->_addMark(self::MARK_CONTENT_STRING, $c2, $this->_cursor());
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn178($n) # b-l-spaced(n)
	{
		// b-as-line-feed l-empty(n,block-in)*
		if (!$this->_ptn29()) {
			return false;
		}
		while ($this->_ptn70($n, self::CONTEXT_BLOCK_IN)) {
			;
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn179($n) # l-nb-spaced-lines(n)
	{
		// s-nb-spaced-text(n) ( b-l-spaced(n) s-nb-spaced-text(n) )*
		if (!$this->_ptn177($n)) {
			return false;
		}
		while (true) {
			$i = $this->_addMark();
			$c = $this->_cursor();
			$r = $this->_ptn178($n);
			if (!$r) {
				$this->_removeMark($i);
				break;
			}
			if (!$this->_ptn177($n)) {
				$this->_removeMark($i);
				$this->_cursor($c);
				break;
			}
			$this->_removeMark($i, true);
		}
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn180($n) # l-nb-same-lines(n)
	{
		// l-empty(n,block-in)* ( l-nb-folded-lines(n) | l-nb-spaced-lines(n) )
		$c = $this->_cursor();
		while ($this->_ptn70($n, self::CONTEXT_BLOCK_IN)) {
			;
		}
		return $this->_check($c, $this->_ptn176($n) || $this->_ptn179($n));
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn181($n) # l-nb-diff-lines(n)
	{
		// l-nb-same-lines(n) ( b-as-line-feed l-nb-same-lines(n) )*
		if (!$this->_ptn180($n)) {
			return false;
		}
		while (true) {
			$i = $this->_addMark();
			$c = $this->_cursor();
			$r = $this->_ptn29();
			if (!$r) {
				$this->_removeMark($i);
				break;
			}
			if (!$this->_ptn180($n)) {
				$this->_removeMark($i);
				$this->_cursor($c);
				break;
			}
			$this->_removeMark($i, true);
		}
		return true;
	}

	/**
	 * @param int $n
	 * @param int $t
	 *
	 * @return bool
	 */
	protected function _ptn182($n, $t) # l-folded-content(n,t)
	{
		// ( l-nb-diff-lines(n)  b-chomped-last(t) )? l-chomped-empty(n,t)
		$i = $this->_addMark(self::MARK_SCALAR);
		$c = $this->_cursor();
		$r = $this->_ptn181($n);
		if ($r) {
			$r = $this->_ptn165($t);
		}
		if ($r) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_cursor($c);
			$this->_removeMark($i);
		}
		return $this->_check($c, $this->_ptn166($n, $t));
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn183($n) # l+block-sequence(n)
	{
		// ( s-indent(n+m)  c-l-block-seq-entry(n+m) )+ /* For some fixed auto-detected m > 0 */
		$c = $this->_cursor();
		$this->_read(0x20);
		$m = $this->_cursor($c) - $c;
		if ($m <= $n) {
			return false;
		}
		$rr = false;
		$i = $this->_addMark(self::MARK_SEQUENCE);
		while (true) {
			$c = $this->_cursor();
			$r = $this->_ptn63($m);
			if (!$r) {
				break;
			}
			if (!$this->_ptn184($m)) {
				$this->_cursor($c);
				break;
			}
			$rr = true;
		}
		if ($rr) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i);
		}
		return $rr;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn184($n) # c-l-block-seq-entry(n)
	{
		//“-” /* Not followed by an ns-char */ s-l+block-indented(n,block-in)'
		if (!$this->_read(0x2D, 1)) {
			return false;
		}
		return $this->_ptn185($n, self::CONTEXT_BLOCK_IN);
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn185($n, $z) # s-l+block-indented(n,c)
	{
		// ( s-indent(m) ( ns-l-compact-sequence(n+1+m) | ns-l-compact-mapping(n+1+m) ) ) | s-l+block-node(n,c) | ( e-node s-l-comments )
		$c = $this->_cursor();
		$this->_read(0x20);
		$m = $this->_cursor($c) - $c;
		if ($m == 0) {
			return false;
		}
		$c = $this->_cursor();
		$r = $this->_ptn63($m);
		if ($r) {
			$r = $this->_ptn186($n + 1 + $m);
			if (!$r) {
				$r = $this->_ptn195($n + 1 + $m);
			}
		}
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn196($n, $z);
		}
		if (!$r) {
			$this->_cursor($c);
			$r = $this->_ptn106() && $this->_ptn79();
		}
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn186($n) # ns-l-compact-sequence(n)
	{
		// c-l-block-seq-entry(n) ( s-indent(n) c-l-block-seq-entry(n) )*
		if (!$this->_read(0x2D, 1)) {
			return false;
		}
		$this->_cursor(-1);
		$i = $this->_addMark(self::MARK_SEQUENCE);
		if (!$this->_ptn184($n)) {
			$this->_removeMark($i);
			return false;
		}
		while (true) {
			$c = $this->_cursor();
			$r = $this->_ptn63($n);
			if (!$r) {
				break;
			}
			if (!$this->_ptn184($n)) {
				$this->_cursor($c);
				break;
			}
		}
		$this->_addMark(self::MARK_END);
		return true;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn187($n) # l+block-mapping(n)
	{
		// ( s-indent(n+m)  ns-l-block-map-entry(n+m) )+ /* For some fixed auto-detected m > 0 */
		$c = $this->_cursor();
		$this->_read(0x20);
		$m = $this->_cursor($c) - $c;
		if ($m <= $n) {
			return false;
		}
		$rr = false;
		$i = $this->_addMark(self::MARK_MAPPING);
		while (true) {
			$c = $this->_cursor();
			$r = $this->_ptn63($m);
			if (!$r) {
				break;
			}
			if (!$this->_ptn188($m)) {
				$this->_cursor($c);
				break;
			}
			$rr = true;
		}
		if ($rr) {
			$this->_addMark(self::MARK_END);
		}
		else {
			$this->_removeMark($i);
		}
		return $rr;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn188($n) # ns-l-block-map-entry(n)
	{
		// c-l-block-map-explicit-entry(n) | ns-l-block-map-implicit-entry(n)
		$i = $this->_addMark();
		$r = $this->_ptn189($n) || $this->_ptn192($n);
		$this->_removeMark($i, $r);
		return $r;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn189($n) # c-l-block-map-explicit-entry(n)
	{
		// c-l-block-map-explicit-key(n) ( l-block-map-explicit-value(n) | e-node )
		$c = $this->_cursor();
		$r = $this->_ptn190($n) && ($this->_ptn191($n) || $this->_ptn106());
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn190($n) # c-l-block-map-explicit-key(n)
	{
		// “?” s-l+block-indented(n,block-out)
		return $this->_read(0x3F, 1) && $this->_ptn185($n, self::CONTEXT_BLOCK_OUT);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn191($n) # l-block-map-explicit-value(n)
	{
		// s-indent(n) “:” s-l+block-indented(n,block-out)
		$c = $this->_cursor();
		$r = $this->_ptn63($n) && $this->_read(0x3A, 1)
			&& $this->_ptn185($n, self::CONTEXT_BLOCK_OUT);
		return $this->_check($c, $r);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn192($n) # ns-l-block-map-implicit-entry(n)
	{
		$c = $this->_cursor();
		// ( ns-s-block-map-implicit-key | e-node ) c-l-block-map-implicit-value(n)
		$r = ($this->_ptn193() || $this->_ptn106())
			&& $this->_ptn194($n);
		return $this->_check($c, $r);
	}

	/**
	 * @return bool
	 */
	protected function _ptn193() # ns-s-block-map-implicit-key
	{
		//c-s-implicit-json-key(block-key) | ns-s-implicit-yaml-key(block-key)
		return $this->_ptn155(self::CONTEXT_BLOCK_KEY)
			|| $this->_ptn154(self::CONTEXT_BLOCK_KEY);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn194($n) # c-l-block-map-implicit-value(n)
	{
		//“:”  ( s-l+block-node(n,block-out) | ( e-node s-l-comments ) )
		if (!$this->_read(0x3A, 1)) {
			return false;
		}
		return $this->_check(
			$this->_cursor() - 1,
			$this->_ptn196($n, self::CONTEXT_BLOCK_OUT) || ($this->_ptn106() && $this->_ptn79())
		);
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn195($n) # ns-l-compact-mapping(n)
	{
		// ns-l-block-map-entry(n) ( s-indent(n) ns-l-block-map-entry(n) )*
		$i = $this->_addMark(self::MARK_MAPPING);
		if (!$this->_ptn188($n)) {
			$this->_removeMark($i);
			return false;
		}
		while (true) {
			$c = $this->_cursor();
			$r = $this->_ptn63($n);
			if (!$r) {
				break;
			}
			if (!$this->_ptn188($n)) {
				$this->_cursor($c);
				break;
			}
		}
		$this->_addMark(self::MARK_END);
		return true;
	}

	/**
	 * @param int $n
	 * @param int $c
	 *
	 * @return bool
	 */
	protected function _ptn198($n, $c) # s-l+block-in-block(n,c)
	{
		//s-l+block-scalar(n,c) | s-l+block-collection(n,c)
		$i = $this->_addMark();
		$r = $this->_ptn199($n, $c);
		$this->_removeMark($i, $r);
		if (!$r) {
			$i = $this->_addMark();
			$r = $this->_ptn200($n, $c);
			$this->_removeMark($i, $r);
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn199($n, $z) # s-l+block-scalar(n,c)
	{
		//s-separate(n+1,c) ( c-ns-properties(n+1,c) s-separate(n+1,c) )? ( c-l+literal(n) | c-l+folded(n) )
		$c = $this->_cursor();
		if (!$this->_ptn80($n + 1, $z)) {
			return false;
		}

		$c2 = $this->_cursor();
		$i = $this->_addMark();
		$r = $this->_ptn96($n + 1, $z) && $this->_ptn80($n + 1, $z);
		$this->_removeMark($i, $r);
		$this->_check($c2, $r);

		$c2 = $this->_cursor();
		$r = $this->_ptn170($n < 0 ? 0 : $n);
		if (!$this->_check($c2, $r)) {
			$r = $this->_check($c, $this->_ptn174($n < 0 ? 0 : $n));
		}
		return $r;
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn196($n, $z) # s-l+block-node(n,c)
	{
		//s-l+block-in-block(n,c) | s-l+flow-in-block(n)
		$r = $this->_ptn198($n, $z);
		if (!$r) {
			$i = $this->_addMark();
			$c = $this->_cursor();
			$r = $this->_ptn197($n);
			$this->_removeMark($i, $r);
			$this->_check($c, $r);
		}
		return $r;
	}

	/**
	 * @param int $n
	 *
	 * @return bool
	 */
	protected function _ptn197($n) # s-l+flow-in-block(n)
	{
		// s-separate(n+1,flow-out) ns-flow-node(n+1,flow-out) s-l-comments
		return $this->_ptn80($n + 1, self::CONTEXT_FLOW_OUT)
			&& $this->_ptn161($n + 1, self::CONTEXT_FLOW_OUT)
			&& $this->_ptn79();
	}

	/**
	 * @param int $n
	 * @param int $z
	 *
	 * @return bool
	 */
	protected function _ptn200($n, $z) # s-l+block-collection(n,c)
	{
		// ( s-separate(n+1,c)  c-ns-properties(n+1,c) )? s-l-comments ( l+block-sequence(seq-spaces(n,c)) | l+block-mapping(n) )
		$c = $this->_cursor();
		$r = $this->_ptn80($n + 1, $z);

		if ($r) {
			$i = $this->_addMark();
			$r2 = $this->_ptn96($n + 1, $z);
			if (!$r2) {
				$this->_cursor($c);
			}
			$this->_removeMark($i, $r2);
		}
		if (!$this->_ptn79()) {
			$this->_cursor($c);
			return false;
		}
		// seq-spaces(n,c)
		return $this->_check($c,
			$this->_ptn183(($z === self::CONTEXT_BLOCK_OUT) ? $n - 1 : $n) || $this->_ptn187($n)
		);
	}

	/**
	 * @return bool
	 */
	protected function _ptn202() # l-document-prefix
	{
		// c-byte-order-mark? l-comment*
		$r1 = $this->_read(0xFEFF, 1);
		$r2 = false;
		while ($this->_ptn78()) {
			$r2 = true;
		}
		return $r1 || $r2;
	}

	/**
	 * @return bool
	 */
	protected function _ptn205() # l-document-suffix
	{
		// c-document-end  s-l-comments
		$c = $this->_cursor();
		if (!$this->_read(0x2E, 3)) {
			return false;
		}
		return $this->_check($c, $this->_ptn79());
	}

	/**
	 * @return bool
	 */
	protected function _ptn207() # l-bare-document
	{
		// s-l+block-node(-1,block-in) /* Excluding c-forbidden content */
		$m = $this->_addMark(self::MARK_DOCUMENT_START);

		$r = $this->_ptn196(-1, self::CONTEXT_BLOCK_IN);
		if ($r) {
			$this->_addMark(self::MARK_DOCUMENT_END);
		}
		else {
			$this->_removeMark($m);
		}

		return $r;
	}

	/**
	 * @return bool
	 */
	protected function _ptn208() # l-explicit-document
	{
		// c-directives-end ( l-bare-document | ( e-node s-l-comments ) )

		// c-directives-end = “-” “-” “-”
		if (!$this->_read(0x2D, 3)) {
			return false;
		}
		return $this->_ptn207() || ($this->_ptn106() && $this->_ptn79());
	}

	/**
	 * @return bool
	 */
	protected function _ptn209() # l-directive-document
	{
		//l-directive+ l-explicit-document
		$c = $this->_cursor();
		$r = false;
		while ($this->_ptn82()) {
			$r = true;
		}
		return $this->_check($c, $r && $this->_ptn208());
	}

	/**
	 * @return bool
	 */
	protected function _ptn210() # l-any-document
	{
		//l-directive-document | l-explicit-document | l-bare-document
		return $this->_ptn209() || $this->_ptn208() || $this->_ptn207();
	}

	/**
	 */
	protected function _ptn211() # l-yaml-stream
	{
		// l-document-prefix* l-any-document? ( l-document-suffix+ l-document-prefix* l-any-document? | l-document-prefix* l-explicit-document? )*
		while ($this->_ptn202()) {
		}
		$this->_ptn210();
		do {
			$r = false;
			while ($this->_ptn205()) {
				$r = true;
			}
			if ($r) {
				while ($this->_ptn202()) {
					;
				}
				$this->_ptn210();
				continue;
			}
			while ($this->_ptn202()) {
				;
			}
			$r = $this->_ptn208();

		}
		while ($r);
	}
}