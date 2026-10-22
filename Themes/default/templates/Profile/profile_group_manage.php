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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Display a load of drop down selectors for allowing the user to change group.
 */
?>
							<dt>
								<label for="id_group"><strong><?= Lang::getTxt('primary_membergroup', file: 'Profile') ?></strong></label><br>
								<span class="smalltext"><a href="<?= Config::$scripturl ?>?action=helpadmin;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span> <?= Lang::getTxt('moderator_why_missing', file: 'Profile') ?></a></span>
							</dt>
							<dd>
								<select name="id_group" id="id_group" <?= (Profile::$member->is_me && Utils::$context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . Lang::getTxt('deadmin_confirm', file: 'Profile') . '\')) this.value = 1;"' : '') ?>>
<?php /* Fill the select box with all primary member groups that can be assigned to a member. */ ?>
<?php foreach (Utils::$context['member_groups'] as $member_group): ?>
<?php if (!empty($member_group['can_be_primary'])): ?>
									<option value="<?= $member_group['id'] ?>"<?= $member_group['is_primary'] ? ' selected' : '' ?>>
										<?= $member_group['name'] ?>
									</option>
<?php endif; ?>
<?php endforeach; ?>
								</select>
							</dd>
							<dt>
								<strong><?= Lang::getTxt('additional_membergroups', file: 'Profile') ?></strong>
							</dt>
							<dd>
								<span id="additional_groupsList">
									<input type="hidden" name="additional_groups[]" value="0">
<?php /* For each membergroup show a checkbox so members can be assigned to more than one group. */ ?>
<?php foreach (Utils::$context['member_groups'] as $member_group): ?>
<?php if ($member_group['can_be_additional']): ?>
									<label for="additional_groups-<?= $member_group['id'] ?>"><input type="checkbox" name="additional_groups[]" value="<?= $member_group['id'] ?>" id="additional_groups-<?= $member_group['id'] ?>"<?= $member_group['is_additional'] ? ' checked' : '' ?>> <?= $member_group['name'] ?></label><br>
<?php endif; ?>
<?php endforeach; ?>
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById('additional_groupsList').style.display = 'block'; document.getElementById('additional_groupsLink').style.display = 'none'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down"><?= Lang::getTxt('additional_membergroups_show', file: 'Profile') ?></a>
								<script>
									document.getElementById("additional_groupsList").style.display = "none";
									document.getElementById("additional_groupsLink").style.display = "";
								</script>
							</dd>