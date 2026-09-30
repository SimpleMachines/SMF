<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The theme admin's page for copying the default theme's files into a theme.
 */
#[CoversNothing]
class ThemeCopyListTest extends HttpTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Besides the templates, the page works out which language files the theme
	 * already has, and whether each can be written. It built that file's path
	 * out of the array describing it, which logged "Array to string
	 * conversion" for every language file the theme had.
	 */
	public function testThePageLogsNothing(): void
	{
		$this->signInAsAdmin();

		$page = $this->http->get('?action=admin;area=theme;th=1;sa=copy');
		$prompt = '//form[.//input[@name="admin_pass"]]';

		// The admin area asks for the password again, on a forum with that switched on.
		if ($page->xpath($prompt)->length > 0) {
			$this->submitForm($page, ['admin_pass' => self::adminPassword()], $prompt);
		}

		$page = $this->fetch('?action=admin;area=theme;th=1;sa=copy');

		$this->assertStringContainsString('templates/Stats/', $page->text(), 'the copy page does not list the templates. The forum said: ' . $page->errorText());

		$this->assertNoErrorsLogged('the copy page logged something.' . "\n");
	}
}
