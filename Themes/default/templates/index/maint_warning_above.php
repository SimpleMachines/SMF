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

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The upper part of the maintenance warning box
 */
?>

	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious"><?= Lang::getTxt('forum_in_maintenance', file: 'General') ?></strong>
			</dt>
			<dd class="error" id="error_list">
				<?= Lang::getTxt('maintenance_page', ['url' => Config::$scripturl . '?action=admin;area=serversettings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']], file: 'General') ?>

			</dd>
		</dl>
	</div>