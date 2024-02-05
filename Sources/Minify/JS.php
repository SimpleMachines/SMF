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

namespace SMF\Minify;

use MatthiasMullie\Minify\JS as BaseJS;

class JS extends BaseJS
{
	public function minify($path = null)
	{
		$content = $this->execute($path);

		// These are not the droids you're looking for
		$content = "/* Any changes to this file will be overwritten. To change the content\nof this file, edit the source files from which it was compiled. */\n" . $content;

		// save to path
		if ($path !== null) {
			$this->save($content, $path);
		}

		return $content;
	}
}

?>