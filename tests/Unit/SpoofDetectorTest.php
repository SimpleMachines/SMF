<?php

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
	 * The installer stores the default list with the separators written out as
	 * the two characters backslash and n, because the value travels through
	 * Table::populate() as a placeholder in a PHP string. Splitting it on real
	 * newlines alone gives one long name that nobody would ever type, so every
	 * name an admin put on the list was free to register.
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
	 * A list an admin edited by hand arrives with real line breaks, whichever
	 * kind their browser sent.
	 */
	public function testAListWrittenWithRealLineBreaksStillWorks(): void
	{
		Config::$modSettings['reserveNames'] = "Admin\nWebmaster";

		$this->assertTrue(SpoofDetector::checkReservedName('Webmaster'));

		Config::$modSettings['reserveNames'] = "Admin\r\nWebmaster";

		$this->assertTrue(SpoofDetector::checkReservedName('Webmaster'));
	}

	public function testANameNobodyReservedIsAllowed(): void
	{
		Config::$modSettings['reserveNames'] = 'Admin\nWebmaster\nGuest\nroot';

		$this->assertFalse(SpoofDetector::checkReservedName('Somebody'));
	}

	public function testAnEmptyListReservesNothing(): void
	{
		Config::$modSettings['reserveNames'] = '';

		$this->assertFalse(SpoofDetector::checkReservedName('Admin'));
	}

	/**
	 * The point of the class: a name is compared by its skeleton, so a
	 * character that merely looks like the one on the list counts as being on
	 * the list. U+0410 is Cyrillic capital A.
	 */
	public function testACharacterThatMerelyLooksTheSameIsStillReserved(): void
	{
		Config::$modSettings['reserveNames'] = 'Admin';

		$this->assertTrue(SpoofDetector::checkReservedName("\u{0410}dmin"));
	}

	/**
	 * The admin's list and the name being checked are both decoded first, so
	 * neither side can hide behind an entity.
	 */
	public function testAnEntityIsDecodedBeforeComparison(): void
	{
		Config::$modSettings['reserveNames'] = 'Webmaster';

		$this->assertTrue(SpoofDetector::checkReservedName('Web&#109;aster'));
	}

	#[DataProvider('reserveWordCases')]
	public function testReserveWordDecidesWhetherPartOfANameCounts(int $reserve_word, string $name, bool $expected): void
	{
		Config::$modSettings['reserveNames'] = 'Admin';
		Config::$modSettings['reserveWord'] = $reserve_word;

		$this->assertSame($expected, SpoofDetector::checkReservedName($name));
	}

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
