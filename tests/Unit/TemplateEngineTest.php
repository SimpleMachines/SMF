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
use SMF\TemplateEngine;
use SMF\Theme;

/**
 * Covers how SMF\TemplateEngine finds the Plates template for a sub-template.
 *
 * Each test builds a default theme and a child theme in a temporary directory,
 * so nothing depends on which templates the real default theme has converted.
 */
#[CoversClass(TemplateEngine::class)]
class TemplateEngineTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string The temporary directory holding the themes.
	 */
	private string $root;

	/**
	 * @var string The template directory of the default theme.
	 */
	private string $default;

	/**
	 * @var string The template directory of a theme based on the default.
	 */
	private string $child;

	/****************
	 * Public methods
	 ****************/

	/**
	 * A loaded Plates template provides its sub-templates.
	 *
	 * Expected: with Stats loaded, find('main') returns 'Stats/main' and
	 *           rendering it outputs the file.
	 */
	public function testALoadedPlatesTemplateRendersItsSubTemplates(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertSame('Stats/main', $engine->find('main'));
		$this->assertSame('stats page', $engine->render('Stats/main'));
	}

	/**
	 * A Plates template that was not loaded is not searched.
	 *
	 * Expected: with Stats on disk but not loaded, find('main') returns null.
	 */
	public function testATemplateThatWasNotLoadedIsNotSearched(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);

		$this->assertNull($engine->find('main'));
	}

	/**
	 * A sub-template the Plates template lacks is left to the template_*() functions.
	 *
	 * Expected: with Stats loaded and no stats.php in it, find('stats') returns
	 *           null.
	 */
	public function testASubTemplateThePlatesTemplateLacksIsLeftToTheFunctions(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('stats'));
	}

	/**
	 * A template directory's index.php is never a sub-template.
	 *
	 * Every directory in a theme has an index.php that sends a visitor
	 * elsewhere, and it must never be rendered as though it were a page.
	 *
	 * Expected: with Stats loaded and an index.php in it, find('index') returns
	 *           null.
	 */
	public function testTheIndexFileIsNeverASubTemplate(): void
	{
		$this->write($this->default, 'Stats/index.php', 'go away');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('index'));
	}

	/**
	 * A template is a Plates template in a theme that has its directory.
	 *
	 * Expected: isPlatesTemplate() is true for Stats in the theme holding
	 *           Stats/, and false for a theme without it and for a template
	 *           with no directory.
	 */
	public function testATemplateDirectoryIsWhatMakesATemplatePlates(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->child, $this->default]);

		$this->assertTrue($engine->isPlatesTemplate($this->default, 'Stats'));
		$this->assertFalse($engine->isPlatesTemplate($this->child, 'Stats'));
		$this->assertFalse($engine->isPlatesTemplate($this->default, 'Display'));
	}

	/**
	 * A theme overrides one sub-template and inherits the rest.
	 *
	 * A theme only has to hold the sub-templates it changes. The rest come
	 * from the theme it is based on.
	 *
	 * Expected: with the child theme first, main renders the child's file,
	 *           other renders the default theme's, and path('Stats/other') is
	 *           the default theme's file.
	 */
	public function testAThemeOverridesOneSubTemplateAndInheritsTheRest(): void
	{
		$this->write($this->default, 'Stats/main.php', 'default main');
		$this->write($this->default, 'Stats/other.php', 'default other');
		$this->write($this->child, 'Stats/main.php', 'child main');

		$engine = new TemplateEngine([$this->child, $this->default]);
		$engine->addLoaded('Stats');

		$this->assertSame('child main', $engine->render($engine->find('main')));
		$this->assertSame('default other', $engine->render($engine->find('other')));
		$this->assertSame(
			realpath($this->default . '/templates/Stats/other.php'),
			realpath($engine->path('Stats/other')),
		);
	}

	/**
	 * A template that does not exist has no path.
	 *
	 * Expected: path('Stats/main') returns null and exists('Stats/main')
	 *           returns false.
	 */
	public function testThePathOfAMissingTemplateIsNull(): void
	{
		$engine = new TemplateEngine([$this->default]);

		$this->assertNull($engine->path('Stats/main'));
		$this->assertFalse($engine->exists('Stats/main'));
	}

	/**
	 * The data a template is rendered with becomes the template's variables.
	 *
	 * Theme::loadSubTemplate() passes the parameters it was given as the
	 * template's variables.
	 *
	 * Expected: render('Stats/main', ['greeting' => 'Hello', 'name' =>
	 *           'world']) outputs 'Hello, world'.
	 */
	public function testDataIsPassedToTheTemplateAsVariables(): void
	{
		$this->write($this->default, 'Stats/main.php', '<?= $greeting ?>, <?= $name ?>');

		$engine = new TemplateEngine([$this->default]);

		$this->assertSame('Hello, world', $engine->render('Stats/main', ['greeting' => 'Hello', 'name' => 'world']));
	}

	/**
	 * Between a Plates template and a function, the one loaded last provides the sub-template.
	 *
	 * Before Plates, sub-template names were global: whichever template file
	 * defined the function ran it. With both kinds loaded, the template loaded
	 * last decides, as the function it defined would have.
	 *
	 * Expected: find('plates_or_function') returns null after a legacy template
	 *           defining it is loaded, and 'Stats/plates_or_function' once
	 *           Stats is loaded again.
	 */
	public function testAFunctionFromATemplateLoadedLaterWins(): void
	{
		$this->write($this->default, 'Stats/plates_or_function.php', 'plates');
		$legacy = $this->write($this->default, '../Legacy.template.php', '<?php function template_plates_or_function(): void {}');

		require_once $legacy;

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');
		$engine->addLoaded('Legacy', $legacy);

		$this->assertNull($engine->find('plates_or_function'));

		// And the other way around.
		$engine->addLoaded('Stats');

		$this->assertSame('Stats/plates_or_function', $engine->find('plates_or_function'));
	}

	/**
	 * A legacy template loaded later does not hide a Plates sub-template it does not define.
	 *
	 * A legacy template that does not define the function has no say in it.
	 *
	 * Expected: find('main') still returns 'Stats/main' after an unrelated
	 *           legacy template is loaded.
	 */
	public function testAFunctionFromAnotherFileDoesNotHideThePlatesTemplate(): void
	{
		$this->write($this->default, 'Stats/main.php', 'plates');
		$legacy = $this->write($this->default, '../Other.template.php', '<?php');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');
		$engine->addLoaded('Other', $legacy);

		$this->assertSame('Stats/main', $engine->find('main'));
	}

	/**
	 * A directory named twice is looked in once.
	 *
	 * Theme::loadTemplate() can add the default theme to the list a second
	 * time when it goes looking for a misconfigured default theme directory.
	 * Plates refuses a hierarchy that names the same theme twice.
	 *
	 * Expected: a TemplateEngine given the default theme twice keeps it once in
	 *           $dirs and still renders its templates.
	 */
	public function testTheSameDirectoryTwiceIsLookedInOnce(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default, $this->default]);
		$engine->addLoaded('Stats');

		$this->assertSame([$this->default], $engine->dirs);
		$this->assertSame('stats page', $engine->render('Stats/main'));
	}

	/**
	 * With no template directories, nothing is found.
	 *
	 * Theme::loadSubTemplate() asks the engine first, and an error page can
	 * ask before a theme has said where its templates are.
	 *
	 * Expected: find('main') and path('Stats/main') return null on an engine
	 *           with no directories.
	 */
	public function testWithNoDirectoriesNothingIsFound(): void
	{
		$engine = new TemplateEngine([]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('main'));
		$this->assertNull($engine->path('Stats/main'));
	}

	/**
	 * Changing the template directories keeps the templates already loaded.
	 *
	 * Expected: after setDirs() adds the child theme, find('main') renders the
	 *           child's file for the Stats loaded before.
	 */
	public function testChangingTheDirectoriesKeepsWhatWasLoaded(): void
	{
		$this->write($this->child, 'Stats/main.php', 'child main');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');
		$engine->setDirs([$this->child, $this->default]);

		$this->assertSame('child main', $engine->render($engine->find('main')));
	}

	/**
	 * A template calls sub-templates of either kind the same way.
	 *
	 * A template calls another sub-template the same way whether that one has
	 * been converted to Plates yet or is still a template_*() function.
	 *
	 * Expected: a Plates template calling subTemplate() for a Plates sub-
	 *           template and for a template_*() function outputs 'hello Plates,
	 *           hi function'.
	 */
	public function testATemplateCallsSubTemplatesOfEitherKind(): void
	{
		$this->write($this->default, 'Stats/main.php', '<?php $this->subTemplate(\'greeting\', [\'name\' => \'Plates\']) ?>, <?php $this->subTemplate(\'engine_test_greeting\', [\'name\' => \'function\']) ?>');
		$this->write($this->default, 'Stats/greeting.php', 'hello <?= $name ?>');
		$legacy = $this->write($this->default, '../Greeting.template.php', '<?php function template_engine_test_greeting(string $name): void { echo "hi ", $name; }');

		require_once $legacy;

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Greeting', $legacy);
		$engine->addLoaded('Stats');

		$this->assertSame('hello Plates, hi function', $engine->render('Stats/main'));
	}

	/**
	 * hasSubTemplate() finds sub-templates of either kind.
	 *
	 * Expected: hasSubTemplate() is true for a Plates sub-template and for a
	 *           template_*() function, false for neither, and gives the same
	 *           answer inside a template.
	 */
	public function testHasSubTemplateSeesEitherKind(): void
	{
		$this->write($this->default, 'Stats/main.php', '<?= $this->hasSubTemplate(\'main\') ? \'yes\' : \'no\' ?>');
		$legacy = $this->write($this->default, '../Has.template.php', '<?php function template_engine_test_has(): void {}');

		require_once $legacy;

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Has', $legacy);
		$engine->addLoaded('Stats');

		$this->assertTrue($engine->hasSubTemplate('main'));
		$this->assertTrue($engine->hasSubTemplate('engine_test_has'));
		$this->assertFalse($engine->hasSubTemplate('no_such_sub_template'));
		$this->assertSame('yes', $engine->render('Stats/main'));
	}

	/**
	 * Calling a sub-template that does not exist is an error.
	 *
	 * Calling a function that does not exist is an error, and so is calling a
	 * sub-template that does not exist.
	 *
	 * Expected: rendering a template that calls
	 *           subTemplate('no_such_sub_template') throws an Error naming it.
	 */
	public function testCallingAMissingSubTemplateIsAnError(): void
	{
		$this->write($this->default, 'Stats/main.php', '<?php $this->subTemplate(\'no_such_sub_template\') ?>');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->expectException(\Error::class);
		$this->expectExceptionMessage('no_such_sub_template');

		$engine->render('Stats/main');
	}

	/**
	 * Fetching a sub-template returns its output as a string, whatever its kind.
	 *
	 * Some template_*() functions return their HTML for the caller to use,
	 * and a Plates template can only output it. Fetching gets the same string
	 * from either.
	 *
	 * Expected: fetchSubTemplate() returns 'plates 1' for a Plates template and
	 *           'echoed returned 2' for a function that echoes and returns, and
	 *           outputs nothing itself.
	 */
	public function testFetchingGetsTheOutputOfEitherKindAsAString(): void
	{
		$this->write($this->default, 'Stats/main.php', 'plates <?= $n ?>');
		$legacy = $this->write($this->default, '../Fetch.template.php', '<?php function template_engine_test_returns(int $n): string { echo "echoed "; return "returned " . $n; }');

		require_once $legacy;

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Fetch', $legacy);
		$engine->addLoaded('Stats');

		ob_start();
		$plates = $engine->fetchSubTemplate('main', ['n' => 1]);
		$function = $engine->fetchSubTemplate('engine_test_returns', ['n' => 2]);
		$leaked = ob_get_clean();

		$this->assertSame('plates 1', $plates);
		$this->assertSame('echoed returned 2', $function);
		$this->assertSame('', $leaked, 'fetching output something');
	}

	/**
	 * A template_*() function with its own parameter names gets the data in order.
	 *
	 * A call through a name built at runtime names its parameters after the
	 * default theme's sub-templates, and a mod's function may call its own
	 * something else.
	 *
	 * Expected: fetchSubTemplate() passes ['board' => ...] to a function whose
	 *           parameter is $b, and passes ['b' => ..., 'extra' => '?'] by
	 *           name.
	 */
	public function testAFunctionWithOtherParameterNamesGetsThemInOrder(): void
	{
		$legacy = $this->write($this->default, '../Names.template.php', '<?php function template_engine_test_names($b, $extra = "!"): void { echo $b["name"], $extra; }');

		require_once $legacy;

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Names', $legacy);

		$this->assertSame('general!', $engine->fetchSubTemplate('engine_test_names', ['board' => ['name' => 'general']]));
		$this->assertSame('general?', $engine->fetchSubTemplate('engine_test_names', ['b' => ['name' => 'general'], 'extra' => '?']));
	}

	/**
	 * Loading the Plates index template defines the functions mods call.
	 *
	 * Mods and themes call template_button_strip() and friends from templates
	 * of their own. Loading the Plates template that holds them defines them.
	 *
	 * Expected: template_button_strip() and template_quickbuttons() render the
	 *           Plates sub-templates, and quickbuttons returns its output when
	 *           asked to with 'return'.
	 */
	public function testLoadingThePlatesIndexTemplateDefinesTheFunctionsModsCall(): void
	{
		$this->write($this->default, 'index/button_strip.php', 'strip <?= implode(\',\', array_keys($button_strip)) ?> <?= $direction ?>|');
		$this->write($this->default, 'index/quickbuttons.php', 'buttons <?= $list_class ?>|');

		$current = Theme::$current;
		Theme::$current = (object) ['settings' => ['template_dirs' => [$this->default]]];

		try {
			TemplateEngine::get()->addLoaded('index');

			ob_start();
			template_button_strip(['home' => [], 'search' => []], 'right');
			$echoed = template_quickbuttons([], 'post');
			$returned = template_quickbuttons([], 'post', 'return');
			$output = ob_get_clean();
		} finally {
			Theme::$current = $current;
		}

		$this->assertSame('strip home,search right|buttons post|', $output);
		$this->assertNull($echoed);
		$this->assertSame('buttons post|', $returned);
	}

	/**
	 * renderSubTemplate() outputs a sub-template it finds and reports one it does not.
	 *
	 * Expected: renderSubTemplate('main') outputs the file and returns true,
	 *           and returns false for a sub-template that does not exist.
	 */
	public function testRenderSubTemplateOutputsWhatItFinds(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		ob_start();
		$found = $engine->renderSubTemplate('main');
		$missing = $engine->renderSubTemplate('no_such_sub_template');
		$output = ob_get_clean();

		$this->assertTrue($found);
		$this->assertFalse($missing);
		$this->assertSame('stats page', $output);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'smf-template-engine-' . bin2hex(random_bytes(4));
		$this->default = $this->root . \DIRECTORY_SEPARATOR . 'default';
		$this->child = $this->root . \DIRECTORY_SEPARATOR . 'child';

		mkdir($this->default, 0o777, true);
		mkdir($this->child, 0o777, true);
	}

	protected function tearDown(): void
	{
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($this->root);
	}

	/**
	 * Writes a file into a theme's templates directory, or, for a path that
	 * starts with ../, into the theme's template directory itself.
	 *
	 * @param string $theme_dir The theme's template directory.
	 * @param string $file The file, relative to its templates directory.
	 * @param string $contents What to put in it.
	 * @return string The path of the file.
	 */
	private function write(string $theme_dir, string $file, string $contents): string
	{
		$path = str_starts_with($file, '../')
			? $theme_dir . '/' . substr($file, 3)
			: $theme_dir . '/' . TemplateEngine::DIRECTORY . '/' . $file;

		if (!is_dir(\dirname($path))) {
			mkdir(\dirname($path), 0o777, true);
		}

		file_put_contents($path, $contents);

		return $path;
	}
}
