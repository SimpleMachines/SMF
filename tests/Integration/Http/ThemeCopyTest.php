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

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Tests\Support\HttpResponse;

/**
 * Copying one of the default theme's templates into a theme, from the admin
 * panel.
 *
 * The copy opens the destination for writing, which empties it, and only then
 * reads the file being copied. So it must never be asked to copy a file onto
 * itself, and a link that makes it do so must not be forgeable.
 *
 * The theme copied into is made for the test, in a directory of its own under
 * Themes, and removed again afterwards along with its rows. A default template
 * that a regression manages to empty is put back from the copy taken first.
 */
#[CoversNothing]
class ThemeCopyTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string The default template the tests copy.
	 */
	private const TEMPLATE = 'Stats';

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

	/**
	 * @var ?string The default template as it was before the test.
	 */
	private ?string $original = null;

	/****************
	 * Public methods
	 ****************/

	public function testATemplateIsCopiedIntoATheme(): void
	{
		$this->makeTheme();
		$this->signInAsAdminPastThePrompt();

		$page = $this->fetch('?action=admin;area=theme;th=' . $this->theme_id . ';sa=copy');
		$link = $this->copyLink($page);

		$this->assertNotNull($link, 'the copy page offers no way to copy ' . self::TEMPLATE . '.template.php');

		$this->http->get($link);

		$this->assertFileEquals($this->defaultTemplate(), $this->theme_dir . '/' . self::TEMPLATE . '.template.php');

		$this->assertNoErrorsLogged('copying a template logged something.' . "\n");
	}

	/**
	 * The default theme is where the templates are copied from, so copying
	 * into it would empty the template before reading it. The page offers no
	 * link to do it, but the request can still be made.
	 */
	public function testTheDefaultThemeIsNeverCopiedOntoItself(): void
	{
		$this->signInAsAdminPastThePrompt();

		$page = $this->fetch('?action=admin;area=theme;th=1;sa=copy');

		$this->http->get('?action=admin;area=theme;th=1;sa=copy;template=' . self::TEMPLATE . ';' . $this->sessionQuery($page));

		clearstatcache();

		$this->assertSame($this->original, file_get_contents($this->defaultTemplate()), 'copying the default theme onto itself emptied the template');
	}

	/**
	 * The link carries the session. Without the check, any page an admin
	 * visited could make the request for them.
	 */
	public function testCopyingNeedsTheSession(): void
	{
		$this->makeTheme();
		$this->signInAsAdminPastThePrompt();

		$this->http->get('?action=admin;area=theme;th=' . $this->theme_id . ';sa=copy;template=' . self::TEMPLATE);

		$this->assertFileDoesNotExist($this->theme_dir . '/' . self::TEMPLATE . '.template.php');
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->original = file_get_contents($this->defaultTemplate());
	}

	protected function tearDown(): void
	{
		if ($this->original !== null && file_get_contents($this->defaultTemplate()) !== $this->original) {
			file_put_contents($this->defaultTemplate(), $this->original);
		}

		if ($this->theme_id !== 0) {
			Db::$db->query(
				'DELETE FROM {db_prefix}themes
				WHERE id_theme = {int:theme}',
				['theme' => $this->theme_id],
			);

			$this->theme_id = 0;
		}

		if ($this->theme_dir !== '' && is_dir($this->theme_dir)) {
			foreach (new \DirectoryIterator($this->theme_dir) as $file) {
				if ($file->isFile()) {
					unlink($file->getPathname());
				}
			}

			rmdir($this->theme_dir);
		}

		parent::tearDown();
	}

	/**
	 * The default theme's copy of the template.
	 *
	 * @return string Its path.
	 */
	private function defaultTemplate(): string
	{
		return Config::$boarddir . '/Themes/default/' . self::TEMPLATE . '.template.php';
	}

	/**
	 * Makes an empty theme for the test to copy into.
	 */
	private function makeTheme(): void
	{
		$name = 'copy_test_' . bin2hex(random_bytes(4));

		$this->theme_dir = Config::$boarddir . '/Themes/' . $name;

		mkdir($this->theme_dir);

		// The web server may run as another user than this test does.
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
	 * Finds the link that copies the template.
	 *
	 * @param HttpResponse $page The copy page.
	 * @return ?string The link, or null if the page offers none.
	 */
	private function copyLink(HttpResponse $page): ?string
	{
		foreach ($page->xpath('//a[contains(@href, "sa=copy;template=")]') as $link) {
			$href = html_entity_decode($link->getAttribute('href'));

			if (str_ends_with($href, 'template=' . self::TEMPLATE)) {
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
