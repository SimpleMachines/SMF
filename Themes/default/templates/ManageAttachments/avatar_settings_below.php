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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * JavaScript to be output below the simple settings page
 */
?>
	<script>
		var fUpdateStatus = function ()
		{
			document.getElementById("avatar_max_width_external").disabled = document.getElementById("avatar_download_external").checked;
			document.getElementById("avatar_max_height_external").disabled = document.getElementById("avatar_download_external").checked;
			document.getElementById("avatar_action_too_large").disabled = document.getElementById("avatar_download_external").checked;
		}
		addLoadEvent(fUpdateStatus);
	</script>