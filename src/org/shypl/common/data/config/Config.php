<?php
namespace org\shypl\common\data\config;

use org\shypl\common\cache\ICache;
use org\shypl\common\cache\link\FileListCacheLink;
use org\shypl\common\data\AssocData;

class Config extends AssocData
{
	const COMPILER_YAML = 'yaml';

	/**
	 * @var array
	 */
	static private $_compilerMap = array(
		self::COMPILER_YAML => 'Yaml'
	);

	/**
	 * @param ICache          $cache
	 * @param string          $file
	 * @param string|callable $compiler
	 * @param bool            $dev
	 * @param bool            $readOnly
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(ICache $cache, $file, $compiler, $dev, $readOnly = true)
	{
		if (is_string($compiler)) {
			if (!isset(self::$_compilerMap[$compiler])) {
				throw new \InvalidArgumentException('Undefined compiler name "'.$compiler.'"');
			}
			$compiler = array(__NAMESPACE__.'\\compiler\\'.self::$_compilerMap[$compiler].'Compiler', 'compile');
		}
		else if (!is_callable($compiler)) {
			throw new \InvalidArgumentException('Compiler "'.$compiler.'" not callable');
		}

		$link = new FileListCacheLink($cache, $file, $compiler);

		parent::__construct($link->get($dev), $readOnly);
	}
}