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
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PaidSubs extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding paid subscriptions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the tables are structured correctly.
		$table = new Schema\v2_0\Subscriptions();
		$table->normalize();

		$table = new Schema\v2_0\LogSubscribed();
		$table->normalize();

		// Clean up any pre-2.0 mod settings.
		Config::updateModSettings([
			'paid_currency_code' => Config::$modSettings['currency_code'] ?? null,
			'paid_currency_symbol' => Config::$modSettings['currency_symbol'] ?? null,
			'currency_code' => null,
			'currency_symbol' => null,
		]);

		$this->query(
			'UPDATE {db_prefix}log_subscribed
			SET status = 0
			WHERE status = 1',
			[],
		);

		$this->query(
			'UPDATE {db_prefix}log_subscribed
			SET status = 1
			WHERE status = 2',
			[],
		);

		$this->handleTimeout();

		return true;
	}
}
