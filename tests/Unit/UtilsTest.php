<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public function testEntityAwareLengthCountsAnEntityAsOneCharacter(): void
	{
		$this->assertSame(3, Utils::entityStrlen('a&amp;b'));
		$this->assertSame(4, Utils::entityStrlen('déjà'));
	}

	public function testEntityAwareSubstrDoesNotSplitAnEntity(): void
	{
		$this->assertSame('a&amp;', Utils::entitySubstr('a&amp;bc', 0, 2));
	}

	public function testEntityAwareStrposCountsEntitiesAsOne(): void
	{
		$this->assertSame(2, Utils::entityStrpos('a&amp;bc', 'b'));
	}

	public function testEntityAwareSplitKeepsEntitiesWhole(): void
	{
		$this->assertSame(['a', '&amp;', 'b'], Utils::entityStrSplit('a&amp;b'));
	}

	public function testHtmlTrimRemovesEntityWhitespaceAtBothEnds(): void
	{
		$this->assertSame('a', Utils::htmlTrim(' &nbsp; a &nbsp; '));
		$this->assertSame('a', Utils::htmlTrimLeft(' &nbsp;a'));
		$this->assertSame('a', Utils::htmlTrimRight('a&nbsp; '));
	}

	public function testTruncateRefusesToCutAnEntityInHalf(): void
	{
		$this->assertSame('abcde', Utils::truncate('abcdefghij', 5));

		// '&amp;' would not fit in the remaining budget, so it is dropped whole
		// rather than emitted as a broken fragment.
		$this->assertSame('a', Utils::truncate('a&amp;bcdef', 5));
	}

	public function testShortenAppendsAnEllipsisOnlyWhenItShortens(): void
	{
		$this->assertSame('abcde...', Utils::shorten('abcdefghij', 5));
		$this->assertSame('abc', Utils::shorten('abc', 5));
	}

	public function testNormalizeComposesAndDecomposes(): void
	{
		$this->assertSame("\u{00E1}", Utils::normalize("a\u{0301}", 'c'));
		$this->assertSame(2, mb_strlen(Utils::normalize("\u{00E1}", 'd')));
	}

	public function testConvertCaseHandlesCharactersWithNoSimpleMapping(): void
	{
		// Uppercasing the sharp s expands it to two characters, which a naive
		// strtoupper() on bytes cannot do.
		$this->assertSame('STRASSE', Utils::convertCase('Straße', 'upper'));
		$this->assertSame('Hello World', Utils::convertCase('hello world', 'title'));
	}

	public function testConvertCaseTitlecasesDigraphsToTheirTitleForm(): void
	{
		// U+01F3 dz titlecases to U+01F2 Dz, which is neither upper nor lower.
		$this->assertSame("\u{01F2}", Utils::convertCase("\u{01F3}", 'title'));
	}

	public function testConvertCaseFoldsForCaseInsensitiveComparison(): void
	{
		$this->assertSame(
			Utils::convertCase('ÄÖÜ', 'fold'),
			Utils::convertCase('äöü', 'fold'),
		);
	}

	public function testSanitizeCharsReplacesDirectionalOverridesAtLevelOne(): void
	{
		// A right-to-left override can be used to disguise a file name or link.
		$this->assertSame("a\u{202E}b", Utils::sanitizeChars("a\u{202E}b", 0));
		$this->assertSame("a\u{FFFD}b", Utils::sanitizeChars("a\u{202E}b", 1));
	}

	public function testNormalizeSpacesCollapsesExoticWhitespace(): void
	{
		$this->assertSame('a b', Utils::normalizeSpaces("a\u{00A0}b", true, true));
	}

	public function testSanitizeEntitiesReplacesEntitiesForControlCharacters(): void
	{
		$this->assertSame('&#65533;', Utils::sanitizeEntities('&#8;'));
		$this->assertSame('&#65;', Utils::sanitizeEntities('&#65;'));
	}

	public function testHtmlspecialcharsLeavesSingleQuotesAloneByDefault(): void
	{
		$this->assertSame('a&quot;b\'c&lt;&gt;&amp;', Utils::htmlspecialchars('a"b\'c<>&'));
		$this->assertSame('a&quot;b&#39;c', Utils::htmlspecialchars('a"b\'c', ENT_QUOTES));
	}

	public function testHtmlspecialcharsDecodeRoundTrips(): void
	{
		$original = 'a"b<c>&d';

		$this->assertSame(
			$original,
			Utils::htmlspecialcharsDecode(Utils::htmlspecialchars($original, ENT_QUOTES)),
		);
	}

	public function testJsonRoundTrips(): void
	{
		$this->assertSame('{"a":1}', Utils::jsonEncode(['a' => 1]));
		$this->assertSame(['a' => 1], Utils::jsonDecode('{"a":1}', true));
	}

	#[DataProvider('entityLengthProvider')]
	public function testEntityStrlenAcrossInputs(string $input, int $expected): void
	{
		$this->assertSame($expected, Utils::entityStrlen($input));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function entityLengthProvider(): array
	{
		return [
			'empty' => ['', 0],
			'ascii' => ['abc', 3],
			'named entity' => ['&amp;', 1],
			'numeric entity' => ['&#169;', 1],
			'multibyte' => ["\u{00E9}\u{00E8}", 2],
			'mixed' => ['a&amp;é', 3],
		];
	}
}
