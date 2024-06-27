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

use SMF\Calendar\Event;
use SMF\Calendar\Holiday;
use SMF\Config;
use SMF\Url;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * Imports updates from subscribed calendars into SMF's calendar.
 */
class FetchCalendarSubscriptions extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		foreach (Utils::jsonDecode(Config::$modSettings['calendar_subscriptions'] ?? '[]', true) as $url => $type) {
			$url = new Url($url, true);

			if ($url->isValid()) {
				$ics_data = WebFetchApi::fetch($url);
			}

			if (!empty($ics_data)) {
				switch ($type) {
					case Event::TYPE_HOLIDAY:
						Holiday::import($ics_data);
						break;

					case Event::TYPE_EVENT:
						Event::import($ics_data);
						break;
				}
			}
		}

		return true;
	}
}

?>