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
	 * @var string Config::$language as it was before the test ran.
	 */
	private string $language = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Language strings load in a process with no database.
	 *
	 * Expected: load('General') returns 'en_US', and Lang::$txt
	 *           then has the 'number_of_days' and 'days'
	 *           strings.
	 */
	public function testItLoadsLanguageStringsWithNoDatabase(): void
	{
		$this->assertSame('en_US', Lang::load('General'));

		$this->assertArrayHasKey('number_of_days', Lang::$txt);
		$this->assertArrayHasKey('days', Lang::$txt);
	}

	/**
	 * With no current user, the language defaults to the forum language.
	 *
	 * User::$me is a typed static with no default, and reading an
	 * uninitialized one throws rather than yielding null. load() gets away
	 * with `User::$me->language ?? Config::$language` only because ??
	 * evaluates its left side in an isset() context, which is easy to break
	 * by "tidying" it into something that reads the property first.
	 *
	 * Expected: with User::$me unset, load('General') returns
	 *           Config::$language.
	 */
	public function testItDefaultsToTheForumLanguageWhenThereIsNoUser(): void
	{
		$this->assertFalse(isset(User::$me));

		$this->assertSame(Config::$language, Lang::load('General'));
	}

	/**
	 * With no theme, only the languages directory is searched.
	 *
	 * Expected: after addDirs(), the private Lang::$dirs holds
	 *           just the canonical path of
	 *           Config::$languagesdir.
	 */
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

	/**
	 * A custom directory that does not exist is ignored.
	 *
	 * Expected: after addDirs() is given a path that is not
	 *           there, Lang::$dirs holds just the canonical path
	 *           of Config::$languagesdir.
	 */
	public function testItIgnoresACustomDirectoryThatIsNotThere(): void
	{
		Lang::addDirs(Config::$boarddir . '/no/such/directory');

		$dirs = (new \ReflectionProperty(Lang::class, 'dirs'))->getValue();

		$this->assertSame(
			[Sapi::canonicalPath(Config::$languagesdir)],
			array_values($dirs),
		);
	}

	/**
	 * The installed languages are listed with their names and locations.
	 *
	 * Expected: get(false) includes 'en_US', named 'English
	 *           (US)', located at the canonical path of
	 *           en_US/General.php in Config::$languagesdir.
	 */
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

	/**
	 * Naming a file when asking for a string loads that file.
	 *
	 * Expected: getTxt('number_of_days', [1], file: 'General')
	 *           returns '1 day' and, with 2, returns '2 days'.
	 */
	public function testItLoadsTheFileAStringWasAskedForFrom(): void
	{
		// Nothing has been loaded at this point; naming the file is what makes
		// getTxt() go and find it.
		$this->assertSame('1 day', Lang::getTxt('number_of_days', [1], file: 'General'));
		$this->assertSame('2 days', Lang::getTxt('number_of_days', [2], file: 'General'));
	}

	/**
	 * A string that only exists in a file on disk can be found.
	 *
	 * Expected: txtExists('actual_theme_dir') is false until
	 *           file: 'Themes' is named, when it is true;
	 *           txtExists('no_such_string_anywhere', file:
	 *           'Themes') is false.
	 */
	public function testItFindsAStringThatOnlyExistsInAFileOnDisk(): void
	{
		$this->assertFalse(Lang::txtExists('actual_theme_dir'));

		$this->assertTrue(Lang::txtExists('actual_theme_dir', file: 'Themes'));
		$this->assertFalse(Lang::txtExists('no_such_string_anywhere', file: 'Themes'));
	}

	/**
	 * The language that was asked for is loaded, not the forum default.
	 *
	 * Expected: after load('TestStrings', 'de_DE', false) against
	 *           the fixture languages,
	 *           Lang::$txt['test_in_all_three'] is 'German'.
	 * Guards:   load() stopped at the first file it found, which
	 *           after the attempts were reversed was the forum
	 *           default's, so a member whose language was
	 *           anything else read the forum, and received
	 *           email, in the forum default.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/d33481a33 Introduced by "Uses consistent logic to load both standard and legacy language files"
	 * @link https://github.com/SimpleMachines/SMF/pull/9612
	 */
	public function testItLoadsTheLanguageItWasAskedForRatherThanTheForumDefault(): void
	{
		Lang::addDirs(self::fixtureLanguages());

		Lang::load('TestStrings', 'de_DE', false);

		// load() builds its attempts as [asked for, forum default, English] per
		// directory and then reverses the lot, so the forum default sits ahead
		// of the language that was asked for. Stopping at the first file that
		// exists therefore hands back the wrong language every time, and since
		// the default's file is always present, every time is every call: a
		// member with lngfile de_DE reads the whole forum in en_US, and a
		// digest or notification addressed to them goes out in it too.
		$this->assertSame('German', Lang::$txt['test_in_all_three']);
	}

	/**
	 * A string missing from a translation falls back to the forum default,
	 * then English.
	 *
	 * The forum's default language need not be English, and then there are
	 * three files in play rather than two. Each string below is defined in
	 * a different subset of them, so together they say how far down the
	 * chain each one had to go.
	 *
	 * Expected: with the forum default es_ES, load('TestStrings',
	 *           'de_DE', false) gives 'German' for
	 *           test_in_all_three, 'Spanish' for
	 *           test_not_in_german and 'English' for
	 *           test_english_only.
	 * Guards:   load() stopped at the first file it found, which
	 *           was the forum default's, so the language that
	 *           was asked for was never loaded.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/d33481a33 Introduced by "Uses consistent logic to load both standard and legacy language files"
	 * @link https://github.com/SimpleMachines/SMF/pull/9612
	 */
	public function testItFallsBackThroughTheForumDefaultToEnglishStringByString(): void
	{
		Config::$language = 'es_ES';

		Lang::addDirs(self::fixtureLanguages());

		Lang::load('TestStrings', 'de_DE', false);

		// Translated: the language that was asked for.
		$this->assertSame('German', Lang::$txt['test_in_all_three']);

		// Not translated, but the forum's default language has it, and the
		// default is nearer than English.
		$this->assertSame('Spanish', Lang::$txt['test_not_in_german']);

		// Neither has it, so English is the last resort.
		$this->assertSame('English', Lang::$txt['test_english_only']);

		// These last two are also what tells the fix apart from the other
		// obvious one. Dropping the reverse and keeping the stop at the first
		// file found picks the right language too, but loads that file alone,
		// leaving a partial translation full of gaps.
	}

	/**
	 * With the English fallback disabled, loading stops at the forum
	 * default.
	 *
	 * Expected: with disable_language_fallback on and es_ES as
	 *           the forum default, load('TestStrings', 'de_DE',
	 *           false) gives 'German' and 'Spanish' for the
	 *           strings they define, and leaves
	 *           test_english_only out of Lang::$txt.
	 * Guards:   load() stopped at the first file it found, which
	 *           was the forum default's, so the language that
	 *           was asked for was never loaded.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/d33481a33 Introduced by "Uses consistent logic to load both standard and legacy language files"
	 * @link https://github.com/SimpleMachines/SMF/pull/9612
	 */
	public function testItStopsAtTheForumDefaultWhenTheEnglishFallbackIsDisabled(): void
	{
		Config::$language = 'es_ES';
		Config::$modSettings['disable_language_fallback'] = true;

		Lang::addDirs(self::fixtureLanguages());

		Lang::load('TestStrings', 'de_DE', false);

		$this->assertSame('German', Lang::$txt['test_in_all_three']);
		$this->assertSame('Spanish', Lang::$txt['test_not_in_german']);

		// English is never attempted, so a string only English defines is
		// simply not there.
		$this->assertArrayNotHasKey('test_english_only', Lang::$txt);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->backup = self::langState();
		$this->language = Config::$language;
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

		Config::$language = $this->language;
		unset(Config::$modSettings['disable_language_fallback']);

		parent::tearDown();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * The language directory the fixture files live in.
	 *
	 * @return string Path to the directory holding en_US, es_ES and de_DE.
	 */
	private static function fixtureLanguages(): string
	{
		return __DIR__ . '/../fixtures/languages';
	}

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
