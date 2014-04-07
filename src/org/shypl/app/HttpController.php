<?php
namespace org\shypl\app;

use Exception;

abstract class HttpController
{
	public function run()
	{
		try {
			$this->process(new HttpRequest())->send();
		}
		catch (Exception $e) {
			$this->processError($e)->send();
		}
	}

	/**
	 * @param Exception $e
	 *
	 * @return HttpResponse
	 */
	protected function processError(Exception $e)
	{
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, $e->__toString(), 500);
	}

	/**
	 * @param HttpRequest $request
	 *
	 * @return HttpResponse
	 */
	abstract protected function process(HttpRequest $request);
}