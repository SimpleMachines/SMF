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

namespace SMF\PersonalMessage;

use SMF\IntegrationHook;
use SMF\Search\SearchResult as SR;

/**
 * Shows personal message search results.
 */
class SearchResult extends PM
{
	/**
	 * Generator that yields formatted PMs for use in a search results list.
	 *
	 * @param int|array $ids The ID numbers of one or more personal messages.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<array> Iterating over result gives SearchResult instances.
	 */
	public static function getFormatted($ids, array $query_customizations = [])
	{
		foreach (parent::get($ids, $query_customizations) as $pm) {
			$output = $pm->format(0, ['no_bcc' => true]);

			$output['body'] = SR::highlight($output['body'], Search::$to_mark);
			$output['subject'] = SR::highlight($output['subject'], Search::$to_mark);
			$output['link'] = '<a href="' . $output['href'] . '">' . $output['subject'] . '</a>';

			unset($output['quickbuttons']['quickmod']);

			IntegrationHook::call('integrate_pm_search_result', [&$output]);

			yield $output;
		}
	}
}

?>