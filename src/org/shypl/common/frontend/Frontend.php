<?php
namespace org\shypl\common\frontend;

use Exception;
use org\shypl\common\net\HttpRequest;

class Frontend {

	/**
	 * @param AbstractRootService $rootService
	 */
	public static function run(AbstractRootService $rootService) {
		$path = $_SERVER['SCRIPT_NAME'];
		$path = trim($path, '/');
		$path = substr($path, 0, strrpos($path, '/'));

		(new Frontend(substr_count($path, '/'), $rootService))->process(HttpRequest::factoryFromGlobals());
	}

	private $rootPathOffset;
	private $rootService;

	/**
	 * @param int                 $rootPathOffset
	 * @param AbstractRootService $rootService
	 */
	public function __construct($rootPathOffset, AbstractRootService $rootService) {
		$this->rootPathOffset = $rootPathOffset;
		$this->rootService = $rootService;
	}

	/**
	 * @param HttpRequest $request
	 */
	public function process(HttpRequest $request) {
		$path = new ActionPath($this->rootPathOffset, $request->getUrl()->getPath());

		try {
			$response = $this->rootService->processRequest($request, $path);
		}
		catch (NotFoundException $e) {
			$response = $this->rootService->processExceptionNotFound($request, $e);
		}
		catch (BadRequestException $e) {
			$response = $this->rootService->processExceptionBadRequest($request, $e);
		}
		catch (Exception $e) {
			$response = $this->rootService->processError($request, $e);
		}
		$response->send();
	}

}