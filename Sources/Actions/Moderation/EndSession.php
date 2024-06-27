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

namespace SMF\Actions\Moderation;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Utils;

/**
 * Ends a moderator session, requiring authentication to access the moderation
 * center again.
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
		unset($_SESSION['moderate_time']);

		// Clean any moderator tokens as well.
		foreach ($_SESSION['token'] as $key => $token) {
			if (str_contains($key, '-mod')) {
				unset($_SESSION['token'][$key]);
			}
		}

		Utils::redirectexit();
	}
}

?>