<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Search;

/**
 * Interface SearchApiInterface
 */
interface SearchApiSettingsiInterface extends SearchApiInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Additional settings this search method supports.
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	public function searchSettings(array $config_vars = []): void;
}
