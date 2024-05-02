<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Utils;

/**
 * Ends an admin session, requiring authentication to access the ACP again.
 */
class EndSession implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// This is so easy!
		unset($_SESSION['admin_time']);

		// Clean any admin tokens as well.
		foreach ($_SESSION['token'] as $key => $token) {
			if (strpos($key, '-admin') !== false) {
				unset($_SESSION['token'][$key]);
			}
		}

		Utils::redirectexit();
	}
}

?>