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
 * Provides a simple implementation of the SMF\Routable interface for actions.
 *
 * This trait's methods provide baseline support for translating between queries
 * like '?action=foo;area=bar;sa=baz' and routes like '/foo/bar/baz'.
 *
 * This basic support is provided by the protected methods buildActionRoute()
 * and parseActionRoute(), which are called internally by the public methods
 * buildRoute() and parseRoute().
 *
 * Classes using this trait that need to customize buildRoute() or parseRoute()
 * but also still want access to the basic behaviour can call the protected
 * methods as needed.
 */
trait ActionRouter
{
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
		$route = self::buildActionRoute($params);

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
		$params = array_merge($params, self::parseActionRoute($route));

		return $params;
	}

	/************************
	 * Interal static methods
	 ************************/

	/**
	 * Builds a routing path for an action based on URL query parameters.
	 *
	 * The 'action', 'area', and 'sa' parameters will be mapped to route path
	 * components in that order. The 'action' parameter is required, whereas
	 * 'area' and 'sa' are optional and will be silently skipped if not set.
	 *
	 * The parameters are passed by reference and parameters are removed from
	 * the array when recognized. This lets wrapper methods continue parsing the
	 * remainder of the parameters without worrying about duplicate elements.
	 *
	 * @param array &$params URL query parameters.
	 * @return array The route path elements.
	 */
	protected static function buildActionRoute(array &$params): array
	{
		if (str_starts_with(self::class, 'SMF\\Actions')) {
			if (!isset($params['action'])) {
				foreach (Forum::$actions as $action => $info) {
					if (is_string($info[1]) && str_starts_with($info[1], self::class)) {
						$params['action'] = $action;
						break;
					}
				}

				if (!isset($params['action'])) {
					return ['route' => [], 'params' => $params];
				}
			}

			$route[] = $params['action'];
			unset($params['action']);

			if (isset($params['area'])) {
				// Skip the default area unless there is also a sub-action.
				if ($params['area'] !== 'index' || isset($params['sa'])) {
					$route[] = $params['area'];
				}

				unset($params['area']);
			}

			if (isset($params['sa'])) {
				$route[] = $params['sa'];
				unset($params['sa']);
			}
		}

		return $route;
	}

	/**
	 * Parses a route for an action to get URL query parameters.
	 *
	 * The first element of the route path is mapped to the 'action' parameter.
	 *
	 * For the 'admin', 'moderate', and 'profile' actions, the second element
	 * of the route is mapped to the 'area' parameter and the third element is
	 * mapped to the 'sa' parameter.
	 *
	 * For all other actions, the second element of the route path is mapped to
	 * the 'sa' parameter.
	 *
	 * The route is passed by reference and route path elements are removed from
	 * the route when recognized. This lets wrapper methods continue parsing the
	 * remainder of the route without worrying about duplicate elements.
	 *
	 * @param array &$route Array of routing path components.
	 * @return array URL query parameters
	 */
	protected static function parseActionRoute(array &$route): array
	{
		$params = [];

		if (isset(Forum::$actions[$route[0]])) {
			$params['action'] = array_shift($route);

			if (!empty($route) && preg_match('/^SMF\\\\Actions\\\\.*\\\\/', self::class)) {
				$params['area'] = array_shift($route);
			}

			if (!empty($route)) {
				$params['sa'] = array_shift($route);
			}
		}

		return $params;
	}
}

?>