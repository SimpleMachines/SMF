<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use SMF\Tests\Integration\IntegrationTestCase;
use SMF\Tests\Support\HttpClient;
use SMF\Tests\Support\HttpResponse;

/**
 * Base class for tests that drive the forum over HTTP.
 *
 * These are the ones that prove a page actually works, rather than that the code
 * behind it parses. Everything a visitor touches on the way - the session, the
 * cookies, the theme, the template - is in the path.
 *
 * They cost more than the other integration tests and they cannot be rolled back,
 * so keep them to journeys that are worth the money.
 */
abstract class HttpTestCase extends IntegrationTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * How long to wait out flood control, in seconds.
	 *
	 * Security::spamProtection() allows a moderator one login, post or search
	 * every two seconds, counted per IP address - and every test here arrives
	 * from the same one, back to back, far faster than a person would. Three
	 * seconds clears the two second window with room to spare.
	 */
	protected const FLOOD_WAIT = 3;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var HttpClient The browser for this test.
	 */
	protected HttpClient $http;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The administrator to sign in as.
	 *
	 * @return string The member name.
	 */
	public static function adminName(): string
	{
		return (string) (getenv('SMF_ADMIN_USER') ?: 'admin');
	}

	/**
	 * That administrator's password.
	 *
	 * @return string The password.
	 */
	public static function adminPassword(): string
	{
		return (string) (getenv('SMF_ADMIN_PASS') ?: 'password');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * These tests are not wrapped in a transaction, and cannot be.
	 *
	 * The request runs in the web server's process on its own connection, so
	 * nothing this one does is visible to it and nothing it does can be rolled
	 * back from here. Worse, MySQL defaults to REPEATABLE READ: an open
	 * transaction here would keep reading the snapshot it took before the
	 * request, so assertNoErrorsLogged() would never see an error the request
	 * logged, and would pass no matter what happened.
	 *
	 * Anything written therefore stays written. Tests here either read, or clean
	 * up after themselves.
	 *
	 * @return bool Always false.
	 */
	protected function usesTransaction(): bool
	{
		return false;
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->http = new HttpClient();

		// Arrive at the forum before doing anything else, which is what a person
		// does and what the tests below depend on.
		//
		// The very first request of a new session regenerates it - SMF sets a
		// guest login cookie, and Cookie::setLoginCookie() throws the session
		// away and starts another whenever that value changes. Anything minted
		// earlier in that same request is minted against the session that just
		// went away, so a security token taken from the first page a visitor
		// ever sees can never be validated. Posting that form comes back 403,
		// "Token verification failed", with nothing to suggest the token was
		// fine and the session underneath it was not.
		$this->http->get('');
	}

	/**
	 * Signs in as the forum administrator.
	 *
	 * The credentials are the ones .docker/install-forum.sh uses, overridable
	 * through the environment for a forum that was set up some other way.
	 *
	 * @return HttpResponse The response to the login post.
	 */
	protected function signInAsAdmin(): HttpResponse
	{
		$response = $this->attemptSignIn();

		if (self::isThrottled($response)) {
			sleep(self::FLOOD_WAIT);

			$response = $this->attemptSignIn();
		}

		// A password that does not match is a misconfigured forum rather than a
		// regression, and failing every test in the file over it would say
		// nothing useful. Skipping names the variable to set.
		if (str_contains($response->text(), 'username or password you entered is incorrect')) {
			self::markTestSkipped(
				'cannot sign in as "' . self::adminName() . '". Set SMF_ADMIN_USER and '
				. 'SMF_ADMIN_PASS to this forum\'s administrator, or reinstall with '
				. '.docker/install-forum.sh --engine mysql --force',
			);
		}

		$this->assertLessThan(
			400,
			$response->status,
			'logging in returned ' . $response->status . ': ' . $response->errorText(),
		);

		return $response;
	}

	/**
	 * Submits a form, waiting out flood control if it gets in the way.
	 *
	 * Tests do in half a second what a person would take a minute over, so they
	 * trip SMF's flood protection routinely. That is the forum working, not a
	 * regression, and the difference between a suite people trust and one that
	 * fails now and then for reasons nobody can reproduce.
	 *
	 * @param HttpResponse $page The page holding the form.
	 * @param array $overrides Values to change or add, including the button.
	 * @param string $xpath Which form.
	 * @return HttpResponse The response.
	 */
	protected function submitForm(HttpResponse $page, array $overrides, string $xpath): HttpResponse
	{
		$response = $this->http->submit($page, $overrides, $xpath);

		if (!self::isThrottled($response)) {
			return $response;
		}

		sleep(self::FLOOD_WAIT);

		// The page has to be fetched again rather than resubmitted: its security
		// token was spent on the attempt that just bounced.
		return $this->http->submit($this->http->get($page->url), $overrides, $xpath);
	}

	/**
	 * Asserts the client is, or is not, signed in.
	 *
	 * Uses the logout link, which the theme only renders for a member.
	 *
	 * @param bool $expected Whether we should be signed in.
	 * @param string $message What was being checked.
	 */
	protected function assertSignedIn(bool $expected, string $message = ''): void
	{
		$signed_in = $this->fetch('')->xpath('//a[contains(@href, "action=logout")]')->length > 0;

		$this->assertSame($expected, $signed_in, $message !== '' ? $message : ($expected ? 'not signed in' : 'still signed in'));
	}

	/**
	 * Fetches a page and asserts it came back whole.
	 *
	 * @param string $path Where to go, as HttpClient::get() takes it.
	 * @param int $expected The status it should return.
	 * @return HttpResponse The response, for further assertions.
	 */
	protected function fetch(string $path, int $expected = 200): HttpResponse
	{
		$response = $this->http->get($path);

		$this->assertSame(
			$expected,
			$response->status,
			$path . ' returned ' . $response->status . ' from ' . $this->http->base_url,
		);

		return $response;
	}

	/**
	 * Asserts a page is a real forum page rather than an error SMF rendered
	 * with a 200.
	 *
	 * A fatal error in SMF is a normal page with an apologetic message in it, so
	 * the status code alone proves very little.
	 *
	 * @param HttpResponse $response The response to check.
	 * @param string $where What was being fetched, for the failure message.
	 */
	protected function assertLooksLikeAForumPage(HttpResponse $response, string $where): void
	{
		$this->assertNotSame('', $response->title(), $where . ' has no <title>');

		$this->assertGreaterThan(
			0,
			$response->xpath('//div[@id="footer"] | //footer | //*[@id="bot"]')->length,
			$where . ' has no footer, so the template did not finish rendering',
		);

		$this->assertSame(
			0,
			$response->xpath('//*[contains(@class, "errorbox")]')->length,
			$where . ' rendered an error box: ' . $response->errorText(),
		);
	}

	/**
	 * One go at the login form.
	 *
	 * @return HttpResponse The response to the post.
	 */
	private function attemptSignIn(): HttpResponse
	{
		$form = $this->fetch('?action=login');

		return $this->http->submit($form, [
			'user' => self::adminName(),
			'passwrd' => self::adminPassword(),
		], '//form[contains(@action, "action=login2")]');
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Whether a response is SMF turning us away for going too fast.
	 *
	 * @param HttpResponse $response The response to look at.
	 * @return bool Whether flood control rejected it.
	 */
	protected static function isThrottled(HttpResponse $response): bool
	{
		$error = $response->errorText();

		return str_contains($error, 'You will have to wait')
			|| str_contains($error, 'The last posting from your IP');
	}
}
