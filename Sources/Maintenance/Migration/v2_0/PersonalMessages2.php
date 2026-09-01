<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PersonalMessages2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding new personal message settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table is structured correctly.
		$table = new Schema\v2_0\Members();
		$table->normalize();

		// Don't do this if we've done this already.
		if (empty(Config::$modSettings['dont_repeat_buddylists'])) {
			// Update previous ignore lists if they're set to ignore all.
			$this->query(
				"UPDATE {db_prefix}members
				SET pm_receive_from = 3, pm_ignore_list = ''
				WHERE pm_ignore_list = '*'",
				[],
			);

			// Ignore posts made by ignored users by default.
			Db::$db->insert(
				method: 'replace',
				table: '{db_prefix}themes',
				columns: [
					'id_member' => 'int',
					'id_theme' => 'int',
					'variable' => 'string-255',
					'value' => 'string-65535',
				],
				data: [
					[-1, 1, 'posts_apply_ignore_list', '1'],
				],
				keys: [],
			);

			// Enable buddy and ignore lists, and make sure not to skip this step next time we run this.
			Config::updateModSettings([
				'enable_buddylist' => 1,
				'dont_repeat_buddylists' => 1,
			]);
		}

		// And yet, and yet... We might have a small hiccup here...
		if (!empty(Config::$modSettings['dont_repeat_buddylists']) && !isset(Config::$modSettings['enable_buddylist'])) {
			// Correct RC3 adopters' setting here...
			if (isset(Config::$modSettings['enable_buddylists'])) {
				Config::updateModSettings([
					'enable_buddylist' => Config::$modSettings['enable_buddylists'],
					'enable_buddylists' => null,
				]);
			} else {
				// This should never happen :)
				Config::updateModSettings([
					'enable_buddylist' => 1,
				]);
			}
		}

		$this->handleTimeout();

		return true;
	}
}
