<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Lang;
use SMF\Sapi;
use SMF\User;

/**
 * Covers the half of SMF\Lang that reads from disk.
 *
 * None of this was reachable from a test until #9581. Asking for a language
 * string went load() -> addDirs() -> Theme::loadEssential(), and the Theme
 * constructor's first act is a query, so a process with no connection died on
 * "Typed static property SMF\Db\DatabaseApi::$db must not be accessed before
 * initialization" thrown out of Theme.php - a message with nothing about
 * languages in it, from three calls below where the test was looking.
 *
 * The rest of the class - censorText(), sentenceList(), numberFormat(),
 * formatText(), tokenTxtReplace(), getLocaleFromLanguageName() - never needed
 * the database and could always have been tested. What is new here is
 * everything that has to find a file first.
 */
#[CoversClass(Lang::class)]
class LangTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Lang's statics as they were before the test ran.
	 */
	private array $backup = [];

	/****************
	 * Public methods
	 ****************/

	public function testItLoadsLanguageStringsWithNoDatabase(): void
	{
		$this->assertSame('en_US', Lang::load('General'));

		$this->assertArrayHasKey('number_of_days', Lang::$txt);
		$this->assertArrayHasKey('days', Lang::$txt);
	}

	public function testItDefaultsToTheForumLanguageWhenThereIsNoUser(): void
	{
		// User::$me is a typed static with no default, and reading an
		// uninitialized one throws rather than yielding null. load() gets away
		// with `User::$me->language ?? Config::$language` only because ??
		// evaluates its left side in an isset() context, which is easy to
		// break by "tidying" it into something that reads the property first.
		$this->assertFalse(isset(User::$me));

		$this->assertSame(Config::$language, Lang::load('General'));
	}

	public function testItSearchesOnlyTheLanguagesDirectoryWhenThereIsNoTheme(): void
	{
		Lang::addDirs();

		// Lang::$dirs is private, and there is no accessor. It is worth reading
		// anyway: the whole behaviour under test is which directories end up in
		// it, and every other assertion here can only see that a file was found
		// somewhere.
		$dirs = (new \ReflectionProperty(Lang::class, 'dirs'))->getValue();

		$this->assertSame(
			[Sapi::canonicalPath(Config::$languagesdir)],
			array_values($dirs),
		);
	}

	public function testItIgnoresACustomDirectoryThatIsNotThere(): void
	{
		Lang::addDirs(Config::$boarddir . '/no/such/directory');

		$dirs = (new \ReflectionProperty(Lang::class, 'dirs'))->getValue();

		$this->assertSame(
			[Sapi::canonicalPath(Config::$languagesdir)],
			array_values($dirs),
		);
	}

	public function testItListsTheInstalledLanguages(): void
	{
		$languages = Lang::get(false);

		$this->assertArrayHasKey('en_US', $languages);

		// The name comes from $txt['native_name'] inside the file, which get()
		// reads a line at a time rather than by including it.
		$this->assertSame('English (US)', $languages['en_US']['name']);

		$this->assertSame(
			Sapi::canonicalPath(Config::$languagesdir . '/en_US/General.php'),
			$languages['en_US']['location'],
		);
	}

	public function testItLoadsTheFileAStringWasAskedForFrom(): void
	{
		// Nothing has been loaded at this point; naming the file is what makes
		// getTxt() go and find it.
		$this->assertSame('1 day', Lang::getTxt('number_of_days', [1], file: 'General'));
		$this->assertSame('2 days', Lang::getTxt('number_of_days', [2], file: 'General'));
	}

	public function testItFindsAStringThatOnlyExistsInAFileOnDisk(): void
	{
		$this->assertFalse(Lang::txtExists('actual_theme_dir'));

		$this->assertTrue(Lang::txtExists('actual_theme_dir', file: 'Themes'));
		$this->assertFalse(Lang::txtExists('no_such_string_anywhere', file: 'Themes'));
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->backup = self::langState();
	}

	protected function tearDown(): void
	{
		// PHPUnit does not reset SMF's statics between tests, and a test here
		// leaves a loaded language behind: 700-odd strings in Lang::$txt, the
		// directories in Lang::$dirs, and the record in Lang::$already_loaded
		// that stops a second load() from doing anything. Each test starts from
		// nothing loaded, which is the state the ones above are about.
		foreach ($this->backup as $name => $value) {
			(new \ReflectionProperty(Lang::class, $name))->setValue(null, $value);
		}

		parent::tearDown();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * The statics that loading a language file writes to.
	 *
	 * @return array Property name => current value.
	 */
	private static function langState(): array
	{
		$state = [];

		foreach (['txt', 'txtBirthdayEmails', 'tztxt', 'editortxt', 'helptxt', 'dirs', 'already_loaded', 'loaded_keys'] as $name) {
			$state[$name] = (new \ReflectionProperty(Lang::class, $name))->getValue();
		}

		return $state;
	}
}
