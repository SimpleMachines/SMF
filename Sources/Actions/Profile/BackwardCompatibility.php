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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility as BackCompat;
use SMF\Profile;

trait BackwardCompatibility
{
	use BackCompat;

	/**
	 *
	 * Backwards compatibility function for handling profile-related subactions
	 *
	 * @param int $memID The member ID
	 * @param null|string $sa The subaction
	 * @param bool $updateRequest Whether to update $_REQUEST['u']
	 * @param bool $loadSelfFirst Whether to load the current user's profile first
	 * @param bool $loadProfile Whether to load the profile of the specified member
	 * @param bool $defaultSettings Not used?
	 */
	public static function subActionProvider(
		int $memID,
		?string $sa = null,
		bool $updateRequest = false,
		bool $loadSelfFirst = true,
		bool $loadProfile = false,
		bool $defaultSettings = false,
	): void {

		if ($updateRequest) {
			$u = $_REQUEST['u'] ?? null;
			$_REQUEST['u'] = $memID;
		}

		if ($loadSelfFirst) {
			self::load();

			if ($loadProfile) {
				Profile::load($memID);
			}
		} else {
			if ($loadProfile) {
				Profile::load($memID);
			}
			self::load();
		}

		if ($updateRequest) {
			$_REQUEST['u'] = $u;
		}

		if (isset($sa)) {
			self::$obj->subaction = $sa;
		}

		self::$obj->execute();
	}
}

?>