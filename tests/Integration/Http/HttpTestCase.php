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

use SMF\Db\DatabaseApi as Db;
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
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var HttpClient The browser for this test.
	 */
	protected HttpClient $http;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * Cached HTTP clients, keyed by the audience they are authenticated as.
	 *
	 * Reusing authenticated clients avoids repeatedly logging in during the
	 * test suite, which would otherwise consume time and repeatedly trigger
	 * flood control.
	 *
	 * @var array<string, HttpClient>
	 */
	private static array $client_cache = [];

	/**
	 * Cached responses for requests whose result is reused by multiple tests.
	 *
	 * @var array<string, HttpResponse>
	 */
	private static array $request_cache = [];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the forum administrator username used by HTTP tests.
	 *
	 * The value comes from SMF_ADMIN_USER when set, otherwise the default
	 * administrator username used by the development forum is assumed.
	 *
	 * @return string The administrator username.
	 */
	public static function adminName(): string
	{
		return (string) (getenv('SMF_ADMIN_USER') ?: 'admin');
	}

	/**
	 * Gets the forum administrator password used by HTTP tests.
	 *
	 * The value comes from SMF_ADMIN_PASS when set, otherwise the default
	 * administrator password used by the development forum is assumed.
	 *
	 * @return string The administrator password.
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

	/**
	 * Initializes the HTTP client used by the test.
	 *
	 * Guest clients are shared between tests because they do not carry an
	 * authenticated session that needs to be isolated per test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->http = $this->client();
	}

	/**
	 * A browser for the test.
	 *
	 * Tests share one guest browser. A class whose browser signs in as
	 * somebody has to give it one of its own, or every test after it would
	 * be browsing as that member.
	 *
	 * @return HttpClient The browser.
	 */
	protected function client(): HttpClient
	{
		return self::$client_cache['guest'] ??= new HttpClient();
	}

	/**
	 * Signs in the HTTP client as the forum administrator.
	 *
	 * The credentials default to those used by .dev/install-forum.sh and can be
	 * overridden with SMF_ADMIN_USER and SMF_ADMIN_PASS for an independently
	 * configured forum.
	 *
	 * When the administrator client has already been cached, that client and
	 * its original login response are reused unless caching is disabled.
	 *
	 * @param bool $use_client_cache Whether to reuse the cached administrator
	 *     client when one is available.
	 * @return HttpResponse The response to the login request.
	 */
	protected function signInAsAdmin(bool $use_client_cache = true): HttpResponse
	{
		if (isset(self::$client_cache['admin']) && $use_client_cache) {
			$this->http = self::$client_cache['admin'];

			return self::$request_cache['admin_login'];
		}

		$this->http = new HttpClient();

		// Load a fresh token first.
		$this->http->get('');

		$response = $this->submitForm($this->fetch('?action=login'), [
			'user' => self::adminName(),
			'passwrd' => self::adminPassword(),
		], '//form[contains(@action, "action=login2")]');

		// A password that does not match is a misconfigured forum rather than a
		// regression, and failing every test in the file over it would say
		// nothing useful. Skipping names the variable to set.
		if (str_contains($response->text(), 'username or password you entered is incorrect')) {
			self::markTestSkipped(
				'cannot sign in as "' . self::adminName() . '". Set SMF_ADMIN_USER and '
				. 'SMF_ADMIN_PASS to this forum\'s administrator, or reinstall with '
				. '.dev/install-forum.sh --engine mysql --force',
			);
		}

		$this->assertLessThan(
			400,
			$response->status,
			'logging in returned ' . $response->status . ': ' . $response->errorText(),
		);

		return self::$request_cache['admin_login'] = $response;
	}

	/**
	 * Submits a form, with flood control out of the way.
	 *
	 * @param HttpResponse $page The page holding the form.
	 * @param array $overrides Values to change or add, including the button.
	 * @param string $xpath Which form.
	 * @return HttpResponse The response.
	 */
	protected function submitForm(HttpResponse $page, array $overrides, string $xpath): HttpResponse
	{
		$this->forgetFloodControl();

		return $this->http->submit($page, $overrides, $xpath);
	}

	/**
	 * Forgets every recent login, post and search the forum is counting.
	 *
	 * Security::spamProtection() allows a moderator one login, post or search
	 * every two seconds, counted per IP address, by keeping a row for each in
	 * log_floodcontrol until it is old enough. Every test here arrives from the
	 * same address, back to back, far faster than a person would, and being
	 * turned away for it is the forum working rather than a regression. With
	 * the rows gone there is nothing to wait out.
	 *
	 * The table only ever holds the last few seconds, so clearing all of it
	 * takes nothing from anybody else.
	 */
	protected function forgetFloodControl(): void
	{
		Db::$db->query(
			'DELETE FROM {db_prefix}log_floodcontrol',
			[],
		);
	}

	/**
	 * Asserts the client is, or is not, signed in.
	 *
	 * Uses the logout link, which the theme only renders for a member.
	 *
	 * @param bool $expected Whether we should be signed in.
	 * @param string $message What was being checked.
	 * @param HttpResponse|null $page The page to inspect.
	 */
	protected function assertSignedIn(bool $expected, string $message = '', ?HttpResponse $page = null): void
	{
		$signed_in = ($page ?? $this->http->get(''))->xpath('//a[contains(@href, "action=logout")]')->length > 0;

		$this->assertSame(
			$expected,
			$signed_in,
			$message !== '' ? $message : ($expected ? 'not signed in' : 'still signed in'),
		);
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
}
