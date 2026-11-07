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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for setting up 2FA backup code
 */
?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('tfa_backup_title', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<div>
					<div class="smalltext"><?= Lang::getTxt('tfa_backup_desc', file: 'Profile') ?></div>
					<div class="bbc_code" style="resize: none; border: none;"><?= Utils::$context['tfa_backup'] ?></div>
				</div>
			</div>