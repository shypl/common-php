<?php
namespace org\shypl\common\frontend;

use RuntimeException;

class ActionPath {
	private $path;
	private $size;
	private $index = 0;

	/**
	 * @param int    $rootOffset
	 * @param string $path
	 */
	public function __construct($rootOffset, $path) {
		$path = preg_replace('#//+#', '/', trim($path, '/'));
		$path = $path === '' ? [] : explode('/', $path);
		$this->path = $rootOffset == 0 ? $path : array_slice($path, $rootOffset);
		$this->size = count($this->path);
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
	 * @return string
	 */
	public function prevPart() {
		if ($this->index > 0) {
			return $this->path[--$this->index];
		}
		throw new RuntimeException("Not have previous part");
	}

	/**
	 * @return string
	 */
	public function currentPart() {
		if ($this->index == 0) {
			throw new RuntimeException("Not have current part");
		}
		return $this->path[$this->index - 1];
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