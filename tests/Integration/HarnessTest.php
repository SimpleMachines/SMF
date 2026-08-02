<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * Checks the integration harness itself.
 *
 * A suite whose isolation quietly stopped working would not fail; it would start
 * passing things it should not, or failing things depending on the order they
 * ran in. These tests are here so that breaks loudly instead.
 */
#[CoversNothing]
class HarnessTest extends IntegrationTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The variable the rollback tests write. Named so that finding it left
	 * behind in a real forum points straight back here.
	 */
	private const LEFTOVER = 'smf_tests_rollback_canary';

	/****************
	 * Public methods
	 ****************/

	public function testTheForumIsInstalled(): void
	{
		$this->assertNotEmpty(Config::$modSettings['smfVersion']);
		$this->assertSame(SMF_VERSION, Config::$modSettings['smfVersion']);
	}

	public function testTheEngineIsOneSmfSupports(): void
	{
		$this->assertContains(
			strtolower(Config::$db_type),
			['mysql', 'postgresql'],
			'Settings.php names an engine this suite does not know about',
		);
	}

	/**
	 * Writes a row the next test then looks for. Together with the test below,
	 * this is what proves the rollback in tearDown() is real.
	 */
	public function testAWriteIsVisibleInsideTheTestThatMadeIt(): void
	{
		Db::$db->insert(
			'replace',
			'{db_prefix}settings',
			['variable' => 'string', 'value' => 'string'],
			[[self::LEFTOVER, 'written']],
			['variable'],
		);

		$this->assertSame('written', $this->rawSetting(self::LEFTOVER));
	}

	#[Depends('testAWriteIsVisibleInsideTheTestThatMadeIt')]
	public function testThatWriteIsGoneByTheNextTest(): void
	{
		$this->assertNull(
			$this->rawSetting(self::LEFTOVER),
			'the previous test\'s write survived, so tests are not isolated',
		);
	}

	public function testModSettingsIsRestoredEvenThoughItIsAStaticArray(): void
	{
		// The rollback returns the table, not the copy in memory. tearDown() has
		// to put that back by hand, and this is the check that it does.
		Config::$modSettings['smf_tests_in_memory_only'] = 'x';

		$this->assertArrayHasKey('smf_tests_in_memory_only', Config::$modSettings);
	}

	#[Depends('testModSettingsIsRestoredEvenThoughItIsAStaticArray')]
	public function testModSettingsHasNoLeftoversFromTheLastTest(): void
	{
		$this->assertArrayNotHasKey('smf_tests_in_memory_only', Config::$modSettings);
	}

	public function testAdminIdFindsAnAdministrator(): void
	{
		$id = $this->adminId();

		$this->assertGreaterThan(0, $id);

		$this->actingAs($id);

		$this->assertSame($id, \SMF\User::$me->id);
		$this->assertTrue(\SMF\User::$me->is_admin, 'actingAs() did not produce an administrator');
	}

	public function testNothingIsLoggedByAnEmptyTest(): void
	{
		$this->assertNoErrorsLogged();
	}

	/**
	 * The assertion is only worth anything if it can fail, and it reads the log
	 * through a watermark taken in setUp() rather than a count, so an empty log
	 * is not what makes it pass.
	 */
	public function testAssertNoErrorsLoggedNoticesALoggedError(): void
	{
		Db::$db->insert(
			'insert',
			'{db_prefix}log_errors',
			[
				'log_time' => 'int',
				'id_member' => 'int',
				'ip' => 'inet',
				'url' => 'string',
				'message' => 'string',
				'session' => 'string',
				'error_type' => 'string',
				'file' => 'string',
				'line' => 'int',
				'backtrace' => 'string',
			],
			[[time(), 0, '', '', 'canary', '', 'general', __FILE__, __LINE__, '[]']],
			['id_error'],
		);

		$this->expectException(AssertionFailedError::class);

		$this->assertNoErrorsLogged();
	}
}
