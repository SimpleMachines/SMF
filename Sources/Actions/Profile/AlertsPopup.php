<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Alert;
use SMF\Config;
use SMF\Debug\DebugUtils;
use SMF\User;
use SMF\Utils;

/**
 * Shows the popup for the current user's alerts.
 */
class AlertsPopup implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// We do not want to output debug information here.
		DebugUtils::disable();

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];

		// No funny business allowed
		$counter = isset($_REQUEST['counter']) ? max(0, (int) $_REQUEST['counter']) : 0;

		$limit = !empty(Config::$modSettings['alerts_per_page']) && (int) Config::$modSettings['alerts_per_page'] < 1000 ? min((int) Config::$modSettings['alerts_per_page'], 1000) : 25;

		Utils::$context['unread_alerts'] = [];

		if ($counter < User::$me->alerts) {
			// Now fetch me my unread alerts, pronto!
			Utils::$context['unread_alerts'] = Alert::fetch(User::$me->id, false, !empty($counter) ? User::$me->alerts - $counter : $limit, 0, !isset($_REQUEST['counter']));
		}
	}
}
