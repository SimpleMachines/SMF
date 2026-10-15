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

	/**
	 * Strings that start the same share one copy of that prefix in the regex.
	 *
	 * Expected: buildRegex(['abc', 'abd']) returns '(?>ab(?>c|d))'.
	 */
	public function testBuildRegexSharesACommonPrefix(): void
	{
		$this->assertSame('(?>ab(?>c|d))', Utils::buildRegex(['abc', 'abd']));
	}

	/**
	 * The regex matches every string it was built from.
	 *
	 * Expected: a regex built from strings containing regex metacharacters,
	 *           such as 'a.b', 'a+b' and 'a(b)', matches each of them in full.
	 */
	public function testBuildRegexMatchesEveryStringItWasBuiltFrom(): void
	{
		$strings = ['abc', 'abd', 'xyz', 'a.b', 'a+b', 'a(b)'];
		$regex = Utils::buildRegex($strings);

		foreach ($strings as $string) {
			$this->assertMatchesRegularExpression('~^' . $regex . '$~', $string);
		}
	}

	/**
	 * A trailing character that is special in a regex stays quoted.
	 *
	 * Expected: buildRegex(['ab.', 'ab']) matches 'ab.' but not 'abx'.
	 * Guards:   the trailing character that all the strings shared was added to
	 *           the pattern unquoted, so a "." there matched any character and
	 *           the pattern matched things it should not.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/ea308c3cf Introduced by "Improvements to Utils::buildRegex()"
	 * @link https://github.com/SimpleMachines/SMF/pull/9318
	 */
	public function testBuildRegexQuotesTrailingSpecialCharacters(): void
	{
		$regex = Utils::buildRegex(['ab.', 'ab']);

		$this->assertMatchesRegularExpression('~^' . $regex . '$~', 'ab.');
		$this->assertDoesNotMatchRegularExpression('~^' . $regex . '$~', 'abx');
	}

	/**
	 * A list holding a single string yields a regex that matches it.
	 *
	 * Expected: buildRegex(['solo']) matches 'solo'.
	 */
	public function testBuildRegexHandlesASingleString(): void
	{
		$this->assertMatchesRegularExpression('~^' . Utils::buildRegex(['solo']) . '$~', 'solo');
	}

	/**
	 * An HTML entity counts as one character when measuring length.
	 *
	 * Expected: entityStrlen('a&amp;b') returns 3 and entityStrlen('déjà')
	 *           returns 4.
	 */
	public function testEntityAwareLengthCountsAnEntityAsOneCharacter(): void
	{
		$this->assertSame(3, Utils::entityStrlen('a&amp;b'));
		$this->assertSame(4, Utils::entityStrlen('déjà'));
	}

	/**
	 * A substring is cut on entity boundaries, never inside an entity.
	 *
	 * Expected: entitySubstr('a&amp;bc', 0, 2) returns 'a&amp;'.
	 */
	public function testEntityAwareSubstrDoesNotSplitAnEntity(): void
	{
		$this->assertSame('a&amp;', Utils::entitySubstr('a&amp;bc', 0, 2));
	}

	/**
	 * A position is counted in characters, with an entity as one.
	 *
	 * Expected: entityStrpos('a&amp;bc', 'b') returns 2.
	 */
	public function testEntityAwareStrposCountsEntitiesAsOne(): void
	{
		$this->assertSame(2, Utils::entityStrpos('a&amp;bc', 'b'));
	}

	/**
	 * Splitting a string into characters keeps each entity in one piece.
	 *
	 * Expected: entityStrSplit('a&amp;b') returns ['a', '&amp;', 'b'].
	 */
	public function testEntityAwareSplitKeepsEntitiesWhole(): void
	{
		$this->assertSame(['a', '&amp;', 'b'], Utils::entityStrSplit('a&amp;b'));
	}

	/**
	 * Trimming also removes whitespace written as an entity.
	 *
	 * Expected: htmlTrim(' &nbsp; a &nbsp; ') returns 'a', htmlTrimLeft()
	 *           removes the leading '&nbsp;' and htmlTrimRight() the
	 *           trailing one.
	 */
	public function testHtmlTrimRemovesEntityWhitespaceAtBothEnds(): void
	{
		$this->assertSame('a', Utils::htmlTrim(' &nbsp; a &nbsp; '));
		$this->assertSame('a', Utils::htmlTrimLeft(' &nbsp;a'));
		$this->assertSame('a', Utils::htmlTrimRight('a&nbsp; '));
	}

	/**
	 * Truncating drops an entity that does not fit rather than cutting it.
	 *
	 * Expected: truncate('abcdefghij', 5) returns 'abcde', and
	 *           truncate('a&amp;bcdef', 5) returns 'a'.
	 */
	public function testTruncateRefusesToCutAnEntityInHalf(): void
	{
		$this->assertSame('abcde', Utils::truncate('abcdefghij', 5));

		// '&amp;' would not fit in the remaining budget, so it is dropped whole
		// rather than emitted as a broken fragment.
		$this->assertSame('a', Utils::truncate('a&amp;bcdef', 5));
	}

	/**
	 * An ellipsis is appended only when the string was actually shortened.
	 *
	 * Expected: shorten('abcdefghij', 5) returns 'abcde...' and
	 *           shorten('abc', 5) returns 'abc'.
	 */
	public function testShortenAppendsAnEllipsisOnlyWhenItShortens(): void
	{
		$this->assertSame('abcde...', Utils::shorten('abcdefghij', 5));
		$this->assertSame('abc', Utils::shorten('abc', 5));
	}

	/**
	 * Unicode normalization can both compose and decompose.
	 *
	 * Expected: normalize() with form 'c' turns 'a' plus a combining acute
	 *           accent into the single character U+00E1, and with form 'd'
	 *           turns U+00E1 into two characters.
	 */
	public function testNormalizeComposesAndDecomposes(): void
	{
		$this->assertSame("\u{00E1}", Utils::normalize("a\u{0301}", 'c'));
		$this->assertSame(2, mb_strlen(Utils::normalize("\u{00E1}", 'd')));
	}

	/**
	 * Case conversion handles characters that do not map one to one.
	 *
	 * Expected: convertCase('Straße', 'upper') returns 'STRASSE' and
	 *           convertCase('hello world', 'title') returns 'Hello World'.
	 */
	public function testConvertCaseHandlesCharactersWithNoSimpleMapping(): void
	{
		// Uppercasing the sharp s expands it to two characters, which a naive
		// strtoupper() on bytes cannot do.
		$this->assertSame('STRASSE', Utils::convertCase('Straße', 'upper'));
		$this->assertSame('Hello World', Utils::convertCase('hello world', 'title'));
	}

	/**
	 * A digraph is titlecased to its dedicated titlecase character.
	 *
	 * Expected: convertCase() of U+01F3 with 'title' returns U+01F2.
	 */
	public function testConvertCaseTitlecasesDigraphsToTheirTitleForm(): void
	{
		// U+01F3 dz titlecases to U+01F2 Dz, which is neither upper nor lower.
		$this->assertSame("\u{01F2}", Utils::convertCase("\u{01F3}", 'title'));
	}

	/**
	 * Case folding makes strings that differ only in case compare equal.
	 *
	 * Expected: convertCase('ÄÖÜ', 'fold') equals convertCase('äöü', 'fold').
	 */
	public function testConvertCaseFoldsForCaseInsensitiveComparison(): void
	{
		$this->assertSame(
			Utils::convertCase('ÄÖÜ', 'fold'),
			Utils::convertCase('äöü', 'fold'),
		);
	}

	/**
	 * Directional overrides are replaced at level 1 and kept at level 0.
	 *
	 * Expected: sanitizeChars() of a string holding U+202E returns it
	 *           unchanged at level 0 and with U+FFFD in its place at level 1.
	 */
	public function testSanitizeCharsReplacesDirectionalOverridesAtLevelOne(): void
	{
		// A right-to-left override can be used to disguise a file name or link.
		$this->assertSame("a\u{202E}b", Utils::sanitizeChars("a\u{202E}b", 0));
		$this->assertSame("a\u{FFFD}b", Utils::sanitizeChars("a\u{202E}b", 1));
	}

	/**
	 * Unusual whitespace characters are replaced by a plain space.
	 *
	 * Expected: normalizeSpaces("a\u{00A0}b", true, true) returns 'a b'.
	 */
	public function testNormalizeSpacesCollapsesExoticWhitespace(): void
	{
		$this->assertSame('a b', Utils::normalizeSpaces("a\u{00A0}b", true, true));
	}

	/**
	 * Entities for control characters are replaced and others kept.
	 *
	 * Expected: sanitizeEntities('&#8;') returns '&#65533;' and
	 *           sanitizeEntities('&#65;') returns '&#65;'.
	 */
	public function testSanitizeEntitiesReplacesEntitiesForControlCharacters(): void
	{
		$this->assertSame('&#65533;', Utils::sanitizeEntities('&#8;'));
		$this->assertSame('&#65;', Utils::sanitizeEntities('&#65;'));
	}

	/**
	 * Single quotes are only escaped when ENT_QUOTES is asked for.
	 *
	 * Expected: htmlspecialchars('a"b\'c<>&') returns
	 *           'a&quot;b\'c&lt;&gt;&amp;', and with ENT_QUOTES the single
	 *           quote becomes '&#39;'.
	 */
	public function testHtmlspecialcharsLeavesSingleQuotesAloneByDefault(): void
	{
		$this->assertSame('a&quot;b\'c&lt;&gt;&amp;', Utils::htmlspecialchars('a"b\'c<>&'));
		$this->assertSame('a&quot;b&#39;c', Utils::htmlspecialchars('a"b\'c', ENT_QUOTES));
	}

	/**
	 * Decoding undoes what htmlspecialchars() did.
	 *
	 * Expected: htmlspecialcharsDecode(htmlspecialchars($x, ENT_QUOTES))
	 *           returns $x.
	 */
	public function testHtmlspecialcharsDecodeRoundTrips(): void
	{
		$original = 'a"b<c>&d';

		$this->assertSame(
			$original,
			Utils::htmlspecialcharsDecode(Utils::htmlspecialchars($original, ENT_QUOTES)),
		);
	}

	/**
	 * JSON encoding and decoding are inverses of each other.
	 *
	 * Expected: jsonEncode(['a' => 1]) returns '{"a":1}' and
	 *           jsonDecode('{"a":1}', true) returns ['a' => 1].
	 */
	public function testJsonRoundTrips(): void
	{
		$this->assertSame('{"a":1}', Utils::jsonEncode(['a' => 1]));
		$this->assertSame(['a' => 1], Utils::jsonDecode('{"a":1}', true));
	}

	/**
	 * Each input is measured in characters, with an entity as one.
	 *
	 * Expected: each case in entityLengthProvider() has the length that the
	 *           provider gives, as returned by entityStrlen().
	 */
	#[DataProvider('entityLengthProvider')]
	public function testEntityStrlenAcrossInputs(string $input, int $expected): void
	{
		$this->assertSame($expected, Utils::entityStrlen($input));
	}

	/**
	 * A version string is standardized to dot-separated parts.
	 *
	 * Expected: each case in versionStringProvider() is turned into the
	 *           standardized form that the provider gives, as returned by
	 *           standardizeVersionString().
	 */
	#[DataProvider('versionStringProvider')]
	public function testStandardizeVersionString(string $input, string $expected): void
	{
		$this->assertSame($expected, Utils::standardizeVersionString($input));
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

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function versionStringProvider(): array
	{
		return [
			'pre-release-dev' => ['3.0 Alpha 5-dev', '3.0.alpha.5.dev'],
			'pre-release' => ['3.0 Beta 5', '3.0.beta.5'],
			'release-candidate' => ['3.0 RC1', '3.0.rc.1'],
			'patch-dev' => ['3.0.7-dev', '3.0.7.dev'],
			'patch' => ['3.0.7', '3.0.7'],
			'mixed' => ['17.6 (Debian 17.6-1.pgdg13+1)', '17.6.debian.17.6.1.pgdg.13.1'],
			'prefixed' => ['SMF 3.0.7', '3.0.7'],
			'invalid' => ['not a version string', ''],
		];
	}
}
