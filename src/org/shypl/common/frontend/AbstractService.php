<?php
namespace org\shypl\common\frontend;

use org\shypl\common\net\HttpRequest;
use org\shypl\common\net\HttpResponse;
use org\shypl\common\util\StringUtils;

abstract class AbstractService {
	/**
	 * @param HttpRequest $request
	 * @param ActionPath  $path
	 *
	 * @return HttpResponse
	 */
	public function processRequest(HttpRequest $request, ActionPath $path) {
		if ($path->hasNextPart()) {
			$method = "action" . StringUtils::toCamelCase($path->nextPart());
		}
		else {
			$method = 'actionMain';
		}

		if (method_exists($this, $method)) {
			return $this->$method($request, $path);
		}

		throw new NotFoundException();
	}
}