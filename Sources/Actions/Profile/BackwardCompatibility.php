<?php

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility as BackCompat;
use SMF\Profile;

trait BackwardCompatibility
{
	use BackCompat;

	/**
	 *
	 * @param int $memID
	 * @param null|string $sa
	 * @param bool $updateRequest
	 * @param bool $loadSelfFirst
	 * @param bool $loadProfile
	 * @param bool $defaultSettings
	 * @return void
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
