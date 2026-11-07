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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Routable;
use SMF\Statistics;
use SMF\User;

/**
 * Lets simplemachines.org gather statistics if, and only if, the admin allows.
 */
class SmStats implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	public function isRestrictedGuestAccessAllowed(): bool
	{
		return true;
	}

	public function canBeLogged(): bool
	{
		return false;
	}

	/**
	 * Sends stats to simplemachines.org when requested, but only if enabled!
	 *
	 * - Called by simplemachines.org.
	 * - Only returns anything if stats was enabled during installation.
	 * - Can also be accessed by the admin, to show what info is collected.
	 * - Does not return any data directly to simplemachines.org in response to
	 *   the incoming request. Instead, for security, starts a new, separate
	 *   request back to simplemachines.org.
	 *
	 * See https://www.simplemachines.org/about/stats.php for more info.
	 */
	public function execute(): void
	{
		// First, is it disabled?
		if (empty(Config::$modSettings['enable_sm_stats']) || empty(Config::$modSettings['sm_stats_key'])) {
			die();
		}

		// Are we saying who we are, and are we right? (OR an admin)
		if (!User::$me->is_admin && (!isset($_GET['sid']) || $_GET['sid'] != Config::$modSettings['sm_stats_key'])) {
			die();
		}

		// Verify the referer...
		if (!User::$me->is_admin && !Statistics::refererIsValid()) {
			die();
		}

		// If we're an admin, just plonk them out.
		if (User::$me->is_admin) {
			// Turn this into the query string!
			echo http_build_query(Statistics::collect());
		} else {
			Statistics::sendToCollector();
		}

		// Die.
		die('OK');
	}
}
