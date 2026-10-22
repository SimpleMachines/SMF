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

// Form for editing the privacy policy shown to people registering to the forum.
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
		<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?><?php /* Just a big box to edit the text file ;). */ ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('privacy_policy', file: 'General') ?></h3>
		</div><?php /* Is there more than one language to choose from? */ ?><?php if (count(Utils::$context['editable_policies']) > 1): ?>
			<div class="information">
				<form action="<?= Config::$scripturl ?>?action=admin;area=regcenter" id="change_policy" method="post" accept-charset="UTF-8">
					<strong><?= Lang::getTxt('admin_agreement_select_language', file: 'Admin') ?></strong>
					<select name="policy_lang" onchange="document.getElementById('change_policy').submit();"><?php foreach (Utils::$context['editable_policies'] as $lang => $name): ?>
						<option value="<?= $lang ?>" <?= Utils::$context['current_policy_lang'] == $lang ? 'selected="selected"' : '' ?>><?= $name ?></option><?php endforeach; ?>
					</select>
					<div class="righttext">
						<input type="hidden" name="sa" value="policy">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="submit" name="change" value="<?= Lang::getTxt('admin_agreement_select_language_change', file: 'Admin') ?>" class="button">
					</div>
				</form>
			</div><?php endif; ?>
		<div class="windowbg" id="privacy_policy">
			<form action="<?= Config::$scripturl ?>?action=admin;area=regcenter" method="post" accept-charset="UTF-8"><?php /* Show the actual policy in an oversized text box. */ ?>
			<textarea cols="70" rows="20" name="policy" id="agreement"><?= Utils::$context['privacy_policy'] ?></textarea>
				<div class="information"><?php if (empty(Utils::$context['privacy_policy_history'])): ?>
					<span><?= Utils::$context['privacy_policy_info'] ?></span><?php else: ?>
					<a href="" onclick="return false;" class="modified"><?= Utils::$context['privacy_policy_info'] ?></a>
					<div id="edit_history_list_<?= Utils::$context['current_policy_lang'] ?>" class="edit_history_list">
						<div class="edit_history_count">
							<?= Lang::getTxt('edit_history_count', [count(Utils::$context['privacy_policy_history'])], file: 'General') ?>
						</div>
						<ol><?php foreach (Utils::$context['privacy_policy_history'] as $hash => $linktext): ?>
							<li>
								<a href="<?= Config::$scripturl ?>?action=agreement;sa=history;doc=1;lang=<?= Utils::$context['current_policy_lang'] ?>;hash=<?= $hash ?>" onclick="return reqOverlayDiv(this.href, <?= Utils::escapeJavaScript($linktext) ?>, 'history');"><?= $linktext ?></a>
							</li><?php endforeach; ?>
						</ol>
					</div><?php endif; ?>
				</div>
				<div class="righttext">
					<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button" onclick="return resetPolicyConfirm()" />
					<input type="hidden" name="policy_lang" value="<?= Utils::$context['current_policy_lang'] ?>" />
					<input type="hidden" name="sa" value="policy" />
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>" />
					<input type="hidden" name="<?= Utils::$context['admin-regp_token_var'] ?>" value="<?= Utils::$context['admin-regp_token'] ?>" />
					<script>
						function resetPolicyConfirm()
						{
							return true;
						}
					</script>
				</div>
			</form>
		</div>