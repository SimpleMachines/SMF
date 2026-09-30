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
 * Form for editing the agreement shown for people registering to the forum.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>

		<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php elseif (!empty(Utils::$context['could_not_save'])): ?>

		<div class="errorbox"><?= Lang::getTxt('admin_agreement_not_saved', file: 'Admin') ?></div><?php endif; ?><?php /* Warning for if the file isn't writable. */ ?><?php if (!empty(Utils::$context['warning'])): ?>

		<div class="errorbox"><?= Utils::$context['warning'] ?></div><?php endif; ?><?php /* Just a big box to edit the text file ;) */ ?>

		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('registration_agreement', file: 'General') ?></h3>
			</div><?php /* Is there more than one language to choose from? */ ?><?php if (count(Utils::$context['editable_agreements']) > 1): ?>

				<div class="information">
					<form action="<?= Config::$scripturl ?>?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="UTF-8">
						<strong><?= Lang::getTxt('admin_agreement_select_language', file: 'Admin') ?></strong>
						<select name="agree_lang" onchange="document.getElementById('change_reg').submit();"><?php foreach (Utils::$context['editable_agreements'] as $file => $name): ?>

							<option value="<?= $file ?>"<?= Utils::$context['current_agreement'] == $file ? ' selected' : '' ?>><?= $name ?></option><?php endforeach; ?>

						</select>
						<div class="righttext">
							<input type="hidden" name="sa" value="agreement">
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
							<input type="hidden" name="<?= Utils::$context['admin-rega_token_var'] ?>" value="<?= Utils::$context['admin-rega_token'] ?>">
							<input type="submit" name="change" value="<?= Lang::getTxt('admin_agreement_select_language', file: 'Admin') ?>" class="button">
						</div>
					</form>
				</div><!-- .information --><?php endif; ?><?php /* Show the actual agreement in an oversized text box. */ ?>

			<div class="windowbg" id="registration_agreement">
				<form action="<?= Config::$scripturl ?>?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
					<textarea cols="70" rows="20" name="agreement" id="agreement"><?= Utils::$context['agreement'] ?></textarea>
					<div class="information"><?php if (empty(Utils::$context['agreement_history'])): ?>

						<span><?= Utils::$context['agreement_info'] ?></span><?php else: ?>

						<a href="" onclick="return false;" class="modified"><?= Utils::$context['agreement_info'] ?></a>
						<div id="edit_history_list_<?= Utils::$context['current_agreement'] ?>" class="edit_history_list">
							<div class="edit_history_count">
								<?= Lang::getTxt('edit_history_count', [count(Utils::$context['agreement_history'])], file: 'General') ?>

							</div>
							<ol><?php foreach (Utils::$context['agreement_history'] as $hash => $linktext): ?>

								<li>
									<a href="<?= Config::$scripturl ?>?action=agreement;sa=history;doc=0;lang=<?= Utils::$context['current_agreement'] ?>;hash=<?= $hash ?>" onclick="return reqOverlayDiv(this.href, <?= Utils::escapeJavaScript($linktext) ?>, 'history');"><?= $linktext ?></a>
								</li><?php endforeach; ?>

							</ol>
						</div><?php endif; ?>

					</div>
					<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button" onclick="return resetAgreementConfirm()" />
					<input type="hidden" name="agree_lang" value="<?= Utils::$context['current_agreement'] ?>">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<script>
						function resetAgreementConfirm()
						{
							return true;
						}
					</script>
					<input type="hidden" name="<?= Utils::$context['admin-rega_token_var'] ?>" value="<?= Utils::$context['admin-rega_token'] ?>">
				</form>
			</div><!-- #registration_agreement -->
		</div><!-- #admin_form_wrapper -->