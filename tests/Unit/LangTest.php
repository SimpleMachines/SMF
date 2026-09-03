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

	/**
	 * @var string Directory holding the language files a test wrote, if any.
	 */
	private string $fixture_dir = '';

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

	public function testItLoadsTheLanguageItWasAskedForRatherThanTheForumDefault(): void
	{
		Lang::addDirs($this->writeFixtureLanguages());

		Lang::load('TestStrings', 'de_DE', false);

		// load() builds its attempts as [asked for, forum default, English] per
		// directory and then reverses the lot, so the forum default sits ahead
		// of the language that was asked for. Stopping at the first file that
		// exists therefore hands back the wrong language every time, and since
		// the default's file is always present, every time is every call: a
		// member with lngfile de_DE reads the whole forum in en_US, and a
		// digest or notification addressed to them goes out in it too.
		$this->assertSame('German', Lang::$txt['test_translated']);
	}

	public function testItFallsBackToTheDefaultLanguageOneStringAtATime(): void
	{
		Lang::addDirs($this->writeFixtureLanguages());

		Lang::load('TestStrings', 'de_DE', false);

		// The German file does not define this string. Loading the less
		// preferred files first and letting the preferred ones overwrite what
		// they share is what leaves a partial translation readable instead of
		// full of gaps. Dropping the reverse and keeping the stop at the first
		// file found picks the right language too, but loads that file alone,
		// so this is the assertion that tells the two apart.
		$this->assertSame('English only', Lang::$txt['test_untranslated']);
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

		if ($this->fixture_dir !== '') {
			foreach (glob($this->fixture_dir . '/*/*.php') as $file) {
				unlink($file);
			}

			foreach (glob($this->fixture_dir . '/*', GLOB_ONLYDIR) as $dir) {
				rmdir($dir);
			}

			rmdir($this->fixture_dir);

			$this->fixture_dir = '';
		}

		parent::tearDown();
	}

	/**
	 * Writes a two language directory that the tests above can load from.
	 *
	 * Only en_US ships with SMF, so there is no second language on disk to ask
	 * for. These files are the smallest thing that makes the choice visible:
	 * one string both languages define, and one that only the default has.
	 *
	 * @return string Path to the directory holding the language directories.
	 */
	private function writeFixtureLanguages(): string
	{
		$this->fixture_dir = (string) tempnam(sys_get_temp_dir(), 'smf_lang_');

		unlink($this->fixture_dir);
		mkdir($this->fixture_dir . '/en_US', 0777, true);
		mkdir($this->fixture_dir . '/de_DE', 0777, true);

		file_put_contents(
			$this->fixture_dir . '/en_US/TestStrings.php',
			<<<'PHP'
				<?php

				$txt['test_translated'] = 'English';
				$txt['test_untranslated'] = 'English only';
				PHP,
		);

		file_put_contents(
			$this->fixture_dir . '/de_DE/TestStrings.php',
			<<<'PHP'
				<?php

				$txt['test_translated'] = 'German';
				PHP,
		);

		return $this->fixture_dir;
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
