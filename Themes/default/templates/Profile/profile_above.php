<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\BrowserDetector;
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Minor stuff shown above the main profile - mostly used for error messages and showing that the profile update was successful.
 */
?><?php /* Prevent Chrome from auto completing fields when viewing/editing other members profiles */ ?><?php if (BrowserDetector::isBrowser('is_chrome') && !Profile::$member->is_me): ?>
			<script>
				disableAutoComplete();
			</script><?php endif; ?><?php /* If an error occurred while trying to save previously, give the user a clue! */ ?>
			<?php $this->subTemplate('error_message'); ?><?php /* If the profile was update successfully, let the user know this. */ ?><?php if (!empty(Utils::$context['profile_updated'])): ?>
			<div class="infobox">
				<?= Utils::$context['profile_updated'] ?>
			</div><?php endif; ?>
