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
 * Allow the moderator to enter a reason to each user being rejected.
 */
?>

<?php /* Show a welcome message to the user. */ ?>
	<div id="moderationcenter">
		<form action="<?= Config::$scripturl ?>?action=groups;sa=requests" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mc_groups_reason_title', file: 'ModerationCenter') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings"><?php /* Loop through and print out a reason box for each... */ ?>

<?php foreach (Utils::$context['group_requests'] as $request): ?>
					<dt>
						<strong><?= Lang::getTxt('mc_groupr_reason_desc', $request, file: 'ModerationCenter') ?>:</strong>
					</dt>
					<dd>
						<input type="hidden" name="groupr[]" value="<?= $request['id'] ?>">
						<textarea name="groupreason[<?= $request['id'] ?>]" rows="3" cols="40"></textarea>
					</dd>
<?php endforeach; ?>
				</dl>
				<input type="submit" name="go" value="<?= Lang::getTxt('mc_groupr_submit', file: 'ModerationCenter') ?>" class="button">
				<input type="hidden" name="req_action" value="got_reason">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['mod-gr_token_var'] ?>" value="<?= Utils::$context['mod-gr_token'] ?>">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #moderationcenter -->