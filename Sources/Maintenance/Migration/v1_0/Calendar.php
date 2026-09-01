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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Calendar extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting calendar';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Calendar();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'eventDate') === [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}calendar
			DROP PRIMARY KEY,
			CHANGE COLUMN id ID_EVENT smallint(5) unsigned NOT NULL auto_increment PRIMARY KEY,
			CHANGE COLUMN id_board ID_BOARD smallint(5) unsigned NOT NULL default 0,
			CHANGE COLUMN id_topic ID_TOPIC mediumint(8) unsigned NOT NULL default 0,
			CHANGE COLUMN id_member ID_MEMBER mediumint(8) unsigned NOT NULL default 0,
			CHANGE COLUMN title title varchar(48) NOT NULL default {empty}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}calendar
			ADD eventDate date NOT NULL default {string:date}',
			[
				// SMF 1.0 actually used '0000-00-00', but modern versions
				// of MySQL don't like that.
				'date' => '1004-01-01',
			],
		);

		$this->query(
			'ALTER TABLE {db_prefix}calendar
			DROP INDEX idx_year_month',
		);

		$this->query(
			'ALTER TABLE {db_prefix}calendar
			DROP INDEX year',
		);

		$this->query(
			'UPDATE IGNORE {db_prefix}calendar
			SET eventDate = CONCAT(year, {string:hyphen}, month + 1, {string:hyphen}, day)',
			[
				'hyphen' => '-',
			],
		);

		$this->query(
			'ALTER TABLE {db_prefix}calendar
			DROP year,
			DROP month,
			DROP day,
			ADD INDEX eventDate (eventDate)',
		);

		$this->handleTimeout();

		return true;
	}
}
