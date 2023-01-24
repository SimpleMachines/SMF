<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Config;

/**
 * This file contains code used to initiate updates of Config::$modSettings['tld_regex']
 */
class UpdateTldRegex extends BackgroundTask
{
	/**
	 * This executes the task. It just calls set_tld_regex() in Subs.php
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		require_once(Config::$sourcedir . '/Subs.php');
		set_tld_regex(true);

		return true;
	}
}

?>