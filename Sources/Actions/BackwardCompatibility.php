<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\BackwardCompatibility as BackCompat;

trait BackwardCompatibility
{
	use BackCompat;

	/**
	 * Called by Subs-Compat.php BackwardCompatibility wrapper functions to provide subaction
	 * execution for existing mods.  Any new code should not depend on it.
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

		$obj = self::load();

		if (is_string($sa)) {
			$obj->setDefaultAction($sa);
		}

		if (is_string($activity)) {
			$obj->activity = $activity;
		}

		$obj->execute();
	}
}

?>