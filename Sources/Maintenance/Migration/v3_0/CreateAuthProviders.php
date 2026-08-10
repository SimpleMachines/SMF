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

class CreateAuthProviders extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Creating identity provider table';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Nothing to do if the table is already there, so a re-run says "skipped".
	 *
	 * Compares against Config::$db_prefix rather than Db::$db->prefix, since
	 * the latter is database qualified while list_tables() reports bare names.
	 */
	public function isCandidate(): bool
	{
		$auth_providers = new Schema\v3_0\AuthProviders();

		return !\in_array(Config::$db_prefix . $auth_providers->name, Db::$db->list_tables());
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$auth_providers = new Schema\v3_0\AuthProviders();
		$auth_providers->create();

		return true;
	}
}
