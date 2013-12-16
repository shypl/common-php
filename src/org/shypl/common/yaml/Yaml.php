<?php
namespace org\shypl\common\yaml;

class Yaml
{
	/**
	 * @param string $string
	 *
	 * @return Document
	 */
	static public function parseDoc($string)
	{
		$parser = new Parser();
		$docs = $parser->parse($string, 0, 1);
		return isset($docs[0]) ? $docs[0] : null;
	}

	/**
	 * @param string $file
	 *
	 * @return Document
	 */
	static public function parseFileDoc($file)
	{
		if (!is_readable($file)) {
			throw new \InvalidArgumentException('Can not read file "'.$file.'"');
		}

		try {
			return self::parseDoc(file_get_contents($file));
		}
		catch (ParserException $e) {
			throw new \RuntimeException('Can not parse file "'.$file.'"', 0, $e);
		}
	}

	/**
	 * @param string $string
	 *
	 * @return mixed
	 */
	static public function parse($string)
	{
		$doc = self::parseDoc($string);

		return $doc ? $doc->getData() : null;
	}

	/**
	 * @param string $file
	 *
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 * @return mixed
	 */
	static public function parseFile($file)
	{
		$doc = self::parseFileDoc($file);

		return $doc ? $doc->getData() : null;
	}

	/**
	 * @param mixed $data
	 *
	 * @return string
	 */
	static public function dump($data)
	{
		return Document::create($data)->getYaml();
	}

	/**
	 * @param string $file
	 * @param mixed  $data
	 *
	 * @throws \InvalidArgumentException
	 */
	static public function dumpFile($file, $data)
	{
		if (!is_writable($file) && !is_writable(dirname($file))) {
			throw new \InvalidArgumentException('Can not write file "'.$file.'"');
		}
		file_put_contents($file, self::dump($data));
	}
}