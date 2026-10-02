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
use SMF\Theme;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This template displays a user's details without any option to edit them.
 */
?>

<?php /* Display the basic information about the user */ ?>
	<div id="profileview" class="roundframe flow_auto noup">
		<div id="basicinfo">
<?php /* Are there any custom profile fields for above the name? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['above_member'])): ?>
<?php $fields = ''; ?>
<?php foreach (Utils::$context['print_custom_fields']['above_member'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php
$fields .= '
					<li>' . $field['output_html'] . '</li>';
?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($fields)): ?>
			<div class="custom_fields_above_name">
				<ul><?= $fields ?>
				</ul>
			</div>
<?php endif; ?>
<?php endif; ?>
			<div class="username clear">
				<h4>
<?php if (!empty(Utils::$context['print_custom_fields']['before_member'])): ?>
<?php foreach (Utils::$context['print_custom_fields']['before_member'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
					<span><?= $field['output_html'] ?></span>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
					<?= Utils::$context['member']['name'] ?>

<?php if (!empty(Utils::$context['print_custom_fields']['after_member'])): ?>
<?php foreach (Utils::$context['print_custom_fields']['after_member'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
					<span><?= $field['output_html'] ?></span>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
					<span class="position"><?= (!empty(Utils::$context['member']['group']) ? Utils::$context['member']['group'] : Utils::$context['member']['post_group']) ?></span>
				</h4>
			</div>
			<?= Utils::$context['member']['avatar']['image'] ?>

<?php /* Are there any custom profile fields for below the avatar? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['below_avatar'])): ?>
<?php $fields = ''; ?>
<?php foreach (Utils::$context['print_custom_fields']['below_avatar'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php
$fields .= '
					<li>' . $field['output_html'] . '</li>';
?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($fields)): ?>
			<div class="custom_fields_below_avatar">
				<ul><?= $fields ?>
				</ul>
			</div>
<?php endif; ?>
<?php endif; ?>
			<ul class="icon_fields clear">
<?php /* Email is only visible if it's your profile or you have the moderate_forum permission */ ?>
<?php if (Utils::$context['member']['show_email']): ?>
				<li><a href="mailto:<?= Utils::$context['member']['email'] ?>" title="<?= Utils::$context['member']['email'] ?>" rel="nofollow"><span class="main_icons mail" title="<?= Lang::getTxt('email', file: 'General') ?>"></span></a></li>
<?php endif; ?>
<?php /* Don't show an icon if they haven't specified a website. */ ?>
<?php if (Utils::$context['member']['website']['url'] !== '' && !isset(Utils::$context['disabled_fields']['website'])): ?>
				<li><a href="<?= Utils::$context['member']['website']['url'] ?>" title="<?= Utils::$context['member']['website']['title'] ?>" target="_blank" rel="noopener"><span class="main_icons www" title="<?= Utils::$context['member']['website']['title'] ?>"></span></a></li>
<?php endif; ?>
<?php /* Are there any custom profile fields as icons? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['icons'])): ?>
<?php foreach (Utils::$context['print_custom_fields']['icons'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
				<li class="custom_field"><?= $field['output_html'] ?></li>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
			</ul>
			<span id="userstatus">
				<?= Utils::$context['can_send_pm'] ? '<a href="' . Utils::$context['member']['online']['href'] . '" title="' . Utils::$context['member']['online']['text'] . '" rel="nofollow">' : '' ?><span class="<?= (Utils::$context['member']['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= Utils::$context['member']['online']['text'] ?>"></span><?= Utils::$context['can_send_pm'] ? '</a>' : '' ?><span class="smalltext"> <?= Utils::$context['member']['online']['label'] ?></span>
<?php /* Can they add this member as a buddy? */ ?>
<?php if (!empty(Utils::$context['can_have_buddy']) && !Profile::$member->is_me): ?>
				<br>
				<a href="<?= Config::$scripturl ?>?action=buddy;u=<?= Utils::$context['id_member'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('buddy_' . (Utils::$context['member']['is_buddy'] ? 'remove' : 'add'), file: 'Profile') ?></a>
<?php endif; ?>
			</span>
<?php if (!Profile::$member->is_me && Utils::$context['can_send_pm']): ?>
			<a href="<?= Config::$scripturl ?>?action=pm;sa=send;u=<?= Utils::$context['id_member'] ?>" class="infolinks"><?= Lang::getTxt('profile_sendpm_short', file: 'Profile') ?></a>
<?php endif; ?>
			<a href="<?= Config::$scripturl ?>?action=profile;area=showposts;u=<?= Utils::$context['id_member'] ?>" class="infolinks"><?= Lang::getTxt('showPosts', file: 'Profile') ?></a>
<?php if (Profile::$member->is_me && !empty(Config::$modSettings['drafts_post_enabled'])): ?>
			<a href="<?= Config::$scripturl ?>?action=profile;area=showdrafts;u=<?= Utils::$context['id_member'] ?>" class="infolinks"><?= Lang::getTxt('drafts_show', file: 'Drafts') ?></a>
<?php endif; ?>
			<a href="<?= Config::$scripturl ?>?action=profile;area=statistics;u=<?= Utils::$context['id_member'] ?>" class="infolinks"><?= Lang::getTxt('statPanel', file: 'Profile') ?></a>
<?php /* Are there any custom profile fields for bottom? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['bottom_poster'])): ?>
<?php $fields = ''; ?>
<?php foreach (Utils::$context['print_custom_fields']['bottom_poster'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php
$fields .= '
					<li>' . $field['output_html'] . '</li>';
?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($fields)): ?>
			<div class="custom_fields_bottom">
				<ul class="nolist"><?= $fields ?>
				</ul>
			</div>
<?php endif; ?>
<?php endif; ?>
		</div><!-- #basicinfo -->

		<div id="detailedinfo">
			<dl class="settings">
<?php if (Profile::$member->is_me || User::$me->is_admin): ?>
				<dt><?= Lang::getTxt('username', file: 'General') ?></dt>
				<dd><?= Utils::$context['member']['username'] ?></dd>
<?php endif; ?>
<?php if (!isset(Utils::$context['disabled_fields']['posts'])): ?>
				<dt><?= Lang::getTxt('profile_posts', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['posts'] ?> (<?= is_numeric(Utils::$context['member']['posts_per_day']) ? Lang::getTxt('posts_per_day', [Utils::$context['member']['posts_per_day']], file: 'Profile') : Utils::$context['member']['posts_per_day'] ?>)</dd>
<?php endif; ?>
<?php if (Utils::$context['member']['show_email']): ?>
				<dt><?= Lang::getTxt('email', file: 'General') ?></dt>
				<dd><a href="mailto:<?= Utils::$context['member']['email'] ?>"><?= Utils::$context['member']['email'] ?></a></dd>
<?php endif; ?>
<?php if (!empty(Config::$modSettings['titlesEnable']) && !empty(Utils::$context['member']['title'])): ?>
				<dt><?= Lang::getTxt('custom_title', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['title'] ?></dd>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['blurb'])): ?>
				<dt><?= Lang::getTxt('personal_text', file: 'General') ?></dt>
				<dd><?= Utils::$context['member']['blurb'] ?></dd>
<?php endif; ?>
				<dt><?= Lang::getTxt('age', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['age'] ?><?= (Utils::$context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . Theme::$current->settings['images_url'] . '/cake.png" alt="">' : '') ?></dd>
			</dl>
<?php /* Any custom fields for standard placement? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['standard'])): ?>
<?php $fields = []; ?>
<?php foreach (Utils::$context['print_custom_fields']['standard'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php $fields[] = $field; ?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (count($fields) > 0): ?>
			<dl class="settings">
<?php foreach ($fields as $field): ?>
				<dt><?= $field['name'] ?>:</dt>
				<dd><?= $field['output_html'] ?></dd>
<?php endforeach; ?>
			</dl>
<?php endif; ?>
<?php endif; ?>
			<dl class="settings">
				<dt><?= Lang::getTxt('date_registered', file: 'General') ?></dt>
				<dd><?= Utils::$context['member']['registered'] ?></dd>
<?php /* If the person looking is allowed, they can check the members IP address and hostname. */ ?>
<?php if (Utils::$context['can_see_ip']): ?>
<?php if (!empty(Utils::$context['member']['ip'])): ?>
				<dt><?= Lang::getTxt('ip', file: 'General') ?></dt>
				<dd><a href="<?= Config::$scripturl ?>?action=profile;area=tracking;sa=ip;searchip=<?= Utils::$context['member']['ip'] ?>;u=<?= Utils::$context['member']['id'] ?>"><?= Utils::$context['member']['ip'] ?></a></dd>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['hostname'])): ?>
				<dt><?= Lang::getTxt('hostname', file: 'General') ?></dt>
				<dd><?= Utils::$context['member']['hostname'] ?></dd>
<?php endif; ?>
<?php endif; ?>
				<dt><?= Lang::getTxt('local_time', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['local_time'] ?></dd>
<?php if (!empty(Config::$modSettings['userLanguage']) && !empty(Utils::$context['member']['language'])): ?>
				<dt><?= Lang::getTxt('language', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['language'] ?></dd>
<?php endif; ?>
<?php if (Utils::$context['member']['show_last_login']): ?>
				<dt><?= Lang::getTxt('lastLoggedIn', file: 'Profile') ?></dt>
				<dd><?= Utils::$context['member']['last_login'] ?><?= (!empty(Utils::$context['member']['is_hidden']) ? ' (' . Lang::getTxt('hidden', file: 'Profile') . ')' : '') ?></dd>
<?php endif; ?>
			</dl>
<?php /* Are there any custom profile fields for above the signature? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['above_signature'])): ?>
<?php $fields = ''; ?>
<?php foreach (Utils::$context['print_custom_fields']['above_signature'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php
$fields .= '
					<li>' . $field['output_html'] . '</li>';
?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($fields)): ?>
			<div class="custom_fields_above_signature">
				<ul class="nolist"><?= $fields ?>
				</ul>
			</div>
<?php endif; ?>
<?php endif; ?>
<?php /* Show the users signature. */ ?>
<?php if (Utils::$context['signature_enabled'] && !empty(Utils::$context['member']['signature'])): ?>
			<div class="signature">
				<h5><?= Lang::getTxt('signature', file: 'Profile') ?></h5>
				<?= Utils::$context['member']['signature'] ?>
			</div>
<?php endif; ?>
<?php /* Are there any custom profile fields for below the signature? */ ?>
<?php if (!empty(Utils::$context['print_custom_fields']['below_signature'])): ?>
<?php $fields = ''; ?>
<?php foreach (Utils::$context['print_custom_fields']['below_signature'] as $field): ?>
<?php if (!empty($field['output_html'])): ?>
<?php
$fields .= '
					<li>' . $field['output_html'] . '</li>';
?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($fields)): ?>
			<div class="custom_fields_below_signature">
				<ul class="nolist"><?= $fields ?>
				</ul>
			</div>
<?php endif; ?>
<?php endif; ?>
<?php
if (
		(Utils::$context['can_view_warning'] && Utils::$context['member']['warning'])
		|| !empty(Utils::$context['activate_message'])
		|| !empty(Utils::$context['member']['bans'])
	):
?>
			<dl class="settings">
<?php /* Can they view/issue a warning? */ ?>
<?php if (Utils::$context['can_view_warning'] && Utils::$context['member']['warning']): ?>
					<dt><?= Lang::getTxt('profile_warning_level', file: 'Profile') ?></dt>
					<dd>
						<a href="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=<?= (Utils::$context['can_issue_warning'] && !Profile::$member->is_me ? 'issuewarning' : 'viewwarning') ?>"><?= Lang::formatText('{0, number, :: percent}', [Utils::$context['member']['warning']]) ?></a>
<?php /* Can we provide information on what this means? */ ?>
<?php if (!empty(Utils::$context['warning_status'])): ?>
						<span class="smalltext">(<?= Utils::$context['warning_status'] ?>)</span>
<?php endif; ?>
					</dd>
<?php endif; ?>
<?php /* Is this member requiring activation and/or banned? */ ?>
<?php if (!empty(Utils::$context['activate_message']) || !empty(Utils::$context['member']['bans'])): ?>
<?php /* If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves. */ ?>
<?php if (!empty(Utils::$context['activate_message'])): ?>
					<dt>
						<span class="alert"><?= Utils::$context['activate_message'] ?></span>
					</dt>
					<dd>
						<a href="<?= Utils::$context['activate_link'] ?>" class="button"><?= Utils::$context['activate_link_text'] ?></a>
					</dd>
<?php endif; ?>
<?php if (!empty(Config::$modSettings['always_anonymize_deleted_accounts'])): ?>
					<dt></dt>
					<dd>
						<?= Lang::getTxt('deleteAccount_anonymize_forced', file: 'Profile') ?>
					</dd>
<?php elseif (Utils::$context['activate_type'] % User::BANNED == User::REQUESTED_DELETE): ?>
					<dt>
						<label><?= Lang::getTxt('deleteAccount_anonymize', file: 'Profile') ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="anonymize" id="anonymize" value="1"<?= !empty(Config::$modSettings['always_anonymize_deleted_accounts']) ? ' checked disabled' : '' ?>>
					</dd>
<?php endif; ?>
<?php /* If the current member is banned, show a message and possibly a link to the ban. */ ?>
<?php if (!empty(Utils::$context['member']['bans'])): ?>
					<dt class="clear">
						<span class="alert"><?= Lang::getTxt('user_is_banned', file: 'Profile') ?></span>&nbsp;<a href="#" onclick="document.getElementById('ban_info').classList.toggle('hidden');return false;"><?= Lang::getTxt('view_ban', file: 'Profile') ?></a>
					</dt>
					<dt class="clear hidden" id="ban_info">
						<strong><?= Lang::getTxt('user_banned_by_following', file: 'Profile') ?></strong>
<?php foreach (Utils::$context['member']['bans'] as $ban): ?>
						<br>
						<span class="smalltext"><?= $ban['explanation'] ?></span>
<?php endforeach; ?>
					</dt>
<?php endif; ?>
<?php endif; ?>
			</dl>
<?php endif; ?>
		</div><!-- #detailedinfo -->
	</div><!-- #profileview -->