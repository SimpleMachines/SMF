<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

/**
 * Similar to SMF\ActionRouter, but designed to build routes for actions that
 * are appended to board or topic routes rather than having their own
 * independent routes.
 *
 * For example, this trait's buildRoute() method would rewrite the query string
 * '?action=post;topic=123' as '/topics/123/post' instead of '/post/?topic=123'.
 *
 * Note that routes built by this trait will be parsed by Board::parseRoute()
 * or Topic::parseRoute() rather than by the parseRoute() method of the class
 * that uses this trait.
 */
trait ActionSuffixRouter
{
	use ActionRouter;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @param bool $append If true, the action route will be appended to the
	 *    topic or board route indicated by the topic or board params.
	 *    Default: false.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		if (isset($params['topic'])) {
			extract(Topic::buildRoute($params));
		} elseif (isset($params['board'])) {
			extract(Board::buildRoute($params));
		}

		$route = array_merge($route ?? [], self::buildActionRoute($params));

		return ['route' => $route, 'params' => $params];
	}

}

?>