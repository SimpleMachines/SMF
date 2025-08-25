<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class MailType extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update mail_type';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		Db::$db->query(
			'UPDATE {db_prefix}settings
			SET value =
				CASE
					WHEN value = 0
						THEN {string:SendMail}
					WHEN value = 1
						THEN {string:SMTP}
					WHEN value = 2
						THEN {string:SMTPTLS}
					ELSE
						value
					END
			WHERE variable = {string:mail_type}
				AND value IN (0,1,2)',
			[
				'SendMail' => 'SendMail',
				'SMTP' => 'SMTP',
				'SMTPTLS' => 'SMTPTLS',
				'mail_type' => 'mail_type',
				'int_values' => [0, 1, 2],
			],
		);

		return true;
	}
}
