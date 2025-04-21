<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Lang;

/**
 * Represents the details BBCode.
 */
class Details extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'details';

	/**
	 *
	 */
	public ?array $parameters = [
		'summary' => [
			'quoted' => 'optional',
			'match' => '(.*?)',
			'optional' => true,
			'default' => '{editortxt_details}',
		],
	];

	/**
	 *
	 */
	public ?string $before =  '<details class="bbc_details"><summary class="bbc_summary">{summary}</summary><div class="bbc_details_content">';

	/**
	 *
	 */
	public ?string $after = '</div></details>';

	/**
	 *
	 */
	public ?string $disabled_before = '<div>';

	/**
	 *
	 */
	public ?string $disabled_after = '</div>';

	/**
	 *
	 */
	public bool $block_level = true;

	/**
	 *
	 */
	public string $trim = 'both';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (strlen($params['{summary}'] ?? '') === 0) {
			$bbc['before'] = Lang::formatText($bbc['before'], ['summary' => Lang::getTxt('details', var: 'editortxt')]);
		}
	}
}

?>