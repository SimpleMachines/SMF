<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Config;

/**
 * Signing in over HTTP.
 *
 * Worth testing this way rather than through User::setMe(), which is what the
 * rest of the integration suite uses: the parts most likely to break are exactly
 * the ones setMe() skips. The session has to survive between requests, the
 * security token minted with the form has to still be valid when it comes back,
 * the cookie has to be signed with $auth_secret, and a post that arrives without
 * a session check has to be turned away.
 */
#[CoversNothing]
class LoginTest extends HttpTestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testTheAdministratorCanSignIn(): void
	{
		$this->signInAsAdmin();

		$this->assertSignedIn(true, 'the session did not survive the login redirect');
		$this->assertNoErrorsLogged('signing in logged something.' . "\n");
	}

	/**
	 * The cookie is what carries the login between requests, so it is worth
	 * checking it was issued rather than inferring it from the page changing.
	 */
	public function testSigningInIssuesTheForumCookie(): void
	{
		$response = $this->signInAsAdmin();

		$this->assertNotEmpty($response->set_cookies, 'logging in set no cookie at all');

		// Note the plural: the response carries the session cookie as well, and
		// keeping only the last one would test whichever happened to come second.
		$this->assertStringContainsString(
			(string) Config::$cookiename,
			implode("\n", $response->set_cookies),
			'the forum cookie was not among those set: ' . implode(' | ', $response->set_cookies),
		);
	}

	public function testTheWrongPasswordDoesNotSignAnyoneIn(): void
	{
		$form = $this->fetch('?action=login');

		$this->http->submit($form, [
			'user' => self::adminName(),
			'passwrd' => 'definitely not the password',
		], '//form[contains(@action, "action=login2")]');

		$this->assertSignedIn(false, 'a wrong password signed us in anyway');
	}

	/**
	 * A post carrying no session check should be turned away. This is the guard
	 * that stops another site from posting to the forum on a visitor's behalf,
	 * and nothing that does not go over HTTP can exercise it.
	 */
	public function testAPostWithoutTheSessionCheckIsRejected(): void
	{
		// Hand built rather than submitted from the form, so none of the session
		// fields the form carries are included.
		$this->http->post('?action=login2', [
			'user' => self::adminName(),
			'passwrd' => self::adminPassword(),
		]);

		$this->assertSignedIn(false, 'a login with no session check was accepted');
	}

	public function testSigningOutEndsTheSession(): void
	{
		$this->signInAsAdmin();
		$this->assertSignedIn(true);

		$page = $this->fetch('');
		$logout = $page->xpath('//a[contains(@href, "action=logout")]')->item(0);

		$this->assertNotNull($logout, 'no logout link to follow');

		// The link carries its own session check in the query string.
		$this->http->get((string) $logout?->attributes?->getNamedItem('href')?->nodeValue);

		$this->assertSignedIn(false, 'still signed in after logging out');
		$this->assertNoErrorsLogged('logging out logged something.' . "\n");
	}
}
