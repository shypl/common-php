<?php
namespace org\shypl\common\yaml;

class ParserException extends \RuntimeException
{
	/**
	 * @var int
	 */
	protected $errorLine;

	/**
	 * @var int
	 */
	protected $errorChar;

	/**
	 * @var string
	 */
	protected $originalMessage;

	/**
	 * @param string $message
	 */
	public function __construct($message)
	{
		$this->originalMessage = $message;
		parent::__construct($this->originalMessage);
	}

	/**
	 * @return int
	 */
	public function getErrorLine()
	{
		return $this->errorLine;
	}

	/**
	 * @return int
	 */
	public function getErrorChar()
	{
		return $this->errorChar;
	}

	/**
	 * @param int $line
	 * @param int $char
	 *
	 * @return void
	 */
	public function setErrorPosition($line, $char)
	{
		$this->errorLine = $line;
		$this->errorChar = $char;
		$this->message = $this->originalMessage.' (line:'.$this->errorLine.', char: '.$this->errorChar.')';
	}
}