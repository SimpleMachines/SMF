<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Standard non full index, non custom index search
 */
class standard_search
{
	/**
	 * This is the last version of SMF that this was tested on, to protect against API changes.
	 *
	 * @var type
	 */
	public $version_compatible = 'SMF 2.1 ALpha';

	/**
	 * This won't work with versions of SMF less than this.
	 *
	 * @var type
	 */
	public $min_smf_version = 'SMF 2.1 Alpha 1';

	/**
	 * Standard search is supported by default.
	 * @var type
	 */
	public $is_supported = true;

	/**
	 * Method to check whether the method can be performed by the API.
	 *
	 * @param type $methodName
	 * @param type $query_params
	 * @return boolean
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		// Always fall back to the standard search method.
		return false;
	}
}

?>