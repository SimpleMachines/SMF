<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Config;
use SMF\Url;

/**
 * This file contains code used to initiate updates of Config::$modSettings['tld_regex']
 */
class UpdateTldRegex extends BackgroundTask
{
	/**
	 * This executes the task. It just calls Url::setTldRegex()
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		Url::setTldRegex(true);

		return true;
	}
}

?>