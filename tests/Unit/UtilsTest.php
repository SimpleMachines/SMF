<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Utils;

#[CoversClass(Utils::class)]
class UtilsTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testBuildRegexSharesACommonPrefix(): void
	{
		$this->assertSame('(?>ab(?>c|d))', Utils::buildRegex(['abc', 'abd']));
	}

	public function testBuildRegexMatchesEveryStringItWasBuiltFrom(): void
	{
		$strings = ['abc', 'abd', 'xyz', 'a.b', 'a+b', 'a(b)'];
		$regex = Utils::buildRegex($strings);

		foreach ($strings as $string) {
			$this->assertMatchesRegularExpression('~^' . $regex . '$~', $string);
		}
	}

	public function testBuildRegexQuotesTrailingSpecialCharacters(): void
	{
		// A trailing character that is special in a regex must stay quoted, or the
		// resulting pattern matches things it should not.
		$regex = Utils::buildRegex(['ab.', 'ab']);

		$this->assertMatchesRegularExpression('~^' . $regex . '$~', 'ab.');
		$this->assertDoesNotMatchRegularExpression('~^' . $regex . '$~', 'abx');
	}

	public function testBuildRegexHandlesASingleString(): void
	{
		$this->assertMatchesRegularExpression('~^' . Utils::buildRegex(['solo']) . '$~', 'solo');
	}
}
