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
 * Represents the unparsed_equals version of the spoiler BBCode.
 *
 * For backward compatibility with old spoiler BBC mods, this BBCode renders
 * `[spoiler="foo"]bar[/spoiler]` like `[details summary="foo"]bar[/details]`
 */
class Spoiler2 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'spoiler';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS;

	/**
	 *
	 */
	public ?string $before = '<details class="bbc_details"><summary class="bbc_summary">$1</summary><div class="bbc_details_content">';

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

	/**
	 *
	 */
	public ?string $quoted = 'optional';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (\strlen((string) $data) === 0) {
			$data = Lang::getTxt('summary_default', var: 'editortxt');
		}
	}
}
