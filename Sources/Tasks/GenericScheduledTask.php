<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Tasks;

use SMF\Utils;

/**
 * A class for running scheduled tasks with custom callable functions.
 */
class GenericScheduledTask extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		$callable_task = Utils::getCallable($this->_details['callable']);

		// Perform the task.
		$this->should_log = !empty($callable_task) ? call_user_func($callable_task) : false;

		return true;
	}
}

?>