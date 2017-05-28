<?php

/**
 * This file initiates updates of $modSettings['tld_regex']
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
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