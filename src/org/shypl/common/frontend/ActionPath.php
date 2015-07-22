<?php
namespace org\shypl\common\frontend;

class ActionPath {
	private $path;

	/**
	 * @param int    $rootOffset
	 * @param string $path
	 */
	public function __construct($rootOffset, $path) {
		$path = preg_replace('#//+#', '/', trim($path, '/'));
		$path = $path === '' ? [] : explode('/', $path);
		$this->path = $rootOffset == 0 ? $path : array_slice($path, $rootOffset);
		$this->size = count($this->path);
		$this->index = 0;
	}

	/**
	 * @return string
	 */
	public function nextPart() {
		if ($this->index == $this->size) {
			return null;
		}
		return $this->path[$this->index++];
	}

	/**
	 * @return bool
	 */
	public function hasNextPart() {
		return isset($this->path[$this->index]);
	}

	/**
	 * @param int $index
	 *
	 * @return string
	 */
	public function partAt($index) {
		return isset($this->path[$index]) ? $this->path[$index] : null;
	}

	/**
	 * @param int $index
	 *
	 * @return bool
	 */
	public function hasPartAt($index) {
		return isset($this->path[$index]);
	}

	public function resetPartIterator() {
		$this->index = 0;
	}
}