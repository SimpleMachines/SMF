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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class CreateMemberAuth extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Creating alternative authentication table';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Nothing to do if the table is already there, so a re-run says "skipped".
	 *
	 * Note this compares against Config::$db_prefix rather than Db::$db->prefix.
	 * The latter is database qualified, e.g. `smf`.smf_, while list_tables()
	 * reports bare names, so it would never match. That mismatch is also why
	 * Table::exists() cannot be used here.
	 */
	public function isCandidate(): bool
	{
		$member_auth = new Schema\v3_0\MemberAuth();

		return !\in_array(Config::$db_prefix . $member_auth->name, Db::$db->list_tables());
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$member_auth = new Schema\v3_0\MemberAuth();
		$member_auth->create();

		return true;
	}
}
