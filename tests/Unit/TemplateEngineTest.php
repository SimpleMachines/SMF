<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\TemplateEngine;

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

	public function testALoadedPlatesTemplateRendersItsSubTemplates(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertSame('Stats/main', $engine->find('main'));
		$this->assertSame('stats page', $engine->render('Stats/main'));
	}

	public function testATemplateThatWasNotLoadedIsNotSearched(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);

		$this->assertNull($engine->find('main'));
	}

	public function testASubTemplateThePlatesTemplateLacksIsLeftToTheFunctions(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('stats'));
	}

	/**
	 * Every directory in a theme has an index.php that sends a visitor
	 * elsewhere, and it must never be rendered as though it were a page.
	 */
	public function testTheIndexFileIsNeverASubTemplate(): void
	{
		$this->write($this->default, 'Stats/index.php', 'go away');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('index'));
	}

	public function testATemplateDirectoryIsWhatMakesATemplatePlates(): void
	{
		$this->write($this->default, 'Stats/main.php', 'stats page');

		$engine = new TemplateEngine([$this->child, $this->default]);

		$this->assertTrue($engine->isPlatesTemplate($this->default, 'Stats'));
		$this->assertFalse($engine->isPlatesTemplate($this->child, 'Stats'));
		$this->assertFalse($engine->isPlatesTemplate($this->default, 'Display'));
	}

	/**
	 * A theme only has to hold the sub-templates it changes. The rest come
	 * from the theme it is based on.
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

	public function testThePathOfAMissingTemplateIsNull(): void
	{
		$engine = new TemplateEngine([$this->default]);

		$this->assertNull($engine->path('Stats/main'));
		$this->assertFalse($engine->exists('Stats/main'));
	}

	/**
	 * Theme::loadSubTemplate() passes the parameters it was given as the
	 * template's variables.
	 */
	public function testDataIsPassedToTheTemplateAsVariables(): void
	{
		$this->write($this->default, 'Stats/main.php', '<?= $greeting ?>, <?= $name ?>');

		$engine = new TemplateEngine([$this->default]);

		$this->assertSame('Hello, world', $engine->render('Stats/main', ['greeting' => 'Hello', 'name' => 'world']));
	}

	/**
	 * Before Plates, sub-template names were global: whichever template file
	 * defined the function ran it. With both kinds loaded, the template loaded
	 * last decides, as the function it defined would have.
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
	 * A legacy template that does not define the function has no say in it.
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
	 * Theme::loadTemplate() can add the default theme to the list a second
	 * time when it goes looking for a misconfigured default theme directory.
	 * Plates refuses a hierarchy that names the same theme twice.
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
	 * Theme::loadSubTemplate() asks the engine first, and an error page can
	 * ask before a theme has said where its templates are.
	 */
	public function testWithNoDirectoriesNothingIsFound(): void
	{
		$engine = new TemplateEngine([]);
		$engine->addLoaded('Stats');

		$this->assertNull($engine->find('main'));
		$this->assertNull($engine->path('Stats/main'));
	}

	public function testChangingTheDirectoriesKeepsWhatWasLoaded(): void
	{
		$this->write($this->child, 'Stats/main.php', 'child main');

		$engine = new TemplateEngine([$this->default]);
		$engine->addLoaded('Stats');
		$engine->setDirs([$this->child, $this->default]);

		$this->assertSame('child main', $engine->render($engine->find('main')));
	}

	/**
	 * A template calls another sub-template the same way whether that one has
	 * been converted to Plates yet or is still a template_*() function.
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
	 * Calling a function that does not exist is an error, and so is calling a
	 * sub-template that does not exist.
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
	 * Writes a file into a theme's templates directory.
	 *
	 * @param string $theme_dir The theme's template directory.
	 * @param string $file The file, relative to its templates directory.
	 * @param string $contents What to put in it.
	 * @return string The path of the file.
	 */
	private function write(string $theme_dir, string $file, string $contents): string
	{
		$path = $theme_dir . '/' . TemplateEngine::DIRECTORY . '/' . $file;

		if (!is_dir(\dirname($path))) {
			mkdir(\dirname($path), 0o777, true);
		}

		file_put_contents($path, $contents);

		return $path;
	}
}
