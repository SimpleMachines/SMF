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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility as BackCompat;
use SMF\Profile;

trait BackwardCompatibility
{
	use BackCompat;

	/**
	 * Helps provide backwards compatbility for profile
	 * actions.  Any new code should not depend on it.
	 *
	 * @param int $memID
	 * @param null|string $sa
	 * @param bool $updateRequest
	 * @param bool $loadSelfFirst
	 * @param bool $loadProfile
	 * @param bool $defaultSettings
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
			$obj = self::load();

			if ($loadProfile) {
				Profile::load($memID);
			}
		} else {
			if ($loadProfile) {
				Profile::load($memID);
			}
			$obj = self::load();
		}

		if ($updateRequest) {
			$_REQUEST['u'] = $u;
		}

		if (isset($sa)) {
			$obj->setDefaultAction($sa);
		}

		$obj->execute();
	}
}

?>