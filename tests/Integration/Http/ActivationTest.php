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
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * The two links in every activation email: one carrying the code, and one
 * without it, where the email says to type the code in by hand.
 *
 * Activation emails go out whatever the registration method is: an admin can
 * require it of a member they register, approve a member subject to it, or
 * remind one, and members changing their email address revalidate it. So the
 * forum here uses immediate registration, where those links fared worst.
 */
#[CoversNothing]
class ActivationTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const CODE = 'a1b2c3d4e5';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made, so tearDown() can remove them.
	 */
	private int $member_id = 0;

	/**
	 * @var ?string The registration method before the test, to put back.
	 */
	private ?string $registration_method = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * The activation link carrying the code activates the account.
	 *
	 * Expected: ?action=activate;u=N;code=C says the account was successfully
	 *           activated and sets is_activated to 1.
	 */
	public function testTheLinkWithTheCodeActivatesTheAccount(): void
	{
		$member = $this->makeMember(0);

		$response = $this->fetch('?action=activate;u=' . $member . ';code=' . self::CODE);

		$this->assertStringContainsString('successfully activated', $response->text(), 'the account was not activated: ' . $response->errorText());
		$this->assertSame('1', (string) $this->queryRow('SELECT is_activated FROM {db_prefix}members WHERE id_member = {int:m}', ['m' => $member])['is_activated']);
	}

	/**
	 * The activation link without the code asks for the code.
	 *
	 * Expected: ?action=activate;u=N, for a member who is not activated or is
	 *           revalidating a changed email address, shows a form with a code
	 *           field and no error.
	 * Guards:   it showed the form for resending the email instead, which asks
	 *           for a username, a new address and a password, and has no field
	 *           for the code unless the forum uses email activation. Under any
	 *           other registration method the form is refused outright, so the
	 *           link was a 403: "You are not allowed to access this section". A
	 *           member revalidating a changed email address was told their
	 *           account was already activated.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/08f009cfb Introduced by "Refactors SMF\Actions\Activate"
	 * @link https://github.com/SimpleMachines/SMF/commit/cfce79d1d Introduced by "Implements SMF\User::save() and SMF\User::saveBatch()"
	 * @link https://github.com/SimpleMachines/SMF/pull/9740
	 */
	#[DataProvider('members')]
	public function testTheLinkWithoutTheCodeAsksForTheCode(int $is_activated): void
	{
		$member = $this->makeMember($is_activated);

		$response = $this->fetch('?action=activate;u=' . $member);

		$this->assertSame('', $response->errorText(), 'the page refused the member');
		$this->assertSame(
			1,
			$response->xpath('//form[contains(@action, "action=activate;u=' . $member . '")]//input[@name="code"]')->length,
			'the page has no field for the activation code',
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Members an activation email is sent to.
	 *
	 * @return array The cases: their is_activated value.
	 */
	public static function members(): array
	{
		return [
			'a new member' => [0],
			'a member who changed their email address' => [2],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->registration_method = $this->rawSetting('registration_method');
		Config::updateModSettings(['registration_method' => '0']);
	}

	protected function tearDown(): void
	{
		if ($this->member_id !== 0) {
			Db::$db->query('DELETE FROM {db_prefix}members WHERE id_member = {int:m}', ['m' => $this->member_id]);
			$this->member_id = 0;
		}

		if (isset(Db::$db)) {
			Config::updateModSettings(['registration_method' => $this->registration_method ?? '0']);
		}

		parent::tearDown();
	}

	/**
	 * Makes a member waiting on activation.
	 *
	 * @param int $is_activated Their activation state.
	 * @return int Their id.
	 */
	private function makeMember(int $is_activated): int
	{
		$name = 'activate_' . bin2hex(random_bytes(4));

		return $this->member_id = (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'passwd' => 'string',
				'date_registered' => 'int',
				'is_activated' => 'int',
				'validation_code' => 'string',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$name, $name, $name . '@example.com', '', time(), $is_activated, self::CODE, '', '', '']],
			['id_member'],
			1,
		);
	}
}
