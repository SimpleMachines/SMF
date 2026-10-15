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

	/**
	 * The forum the suite runs against is installed, at the current version.
	 *
	 * Expected: modSettings['smfVersion'] is not empty and equals SMF_VERSION.
	 */
	public function testTheForumIsInstalled(): void
	{
		$this->assertNotEmpty(Config::$modSettings['smfVersion']);
		$this->assertSame(SMF_VERSION, Config::$modSettings['smfVersion']);
	}

	/**
	 * The configured database engine is one SMF supports.
	 *
	 * Expected: the lower-cased Config::$db_type is mysql or postgresql.
	 */
	public function testTheEngineIsOneSmfSupports(): void
	{
		$this->assertContains(
			strtolower(Config::$db_type),
			['mysql', 'postgresql'],
			'Settings.php names an engine this suite does not know about',
		);
	}

	/**
	 * A write is visible inside the test that made it.
	 *
	 * Writes a row the next test then looks for. Together with
	 * testThatWriteIsGoneByTheNextTest(), this is what proves the rollback in
	 * tearDown() is real.
	 *
	 * Expected: after replacing a setting, rawSetting() returns the value that
	 *           was written.
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

	/**
	 * The previous test's write is gone by the next test.
	 *
	 * Expected: rawSetting() for the variable the previous test wrote returns
	 *           null.
	 */
	#[Depends('testAWriteIsVisibleInsideTheTestThatMadeIt')]
	public function testThatWriteIsGoneByTheNextTest(): void
	{
		$this->assertNull(
			$this->rawSetting(self::LEFTOVER),
			'the previous test\'s write survived, so tests are not isolated',
		);
	}

	/**
	 * Config::$modSettings is restored even though it is a static array.
	 *
	 * The rollback returns the table, not the copy in memory. tearDown() has to
	 * put that back by hand, and testModSettingsHasNoLeftoversFromTheLastTest()
	 * is the check that it does.
	 *
	 * Expected: a key written only to Config::$modSettings is present for the
	 *           rest of this test.
	 */
	public function testModSettingsIsRestoredEvenThoughItIsAStaticArray(): void
	{
		Config::$modSettings['smf_tests_in_memory_only'] = 'x';

		$this->assertArrayHasKey('smf_tests_in_memory_only', Config::$modSettings);
	}

	/**
	 * Config::$modSettings has no leftovers from the last test.
	 *
	 * Expected: the key the previous test wrote only to memory is absent from
	 *           Config::$modSettings.
	 */
	#[Depends('testModSettingsIsRestoredEvenThoughItIsAStaticArray')]
	public function testModSettingsHasNoLeftoversFromTheLastTest(): void
	{
		$this->assertArrayNotHasKey('smf_tests_in_memory_only', Config::$modSettings);
	}

	/**
	 * adminId() finds an administrator that actingAs() can become.
	 *
	 * Expected: adminId() returns a positive id, and after actingAs() with it
	 *           User::$me->id is that id and User::$me->is_admin is true.
	 */
	public function testAdminIdFindsAnAdministrator(): void
	{
		$id = $this->adminId();

		$this->assertGreaterThan(0, $id);

		$this->actingAs($id);

		$this->assertSame($id, \SMF\User::$me->id);
		$this->assertTrue(\SMF\User::$me->is_admin, 'actingAs() did not produce an administrator');
	}

	/**
	 * A test that does nothing logs no errors.
	 *
	 * Expected: assertNoErrorsLogged() passes in a test with an empty body.
	 */
	public function testNothingIsLoggedByAnEmptyTest(): void
	{
		$this->assertNoErrorsLogged();
	}

	/**
	 * assertNoErrorsLogged() notices an error logged during the test.
	 *
	 * The assertion is only worth anything if it can fail, and it reads the log
	 * through a watermark taken in setUp() rather than a count, so an empty log
	 * is not what makes it pass.
	 *
	 * Expected: after a row is inserted into log_errors, assertNoErrorsLogged()
	 *           throws AssertionFailedError.
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
