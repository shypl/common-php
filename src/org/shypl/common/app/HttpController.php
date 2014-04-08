<?php
namespace org\shypl\common\app;

use Exception;

abstract class HttpController
{
	public function run()
	{
		$request = new HttpRequest();
		try {
			$this->process($request)->send();
		}
		catch (Exception $e) {
			$this->processError($e, $request)->send();
		}
	}

	/**
	 * @param HttpRequest $request
	 *
	 * @return HttpResponse
	 */
	abstract protected function process(HttpRequest $request);

	/**
	 * @param Exception $error
	 * @param HttpRequest $request
	 *
	 * @return HttpResponse
	 */
	protected function processError(Exception $error,
		/** @noinspection PhpUnusedParameterInspection */
		HttpRequest $request)
	{
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, $error->__toString(), 500);
	}
}