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

trait ReactionTrait
{

	/**
	 * @var array
	 *
	 * An array of information about available reactions
	 */
	protected array $reactions = [];

	/**
	 * @return array
	 *
	 * Load up our available reactions
	 */
	public function getReactions(): array
	{
		if (is_null($reactions = CacheApi::get('reactions'))) {
			$request = Db::$db->query(
				'',
				'SELECT * FROM {db_prefix}reactions',
			[]);

			while ($result = Db::$db->fetchAssoc($request)) {
				$this->reactions[$result['id_react']] = $result;
			}

			Db::$db->free($request);

			// Cache the results
			CacheApi::put('reactions', $reactions);
		}
		return $this->reactions;
	}
}