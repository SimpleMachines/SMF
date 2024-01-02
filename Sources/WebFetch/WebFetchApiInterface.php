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

namespace SMF\WebFetch;

/**
 *
 */
interface WebFetchApiInterface
{
	/**
	 * Main calling function.
	 *
	 *  - Will request the page data from a given $url.
	 *  - Optionally will post data to the page form if $post_data is supplied.
	 *    Passed arrays will be converted to a POST string joined with &'s.
	 *
	 * @param string $url the site we are going to fetch
	 * @param array|string $post_data any post data as form name => value
	 * @return object A reference to the object for method chaining.
	 */
	public function request(string $url, array $post_data = []): ?object;

	/**
	 * Used to return the results to the caller.
	 *
	 *  - called as ->result() will return the full final array.
	 *  - called as ->result('body') to return the page source of the result.
	 *
	 * @param string $area Used to return an area such as body, header, error.
	 * @return mixed The response.
	 */
	public function result(?string $area = null): mixed;

	/**
	 * Will return all results from all loops (redirects)
	 *
	 *  - Can be called as ->result_raw(x) where x is a specific loop's result.
	 *  - Call as ->result_raw() for everything.
	 *
	 * @param int $response_number Which response to get, or null for all.
	 * @return array The specified response or all the responses.
	 */
	public function resultRaw(?int $response_number = null): array;
}

?>