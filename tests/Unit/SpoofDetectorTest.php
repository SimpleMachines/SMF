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
use SMF\Config;
use SMF\Unicode\SpoofDetector;

/**
 * Covers the part of the spoof detector that needs no database.
 *
 * checkReservedName() reads the admin's list out of
 * Config::$modSettings['reserveNames'] and compares Unicode skeletons, so it
 * is reachable from here. checkSimilarMemberName() and checkSimilarGroupName()
 * ask the members and membergroups tables what else is out there, and are not.
 */
#[CoversClass(SpoofDetector::class)]
class SpoofDetectorTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array The settings this class reads, as they were before the test.
	 */
	private array $backup = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * The list the installer writes is read as one name per line.
	 *
	 * The installer stores the default list with the separators written out as
	 * the two characters backslash and n, because the value travels through
	 * Table::populate() as a placeholder in a PHP string.
	 *
	 * Expected: with that list, checkReservedName() is true for Admin,
	 *           Webmaster, Guest and root.
	 * Guards:   the list was split on real newlines alone, which gave one long
	 *           name that nobody would ever type, so every name an admin put
	 *           on the list was free to register.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9484
	 */
	public function testTheListTheInstallerWritesIsOneNamePerLine(): void
	{
		Config::$modSettings['reserveNames'] = 'Admin\nWebmaster\nGuest\nroot';

		$this->assertTrue(SpoofDetector::checkReservedName('Admin'));
		$this->assertTrue(SpoofDetector::checkReservedName('Webmaster'));
		$this->assertTrue(SpoofDetector::checkReservedName('Guest'));
		$this->assertTrue(SpoofDetector::checkReservedName('root'));
	}

	/**
	 * A list written with real line breaks is read as one name per line.
	 *
	 * A list an admin edited by hand arrives with real line breaks, whichever
	 * kind their browser sent.
	 *
	 * Expected: with 'Admin\nWebmaster' and 'Admin\r\nWebmaster' as the list,
	 *           checkReservedName('Webmaster') is true.
	 */
	public function testAListWrittenWithRealLineBreaksStillWorks(): void
	{
		Config::$modSettings['reserveNames'] = "Admin\nWebmaster";

		$this->assertTrue(SpoofDetector::checkReservedName('Webmaster'));

		Config::$modSettings['reserveNames'] = "Admin\r\nWebmaster";

		$this->assertTrue(SpoofDetector::checkReservedName('Webmaster'));
	}

	/**
	 * A name that is not on the list is allowed.
	 *
	 * Expected: checkReservedName('Somebody') is false.
	 */
	public function testANameNobodyReservedIsAllowed(): void
	{
		Config::$modSettings['reserveNames'] = 'Admin\nWebmaster\nGuest\nroot';

		$this->assertFalse(SpoofDetector::checkReservedName('Somebody'));
	}

	/**
	 * An empty list reserves no names.
	 *
	 * Expected: with an empty list, checkReservedName('Admin') is false.
	 */
	public function testAnEmptyListReservesNothing(): void
	{
		Config::$modSettings['reserveNames'] = '';

		$this->assertFalse(SpoofDetector::checkReservedName('Admin'));
	}

	/**
	 * A character that merely looks the same as a listed one is still reserved.
	 *
	 * The point of the class: a name is compared by its skeleton, so a
	 * character that merely looks like the one on the list counts as being on
	 * the list. U+0410 is Cyrillic capital A.
	 *
	 * Expected: with 'Admin' on the list, checkReservedName() is true for
	 *           '\u{0410}dmin'.
	 */
	public function testACharacterThatMerelyLooksTheSameIsStillReserved(): void
	{
		Config::$modSettings['reserveNames'] = 'Admin';

		$this->assertTrue(SpoofDetector::checkReservedName("\u{0410}dmin"));
	}

	/**
	 * An entity in the name is decoded before it is compared.
	 *
	 * The admin's list and the name being checked are both decoded first, so
	 * neither side can hide behind an entity.
	 *
	 * Expected: with 'Webmaster' on the list, checkReservedName() is true for
	 *           'Web&#109;aster'.
	 */
	public function testAnEntityIsDecodedBeforeComparison(): void
	{
		Config::$modSettings['reserveNames'] = 'Webmaster';

		$this->assertTrue(SpoofDetector::checkReservedName('Web&#109;aster'));
	}

	/**
	 * The reserveWord setting decides whether part of a name counts.
	 *
	 * Expected: with 'Admin' on the list, each case in reserveWordCases() has
	 *           checkReservedName() return the stated boolean.
	 */
	#[DataProvider('reserveWordCases')]
	public function testReserveWordDecidesWhetherPartOfANameCounts(int $reserve_word, string $name, bool $expected): void
	{
		Config::$modSettings['reserveNames'] = 'Admin';
		Config::$modSettings['reserveWord'] = $reserve_word;

		$this->assertSame($expected, SpoofDetector::checkReservedName($name));
	}

	/**
	 * The reserveCase setting decides whether the case has to match.
	 *
	 * Expected: with 'Admin' on the list, each case in reserveCaseCases() has
	 *           checkReservedName() return the stated boolean.
	 */
	#[DataProvider('reserveCaseCases')]
	public function testReserveCaseDecidesWhetherTheCaseHasToMatch(int $reserve_case, string $name, bool $expected): void
	{
		Config::$modSettings['reserveNames'] = 'Admin';
		Config::$modSettings['reserveCase'] = $reserve_case;

		$this->assertSame($expected, SpoofDetector::checkReservedName($name));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{int, string, bool}>
	 */
	public static function reserveWordCases(): array
	{
		return [
			'a reserved name inside a longer one, matching anywhere' => [0, 'SuperAdmin', true],
			'a reserved name inside a longer one, whole names only' => [1, 'SuperAdmin', false],
			'the reserved name itself, matching anywhere' => [0, 'Admin', true],
			'the reserved name itself, whole names only' => [1, 'Admin', true],
		];
	}

	/**
	 * @return array<string, array{int, string, bool}>
	 */
	public static function reserveCaseCases(): array
	{
		return [
			'a different case, compared caselessly' => [0, 'admin', true],
			'a different case, compared exactly' => [1, 'admin', false],
			'the same case, compared caselessly' => [0, 'Admin', true],
			'the same case, compared exactly' => [1, 'Admin', true],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		foreach (['reserveNames', 'reserveCase', 'reserveWord'] as $key) {
			if (isset(Config::$modSettings[$key])) {
				$this->backup[$key] = Config::$modSettings[$key];
			}
		}
	}

	/**
	 * PHPUnit does not reset SMF's statics between tests, so a setting left
	 * behind here would leak into every test that follows.
	 */
	protected function tearDown(): void
	{
		foreach (['reserveNames', 'reserveCase', 'reserveWord'] as $key) {
			unset(Config::$modSettings[$key]);

			if (isset($this->backup[$key])) {
				Config::$modSettings[$key] = $this->backup[$key];
			}
		}

		$this->backup = [];
	}
}
