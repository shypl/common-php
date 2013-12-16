<?php
namespace org\shypl\common\jsy;

class Decoder
{
	const TOKEN_COMMA         = 0;
	const TOKEN_LEFT_BRACE    = 1;
	const TOKEN_RIGHT_BRACE   = 2;
	const TOKEN_LEFT_BRACKET  = 3;
	const TOKEN_RIGHT_BRACKET = 4;
	const TOKEN_COLON         = 5;
	const TOKEN_TRUE          = 6;
	const TOKEN_FALSE         = 7;
	const TOKEN_NULL          = 8;
	const TOKEN_STRING        = 9;
	const TOKEN_NUMBER        = 10;
	const TOKEN_END           = 11;

	/**
	 * @var array
	 */
	static private $_markTokens = array(
		44  => self::TOKEN_COMMA, // ,
		58  => self::TOKEN_COLON, // :
		91  => self::TOKEN_LEFT_BRACKET, // [
		93  => self::TOKEN_RIGHT_BRACKET, // ]
		123 => self::TOKEN_LEFT_BRACE, // {
		125 => self::TOKEN_RIGHT_BRACE, // }
	);

	/**
	 * @var array
	 */
	static private $_valueTokens = array(
		70 => self::TOKEN_FALSE, // F
		78 => self::TOKEN_NULL, // N
		84 => self::TOKEN_TRUE, // T
	);

	/**
	 * @var array
	 */
	static private $_escapes = array(
		'\\' => '\\',
		'n'  => "\n",
		'r'  => "\r",
		't'  => "\t",
		'\'' => '\''
	);

	/**
	 * @var string
	 */
	private $_string;

	/**
	 * @var int
	 */
	private $_len;

	/**
	 * @var int
	 */
	private $_cursor;

	/**
	 * @var string
	 */
	private $_char;

	/**
	 * @var int
	 */
	private $_token;

	/**
	 * @var mixed
	 */
	private $_value;

	/**
	 * @param string $string
	 *
	 * @return mixed
	 */
	public function decode($string)
	{
		$string = preg_replace('/\t\n|\n\r|\n/', '', $string);

		if ($string === '') {
			return '';
		}

		$this->_string = $string;
		$this->_cursor = 0;
		$this->_len    = mb_strlen($string, 'UTF8');

		$this->_nextChar();
		$this->_nextToken();

		$value = $this->_parseValue();

		$this->_string = null;
		$this->_cursor = null;
		$this->_len    = null;
		$this->_char   = null;
		$this->_token  = null;
		$this->_value  = null;

		return $value;
	}

	private function _nextChar()
	{
		$this->_char = mb_substr($this->_string, $this->_cursor++, 1, 'UTF8');
	}

	private function _prevChar()
	{
		$this->_cursor -= 2;
		$this->_nextChar();
	}

	private function _nextToken()
	{
		$this->_token = null;
		$this->_value = null;

		$chr = ord($this->_char);

		if (isset(self::$_markTokens[$chr])) {
			$this->_token = self::$_markTokens[$chr];
			$this->_nextChar();
			while (preg_match('/^\s$/', $this->_char)) {
				$this->_nextChar();
			}
			return;
		}

		if (isset(self::$_valueTokens[$chr])) {
			$this->_nextChar();
			if (isset(self::$_markTokens[ord($this->_char)])) {
				$this->_token = self::$_valueTokens[$chr];
				return;
			}
			$this->_prevChar();
		}

		$this->_readValue();

		return;
	}

	function _readValue()
	{
		$quote = $this->_char === '\'';

		if ($quote) {
			$this->_nextChar();
		}

		$value = '';

		while (true) {
			if ($this->_char === '') {
				break;
			}

			if ($quote) {
				if ($this->_char === '\\') {
					$this->_nextChar();
					if ($this->_char === '\'') {
						$value .= $this->_char;
						$this->_nextChar();
						continue;
					}
					$this->_prevChar();
				}
				if ($this->_char === '\'') {
					$this->_nextChar();
					break;
				}
			}
			else {
				if ($this->_char === '\\') {
					$this->_nextChar();
					if (isset(self::$_escapes[$this->_char])) {
						$value .= self::$_escapes[$this->_char];
						$this->_nextChar();
						continue;
					}
					$next = ord($this->_char);
					if (isset(self::$_markTokens[$next]) || isset(self::$_valueTokens[$next])) {
						$value .= $this->_char;
						$this->_nextChar();
						continue;
					}
					$this->_prevChar();
				}
				if (isset(self::$_markTokens[ord($this->_char)])) {
					break;
				}
			}

			$value .= $this->_char;
			$this->_nextChar();
		}

		//if (!$quote && preg_match('/^(?:0|(?:[1-9]+|-\d?)(?:\.?\d+)|0?\.\d+)?$/', $value)) {
		if (!$quote && preg_match('/^-?(?:[0-9]*\.)?[0-9]+?$/', $value)) {
			$this->_token = self::TOKEN_NUMBER;
		}
		else {
			$this->_token = self::TOKEN_STRING;
		}

		$this->_value = $value;
	}

	/**
	 * @return mixed
	 */
	private function _parseValue()
	{
		switch ($this->_token) {
			case self::TOKEN_LEFT_BRACE:
				return $this->_parseHash();

			case self::TOKEN_LEFT_BRACKET:
				return $this->_parseList();

			case self::TOKEN_NULL:
				return null;

			case self::TOKEN_TRUE:
				return true;

			case self::TOKEN_FALSE:
				return false;

			case self::TOKEN_STRING:
				return (string)$this->_value;

			case self::TOKEN_NUMBER:
				return strpos($this->_value, '.') === false ? (int)$this->_value : (float)$this->_value;
		}

		return null;
	}

	/**
	 * @throws \RuntimeException
	 * @return array
	 */
	private function _parseList()
	{
		$result = array();
		$this->_nextToken();

		if ($this->_token === self::TOKEN_RIGHT_BRACKET) {
			return $result;
		}

		while (true) {
			$result[] = $this->_parseValue();
			$this->_nextToken();

			if ($this->_token === self::TOKEN_RIGHT_BRACKET) {
				break;
			}

			if ($this->_token === self::TOKEN_COMMA) {
				$this->_nextToken();
				continue;
			}

			if ($this->_token === self::TOKEN_END) {
				break;
			}

			throw new \RuntimeException('Bad format');
		}

		return $result;
	}

	/**
	 * @throws \RuntimeException
	 * @return array
	 */
	private function _parseHash()
	{
		$result = array();
		$this->_nextToken();

		if ($this->_token === self::TOKEN_RIGHT_BRACE) {
			return $result;
		}

		while (true) {
			$key = $this->_parseValue();
			$this->_nextToken();

			if ($this->_token === self::TOKEN_COLON) {
				$this->_nextToken();
				$value = $this->_parseValue();
				$this->_nextToken();
			}
			else {
				$value = true;
			}

			$result[$key] = $value;

			if ($this->_token === self::TOKEN_RIGHT_BRACE) {
				break;
			}

			if ($this->_token === self::TOKEN_COMMA) {
				$this->_nextToken();
				continue;
			}

			if ($this->_token === self::TOKEN_END) {
				break;
			}

			throw new \RuntimeException('Bad format');
		}

        return $result;
	}
}