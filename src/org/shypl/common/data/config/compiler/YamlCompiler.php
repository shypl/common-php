<?php
namespace org\shypl\common\data\config\compiler;

use org\shypl\common\cache\link\FileListCacheLink;
use org\shypl\common\util\ArrayUtils;
use org\shypl\common\yaml\Yaml;

class YamlCompiler
{
	/**
	 * @param \org\shypl\common\cache\link\FileListCacheLink $cache
	 * @param string                                         $file
	 *
	 * @return array
	 */
	static public function compile(/** @noinspection PhpUnusedParameterInspection */
		FileListCacheLink $cache, $file)
	{
		$compiler = new self();
		return $compiler->_compile($file);
	}

	###

	/**
	 * @param $file
	 *
	 * @throws \RuntimeException
	 * @return array
	 */
	private function _compile($file)
	{
		$data = $this->_loadFile($file);

		return array($this->_files, $data);
	}

	/**
	 * @param string $file
	 *
	 * @return array
	 */
	private function _loadFile($file)
	{
		array_push($this->_dirs, dirname($file));
		$this->_files[] = $file;

		$doc = Yaml::parseFileDoc($file);

		$data = $doc ? $doc->getData() : null;

		if ($data === null) {
			$data = array();
		}
		else if (!is_array($data)) {
			throw new \RuntimeException('Config should be an array (' . $file . ')');
		}

		if ($doc) {
			foreach ($doc->getDirectives('include') as $include) {
				$data = ArrayUtils::merge($this->_includeFile($include->getParam()), $data);
			}
		}

		return $data;
	}

	/**
	 * @param string $file
	 *
	 * @return array
	 */
	private function _includeFile($file)
	{
		$dir = end($this->_dirs);
		$path = $dir . '/' . $file;

		if (!file_exists($path)) {
			throw new \RuntimeException('File for including "' . $file . '" not found (' . $path . ')');
		}

		return $this->_loadFile($path);
	}

	/**
	 * @var array
	 */
	private $_dirs = array();
	/**
	 * @var array
	 */
	private $_files = array();
}