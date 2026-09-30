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
 * The admin member list.
 */
?><?php $this->subTemplate('show_list', ['list_id' => 'approve_list']); ?><?php /* If we have lots of outstanding members try to make the admin's life easier. */ ?><?php if (Utils::$context['approve_list']['total_num_items'] > -1): ?><?php Utils::$context['browse_type'] = 'activate'; ?>
		<br>
		<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('admin_browse_outstanding', file: 'ManageMembers') ?></h3>
			</div>
			<script>
				function onOutstandingSubmit()
				{
					if (document.forms.postFormOutstanding.todo.value == "")
						return;

					var message = "";
					if (document.forms.postFormOutstanding.todo.value.indexOf("delete") != -1)
						message = "<?= Lang::getTxt('admin_browse_w_delete', file: 'ManageMembers') ?>";
					else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") != -1)
						message = "<?= Lang::getTxt('admin_browse_w_reject', file: 'ManageMembers') ?>";
					else if (document.forms.postFormOutstanding.todo.value == "remind")
						message = "<?= Lang::getTxt('admin_browse_w_remind', file: 'ManageMembers') ?>";
					else
						message = "<?= Lang::getTxt(Utils::$context['browse_type'] == 'approve' ? 'admin_browse_w_approve' : 'admin_browse_w_activate', file: 'ManageMembers') ?>";

					if (confirm(message + " <?= Lang::getTxt('admin_browse_outstanding_warn', file: 'ManageMembers') ?>"))
						return true;
					else
						return false;
				}
			</script>

			<div class="windowbg">
				<p class="settings">
					<?= Lang::getTxt(
						'admin_browse_outstanding_days',
						[
							'input' => '<input type="number" name="time_passed" value="14">',
							'number' => 14,
						],
						file: 'ManageMembers',
					) ?>
				</p>
				<dl class="settings">
					<dt>
						<?= Lang::getTxt('admin_browse_outstanding_perform', file: 'ManageMembers') ?>:
					</dt>
					<dd>
						<select name="todo">
							<?= Utils::$context['browse_type'] == 'activate' ? '
							<option value="ok">' . Lang::getTxt('admin_browse_w_activate', file: 'ManageMembers') . '</option>' : '' ?>
							<option value="okemail"><?= Lang::getTxt(Utils::$context['browse_type'] == 'approve' ? 'admin_browse_w_approve_send_email' : 'admin_browse_w_activate_send_email', file: 'ManageMembers') ?></option><?= Utils::$context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . Lang::getTxt('admin_browse_w_approve_require_activate', file: 'ManageMembers') . '</option>' ?>
							<option value="reject"><?= Lang::getTxt('admin_browse_w_reject', file: 'ManageMembers') ?></option>
							<option value="rejectemail"><?= Lang::getTxt('admin_browse_w_reject_send_email', file: 'ManageMembers') ?></option>
							<option value="delete"><?= Lang::getTxt('admin_browse_w_delete', file: 'ManageMembers') ?></option>
							<option value="deleteemail"><?= Lang::getTxt('admin_browse_w_delete_send_email', file: 'ManageMembers') ?></option><?= Utils::$context['browse_type'] == 'activate' ? '
							<option value="remind">' . Lang::getTxt('admin_browse_w_remind', file: 'ManageMembers') . '</option>' : '' ?>
						</select>
					</dd>
				</dl>
				<input type="submit" value="<?= Lang::getTxt('admin_browse_outstanding_go', file: 'ManageMembers') ?>" class="button">
				<input type="hidden" name="type" value="<?= Utils::$context['browse_type'] ?>">
				<input type="hidden" name="sort" value="<?= Utils::$context['approve_list']['sort']['id'] ?>">
				<input type="hidden" name="start" value="<?= Utils::$context['approve_list']['start'] ?>">
				<input type="hidden" name="orig_filter" value="<?= Utils::$context['current_filter'] ?>">
				<input type="hidden" name="sa" value="approve"><?= !empty(Utils::$context['approve_list']['sort']['desc']) ? '
				<input type="hidden" name="desc" value="1">' : '' ?>
			</div><!-- .windowbg -->
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form><?php endif; ?>
