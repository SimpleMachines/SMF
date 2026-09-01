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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Logs extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting activity logs';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Converting "log_online"
		$table = new Schema\v1_0\LogOnline();
		$table->drop();
		$table->create();

		// Converting "log_floodcontrol"
		$table = new Schema\v1_0\LogFloodcontrol();
		$table->drop();
		$table->create();

		// Converting "log_karma"
		$table = new Schema\v1_0\LogKarma();
		$table->drop();
		$table->create();

		// Retiring "log_clicks"
		Db::$db->drop_table('{db_prefix}log_clicks');

		// Converting "log_notify"
		$inserts = [];

		$request = $this->query(
			'SELECT ID_MEMBER, ID_TOPIC, 0, notificationSent
			FROM {db_prefix}log_topics
			WHERE notificationSent != 0',
		);

		while ($row = Db::$db->fetch_row($request)) {
			$inserts[] = $row;
		}

		Db::$db->free_result($request);

		Db::$db->insert(
			method: '',
			table: '{db_prefix}log_notify',
			columns: [
				'ID_MEMBER' => 'int',
				'ID_TOPIC' => 'int',
				'ID_BOARD' => 'int',
				'sent' => 'int',
			],
			data: $inserts,
			keys: [],
		);

		$table = new Schema\v1_0\LogTopics();
		$table->dropColumn('notificationSent');

		// Converting "log_errors"
		$table = new Schema\v1_0\LogErrors();
		$table->normalize();

		// Converting "log_boards"
		$request = $this->query(
			'SELECT lmr.ID_BOARD, lmr.ID_MEMBER, lmr.logTime
			FROM {db_prefix}log_mark_read AS lmr
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.ID_BOARD = lmr.ID_BOARD AND lb.ID_MEMBER = lmr.ID_MEMBER)
			WHERE lb.logTime < lmr.logTime',
		);

		$inserts = Db::$db->fetch_all($request);

		Db::$db->free_result($request);

		if (!empty($inserts)) {
			Db::$db->insert(
				method: '',
				table: '{db_prefix}log_boards',
				columns: [
					'ID_BOARD' => 'int',
					'ID_MEMBER' => 'int',
					'logTime' => 'int',
				],
				data: $inserts,
				keys: [],
			);
		}

		// Converting "log_activity"
		$table = new Schema\v1_0\LogActivity();
		$structure = $table->getCurrentStructure();

		if (!isset($structure['columns']['date'])) {
			$table->addColumn($table->columns['date']);
		}

		$this->query(
			'UPDATE IGNORE {db_prefix}log_activity
			SET date = year * 10000 + month * 100 + day',
		);

		$table->dropIndex('primary');
		$table->dropColumn('day');
		$table->dropColumn('month');
		$table->dropColumn('year');
		$table->normalize();

		$this->handleTimeout();

		return true;
	}
}
