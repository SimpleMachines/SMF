<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\BackwardCompatibility as BackCompat;

trait BackwardCompatibility
{
	use BackCompat;

	/**
	 * Called by Subs-Compat.php BackwardCompatibility wrapper functions to provide subaction
	 * execution for existing mods
	 *
	 * @param null|string $sa
	 * @param bool $return_config
	 * @return null|array
	 */
	public static function subActionProvider(?string $sa = null, bool $return_config = false, ?string $activity = null): ?array
	{
		if ($return_config) {
			return self::getConfigVars();
		}

		self::load();

		if (is_string($sa)) {
			// make sure it's a supported subaction
			if (array_key_exists($sa, self::$subactions)) {
				self::$obj->subaction = $sa;
			}
		}

		if (is_string($activity)) {
			self::$obj->activity = $activity;
		}

		self::$obj->execute();
	}
}

?>