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
 * The template for determining which boards a group has access to.
 *
 * @param bool $collapse Whether to collapse the list by default
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['collapse' => true, 'form_id' => 'new_group'], EXTR_SKIP);
?>
							<fieldset id="visible_boards"<?= !empty(Config::$modSettings['deny_boards_access']) ? ' class="denyboards_layout"' : '' ?>>
								<legend><?= Lang::getTxt('membergroups_new_board_desc', file: 'ManageMembers') ?></legend>
								<ul class="padding floatleft"><?php foreach (Utils::$context['categories'] as $category): ?><?php if (empty(Config::$modSettings['deny_boards_access'])): ?>
									<li class="category">
										<a href="javascript:void(0);" onclick="selectBoards([<?= implode(', ', $category['child_ids']) ?>], '<?= $form_id ?>'); return false;"><strong><?= $category['name'] ?></strong></a>
										<ul><?php else: ?>
									<li class="category clear">
										<strong><?= $category['name'] ?></strong>
										<span class="select_all_box floatright">
											<em class="all_boards_in_cat"><?= Lang::getTxt('all_boards_in_cat', file: 'Admin') ?></em>
											<select onchange="select_in_category(<?= $category['id'] ?>, this, [<?= implode(',', array_keys($category['boards'])) ?>]);">
												<option>---</option>
												<option value="allow"><?= Lang::getTxt('board_perms_allow', file: 'Admin') ?></option>
												<option value="ignore"><?= Lang::getTxt('board_perms_ignore', file: 'Admin') ?></option>
												<option value="deny"><?= Lang::getTxt('board_perms_deny', file: 'Admin') ?></option>
											</select>
										</span>
										<ul id="boards_list_<?= $category['id'] ?>"><?php endif; ?><?php foreach ($category['boards'] as $board): ?><?php if (empty(Config::$modSettings['deny_boards_access'])): ?>
											<li class="board" style="margin-inline-start: <?= $board['child_level'] ?>em;">
												<input type="checkbox" name="boardaccess[<?= $board['id'] ?>]" id="brd<?= $board['id'] ?>" value="allow"<?= $board['allow'] ? ' checked' : '' ?>> <label for="brd<?= $board['id'] ?>"><?= $board['name'] ?></label>
											</li><?php else: ?>
											<li class="board clear">
												<span style="margin-inline-start: <?= $board['child_level'] ?>em;"><?= $board['name'] ?>: </span>
												<span class="floatright">
													<input type="radio" name="boardaccess[<?= $board['id'] ?>]" id="allow_brd<?= $board['id'] ?>" value="allow"<?= $board['allow'] ? ' checked' : '' ?>> <label for="allow_brd<?= $board['id'] ?>"><?= Lang::getTxt('permissions_option_on', file: 'ManagePermissions') ?></label>
													<input type="radio" name="boardaccess[<?= $board['id'] ?>]" id="ignore_brd<?= $board['id'] ?>" value="ignore"<?= !$board['allow'] && !$board['deny'] ? ' checked' : '' ?>> <label for="ignore_brd<?= $board['id'] ?>"><?= Lang::getTxt('permissions_option_off', file: 'ManagePermissions') ?></label>
													<input type="radio" name="boardaccess[<?= $board['id'] ?>]" id="deny_brd<?= $board['id'] ?>" value="deny"<?= $board['deny'] ? ' checked' : '' ?>> <label for="deny_brd<?= $board['id'] ?>"><?= Lang::getTxt('permissions_option_deny', file: 'ManagePermissions') ?></label>
												</span>
											</li><?php endif; ?><?php endforeach; ?>
										</ul>
									</li><?php endforeach; ?>
								</ul><?php if (empty(Config::$modSettings['deny_boards_access'])): ?>
								<br class="clear"><br>
								<input type="checkbox" id="checkall_check" onclick="invertAll(this, this.form, 'boardaccess');">
								<label for="checkall_check"><em><?= Lang::getTxt('check_all', file: 'General') ?></em></label>
							</fieldset><?php else: ?>
								<br class="clear">
								<span class="select_all_box">
									<em><?= Lang::getTxt('all', file: 'General') ?></em>
									<input type="radio" name="select_all" id="allow_all" onclick="selectAllRadio(this, this.form, 'boardaccess', 'allow');"> <label for="allow_all"><?= Lang::getTxt('board_perms_allow', file: 'Admin') ?></label>
									<input type="radio" name="select_all" id="ignore_all" onclick="selectAllRadio(this, this.form, 'boardaccess', 'ignore');"> <label for="ignore_all"><?= Lang::getTxt('board_perms_ignore', file: 'Admin') ?></label>
									<input type="radio" name="select_all" id="deny_all" onclick="selectAllRadio(this, this.form, 'boardaccess', 'deny');"> <label for="deny_all"><?= Lang::getTxt('board_perms_deny', file: 'Admin') ?></label>
								</span>
							</fieldset>
							<script>
								$(document).ready(function () {
									$(".select_all_box").each(function () {
										$(this).removeClass('select_all_box');
									});
								});
							</script><?php endif; ?><?php if ($collapse): ?>
							<a href="javascript:void(0);" onclick="document.getElementById('visible_boards').classList.remove('hidden'); document.getElementById('visible_boards_link').classList.add('hidden'); return false;" id="visible_boards_link" class="hidden">[ <?= Lang::getTxt('membergroups_select_visible_boards', file: 'ManageMembers') ?> ]</a>
							<script>
								document.getElementById("visible_boards_link").classList.remove('hidden');
								document.getElementById("visible_boards").classList.add('hidden');
							</script><?php endif; ?>
