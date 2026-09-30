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
 * Template for editing a mod comment.
 */
?>
	<div id="modcenter">
		<form action="<?= Config::$scripturl ?>?action=moderate;area=reported<?= Utils::$context['report_type'] ?>;sa=editcomment;mid=<?= Utils::$context['comment_id'] ?>;rid=<?= Utils::$context['report_id'] ?>;save" method="post" accept-charset="UTF-8">
			<br>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mc_modreport_edit_mod_comment', file: 'ModerationCenter') ?></h3>
			</div>
			<div class="windowbg">
				<textarea rows="6" cols="60" style="width: 60%;" name="mod_comment"><?= Utils::$context['comment']['body'] ?></textarea>
				<div>
					<input type="submit" name="edit_comment" value="<?= Lang::getTxt('mc_modreport_edit_mod_comment', file: 'ModerationCenter') ?>" class="button">
				</div>
			</div>
			<br>
			<input type="hidden" name="<?= Utils::$context['mod-reportC-edit_token_var'] ?>" value="<?= Utils::$context['mod-reportC-edit_token'] ?>">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form>
	</div><!-- #modcenter -->