<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file exists to make it easy for developers to update the
 * Unicode data in $sourcedir/Unicode whenever a new version of the
 * Unicode Character Database is released. Just run this file from the
 * command line in order to perform the update.
 *
 * Note:
 *
 *  1. Any updates to the Unicode data files SHOULD be included in the
 *     install and large upgrade packages.
 *
 * 	2. Any updates to the Unicode data files SHOULD NOT be included in
 *     the patch packages. The Update_Unicode background task will take
 *     care of that on existing forums.
 *
 *
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

namespace SMF\other\Updaters;

use SMF\Tasks\UpdateUnicode;

/**
 * Class UnicodeDataUpdater
 */
class UnicodeDataUpdater extends UpdaterBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $commit_msg = 'Updates Unicode data';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		if (php_sapi_name() === 'cli') {
			echo 'Updating Unicode data...', PHP_EOL;
		}

		$this->checkoutNewBranch();

		$updater = new UpdateUnicode(['files_only' => true]);
		$updater->execute();

		if (php_sapi_name() === 'cli') {
			echo 'Done.', !$this->hasChanged() ? ' No changes were made.' : '', PHP_EOL;
		}

		$this->ready_to_commit = true;

		$this->removeUselessBranch();
	}
}
