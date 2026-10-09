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
use SMF\Time;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for displaying a single post.
 *
 * @param array $message An array of information about the message to display. Should have 'id' and 'member'. Can also have 'first_new', 'is_ignored' and 'css_class'.
 */
?><?php $ignoring = false; ?><?php if ($message['can_remove']): ?><?php Utils::$context['removableMessageIDs'][] = $message['id']; ?><?php endif; ?><?php /* Are we ignoring this message? */ ?><?php if (!empty($message['is_ignored'])): ?><?php
$ignoring = true;
Utils::$context['ignoredMsgs'][] = $message['id'];
?><?php endif; ?><?php /* Show a "new" anchor if this message is new. */ ?><?php if (!empty($message['first_new']) && $message['id'] != Utils::$context['first_message']): ?>
				<a id="new"></a><?php endif; ?><?php /* Inform the reader if this message bumped an old topic. */ ?><?php if (!empty($message['bump_notice'])): ?>
				<?= $message['bump_notice'] ?><?php endif; ?><?php /* Show the message. */ ?>
				<div class="<?= $message['css_class'] ?>" id="msg<?= $message['id'] ?>">
					<div class="post_wrapper"><?php /* Show information about the poster of this message. */ ?>
						<div class="poster"><?php /* Are there any custom fields above the member name? */ ?><?php if (!empty($message['custom_fields']['above_member'])): ?>
							<div class="custom_fields_above_member">
								<ul class="nolist"><?php foreach ($message['custom_fields']['above_member'] as $custom): ?>
									<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>
								</ul>
							</div><?php endif; ?>
							<h4><?php /* Show online and offline buttons? */ ?><?php if (!empty(Config::$modSettings['onlineEnable']) && !$message['member']['is_guest']): ?>
								<?= Utils::$context['can_send_pm'] ? '<a href="' . $message['member']['online']['href'] . '" title="' . $message['member']['online']['label'] . '">' : '' ?><span class="<?= ($message['member']['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= $message['member']['online']['text'] ?>"></span><?= Utils::$context['can_send_pm'] ? '</a>' : '' ?><?php endif; ?><?php /* Custom fields BEFORE the username? */ ?><?php if (!empty($message['custom_fields']['before_member'])): ?><?php foreach ($message['custom_fields']['before_member'] as $custom): ?>
								<span class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></span><?php endforeach; ?><?php endif; ?><?php /* Show a link to the member's profile. */ ?>
								<?= $message['member']['link'] ?><?php /* Custom fields AFTER the username? */ ?><?php if (!empty($message['custom_fields']['after_member'])): ?><?php foreach ($message['custom_fields']['after_member'] as $custom): ?>
								<span class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></span><?php endforeach; ?><?php endif; ?><?php /* Begin display of user info */ ?>
							</h4>
							<ul class="user_info"><?php /* Show the member's custom title, if they have one. */ ?><?php if (!empty($message['member']['title'])): ?>
								<li class="title"><?= $message['member']['title'] ?></li><?php endif; ?><?php /* Show the member's primary group (like 'Administrator') if they have one. */ ?><?php if (!empty($message['member']['group'])): ?>
								<li class="membergroup"><?= $message['member']['group'] ?></li><?php endif; ?><?php /* Show the user's avatar. */ ?><?php if (!empty(Config::$modSettings['show_user_images']) && empty(Theme::$current->options['show_no_avatars']) && !empty($message['member']['avatar']['image'])): ?>
								<li class="avatar">
									<a href="<?= $message['member']['href'] ?>"><?= $message['member']['avatar']['image'] ?></a>
								</li><?php endif; ?><?php /* Are there any custom fields below the avatar? */ ?><?php if (!empty($message['custom_fields']['below_avatar'])): ?><?php foreach ($message['custom_fields']['below_avatar'] as $custom): ?>
								<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php /* Don't show these things for guests. */ ?><?php if (!$message['member']['is_guest']): ?><?php /* Show the post group icons */ ?>
								<li class="icons"><?= $message['member']['group_icons'] ?></li><?php /* Show the post group if and only if they have no other group or the option is on, and they are in a post group. */ ?><?php if ((empty(Config::$modSettings['hide_post_group']) || empty($message['member']['group'])) && !empty($message['member']['post_group'])): ?>
								<li class="postgroup"><?= $message['member']['post_group'] ?></li><?php endif; ?><?php /* Show how many posts they have made. */ ?><?php if (!isset(Utils::$context['disabled_fields']['posts'])): ?>
								<li class="postcount"><?= Lang::getTxt('member_postcount_num', [$message['member']['posts']], file: 'General') ?></li><?php endif; ?><?php /* Show their personal text? */ ?><?php if (!empty(Config::$modSettings['show_blurb']) && !empty($message['member']['blurb'])): ?>
								<li class="blurb"><?= $message['member']['blurb'] ?></li><?php endif; ?><?php /* Any custom fields to show as icons? */ ?><?php if (!empty($message['custom_fields']['icons'])): ?>
								<li class="im_icons">
									<ol><?php foreach ($message['custom_fields']['icons'] as $custom): ?>
										<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>
									</ol>
								</li><?php endif; ?><?php /* Show the website and email address buttons. */ ?><?php if ($message['member']['show_profile_buttons']): ?>
								<li class="profile">
									<ol class="profile_icons"><?php /* Don't show an icon if they haven't specified a website. */ ?><?php if (!empty($message['member']['website']['url']) && !isset(Utils::$context['disabled_fields']['website'])): ?>
										<li><a href="<?= $message['member']['website']['url'] ?>" title="<?= $message['member']['website']['title'] ?>" target="_blank" rel="noopener"><span class="main_icons www centericon" title="<?= $message['member']['website']['title'] ?>"></span></a></li><?php endif; ?><?php /* Since we know this person isn't a guest, you *can* message them. */ ?><?php if (Utils::$context['can_send_pm']): ?>
										<li><a href="<?= Config::$scripturl ?>?action=pm;sa=send;u=<?= $message['member']['id'] ?>" title="<?= Lang::getTxt($message['member']['online']['is_online'] ? 'pm_online' : 'pm_offline', file: 'General') ?>"><span class="main_icons im_<?= ($message['member']['online']['is_online'] ? 'on' : 'off') ?> centericon" title="<?= Lang::getTxt($message['member']['online']['is_online'] ? 'pm_online' : 'pm_offline', file: 'General') ?>"></span> </a></li><?php endif; ?><?php /* Show the email if necessary */ ?><?php if (!empty($message['member']['email']) && $message['member']['show_email']): ?>
										<li class="email"><a href="mailto:<?= $message['member']['email'] ?>" rel="nofollow"><span class="main_icons mail centericon" title="<?= Lang::getTxt('email', file: 'General') ?>"></span></a></li><?php endif; ?>
									</ol>
								</li><!-- .profile --><?php endif; ?><?php /* Any custom fields for standard placement? */ ?><?php if (!empty($message['custom_fields']['standard'])): ?><?php foreach ($message['custom_fields']['standard'] as $custom): ?>
								<li class="custom <?= $custom['col_name'] ?>"><?= $custom['title'] ?>: <?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php /* Otherwise, show the guest's email. */ ?><?php elseif (!empty($message['member']['email']) && $message['member']['show_email']): ?>
								<li class="email">
									<a href="mailto:<?= $message['member']['email'] ?>" rel="nofollow"><span class="main_icons mail centericon" title="<?= Lang::getTxt('email', file: 'General') ?>"></span></a>
								</li><?php endif; ?><?php /* Show the IP to this user for this post - because you can moderate? */ ?><?php if (!empty(Utils::$context['can_moderate_forum']) && !empty($message['member']['ip'])): ?>
								<li class="poster_ip">
									<a href="<?= Config::$scripturl ?>?action=<?= !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'] ?>;searchip=<?= $message['member']['ip'] ?>" data-hover="<?= $message['member']['ip'] ?>" class="show_on_hover"><span><?= Lang::getTxt('show_ip', file: 'General') ?></span></a> <a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>
								</li><?php /* Or, should we show it because this is you? */ ?><?php elseif ($message['can_see_ip']): ?>
								<li class="poster_ip">
									<a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help show_on_hover" data-hover="<?= $message['member']['ip'] ?>"><span><?= Lang::getTxt('show_ip', file: 'General') ?></span></a>
								</li><?php /* Okay, are you at least logged in? Then we can show something about why IPs are logged... */ ?><?php elseif (!User::$me->is_guest): ?>
								<li class="poster_ip">
									<a href="<?= Config::$scripturl ?>?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help"><?= Lang::getTxt('logged', file: 'General') ?></a>
								</li><?php /* Otherwise, you see NOTHING! */ ?><?php else: ?>
								<li class="poster_ip"><?= Lang::getTxt('logged', file: 'General') ?></li><?php endif; ?><?php
// Are we showing the warning status?
// Don't show these things for guests.
?><?php if (!$message['member']['is_guest'] && $message['member']['can_see_warning']): ?>
								<li class="warning">
									<?= Utils::$context['can_issue_warning'] ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '' ?><span class="main_icons warning_<?= $message['member']['warning_status'] ?>"></span> <?= Utils::$context['can_issue_warning'] ? '</a>' : '' ?><span class="warn_<?= $message['member']['warning_status'] ?>"><?= Lang::getTxt('warn_' . $message['member']['warning_status'], file: 'General') ?></span>
								</li><?php endif; ?><?php /* Are there any custom fields to show at the bottom of the poster info? */ ?><?php if (!empty($message['custom_fields']['bottom_poster'])): ?><?php foreach ($message['custom_fields']['bottom_poster'] as $custom): ?>
								<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?><?php endif; ?><?php /* Poster info ends. */ ?>
							</ul>
						</div><!-- .poster -->
						<div class="postarea">
							<div class="keyinfo"><?php /* Some people don't want subject... The div is still required or quick edit breaks. */ ?>
								<div id="subject_<?= $message['id'] ?>" class="subject_title<?= (empty(Config::$modSettings['subject_toggle']) ? ' subject_hidden' : '') ?>">
									<?= $message['link'] ?>
								</div>
								<?= !empty($message['counter']) ? '<span class="page_number floatright">#' . $message['counter'] . '</span>' : '' ?>
								<div class="postinfo">
									<span class="messageicon" <?= ($message['icon_url'] === Theme::$current->settings['images_url'] . '/post/xx.png' && !$message['can_modify']) ? ' style="position: absolute; z-index: -1;"' : '' ?>>
										<img src="<?= $message['icon_url'] ?>" alt=""<?= $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '' ?>>
									</span>
									<a href="<?= $message['href'] ?>" rel="nofollow" title="<?= !empty($message['counter']) ? Lang::getTxt('reply_number', [$message['counter']], file: 'General') : '' ?><?= $message['subject'] ?>" class="smalltext"><?= $message['time'] ?></a>
									<span class="spacer"></span><?php
// Show "<< Last Edit: Time by Person >>" if this post was edited. But we need the div even if it wasn't modified!
// Because we insert into it through AJAX and we don't want to stop themers moving it around if they so wish so they can put it where they want it.
?>
									<<?= (!empty(Config::$modSettings['show_modify']) && !empty($message['edit_history']) ? 'a href="" onclick="return false;"' : 'span') ?> class="smalltext modified floatright<?= !empty(Config::$modSettings['show_modify']) && !empty($message['modified']['last_edit_text']) ? ' mvisible' : '' ?>" id="modified_<?= $message['id'] ?>"><?php if (!empty(Config::$modSettings['show_modify']) && !empty($message['modified']['last_edit_text'])): ?><?= $message['modified']['last_edit_text'] ?><?php endif; ?>
									</<?= (!empty(Config::$modSettings['show_modify']) && !empty($message['edit_history']) ? 'a' : 'span') ?>><?php if (!empty(Config::$modSettings['show_modify']) && !empty($message['edit_history'])): ?>
									<div id="edit_history_list_<?= $message['id'] ?>" class="edit_history_list">
										<div class="edit_history_count">
											<?= Lang::getTxt('edit_history_count', [count($message['edit_history'])]) ?>
										</div>
										<ol><?php foreach ($message['edit_history'] as $diff): ?><?php
$last_edit_text = Lang::getTxt(
				'edit_history_linktext' . ($diff->reason !== '' ? '_reason' : ''),
				[
					'time' => (new Time($diff->time1))->setTimezone(User::$me->timezone)->format(),
					'member' => $diff->name === '' ? '(' . Lang::getTxt('unknown') . ')' : $diff->name,
					'reason' => $diff->reason,
				],
			);
?>
											<li>
												<a href="<?= Config::$scripturl ?>?action=edithistory;msg=<?= $message['id'] ?>;hash=<?= $diff->label1 ?>" onclick="return reqOverlayDiv(this.href, <?= Utils::escapeJavaScript($last_edit_text) ?>, 'history');"><?= $last_edit_text ?></a>
											</li><?php endforeach; ?>
										</ol>
									</div><?php endif; ?>
								</div>
								<div id="msg_<?= $message['id'] ?>_quick_mod"<?= $ignoring ? ' style="display:none;"' : '' ?>></div>
							</div><!-- .keyinfo --><?php /* Ignoring this user? Hide the post. */ ?><?php if ($ignoring): ?>
							<div id="msg_<?= $message['id'] ?>_ignored_prompt" class="noticebox">
								<?= Lang::getTxt('ignoring_user', file: 'General') ?>
								<a href="#" id="msg_<?= $message['id'] ?>_ignored_link" style="display: none;"><?= Lang::getTxt('show_ignore_user_post', file: 'General') ?></a>
							</div><?php endif; ?><?php /* Show the post itself, finally! */ ?>
							<div class="post"><?php if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == User::$me->id): ?>
								<div class="noticebox">
									<?= Lang::getTxt('post_awaiting_approval', file: 'General') ?>
								</div><?php endif; ?>
								<div class="inner" data-msgid="<?= $message['id'] ?>" id="msg_<?= $message['id'] ?>"<?= $ignoring ? ' style="display:none;"' : '' ?>>
									<?= Utils::adjustHeadingLevels($message['body'], 4) ?>
								</div>
							</div><!-- .post --><?php /* Assuming there are attachments... */ ?><?php if (!empty($message['attachment'])): ?><?php
$last_approved_state = 1;
// Don't output the div unless we actually have something to show...
$div_output = false;
?><?php foreach ($message['attachment'] as $attachment): ?><?php /* Do we want this attachment to not be showed here? */ ?><?php if ($attachment['is_approved'] && !empty(Config::$modSettings['dont_show_attach_under_post']) && !empty(Utils::$context['show_attach_under_post'][$attachment['id']])): ?><?php continue; ?><?php endif; ?><?php if (!$div_output): ?><?php $div_output = true; ?>
							<div id="msg_<?= $message['id'] ?>_footer" class="attachments"<?= $ignoring ? ' style="display:none;"' : '' ?>><?php endif; ?><?php /* Show a special box for unapproved attachments... */ ?><?php if ($attachment['is_approved'] != $last_approved_state): ?><?php $last_approved_state = 0; ?>
								<fieldset>
									<legend>
										<?= Lang::getTxt('attach_awaiting_approve', file: 'General') ?><?php if (Utils::$context['can_approve']): ?>
										&nbsp;[<a href="<?= Config::$scripturl ?>?action=attachapprove;sa=all;mid=<?= $message['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('approve_all', file: 'General') ?></a>]<?php endif; ?>
									</legend><?php endif; ?>
									<div class="attached"><?php if ($attachment['is_image'] && !empty(Config::$modSettings['attachmentShowImages'])): ?>
										<div class="attachments_top"><?php if ($attachment['thumbnail']['has_thumb']): ?>
											<a href="<?= $attachment['href'] ?>;image" id="link_<?= $attachment['id'] ?>" onclick="<?= $attachment['thumbnail']['javascript'] ?>"><img src="<?= $attachment['thumbnail']['href'] ?>" alt="" id="thumb_<?= $attachment['id'] ?>" class="atc_img"></a><?php else: ?>
											<img src="<?= $attachment['href'] ?>;image" alt="" loading="lazy" class="atc_img"><?php endif; ?>
										</div><!-- .attachments_top --><?php endif; ?>
										<div class="attachments_bot">
											<a href="<?= $attachment['href'] ?>"><img src="<?= Theme::$current->settings['images_url'] ?>/icons/clip.png" class="centericon" alt="*">&nbsp;<?= $attachment['name'] ?></a> <?php if (!$attachment['is_approved'] && Utils::$context['can_approve']): ?>
											[<a href="<?= Config::$scripturl ?>?action=attachapprove;sa=approve;aid=<?= $attachment['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('approve', file: 'General') ?></a>] [<a href="<?= Config::$scripturl ?>?action=attachapprove;sa=reject;aid=<?= $attachment['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('delete', file: 'General') ?></a>] <?php endif; ?>
											<br><?= $attachment['formatted_size'] ?><?= ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . '<br>' . Lang::getTxt('attach_viewed', [$attachment['downloads']], file: 'General') : '<br>' . Lang::getTxt('attach_downloaded', [$attachment['downloads']], file: 'General')) ?>
										</div><!-- .attachments_bot -->
									</div><!-- .attached --><?php endforeach; ?><?php /* If we had unapproved attachments clean up. */ ?><?php if ($last_approved_state == 0): ?>
								</fieldset><?php endif; ?><?php /* Only do this if we output a div above - otherwise it'll break things */ ?><?php if ($div_output): ?>
							</div><!-- #msg_[id]_footer --><?php endif; ?><?php endif; ?>
							<div class="under_message"><?php /* What about likes? */ ?><?php if (!empty(Config::$modSettings['enable_likes'])): ?>
								<ul class="floatleft"><?php if (!empty($message['likes']['can_like'])): ?>
									<li class="smflikebutton" id="msg_<?= $message['id'] ?>_likes"<?= $ignoring ? ' style="display:none;"' : '' ?>>
										<a href="<?= Config::$scripturl ?>?action=likes;ltype=msg;sa=like;like=<?= $message['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="msg_like"><span class="main_icons <?= $message['likes']['you'] ? 'unlike' : 'like' ?>"></span> <?= Lang::getTxt($message['likes']['you'] ? 'unlike' : 'like', file: 'General') ?></a>
									</li><?php endif; ?><?php if (!empty($message['likes']['count'])): ?><?php
Utils::$context['some_likes'] = true;
$count = $message['likes']['count'];
$base = 'likes_count';
?><?php if ($message['likes']['you']): ?><?php
$base = 'you_' . $base;
$count--;
?><?php endif; ?><?php
$like_text = Lang::getTxt($base, ['url' => Config::$scripturl . '?action=likes;sa=view;ltype=msg;like=' . $message['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], 'num' => $count], file: 'General');
// Remove link if no cookies; session reference won't work
// Also, don't show link to guests
?><?php if (empty($_COOKIE) || User::$me->is_guest): ?><?php $like_text = preg_replace('~</?a\b[^>]*>~', '', $like_text); ?><?php endif; ?>
									<li class="like_count smalltext">
										<?= $like_text ?>
									</li><?php endif; ?>
								</ul><?php endif; ?><?php /* Show the quickbuttons, for various operations on posts. */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $message['quickbuttons'], 'list_class' => 'post']); ?>
							</div><!-- .under_message -->
						</div><!-- .postarea -->
						<div class="moderatorbar"><?php /* Are there any custom profile fields for above the signature? */ ?><?php if (!empty($message['custom_fields']['above_signature'])): ?>
							<div class="custom_fields_above_signature">
								<ul class="nolist"><?php foreach ($message['custom_fields']['above_signature'] as $custom): ?>
									<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>
								</ul>
							</div><?php endif; ?><?php /* Show the member's signature? */ ?><?php if (!empty($message['member']['signature']) && empty(Theme::$current->options['show_no_signatures']) && Utils::$context['signature_enabled']): ?>
							<div class="signature" id="msg_<?= $message['id'] ?>_signature"<?= $ignoring ? ' style="display:none;"' : '' ?>>
								<?= $message['member']['signature'] ?>
							</div><?php endif; ?><?php /* Are there any custom profile fields for below the signature? */ ?><?php if (!empty($message['custom_fields']['below_signature'])): ?>
							<div class="custom_fields_below_signature">
								<ul class="nolist"><?php foreach ($message['custom_fields']['below_signature'] as $custom): ?>
									<li class="custom <?= $custom['col_name'] ?>"><?= $custom['value'] ?></li><?php endforeach; ?>
								</ul>
							</div><?php endif; ?>
						</div><!-- .moderatorbar -->
					</div><!-- .post_wrapper -->
				</div><!-- $message[css_class] -->
				<hr class="post_separator">