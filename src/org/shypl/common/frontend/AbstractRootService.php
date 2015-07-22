<?php

namespace org\shypl\common\frontend;

use Exception;
use org\shypl\common\net\HttpRequest;
use org\shypl\common\net\HttpResponse;

abstract class AbstractRootService extends AbstractService {
	/**
	 * @param HttpRequest       $request
	 * @param NotFoundException $error
	 *
	 * @return HttpResponse
	 */
	public function processExceptionNotFound(HttpRequest $request, NotFoundException $error) {
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, "Not Found\n", 404);
	}

	/**
	 * @param HttpRequest         $request
	 * @param BadRequestException $error
	 *
	 * @return HttpResponse
	 */
	public function processExceptionBadRequest(HttpRequest $request, BadRequestException $error) {
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, "Bad Request\n", 400);
	}

	/**
	 * @param HttpRequest $request
	 * @param Exception   $error
	 *
	 * @return HttpResponse
	 */
	public function processError(HttpRequest $request, Exception $error) {
		return HttpResponse::factory(HttpResponse::TYPE_TEXT, "Server Error\n", 500);
	}
}