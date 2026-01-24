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
 * Represents the unparsed_content version of the code BBCode.
 */
class Code1 extends BBCode
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
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<div class="codeheader"><span class="code">{txt_code}</span> <a class="codeoperation smf_select_text">{txt_code_select}</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="{txt_code_shrink}" data-expand-txt="{txt_code_expand}">{txt_code_expand}</a></div><code class="bbc_code">$1</code>';

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

			$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

			for ($php_i = 0, $php_n = \count($php_parts); $php_i < $php_n; $php_i++) {
				// Do PHP code coloring?
				if ($php_parts[$php_i] != '&lt;?php') {
					continue;
				}

				$php_string = '';

				while ($php_i + 1 < \count($php_parts) && $php_parts[$php_i] != '?&gt;') {
					$php_string .= $php_parts[$php_i];
					$php_parts[$php_i++] = '';
				}

				$php_parts[$php_i] = Parser::highlightPhpCode($php_string . $php_parts[$php_i]);

				if (\is_array($data) && empty($data[1])) {
					$data[1] = 'PHP';
				}
			}

			// Fix the PHP code stuff...
			$code = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode('', $php_parts));

			$code = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $code);

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
