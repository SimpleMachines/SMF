<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\TemplateEngine;
use SMF\Tests\Support\HttpResponse;

/**
 * Copying a template written for Plates into a theme, from the admin panel.
 *
 * The page offers every template the default theme has so that a theme author
 * can start from it. A Plates template is a directory rather than a file, and
 * is copied whole.
 *
 * The theme this copies into is made for the test, in a directory of its own
 * under Themes, and removed again afterwards along with its rows.
 */
#[CoversNothing]
class ThemeCopyTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The id of the theme made for the test, or 0 before it is made.
	 */
	private int $theme_id = 0;

	/**
	 * @var string The directory of the theme made for the test.
	 */
	private string $theme_dir = '';

	/****************
	 * Public methods
	 ****************/

	public function testAPlatesTemplateIsCopiedWhole(): void
	{
		$this->makeTheme();
		$this->signInAsAdminPastThePrompt();

		$page = $this->fetch('?action=admin;area=theme;th=' . $this->theme_id . ';sa=copy');
		$link = $this->copyLink($page, 'Stats');

		$this->assertNotNull($link, 'the copy page offers no way to copy the Stats template');

		$this->http->get($link);

		$source = Config::$boarddir . '/Themes/default/' . TemplateEngine::DIRECTORY . '/Stats';
		$target = $this->theme_dir . '/' . TemplateEngine::DIRECTORY . '/Stats';

		$this->assertFileEquals($source . '/main.php', $target . '/main.php');
		$this->assertFileExists($target . '/index.php');
		$this->assertFileExists(\dirname($target) . '/index.php', 'the new templates directory is left listable');

		$after = $this->fetch('?action=admin;area=theme;th=' . $this->theme_id . ';sa=copy');

		$this->assertStringContainsString(
			TemplateEngine::DIRECTORY . '/Stats/ (already exists)',
			$after->text(),
			'the copy page does not see the template it just copied',
		);

		$this->assertNoErrorsLogged('copying a template logged something.' . "\n");
	}

	/**
	 * The default theme is where the templates are copied from. Copying them
	 * onto themselves opens each file for writing, which empties it, before it
	 * is read.
	 */
	public function testTheDefaultThemeCannotBeCopiedOntoItself(): void
	{
		$this->signInAsAdminPastThePrompt();

		$page = $this->fetch('?action=admin;area=theme;th=1;sa=copy');

		$this->assertNull($this->copyLink($page, 'Stats'), 'the default theme is offered a copy of its own template');

		$file = Config::$boarddir . '/Themes/default/' . TemplateEngine::DIRECTORY . '/Stats/main.php';
		$before = file_get_contents($file);

		$this->http->get('?action=admin;area=theme;th=1;sa=copy;template=Stats;' . $this->sessionQuery($page));

		clearstatcache();

		$this->assertSame($before, file_get_contents($file), 'copying the default theme onto itself changed the template');
	}

	/**
	 * The link carries the session, and a request without it could be forged.
	 */
	public function testCopyingNeedsTheSession(): void
	{
		$this->makeTheme();
		$this->signInAsAdminPastThePrompt();

		$this->http->get('?action=admin;area=theme;th=' . $this->theme_id . ';sa=copy;template=Stats');

		$this->assertDirectoryDoesNotExist($this->theme_dir . '/' . TemplateEngine::DIRECTORY . '/Stats');
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->theme_id !== 0) {
			Db::$db->query(
				'DELETE FROM {db_prefix}themes
				WHERE id_theme = {int:theme}',
				['theme' => $this->theme_id],
			);

			$this->theme_id = 0;
		}

		if ($this->theme_dir !== '' && is_dir($this->theme_dir)) {
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($this->theme_dir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST,
			);

			foreach ($files as $file) {
				$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
			}

			rmdir($this->theme_dir);
		}

		parent::tearDown();
	}

	/**
	 * Makes an empty theme for the test to copy into.
	 */
	private function makeTheme(): void
	{
		$name = 'plates_copy_test_' . bin2hex(random_bytes(4));

		$this->theme_dir = Config::$boarddir . '/Themes/' . $name;

		mkdir($this->theme_dir);

		// The web server runs as another user than this test may.
		chmod($this->theme_dir, 0o777);

		$row = $this->queryRow('SELECT COALESCE(MAX(id_theme), 0) + 1 AS id FROM {db_prefix}themes');
		$this->theme_id = (int) $row['id'];

		$rows = [];

		foreach ([
			'name' => $name,
			'theme_dir' => $this->theme_dir,
			'theme_url' => Config::$boardurl . '/Themes/' . $name,
			'images_url' => Config::$boardurl . '/Themes/' . $name . '/images',
		] as $variable => $value) {
			$rows[] = [$this->theme_id, 0, $variable, $value];
		}

		Db::$db->insert(
			'insert',
			'{db_prefix}themes',
			['id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string', 'value' => 'string'],
			$rows,
			['id_theme', 'id_member', 'variable'],
		);
	}

	/**
	 * Signs in as the administrator, and gives the password again if the
	 * admin area asks for it.
	 */
	private function signInAsAdminPastThePrompt(): void
	{
		$this->signInAsAdmin();

		$page = $this->http->get('?action=admin');
		$form = '//form[.//input[@name="admin_pass"]]';

		if ($page->xpath($form)->length > 0) {
			$this->submitForm($page, ['admin_pass' => self::adminPassword()], $form);
		}
	}

	/**
	 * Finds the link that copies a template.
	 *
	 * @param HttpResponse $page The copy page.
	 * @param string $template The template's name.
	 * @return ?string The link, or null if the page offers none.
	 */
	private function copyLink(HttpResponse $page, string $template): ?string
	{
		foreach ($page->xpath('//a[contains(@href, "sa=copy;template=")]') as $link) {
			$href = html_entity_decode($link->getAttribute('href'));

			if (str_ends_with($href, 'template=' . $template)) {
				return $href;
			}
		}

		return null;
	}

	/**
	 * Gets the session variable and id as a query string, from whichever link
	 * on the page carries them.
	 *
	 * @param HttpResponse $page A page of the admin area.
	 * @return string The query string.
	 */
	private function sessionQuery(HttpResponse $page): string
	{
		foreach ($page->xpath('//a[@href]') as $link) {
			if (preg_match('~;([0-9a-f]+=[0-9a-f]{32})\b~', html_entity_decode($link->getAttribute('href')), $match)) {
				return $match[1];
			}
		}

		$this->fail('no link on the page carries the session');
	}
}
