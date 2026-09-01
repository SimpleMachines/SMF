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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Bans extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating ban system';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return Db::$db->list_tables(false, Config::$db_prefix . 'banned') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Splitting ban table.
		$this->query(
			'RENAME TABLE {db_prefix}banned
			TO {db_prefix}ban_groups',
		);

		$bg_table = new Schema\v1_1\BanGroups();
		$bg_table->normalize();

		$bi_table = new Schema\v1_1\BanItems();
		$bi_table->create();

		$request = $this->query(
			'SELECT id_ban_group, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER
			FROM {db_prefix}ban_groups',
		);

		while ($row = Db::$db->fetch_row($request)) {
			Db::$db->insert(
				method: '',
				table: '{db_prefix}ban_items',
				columns: [
					'ID_BAN_GROUP' => 'int',
					'ip_low1' => 'int',
					'ip_high1' => 'int',
					'ip_low2' => 'int',
					'ip_high2' => 'int',
					'ip_low3' => 'int',
					'ip_high3' => 'int',
					'ip_low4' => 'int',
					'ip_high4' => 'int',
					'hostname' => 'string-255',
					'email_address' => 'string-255',
					'ID_MEMBER' => 'int',
				],
				data: [$row],
				keys: ['ID_BAN'],
			);
		}

		Db::$db->free_result($request);

		$bg_table->dropColumn('ban_type');
		$bg_table->dropColumn('ip_low1');
		$bg_table->dropColumn('ip_high1');
		$bg_table->dropColumn('ip_low2');
		$bg_table->dropColumn('ip_high2');
		$bg_table->dropColumn('ip_low3');
		$bg_table->dropColumn('ip_high3');
		$bg_table->dropColumn('ip_low4');
		$bg_table->dropColumn('ip_high4');
		$bg_table->dropColumn('hostname');
		$bg_table->dropColumn('email_address');
		$bg_table->dropColumn('ID_MEMBER');

		$this->handleTimeout();

		// Generate names for existing bans.
		$request = $this->query(
			'SELECT id_ban_group, restriction_type
			FROM {db_prefix}ban_groups
			ORDER BY ban_time ASC',
		);

		$ban_names = [
			'full_ban' => 1,
			'cannot_register' => 1,
			'cannot_post' => 1,
		];

		if ($request != false) {
			while ($row = Db::$db->fetch_assoc($request)) {
				$this->query(
					'UPDATE {db_prefix}ban_groups
					SET name = {string:ban_name}
					WHERE id_ban_group = {int:id}',
					[
						'ban_name' => $row['restriction_type'] . '_' . str_pad($ban_names[$row['restriction_type']]++, 3, '0', STR_PAD_LEFT),
						'id' => $row['id_ban_group'],
					],
				);
			}

			Db::$db->free_result($request);
		}

		$this->handleTimeout();

		// Move each restriction type to its own column.
		$this->query(
			'UPDATE {db_prefix}ban_groups
			SET
				cannot_access = IF(restriction_type = {string:full_ban}, 1, 0),
				cannot_register = IF(restriction_type = {string:cannot_register}, 1, 0),
				cannot_post = IF(restriction_type = {string:cannot_post}, 1, 0)',
			[
				'full_ban' => 'full_ban',
				'cannot_register' => 'cannot_register',
				'cannot_post' => 'cannot_post',
			],
		);

		$bg_table->dropColumn('restriction_type');

		// Make sure everybody's ban situation is re-evaluated.
		$this->query(
			'UPDATE {db_prefix}settings
			SET value = {int:now}
			WHERE variable = {string:var}',
			[
				'now' => time(),
				'var' => 'banLastUpdated',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
