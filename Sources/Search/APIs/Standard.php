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

namespace SMF\Search\APIs;

use SMF\Search\SearchApi;

/**
 * Standard non full index, non custom index search
 */
class Standard extends SearchApi
{
	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = null): bool
	{
		$return = false;

		// Maybe parent got support
		if (!$return) {
			$return = parent::supportsMethod($methodName, $query_params);
		}

		return $return;
	}

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}
}

?>