<?php
namespace org\shypl\common\core;

use ErrorException;
use Exception;
use RuntimeException;

final class ErrorHandler {

	private static $inited;
	private static $cliMode;
	private static $display;
	private static $logFile;
	private static $logFileHasDate;
	private static $defaultTimezoneChecked = false;

	/**
	 * @param bool   $displayErrors
	 * @param string $logFile
	 */
	public static function init($displayErrors, $logFile = null) {
		if (self::$inited) {
			throw new RuntimeException('ErrorHandler already initialized');
		}

		self::$cliMode = PHP_SAPI === 'cli';
		self::$display = $displayErrors;
		self::$logFile = $logFile;
		self::$logFileHasDate = strpos($logFile, '{date}') !== false;

		error_reporting(E_ALL | E_STRICT);
		set_error_handler('org\\shypl\\common\\core\\ErrorHandler::handleError', E_ALL | E_STRICT);
		set_exception_handler('org\\shypl\\common\\core\\ErrorHandler::handleException');
		register_shutdown_function('org\\shypl\\common\\core\\ErrorHandler::handleShutdown');
		ini_set('display_errors', false);
	}

	/**
	 * @param bool $display
	 */
	public static function setDisplayErrors($display) {
		self::$display = $display;
	}

	/**
	 * @param int    $type
	 * @param string $message
	 * @param string $file
	 * @param int    $line
	 */
	public static function handleError($type, $message, $file, $line) {
		throw new ErrorException($message, 0, $type, $file, $line);
	}

	public static function handleShutdown() {
		$error = error_get_last();
		if ($error) {
			include_once 'FatalErrorException.php';
			self::handleException(new FatalErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
		}
	}

	/**
	 * @param Exception $exception
	 */
	public static function handleException(Exception $exception) {
		self::logException($exception);

		if (!self::$display) {
			self::display("Server error.");
		}

		exit(1);
	}

	/**
	 * @param Exception $exception
	 */
	public static function logException(Exception $exception) {
		$log = '[' . self::getDate('Y-m-d H:i:s.u') . '] ';
		$closure = false;

		do {
			if ($closure) {
				$log .= "^\n";
			}

			$log .= self::extractType($exception) . ': ' . $exception->getMessage() . "\n";

			if ($exception instanceof FatalErrorException) {
				$log .= '#- ' . $exception->getFile() . '(' . $exception->getLine() . ")\n";
			}

			$log .= $exception->getTraceAsString() . "\n";

			$exception = $exception->getPrevious();
			$closure = true;
		}
		while ($exception);

		if (self::$display) {
			self::display($log);
		}

		if (self::$logFile) {

			if (self::$logFileHasDate) {
				$file = str_replace('{date}', self::getDate('Ymd'), self::$logFile);
			}
			else {
				$file = self::$logFile;
			}

			$new = !file_exists($file);

			if ((is_writable($file) || is_writable(dirname($file))) && ($handle = fopen($file, 'a'))) {

				while (!flock($handle, LOCK_EX | LOCK_NB)) {
					usleep(10);
				}

				fwrite($handle, $log);
				fflush($handle);
				if ($new) {
					chmod($file, 0666);
				}
				flock($handle, LOCK_UN);
				fclose($handle);
			}
			else {
				self::display('Can not write log file (' . $file . ')');
			}
		}
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	private static function getDate($format) {
		$time = explode(' ', microtime());

		if (!self::$defaultTimezoneChecked) {
			try {
				$tz = date_default_timezone_get();
			}
			catch (Exception $e) {
				$tz = false;
			}
			if (!$tz) {
				date_default_timezone_set('GMT');
			}
			self::$defaultTimezoneChecked = true;
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
	private static function extractType(Exception $exception) {
		$type = get_class($exception);

		if ($exception instanceof ErrorException) {
			$type .= ':' . $exception->getSeverity();
		}

		if ($exception->getCode() !== 0) {
			$type .= ':' . $exception->getCode();
		}

		return $type;
	}

	/**
	 * @param string $log
	 */
	private static function display($log) {
		if (!self::$cliMode && !headers_sent()) {
			header('Content-Type: text/plain', true, 500);
		}

		if (self::$cliMode) {
			fwrite(STDERR, $log);
		}
		else {
			echo $log;
		}
	}
}