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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for displaying a single personal message.
 *
 * @param array $message An array of information about the message to display.
 */
?>

	<div class="windowbg" id="msg<?= $message['id'] ?>">
		<div class="post_wrapper">
			<div class="poster"><?php /* Are there any custom fields above the member name? */ ?><?php if (!empty($message['custom_fields']['above_member'])): ?>

				<div class="custom_fields_above_member">
					<ul class="nolist"><?php foreach ($message['custom_fields']['above_member'] as $custom): ?>

						<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>

					</ul>
				</div><?php endif; ?>

				<h4><?php /* Show online and offline buttons? */ ?><?php if (!empty(Config::$modSettings['onlineEnable']) && !$message['member']['is_guest']): ?>

					<span class="<?= ($message['member']['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= $message['member']['online']['text'] ?>"></span><?php endif; ?><?php /* Custom fields BEFORE the username? */ ?><?php if (!empty($message['custom_fields']['before_member'])): ?><?php foreach ($message['custom_fields']['before_member'] as $custom): ?>

					<span class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></span><?php endforeach; ?><?php endif; ?><?php /* Show a link to the member's profile. */ ?>

		<?= $message['member']['link'] ?><?php /* Custom fields AFTER the username? */ ?><?php if (!empty($message['custom_fields']['after_member'])): ?><?php foreach ($message['custom_fields']['after_member'] as $custom): ?>

					<span class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></span><?php endforeach; ?><?php endif; ?>

				</h4>
				<ul class="user_info"><?php /* Show the member's custom title, if they have one. */ ?><?php if (isset($message['member']['title']) && $message['member']['title'] != ''): ?>

					<li class="title"><?= $message['member']['title'] ?></li><?php endif; ?><?php /* Show the member's primary group (like 'Administrator') if they have one. */ ?><?php if (isset($message['member']['group']) && $message['member']['group'] != ''): ?>

					<li class="membergroup"><?= $message['member']['group'] ?></li><?php endif; ?><?php /* Show the user's avatar. */ ?><?php if (!empty(Config::$modSettings['show_user_images']) && empty(Theme::$current->options['show_no_avatars']) && !empty($message['member']['avatar']['image'])): ?>

					<li class="avatar">
						<a href="<?= Config::$scripturl ?>?action=profile;u=<?= $message['member']['id'] ?>"><?= $message['member']['avatar']['image'] ?></a>
					</li><?php endif; ?><?php /* Are there any custom fields below the avatar? */ ?><?php if (!empty($message['custom_fields']['below_avatar'])): ?><?php foreach ($message['custom_fields']['below_avatar'] as $custom): ?>

					<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php /* Don't show these things for guests. */ ?><?php if (!$message['member']['is_guest']): ?><?php /* Show the post group icons */ ?>

					<li class="icons"><?= $message['member']['group_icons'] ?></li><?php /* Show the post group if and only if they have no other group or the option is on, and they are in a post group. */ ?><?php if ((empty(Config::$modSettings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != ''): ?>

					<li class="postgroup"><?= $message['member']['post_group'] ?></li><?php endif; ?><?php /* Show how many posts they have made. */ ?><?php if (!isset(Utils::$context['disabled_fields']['posts'])): ?>

					<li class="postcount"><?= Lang::getTxt('member_postcount_num', [$message['member']['posts']], file: 'General') ?></li><?php endif; ?><?php /* Show their personal text? */ ?><?php if (!empty(Config::$modSettings['show_blurb']) && $message['member']['blurb'] != ''): ?>

					<li class="blurb"><?= $message['member']['blurb'] ?></li><?php endif; ?><?php /* Any custom fields to show as icons? */ ?><?php if (!empty($message['custom_fields']['icons'])): ?>

					<li class="im_icons">
						<ol><?php foreach ($message['custom_fields']['icons'] as $custom): ?>

							<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>

						</ol>
					</li><?php endif; ?><?php /* Show the IP to this user for this post - because you can moderate? */ ?><?php if (!empty(Utils::$context['can_moderate_forum']) && !empty($message['member']['ip'])): ?>

					<li class="poster_ip">
						<a href="<?= Config::$scripturl ?>?action=<?= !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'] ?>;searchip=<?= $message['member']['ip'] ?>"><?= $message['member']['ip'] ?></a> <a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>
					</li><?php /* Or, should we show it because this is you? */ ?><?php elseif ($message['can_see_ip']): ?>

					<li class="poster_ip">
						<a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help"><?= $message['member']['ip'] ?></a>
					</li><?php /* Okay, you are logged in, then we can show something about why IPs are logged... */ ?><?php else: ?>

					<li class="poster_ip">
						<a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help"><?= Lang::getTxt('logged', file: 'General') ?></a>
					</li><?php endif; ?><?php /* Show the profile, website, email address, and personal message buttons. */ ?><?php if ($message['member']['show_profile_buttons']): ?>

					<li class="profile">
						<ol class="profile_icons"><?php /* Show the profile button */ ?><?php if ($message['member']['can_view_profile']): ?>

							<li><a href="<?= $message['member']['href'] ?>" title="<?= Lang::getTxt('view_profile', file: 'General') ?>"><span class="main_icons members"></span></a></li><?php endif; ?><?php /* Don't show an icon if they haven't specified a website. */ ?><?php if ($message['member']['website']['url'] != '' && !isset(Utils::$context['disabled_fields']['website'])): ?>

							<li><a href="<?= $message['member']['website']['url'] ?>" title="<?= $message['member']['website']['title'] ?>" target="_blank" rel="noopener"><span class="main_icons www centericon" title="<?= $message['member']['website']['title'] ?>"></span></a></li><?php endif; ?><?php /* Don't show the email address if they want it hidden. */ ?><?php if ($message['member']['show_email']): ?>

							<li><a href="mailto:<?= $message['member']['email'] ?>" rel="nofollow"><span class="main_icons mail centericon" title="<?= Lang::getTxt('email', file: 'General') ?>"></span></a></li><?php endif; ?><?php /* Since we know this person isn't a guest, you *can* message them. */ ?><?php if (Utils::$context['can_send_pm'] && $message['member']['id'] != 0): ?>

							<li><a href="<?= Config::$scripturl ?>?action=pm;sa=send;u=<?= $message['member']['id'] ?>" title="<?= Lang::getTxt($message['member']['online']['is_online'] ? 'pm_online' : 'pm_offline', file: 'General') ?>"><span class="main_icons im_<?= ($message['member']['online']['is_online'] ? 'on' : 'off') ?> centericon" title="<?= Lang::getTxt($message['member']['online']['is_online'] ? 'pm_online' : 'pm_offline', file: 'General') ?>"></span></a></li><?php endif; ?>

						</ol>
					</li><?php endif; ?><?php /* Any custom fields for standard placement? */ ?><?php if (!empty($message['custom_fields']['standard'])): ?><?php foreach ($message['custom_fields']['standard'] as $custom): ?>

					<li class="custom <?= $custom['col_name'] ?>"><?= $custom['title'] ?>: <?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php /* Are we showing the warning status? */ ?><?php if ($message['member']['can_see_warning']): ?>

					<li class="warning"><?= Utils::$context['can_issue_warning'] ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '' ?><span class="main_icons warning_<?= $message['member']['warning_status'] ?>"></span><?= Utils::$context['can_issue_warning'] ? '</a>' : '' ?><span class="warn_<?= $message['member']['warning_status'] ?>"><?= Lang::getTxt('warn_' . $message['member']['warning_status'], file: 'General') ?></span></li><?php endif; ?><?php /* Are there any custom fields to show at the bottom of the poster info? */ ?><?php if (!empty($message['custom_fields']['bottom_poster'])): ?><?php foreach ($message['custom_fields']['bottom_poster'] as $custom): ?>

					<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php endif; ?><?php /* Done with the information about the poster... on to the post itself. */ ?>

				</ul>
			</div><!-- .poster -->
			<div class="postarea">
				<div class="keyinfo">
					<div id="subject_<?= $message['id'] ?>" class="subject_title">
						<h5><?= $message['subject'] ?></h5>
					</div>
					<div class="postinfo"><?php /* Show who the message was sent to. */ ?>

						<span class="smalltext"><?= Lang::getTxt(
							'sent_to_date',
							[
								'list' => !empty($message['recipients']['to']) ? Lang::sentenceList($message['recipients']['to']) : Lang::getTxt('pm_undisclosed_recipients', file: 'PersonalMessage'),
								'relative' => str_contains($message['time'], Lang::getTxt('today', file: 'General')) ? 'today' : (str_contains($message['time'], Lang::getTxt('yesterday', file: 'General')) ? 'yesterday' : 'false'),
								'date' => $message['time'],
							],
							file: 'PersonalMessage',
						) ?></span><?php /* If we're in the sent items, show who it was sent to besides the "To:" people. */ ?><?php if (!empty($message['recipients']['bcc'])): ?>

						<span class="smalltext"><?= Lang::getTxt('pm_bcc', ['list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', $message['recipients']['bcc'])]) ?> </span><?php endif; ?><?php if (!empty($message['is_replied_to'])): ?>

						<span class="smalltext"><?= Lang::getTxt(Utils::$context['folder'] == 'sent' ? 'pm_sent_is_replied_to' : 'pm_is_replied_to', file: 'PersonalMessage') ?> </span><?php endif; ?>

					</div><!-- .postinfo -->
				</div><!-- .keyinfo -->
				<div class="post">
					<div class="inner" id="msg_<?= $message['id'] ?>">
						<?= $message['body'] ?>

					</div>
				</div><!-- .post -->
				<div class="under_message"><?php /* Add an extra line at the bottom if we have labels enabled. */ ?><?php if (Utils::$context['folder'] != 'sent' && !empty(Utils::$context['currently_using_labels']) && Utils::$context['display_mode'] != 2): ?>

				<div class="labels floatleft"><?php /* Add the label drop down box. */ ?><?php if (!empty(Utils::$context['currently_using_labels'])): ?>

					<select name="pm_actions[<?= $message['id'] ?>]" onchange="if (this.options[this.selectedIndex].value) form.submit();">
						<option value=""><?= Lang::getTxt('pm_msg_label_title', file: 'PersonalMessage') ?></option>
						<option value="" disabled>---------------</option><?php
// Are there any labels which can be added to this?
// The two lists below hold the same label IDs, so say which list
// this one came from. That is what applyActions() reads, and what
// loadLabelChoices() puts on the drop down for the whole folder.
?><?php if (!$message['fully_labeled']): ?>

						<option value="" disabled><?= Lang::getTxt('pm_msg_label_apply', file: 'PersonalMessage') ?></option><?php foreach (Utils::$context['labels'] as $label): ?><?php if (!isset($message['labels'][$label['id']])): ?>

						<option value="add_<?= $label['id'] ?>"><?= $label['name'] ?></option><?php endif; ?><?php endforeach; ?><?php endif; ?><?php /* ... and are there any that can be removed? */ ?><?php if (!empty($message['labels']) && (count($message['labels']) > 1 || !isset($message['labels'][-1]))): ?>

						<option value="" disabled><?= Lang::getTxt('pm_msg_label_remove', file: 'PersonalMessage') ?></option><?php foreach ($message['labels'] as $label): ?>

						<option value="rem_<?= $label['id'] ?>">&nbsp;<?= $label['name'] ?></option><?php endforeach; ?><?php endif; ?>

					</select>
					<noscript>
						<input type="submit" value="<?= Lang::getTxt('pm_apply', file: 'PersonalMessage') ?>" class="button">
					</noscript><?php endif; ?>

				</div><!-- .labels --><?php endif; ?><?php /* Message options */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $message['quickbuttons'], 'list_class' => 'pm']); ?>

				</div><!-- .under_message -->
			</div><!-- .postarea -->
			<div class="moderatorbar"><?php /* Are there any custom profile fields for above the signature? */ ?><?php if (!empty($message['custom_fields']['above_signature'])): ?>

				<div class="custom_fields_above_signature">
					<ul class="nolist"><?php foreach ($message['custom_fields']['above_signature'] as $custom): ?>

						<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>

					</ul>
				</div><?php endif; ?><?php /* Show the member's signature? */ ?><?php if (!empty($message['member']['signature']) && empty(Theme::$current->options['show_no_signatures']) && Utils::$context['signature_enabled']): ?>

				<div class="signature">
					<?= $message['member']['signature'] ?>

				</div><?php endif; ?><?php /* Are there any custom profile fields for below the signature? */ ?><?php if (!empty($message['custom_fields']['below_signature'])): ?>

				<div class="custom_fields_below_signature">
					<ul class="nolist"><?php foreach ($message['custom_fields']['below_signature'] as $custom): ?>

						<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>

					</ul>
				</div><?php endif; ?>

			</div><!-- .moderatorbar -->
		</div><!-- .post_wrapper -->
	</div><!-- .windowbg -->