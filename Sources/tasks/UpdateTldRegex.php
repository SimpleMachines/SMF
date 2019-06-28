<?php

/**
 * This file initiates updates of $modSettings['tld_regex']
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Class Update_TLD_Regex
 */
class Update_TLD_Regex extends SMF_BackgroundTask
{
    /**
     * This executes the task. It just calls set_tld_regex() in Subs.php
     * @return bool Always returns true
     */
	public function execute()
 	{
		global $sourcedir;

		require_once($sourcedir . '/Subs.php');
		set_tld_regex(true);

		return true;
	}
}

?>