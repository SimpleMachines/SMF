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

			// [code=php] on a snippet that omits the opening tag still wants highlighting,
			// so add one, and take it back off once highlight_string() has done its work.
			$add_begin = (
				\is_array($data)
				&& isset($data[1])
				&& strtoupper($data[1]) === 'PHP'
				&& !str_contains($code, '&lt;?php')
			);

			if ($add_begin) {
				$code = '&lt;?php ' . $code . '?&gt;';
				$data[1] = 'PHP';
			}

			$parts = preg_split('~(&lt;\?php|\?&gt;)~', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

			for ($i = 0, $n = \count($parts); $i < $n; $i++) {
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

				if (\is_array($data) && empty($data[1])) {
					$data[1] = 'PHP';
				}
			}

			$code = implode('', $parts);

			if ($add_begin) {
				$code = preg_replace(['/^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)/', '/\?&gt;((?:\s*<\/(font|span)>)*)$/m'], '$1', $code, 2);
			}

			if (\is_array($data)) {
				$data[0] = $code;
			} else {
				$data = $code;
			}
		}
	}
}
