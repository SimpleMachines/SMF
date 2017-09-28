<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 4
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