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

class MailQueue extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Creating mail queue functionality';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure this obsolete index has been deleted.
		$table = new Schema\v2_0\MailQueue();
		$table->dropIndex('priority');

		// Add new mail queue settings.
		if (!isset(Config::$modSettings['mail_next_send'])) {
			Config::updateModSettings([
				'mail_next_send' => 0,
				'mail_recent' => '0000000000|0',
			]);
		}

		$this->handleTimeout();

		return true;
	}
}
