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

declare(strict_types = 1);

namespace SMF;

Use SMF\Cache\CacheApi;
Use SMF\Db\DatabaseApi as Db;

/**
 * This trait only has one purpose - define a method to load reactions so we don't have to duplicate code
 */
trait ReactionTrait
{
	/**
	 * @return array
	 *
	 * Load up our available reactions
	 */
	public function getReactions(): array
	{
		if (is_null($reactions = CacheApi::get('reactions', 480))) {
			$request = Db::$db->query(
				'',
				'SELECT * FROM {db_prefix}reactions',
			[]);

			while ($result = Db::$db->fetch_assoc($request)) {
				$reactions[$result['id_react']] = $result['name'];
			}

			Db::$db->free($request);

			// Cache the results
			CacheApi::put('reactions', $reactions, 480);
		}
		return $reactions;
	}
}