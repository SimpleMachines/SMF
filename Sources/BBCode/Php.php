<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Parser;
use SMF\Parsers\BBCodeParser;

/**
 * Represents the php BBCode.
 */
class Php extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'php';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<code class="phpcode">$1</code>';

	/**
	 *
	 */
	public ?string $disabled_content = '$1';

	/**
	 *
	 */
	public bool $block_level = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (!isset($disabled['php'])) {
			$add_begin = !str_starts_with(trim($data), '&lt;?');

			$data = Parser::highlightPhpCode($add_begin ? '&lt;?php ' . $data . '?&gt;' : $data);

			if ($add_begin) {
				$data = preg_replace(['/^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)/', '/\?&gt;((?:\s*<\/(font|span)>)*)$/m'], '$1', $data, 2);
			}

			// If the content contains multiple lines, format as a code block.
			if (preg_match('/\n|<br[^>]*>/', $data)) {
				$code = new Code2();
				$bbc->content = strtr(BBCodeParser::insertTxt($code->content), ['$2' => 'PHP']);
				$bbc->disabled_content = $code->disabled_content;
				$bbc->block_level = $code->block_level;
			}
		}
	}
}
