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
use SMF\Maintenance;
use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Migration\MigrationBase;

class PersonalMessageNotification extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Migrating pm notification settings';

	private int $limit = 10000;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		// See which columns we have
		$results = Db::$db->list_columns('{db_prefix}members');

		return in_array('pm_email_notify', $results);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$request = $this->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}members',
			[],
		);

		list($maxMembers) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		Maintenance::$total_items = (int) $maxMembers;

		$is_done = false;

		while (!$is_done) {
			$this->handleTimeout($start);
			$inserts = [];

			// Skip errors here so we don't croak if the columns don't exist...
			$request = $this->query(
				'',
				'
				SELECT id_member, pm_email_notify
				FROM {db_prefix}members
				ORDER BY id_member
				LIMIT {int:start}, {int:limit}',
				[
					'start' => $start,
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$inserts[] = [$row['id_member'], 'pm_new', !empty($row['pm_email_notify']) ? 2 : 0];
				$inserts[] = [$row['id_member'], 'pm_notify', $row['pm_email_notify'] == 2 ? 2 : 1];
			}
			Db::$db->free_result($request);

			if (!empty($inserts)) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}user_alerts_prefs',
					[
						'id_member' => 'int',
						'alert_pref' => 'string',
						'alert_value' => 'string',
					],
					$inserts,
					['id_member', 'alert_pref'],
				);
			}

			$start += $this->limit;

			if ($start >= $maxMembers) {
				$is_done = true;
			}
		}

		if ($is_done) {
			$this->handleTimeout($start);

			$table = new \SMF\Maintenance\Database\Schema\v2_1\Members();
			$existing_structure = $table->getCurrentStructure();

			if (isset($existing_structure['columns']['pm_email_notify'])) {
				$old_col = new Column('pm_email_notify', 'varchar');
				$table->dropColumn($old_col);
			}
		}

		return true;
	}
}

?>