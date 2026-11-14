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

namespace SMF\Db;

enum ReturnMode: int
{
	/************
	 * Enum cases
	 ************/

	case Off = 0;
	case SingleRow = 1;
	case MultiRow = 2;
}
