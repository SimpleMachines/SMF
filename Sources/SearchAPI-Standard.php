<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Standard non full index, non custom index search
 */
class standard_search extends search_api
{
	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		// Always fall back to the standard search method.
		return false;
	}
}

?>