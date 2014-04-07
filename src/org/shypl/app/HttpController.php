<?php
namespace org\shypl\app;

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
	 * @param Exception   $error
	 * @param HttpRequest $request
	 *
	 * @return HttpResponse
	 */
	protected function processError(Exception $error, HttpRequest $request)
	{
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, $error->__toString(), 500);
	}

	/**
	 * @param HttpRequest $request
	 *
	 * @return HttpResponse
	 */
	abstract protected function process(HttpRequest $request);
}