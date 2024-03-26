<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;

class RemoveKarma extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Removing karma';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return !empty($_SESSION['delete_karma']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		// Delete old settings vars.
		Db::$db->query(
			'',
			'
            DELETE FROM {db_prefix}settings
            WHERE variable IN ({array_string:karma_vars})',
			[
				'karma_vars' => ['karmaMode', 'karmaTimeRestrictAdmins', 'karmaWaitTime', 'karmaMinPosts', 'karmaLabel', 'karmaSmiteLabel', 'karmaApplaudLabel'],
			],
		);

		$member_columns = Db::$db->list_columns('{db_prefix}members');

		// Cleaning up old karma member settings.
		if (in_array('karma_good', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'karma_good');
		}

		// Does karma bad was enable?
		if (in_array('karma_bad', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'karma_bad');
		}

		// Cleaning up old karma permissions.
		Db::$db->query(
			'',
			'
            DELETE FROM {db_prefix}permissions
            WHERE permission = {string:karma_vars}',
			[
				'karma_vars' => 'karma_edit',
			],
		);

		// Cleaning up old log_karma table
		Db::$db->drop_table('{db_prefix}log_karma');

		return true;
	}
}

?>