<?php
namespace org\shypl\common\frontend;

use org\shypl\common\net\HttpRequest;
use org\shypl\common\net\HttpResponse;
use org\shypl\common\util\StringUtils;

abstract class AbstractService {
	private $childrenServices;

	/**
	 * @param array $childrenServices
	 */
	public function __construct(array $childrenServices = []) {
		$this->childrenServices = $childrenServices;
	}

	/**
	 * @param HttpRequest $request
	 * @param ActionPath  $path
	 *
	 * @return HttpResponse
	 */
	public function processRequest(HttpRequest $request, ActionPath $path) {
		if ($path->hasNextPart()) {
			$part = $path->nextPart();

			if (isset($this->childrenServices[$part])) {
				/** @var $service AbstractService */
				$service = new $this->childrenServices[$part]();
				return $service->processRequest($request, $path);
			}

			$method = 'action' . StringUtils::toCamelCase($part);
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