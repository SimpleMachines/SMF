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

use SMF\Config;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class MysqlLegacyData extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Aligning legacy column data';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type === MYSQL_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// Updating board_permissions
		if ($start <= 0) {
			$this->query('', '
				ALTER TABLE {db_prefix}board_permissions
				MODIFY COLUMN id_profile SMALLINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating log_digest id_topic
		if ($start <= 1) {
			$this->query('', '
				ALTER TABLE {db_prefix}log_digest
				MODIFY COLUMN id_topic MEDIUMINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating log_digest id_msg
		if ($start <= 2) {
			$this->query('', '
				ALTER TABLE {db_prefix}log_digest
				MODIFY COLUMN id_msg INT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating log_reported
		if ($start <= 3) {
			$this->query('', '
				ALTER TABLE {db_prefix}log_reported
				MODIFY COLUMN body MEDIUMTEXT NOT NULL
			');

			$this->handleTimeout(++$start);
		}

		// Updating log_spider_hits
		if ($start <= 4) {
			$this->query('', '
				ALTER TABLE {db_prefix}log_spider_hits
				MODIFY COLUMN processed TINYINT NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating members new_pm
		if ($start <= 5) {
			$this->query('', '
				ALTER TABLE {db_prefix}members
				MODIFY COLUMN new_pm TINYINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating members pm_ignore_list
		if ($start <= 6) {
			$this->query('', '
				ALTER TABLE {db_prefix}members
				MODIFY COLUMN pm_ignore_list TEXT NULL
			');

			$this->handleTimeout(++$start);
		}

		// Updating password_salt
		if ($start <= 7) {
			$this->query('', '
				ALTER TABLE {db_prefix}members
				MODIFY COLUMN password_salt VARCHAR(255) NOT NULL DEFAULT {empty}
			');

			$this->handleTimeout(++$start);
		}

		// Updating member_logins id_member
		if ($start <= 8) {
			$this->query('', '
				ALTER TABLE {db_prefix}member_logins
				MODIFY COLUMN id_member MEDIUMINT NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating member_logins time
		if ($start <= 9) {
			$this->query('', '
				ALTER TABLE {db_prefix}member_logins
				MODIFY COLUMN time INT NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating pm_recipients is_new
		if ($start <= 10) {
			$this->query('', '
				ALTER TABLE {db_prefix}pm_recipients
				MODIFY COLUMN is_new TINYINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating pm_rules id_member
		if ($start <= 11) {
			$this->query('', '
				ALTER TABLE {db_prefix}pm_rules
				MODIFY COLUMN id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating polls guest_vote
		if ($start <= 12) {
			$this->query('', '
				ALTER TABLE {db_prefix}polls
				MODIFY COLUMN guest_vote TINYINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating polls id_member
		if ($start <= 13) {
			$this->query('', '
				ALTER TABLE {db_prefix}polls
				MODIFY COLUMN id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		// Updating sessions last_update
		if ($start <= 14) {
			$this->query('', '
				ALTER TABLE {db_prefix}sessions
				MODIFY COLUMN last_update INT UNSIGNED NOT NULL DEFAULT {literal:0}
			');

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>