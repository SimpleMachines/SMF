<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Maintenance\Migration\MigrationBase;

class MembersLangUTF8 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Cleaning up after old UTF-8 languages';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$this->query('', '
			UPDATE {db_prefix}members
			SET lngfile = REPLACE(lngfile, {literal:-utf8}, {empty}');

		return true;
	}
}

?>