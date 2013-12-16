<?php
namespace org\shypl\common\sys;

use ErrorException;
use Exception;
use RuntimeException;

final class ErrorHandler
{
	/**
	 * @var array
	 */
	static protected $_errorTypes = array(
		E_ERROR             => 'E_ERROR',
		E_WARNING           => 'E_WARNING',
		E_PARSE             => 'E_PARSE',
		E_NOTICE            => 'E_NOTICE',
		E_CORE_ERROR        => 'E_CORE_ERROR',
		E_CORE_WARNING      => 'E_CORE_WARNING',
		E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
		E_USER_ERROR        => 'E_USER_ERROR',
		E_USER_WARNING      => 'E_USER_WARNING',
		E_USER_NOTICE       => 'E_USER_NOTICE',
		E_STRICT            => 'E_STRICT',
		E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		E_DEPRECATED        => 'E_DEPRECATED',
		E_USER_DEPRECATED   => 'E_USER_DEPRECATED'
	);

	/**
	 * @var ErrorHandler
	 */
	static private $_instance;

	/**
	 * @param bool   $displayErrors
	 * @param string $logFile
	 *
	 * @return ErrorHandler
	 * @throws RuntimeException
	 */
	static public function init($displayErrors, $logFile = null)
	{
		if (self::$_instance) {
			throw new RuntimeException('ErrorHandler already initialized');
		}
		self::$_instance = new ErrorHandler($displayErrors, $logFile);
		return self::$_instance;
	}

	/**
	 * @return ErrorHandler
	 * @throws RuntimeException
	 */
	static public function instance()
	{
		if (self::$_instance) {
			return self::$_instance;
		}
		throw new RuntimeException('ErrorHandler is not initialized');
	}

	/**
	 * @var bool
	 */
	private $_cli;

	/**
	 * @var bool
	 */
	private $_display;

	/**
	 * @var string
	 */
	private $_logFile;

	/**
	 * @var bool
	 */
	private $_logFileHasDate;

	/**
	 * @var bool
	 */
	private $_defaultTimezoneChecked = false;

	/**
	 * @param bool   $displayErrors
	 * @param string $logFile
	 */
	private function __construct($displayErrors, $logFile)
	{
		$this->_cli = PHP_SAPI === 'cli';
		$this->_display = $displayErrors;
		$this->_logFile = $logFile;
		$this->_logFileHasDate = strpos($logFile, '{date}') !== false;

		error_reporting(E_ALL | E_STRICT);
		set_error_handler(array($this, 'handleError'), E_ALL | E_STRICT);
		set_exception_handler(array($this, 'handleException'));
		register_shutdown_function(array($this, 'handleShutdown'));
		ini_set('display_errors', false);
	}

	/**
	 * @param bool $display
	 */
	public function setDisplayErrors($display)
	{
		$this->_display = $display;
	}

	/**
	 * @param int    $type
	 * @param string $message
	 * @param string $file
	 * @param int    $line
	 *
	 * @throws ErrorException
	 */
	public function handleError($type, $message, $file, $line)
	{
		throw new ErrorException($message, 0, $type, $file, $line);
	}

	/**
	 * @param Exception $exception
	 */
	public function handleException(Exception $exception)
	{
		$this->logException($exception);

		if (!$this->_display) {
			$this->_display("Server error.");
		}

		exit(1);
	}

	public function handleShutdown()
	{
		$error = error_get_last();
		if ($error) {
			$this->handleException(new FatalErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
		}
	}

	/**
	 * @param Exception $exception
	 */
	public function logException(Exception $exception)
	{
		$log = '[' . $this->_date('Y-m-d H:i:s.u') . '] ';
		$closure = false;

		do {
			if ($closure) {
				$log .= "^\n";
			}

			$log .= $this->_type($exception)
				. ': ' . $exception->getMessage() . "\n";

			if ($exception instanceof FatalErrorException) {
				$log .= '#- ' . $exception->getFile() . '(' . $exception->getLine() . ")\n";
			}

			$log .= $exception->getTraceAsString() . "\n";

			$exception = $exception->getPrevious();
			$closure = true;
		}
		while ($exception);

		if ($this->_display) {
			$this->_display($log);
		}

		if ($this->_logFile) {

			if ($this->_logFileHasDate) {
				$file = str_replace('{date}', $this->_date('Ymd'), $this->_logFile);
			}
			else {
				$file = $this->_logFile;
			}

			$new = !file_exists($file);

			if ((is_writable($file) || is_writable(dirname($file))) && ($handle = fopen($file, 'a'))) {

				while (!flock($handle, LOCK_EX | LOCK_NB)) {
					usleep(10);
				}

				fwrite($handle, $log);
				fflush($handle);
				if ($new) {
					chmod($file, 0664);
				}
				flock($handle, LOCK_UN);
				fclose($handle);

			}
			else {
				$this->_display('Can not write log file (' . $file . ')');
			}
		}
	}

	/**
	 * @param string $log
	 */
	private function _display($log)
	{
		if (!$this->_cli && !headers_sent()) {
			header('Content-Type: text/plain', true, 500);
		}

		if ($this->_cli) {
			fwrite(STDERR, $log);
		}
		else {
			echo $log;
		}
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	private function _date($format)
	{
		$time = explode(' ', microtime());

		if (!$this->_defaultTimezoneChecked) {
			try {
				$tz = date_default_timezone_get();
			}
			catch (ErrorException $e) {
				$tz = false;
			}
			if (!$tz) {
				date_default_timezone_set('GMT');
			}
			$this->_defaultTimezoneChecked = true;
		}

		if (strpos($format, 'u') !== false) {
			$format = str_replace('u', sprintf('%03d', $time[0] * 1000), $format);
		}

		return date($format, $time[1]);
	}

	/**
	 * @param Exception $exception
	 *
	 * @return string
	 */
	private function _type(Exception $exception)
	{
		$type = get_class($exception);

		if ($exception instanceof ErrorException) {
			$severity = $exception->getSeverity();
			if (isset(static::$_errorTypes[$severity])) {
				$type = static::$_errorTypes[$severity];
			}
			else {
				$type .= ':' . $severity;
			}
		}

		if ($exception->getCode() !== 0) {
			$type .= ':' . $exception->getCode();
		}

		return $type;
	}
}