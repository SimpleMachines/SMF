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

use PHPUnit\Framework\Attributes\CoversClass;
use SMF\Db\DatabaseApi as Db;
use SMF\Permissions\PermissionProfile;

#[CoversClass(PermissionProfile::class)]
class PermissionProfileTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * The profiles come back in the order they were made, as they did in 2.1.
	 *
	 * The rename in the test is what makes PostgreSQL misorder them. On MySQL
	 * the test passes either way.
	 *
	 * Expected: after the default profile is rewritten, loadAll() returns at
	 *           least four profiles, with their ids in ascending order.
	 * Guards:   the query lost its ORDER BY when it was joined to the boards,
	 *           which left the order to the database. MySQL happens to return
	 *           InnoDB rows in primary key order. PostgreSQL returns them in the
	 *           order they sit in the table, and an UPDATE writes a row's new
	 *           version at the end, so renaming the default profile moved it to
	 *           the bottom of every list of profiles.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/1c8b96a95 Introduced by "Implements SMF\Permissions\PermissionProfile"
	 * @link https://github.com/SimpleMachines/SMF/pull/9715
	 */
	public function testProfilesAreLoadedInTheOrderTheyWereMade(): void
	{
		Db::$db->query(
			'UPDATE {db_prefix}permission_profiles
			SET profile_name = profile_name
			WHERE id_profile = {int:profile}',
			[
				'profile' => PermissionProfile::DEFAULT,
			],
		);

		$ids = array_keys(PermissionProfile::loadAll());
		$sorted = $ids;
		sort($sorted);

		$this->assertGreaterThanOrEqual(4, \count($ids), 'the predefined profiles are missing');
		$this->assertSame($sorted, $ids, 'the permission profiles are not in the order they were made');
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		self::forgetLoadedProfiles();
	}

	protected function tearDown(): void
	{
		// What was loaded inside the transaction is gone once it rolls back.
		self::forgetLoadedProfiles();

		parent::tearDown();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Empties loadAll()'s cache, which would otherwise hand back whatever the
	 * first caller in the process loaded.
	 */
	private static function forgetLoadedProfiles(): void
	{
		(new \ReflectionProperty(PermissionProfile::class, 'loaded'))->setValue(null, []);
	}
}
