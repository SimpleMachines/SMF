<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class DropTimeOffset extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing time_offset column from members table';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\Members();
		$existing_structure = $table->getCurrentStructure();

		return isset($existing_structure['columns']['time_offset']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$forum_tzid = $this->getForumTimezoneIdentifier();
		$forum_timezone = new \DateTimeZone($forum_tzid);
		$now = new \DateTimeImmutable('now', $forum_timezone);
		$forum_utc_offset = $forum_timezone->getOffset($now);

		// Build a list of time zones for time offsets currently in use,
		// but only where a time zone has not already been set.
		$offsets = [];

		$request = $this->query(
			'SELECT DISTINCT time_offset
			FROM {db_prefix}members
			WHERE timezone = {empty}',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (isset($offsets[$row['time_offset']])) {
				continue;
			}

			$offset = (int) ($forum_utc_offset + $row['time_offset'] * 3600);

			if ($offset % 3600 != 0) {
				$tzid = @timezone_name_from_abbr('', $offset, (int) $now->format('I'));
			} else {
				$tzid = 'Etc/GMT' . ($offset === 0 ? '' : \sprintf('%+d', $offset / -3600));

				if (!\in_array($tzid, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
					$tzid = null;
				}
			}

			$offsets[$row['time_offset']] = \is_string($tzid) ? $tzid : $forum_tzid;
		}

		Db::$db->free_result($request);

		// Update the timezone column for any member that doesn't have one already.
		if (!empty($offsets)) {
			$set = 'SET timezone = CASE';
			$params = [];

			foreach ($offsets as $offset => $tzid) {
				$offset = (string) $offset;

				$set .= ' WHEN time_offset = {float:' . md5($offset) . '} THEN {string:' . md5($tzid) . '}';

				$params[md5($offset)] = $offset;
				$params[md5($tzid)] = $tzid;
			}

			$set .= ' END';

			$this->query(
				'UPDATE {db_prefix}members
				' . $set . '
				WHERE timezone = {empty}',
				$params,
			);
		}

		// Just in case the previous update missed anyone.
		$this->query(
			'UPDATE {db_prefix}members
			SET timezone = {string:tzid}
			WHERE timezone = {empty}',
			[
				'tzid' => $forum_tzid,
			],
		);

		// Finally, drop the time_offset column.
		$table = new Schema\v3_0\Members();
		$table->dropColumn('time_offset');

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the identifier string of the forum's default time zone.
	 *
	 * @return string
	 */
	private function getForumTimezoneIdentifier(): string
	{
		// First try to retrieve it from the settings table.
		$request = $this->query(
			'SELECT value
			FROM {db_prefix}settings
			WHERE variable = {literal:default_timezone}',
		);

		if (Db::$db->num_rows($request) > 0) {
			list($forum_tzid) = Db::$db->fetch_row($request);
		}

		Db::$db->free_result($request);

		// If we didn't find it or it's invalid, fix it.
		if (
			!\in_array(
				$forum_tzid ?? null,
				timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC),
			)
		) {
			$forum_tzid = date_default_timezone_get();

			Maintenance::$tool->updateModSettings(
				['default_timezone' => $forum_tzid],
				update: true,
			);
		}

		return $forum_tzid;
	}
}
