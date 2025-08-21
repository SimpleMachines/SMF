<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Msg;
use SMF\Routable;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Shows a specific snapshot from the edit history of the requested message.
 */
class ShowMsgHistory implements ActionInterface, Routable
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * We don't log this action for the same reason that we don't log popups.
	 */
	public function canBeLogged(): bool
	{
		return false;
	}

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// We want template_diff() in the EditHistory template file.
		Theme::loadTemplate('EditHistory');

		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'diff';

		// Get the requested message, if the user can see it.
		$msg = current(Msg::load(
			(int) $_GET['msg'],
			[
				'where' => [
					'm.id_msg IN ({array_int:message_list})',
					'{query_see_message_board}',
				],
			],
		));

		Utils::$context['diff'] = '';

		// Find the requested diff in the edit history.
		if ($msg instanceof Msg) {
			$body = $msg->body;
			$hash = hash('crc32c', $body);

			for ($i = 0; $i < \count($msg->edit_history); $i++) {
				$diff = $msg->edit_history[$i];

				Utils::$context['page_title'] = strip_tags($msg->subject . ' - ' . (new Time($diff->time1))->setTimezone(User::getTimezone())->format());

				// Uh-oh. The edit history got corrupted somehow.
				if ($hash !== $diff->label1) {
					Utils::$context['diff'] = $body;

					break;
				}

				// Found it.
				if ($diff->label1 === $_GET['hash']) {
					Utils::$context['diff'] = $diff->formatHTML($body, true, true);

					return;
				}

				$body = $diff->apply($body);
				$hash = hash('crc32c', $body);
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		$route[] = $params['action'];
		unset($params['action']);

		if (isset($params['msg'])) {
			$route[] = $params['msg'];
			unset($params['msg']);
		}

		if (isset($params['hash'])) {
			$route[] = $params['hash'];
			unset($params['hash']);
		}

		return ['route' => $route, 'params' => $params];
	}

	/**
	 * Parses a route to get URL query parameters.
	 *
	 * @param array $route Array of routing path components.
	 * @param array $params Any existing URL query parameters.
	 * @return array URL query parameters
	 */
	public static function parseRoute(array $route, array $params = []): array
	{
		$params['action'] = array_shift($route);

		if (!empty($route)) {
			$params['msg'] = array_shift($route);
		}

		if (!empty($route)) {
			$params['hash'] = array_shift($route);
		}

		return $params;
	}
}
