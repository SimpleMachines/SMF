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
use SMF\Db\DatabaseApi as Db;

/**
 * The tracking section of a member's profile, as a moderator sees it.
 *
 * The member tracked is made for the test and removed again afterwards: an
 * HTTP test runs on another connection than the forum, so it cannot be rolled
 * back.
 */
#[CoversNothing]
class ProfileTrackingTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member made for the test, or 0 before there is one.
	 */
	private int $member_id = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Another member's IP address can be tracked.
	 *
	 * Expected: ?action=profile;area=tracking;sa=ip for a member other than
	 *           the moderator shows the IP address that member last used, with
	 *           nothing logged.
	 * Guards:   the page starts from the IP address the member last used.
	 *           Tracking yourself worked, because you are the member the forum
	 *           has loaded; tracking anybody else read the address from a
	 *           member who had not been loaded, and failed with a TypeError.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9718
	 */
	public function testAnotherMembersIpAddressCanBeTracked(): void
	{
		$this->member_id = $this->makeMember('198.51.100.7');
		$this->signInAsAdmin();

		$path = '?action=profile;u=' . $this->member_id . ';area=tracking;sa=ip';
		$page = $this->fetch($path);

		// Tracking asks for the password again, on a forum with that switched on.
		$prompt = '//form[.//input[@name="admin_pass"]]';

		if ($page->xpath($prompt)->length > 0) {
			$this->submitForm($page, ['admin_pass' => self::adminPassword()], $prompt);
			$page = $this->fetch($path);
		}

		$this->assertStringContainsString(
			'198.51.100.7',
			$page->body,
			'the page does not start from the member\'s address. The forum said: ' . $page->errorText(),
		);

		$this->assertNoErrorsLogged('tracking another member logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member_id !== 0) {
			Db::$db->query(
				'DELETE FROM {db_prefix}members
				WHERE id_member = {int:member}',
				['member' => $this->member_id],
			);

			$this->member_id = 0;
		}

		parent::tearDown();
	}

	/**
	 * Makes a member who last visited from the given address.
	 *
	 * @param string $ip The address.
	 * @return int The member's id.
	 */
	private function makeMember(string $ip): int
	{
		$name = 'tracked_' . bin2hex(random_bytes(4));

		return (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'passwd' => 'string',
				'date_registered' => 'int',
				'is_activated' => 'int',
				'member_ip' => 'inet',
				'member_ip2' => 'inet',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$name, $name, $name . '@example.com', '', time(), 1, $ip, $ip, '', '', '']],
			['id_member'],
			1,
		);
	}
}
