<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Parser;

/**
 * Represents the unparsed_equals_content version of the code BBCode.
 */
class Code2 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'code';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<div class="codeheader">{txt_code} ($2)</div><pre data-select-txt="{txt_code_select}" data-shrink-txt="{txt_code_shrink}" data-expand-txt="{txt_code_expand}" class="bbc_code"><code>$1</code></pre>';

	/**
	 *
	 */
	public ?string $disabled_content = '<div>$1</div>';

	/**
	 *
	 */
	public bool $block_level = true;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (!isset($disabled['code'])) {
			$code = \is_array($data) ? $data[0] : $data;

			$parts = preg_split('~(&lt;\?php|\?&gt;)~', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

			for ($i = 0, $n = count($parts); $i < $n; $i++) {
				// Do PHP code coloring?
				if ($parts[$i] != '&lt;?php') {
					continue;
				}

				$string = '';

				while ($i + 1 < $n && $parts[$i] != '?&gt;') {
					$string .= $parts[$i];
					$parts[$i++] = '';
				}
				$parts[$i] = Parser::highlightPhpCode($string . $parts[$i]);
			}

			if (is_array($data)) {
				$data[0] = implode('', $parts);
			} else {
				$data = implode('', $parts);
			}
		}
	}
}
