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

namespace SMF\Tasks;

/**
 * A class for running scheduled tasks with custom callable functions.
 */
class GenericScheduledTask extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		$callable_task = call_helper($this->_details['callable'], true);

		// Perform the task.
		$this->should_log = !empty($callable_task) ? call_user_func($callable_task) : false;

		return true;
	}
}

?>