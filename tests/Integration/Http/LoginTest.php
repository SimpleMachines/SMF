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
use SMF\Db\InsertType;
use SMF\Group;
use SMF\Security;
use SMF\Tests\Support\HttpClient;
use SMF\User;

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

	/**
	 * The administrator can sign in over HTTP.
	 *
	 * Expected: submitting the login form with the administrator's credentials
	 *           leaves the session signed in, sets the forum cookie, and logs
	 *           nothing.
	 */
	public function testTheAdministratorCanSignIn(): void
	{
		$response = $this->signInAsAdmin();

		$this->assertSignedIn(true, 'the session did not survive the login redirect');

		$this->assertNotEmpty($response->set_cookies, 'logging in set no cookie at all');

		// Note the plural: the response carries the session cookie as well, and
		// keeping only the last one would test whichever happened to come second.
		$this->assertStringContainsString(
			(string) Config::$cookiename,
			implode("\n", $response->set_cookies),
			'the forum cookie was not among those set: ' . implode(' | ', $response->set_cookies),
		);

		$this->assertNoErrorsLogged('signing in logged something.' . "\n");
	}

	/**
	 * An administrator stays signed in after changing their own login name.
	 *
	 * Expected: saving a new login name with only the current password resets
	 *           the stored password and refreshes the current login cookie.
	 * Guards:   the next request used a cookie tied to the old password.
	 *
	 * @link https://github.com/SimpleMachines/SMF/issues/9224
	 */
	public function testChangingTheLoginNameKeepsTheAdministratorSignedIn(): void
	{
		$name = 'rename_' . bin2hex(random_bytes(4));
		$new_name = $name . '_updated';
		$password = 'Test password ' . bin2hex(random_bytes(8));
		$hash = Security::hashPassword($password);
		$member = (int) Db::$db->insert(
			InsertType::Insert,
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'passwd' => 'string',
				'password_salt' => 'string',
				'id_group' => 'int',
				'is_activated' => 'int',
				'date_registered' => 'int',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$name, $name, $name . '@example.invalid', $hash, bin2hex(random_bytes(8)), Group::ADMIN, User::ACTIVATED, time(), '', '', '']],
			['id_member'],
			1,
		);

		try {
			$this->http = new HttpClient();
			$this->fetch('');
			$this->submitForm($this->fetch('?action=login'), [
				'user' => $name,
				'passwrd' => $password,
			], '//form[contains(@action, "action=login2")]');
			$this->assertSignedIn(true, 'the test administrator could not sign in');

			$page = $this->fetch('?action=profile;area=account;changeusername');
			$this->assertSame(1, $page->xpath('//form[@id="creator"]//input[@name="member_name"]')->length);

			$saved = $this->submitForm($page, [
				'member_name' => $new_name,
				'oldpasswrd' => $password,
				'save' => '1',
			], '//form[@id="creator"]');

			$this->assertSame('', $saved->errorText());
			$row = $this->queryRow(
				'SELECT member_name, passwd FROM {db_prefix}members WHERE id_member = {int:member}',
				['member' => $member],
			);
			$this->assertSame($new_name, $row['member_name']);
			$this->assertNotSame($hash, $row['passwd']);
			$this->assertSignedIn(true, 'the new login cookie did not survive a fresh request');

			$this->assertNoErrorsLogged('changing the login name logged an error');
		} finally {
			Db::$db->query('DELETE FROM {db_prefix}log_online WHERE id_member = {int:member}', ['member' => $member]);
			Db::$db->query('DELETE FROM {db_prefix}members WHERE id_member = {int:member}', ['member' => $member]);
		}
	}

	/**
	 * The wrong password does not sign anyone in.
	 *
	 * Expected: submitting the login form with the administrator's name and a
	 *           wrong password leaves the session signed out.
	 */
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
	 * A post carrying no session check is turned away.
	 *
	 * This is the guard that stops another site from posting to the forum on a
	 * visitor's behalf, and nothing that does not go over HTTP can exercise it.
	 *
	 * Expected: posting the correct credentials to ?action=login2 without the
	 *           form's session fields leaves the session signed out.
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

	/**
	 * Signing out ends the session.
	 *
	 * Expected: following the logout link on a signed in page leaves the
	 *           session signed out, and logs nothing.
	 */
	public function testSigningOutEndsTheSession(): void
	{
		// This test should not use the cached authenticated client.
		$this->signInAsAdmin(false);
		$page = $this->fetch('');
		$this->assertSignedIn(true, '', $page);

		$logout = $page->xpath('//a[contains(@href, "action=logout")]')->item(0);

		$this->assertNotNull($logout, 'no logout link to follow');

		// The link carries its own session check in the query string.
		$this->http->get((string) $logout?->attributes?->getNamedItem('href')?->nodeValue);

		$this->assertSignedIn(false, 'still signed in after logging out');
		$this->assertNoErrorsLogged('logging out logged something.' . "\n");
	}
}
