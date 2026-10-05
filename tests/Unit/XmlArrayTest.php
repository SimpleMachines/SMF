<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\PackageManager\XmlArray;

class XmlArrayTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const XML = <<<'XML'
		<?xml version="1.0"?>
		<characters>
			<character film="Star Wars">
				<name>Luke Skywalker</name>
				<weapon>Lightsaber</weapon>
			</character>
			<character film="LOTR">
				<name>Sauron</name>
				<weapon>Evil Eye</weapon>
			</character>
		</characters>
		XML;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Every accessor agrees about a path that matches elements.
	 *
	 * Expected: for each case in providePathCases(), the path exists, path()
	 *           returns an XmlArray, count() is the expected number, to_array()
	 *           is a non-empty array, set() is a list of that many XmlArray
	 *           instances, and create_xml() returns a string.
	 */
	#[DataProvider('providePathCases')]
	public function testPath(string $path, int $count): void
	{
		$result = new XmlArray(self::XML);

		$this->assertTrue($result->exists($path));
		$this->assertInstanceOf(XmlArray::class, $result->path($path));
		$this->assertSame($count, $result->count($path));
		$this->assertIsArray($result->to_array($path));
		$this->assertNotEmpty($result->to_array($path));
		$this->assertCount($count, $result->set($path));
		$this->assertIsList($result->set($path));
		$this->assertContainsOnlyInstancesOf(XmlArray::class, $result->set($path));
		$this->assertIsString($result->create_xml($path));
	}

	/**
	 * Every accessor agrees about a path that ends at an attribute.
	 *
	 * Expected: for each case in provideAttributeCases(), the path exists,
	 *           path() and fetch() return the attribute value, count() is 0,
	 *           to_array() and set() are empty arrays, and create_xml() returns
	 *           false.
	 */
	#[DataProvider('provideAttributeCases')]
	public function testAttribute(string $path, string $name): void
	{
		$result = new XmlArray(self::XML);

		$this->assertTrue($result->exists($path));
		$this->assertSame($name, $result->path($path));
		$this->assertSame($name, $result->fetch($path));
		$this->assertSame(0, $result->count($path));
		$this->assertIsArray($result->to_array($path));
		$this->assertEmpty($result->to_array($path));
		$this->assertIsArray($result->set($path));
		$this->assertEmpty($result->set($path));
		$this->assertFalse($result->create_xml($path));
	}

	/**
	 * to_array(), name(), fetch(), count() and set() read the sample document.
	 *
	 * Expected: to_array('characters[0]') and to_array() give the last
	 *           character's name and weapon ('Sauron' and 'Evil Eye'), name()
	 *           is '', fetch('characters[0]/character[1]/@film') is 'LOTR',
	 *           count('characters') is 1, and set('characters[0]/character')
	 *           has 2 elements.
	 */
	public function testBasicOperations(): void
	{
		$result = new XmlArray(self::XML);

		$this->assertSame(
			[
				'character' => [
					'name' => 'Sauron',
					'weapon' => 'Evil Eye',
				],
			],
			$result->to_array('characters[0]'),
		);

		$this->assertSame(
			[
				'characters' => [
					'character' => [
						'name' => 'Sauron',
						'weapon' => 'Evil Eye',
					],
				],
			],
			$result->to_array(),
		);

		$this->assertSame('', $result->name());

		$this->assertSame(
			'LOTR',
			$result->fetch('characters[0]/character[1]/@film'),
		);

		$this->assertSame(1, $result->count('characters'));
		$this->assertCount(2, $result->set('characters[0]/character'));
	}

	/**
	 * create_xml() reproduces the document it was built from.
	 *
	 * Expected: create_xml() on the sample document equals the sample document
	 *           once the CDATA markers and all whitespace are removed from
	 *           both.
	 */
	public function testCreateXml(): void
	{
		$result = new XmlArray(self::XML);

		$created = $result->create_xml();

		// create_xml() wraps text values in CDATA.
		$created = preg_replace(
			'#<!\[CDATA\[(.+?)\]\]>#s',
			'$1',
			$created,
		);

		$this->assertSame(
			preg_replace('/\s+/', '', self::XML),
			preg_replace('/\s+/', '', $created),
		);
	}

	/**
	 * A missing path raises a notice when the error level asks for one.
	 *
	 * Expected: with an error handler that throws and new XmlArray(XML, true,
	 *           E_ALL), to_array('error') throws an ErrorException.
	 */
	public function testMissingPathRaisesNotice(): void
	{
		set_error_handler(
			static function (int $errno, string $errstr, string $errfile, int $errline): never {
				throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
			},
		);

		try {
			$this->expectException(\ErrorException::class);

			$result = new XmlArray(self::XML, true, E_ALL);
			$result->to_array('error');
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * A missing path is silent when the error level is 0.
	 *
	 * Expected: for the path 'error', exists(), path(), fetch() and
	 *           create_xml() return false, count() is 0, and to_array() and
	 *           set() return empty arrays.
	 */
	public function testSilentMissingPath(): void
	{
		$result = new XmlArray(self::XML, true, 0);

		$this->assertFalse($result->exists('error'));
		$this->assertFalse($result->path('error'));
		$this->assertFalse($result->fetch('error'));
		$this->assertSame(0, $result->count('error'));
		$this->assertIsArray($result->to_array('error'));
		$this->assertEmpty($result->to_array('error'));
		$this->assertIsArray($result->set('error'));
		$this->assertEmpty($result->set('error'));
		$this->assertFalse($result->create_xml('error'));
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function providePathCases(): iterable
	{
		yield 'all characters' => ['characters[0]/character', 2];

		yield 'first character' => ['characters[0]/character[0]', 1];

		yield 'second character' => ['characters[0]/character[1]', 1];
	}

	public static function provideAttributeCases(): iterable
	{
		yield 'first movie name' => ['characters[0]/character[0]/@film', 'Star Wars'];

		yield 'second movie name' => ['characters[0]/character[1]/@film', 'LOTR'];
	}
}
