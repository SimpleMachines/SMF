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
 * Template for viewing and managing a specific report about a user's profile
 */
?><?php /* Let them know the action was a success. */ ?><?php if (!empty(Utils::$context['report_post_action'])): ?>
	<div class="infobox">
		<?= Lang::getTxt('report_action_' . Utils::$context['report_post_action'], file: 'ModerationCenter') ?>
	</div><?php endif; ?>
	<div id="modcenter">
		<form action="<?= Config::$scripturl ?>?action=moderate;area=reportedmembers;sa=handlecomment;rid=<?= Utils::$context['report']['id'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('mc_viewmemberreport', ['member' => Utils::$context['report']['user']['link']], file: 'ModerationCenter') ?>
				</h3>
			</div>
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft">
						<?= Lang::getTxt('mc_memberreport_summary', [Utils::$context['report']['num_reports'], Utils::$context['report']['last_updated']], file: 'ModerationCenter') ?>
					</span><?php
$report_buttons = [
	'ignore' => [
		'text' => !Utils::$context['report']['ignore'] ? 'mc_reportedp_ignore' : 'mc_reportedp_unignore',
		'url' => Config::$scripturl . '?action=moderate;area=reportedmembers;sa=handle;ignore=' . (int) !Utils::$context['report']['ignore'] . ';rid=' . Utils::$context['report']['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['mod-report-ignore_token_var'] . '=' . Utils::$context['mod-report-ignore_token'],
		'class' => !Utils::$context['report']['ignore'] ? ' you_sure' : '',
		'custom' => !Utils::$context['report']['ignore'] ? ' data-confirm="' . Lang::getTxt('mc_reportedp_ignore_confirm', file: 'ModerationCenter') . '"' : '',
		'icon' => 'ignore',
	],
	'close' => [
		'text' => Utils::$context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close',
		'url' => Config::$scripturl . '?action=moderate;area=reportedmembers;sa=handle;closed=' . (int) !Utils::$context['report']['closed'] . ';rid=' . Utils::$context['report']['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['mod-report-closed_token_var'] . '=' . Utils::$context['mod-report-closed_token'],
		'icon' => 'close',
	],
];
// Report buttons
?><?php $this->subTemplate('button_strip', ['button_strip' => $report_buttons, 'direction' => 'right']); ?>
				</h3>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mc_memberreport_whoreported_title', file: 'ModerationCenter') ?></h3>
			</div><?php foreach (Utils::$context['report']['comments'] as $comment): ?>
			<div class="windowbg">
				<div class="smalltext">
					<?= Lang::getTxt(
			'mc_modreport_whoreported_data',
			[
				'member_link' => $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''),
				'datetime' => $comment['time'],
			],
			file: 'ModerationCenter',
		) ?>
				</div>
				<div><?= Utils::adjustHeadingLevels($comment['message'], null) ?></div>
			</div><?php endforeach; ?>
			<br>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mc_modreport_mod_comments', file: 'ModerationCenter') ?></h3>
			</div>
			<div><?php if (empty(Utils::$context['report']['mod_comments'])): ?>
				<div class="information">
					<div class="centertext"><?= Lang::getTxt('mc_modreport_no_mod_comment', file: 'ModerationCenter') ?></div>
				</div><?php endif; ?><?php foreach (Utils::$context['report']['mod_comments'] as $comment): ?>
				<div class="title_bar">
					<h3 class="titlebg"><?= $comment['member']['link'] ?>:  <em class="smalltext">(<?= $comment['time'] ?>)</em><?= ($comment['can_edit'] ? '<span class="floatright"><a href="' . Config::$scripturl . '?action=moderate;area=reportedmembers;sa=editcomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '"  class="button">' . Lang::getTxt('mc_reportedp_comment_edit', file: 'ModerationCenter') . '</a> <a href="' . Config::$scripturl . '?action=moderate;area=reportedmembers;sa=handlecomment;rid=' . Utils::$context['report']['id'] . ';mid=' . $comment['id'] . ';delete;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['mod-reportC-delete_token_var'] . '=' . Utils::$context['mod-reportC-delete_token'] . '"  class="button you_sure" data-confirm="' . Lang::getTxt('mc_reportedp_delete_confirm', file: 'ModerationCenter') . '">' . Lang::getTxt('mc_reportedp_comment_delete', file: 'ModerationCenter') . '</a></span>' : '') ?></h3>
				</div>
				<div class="windowbg">
					<div><?= Utils::adjustHeadingLevels($comment['message'], null) ?></div>
				</div><?php endforeach; ?>
				<div class="cat_bar">
					<h3 class="catbg">
						<span class="floatleft">
							<?= Lang::getTxt('mc_reportedp_new_comment', file: 'ModerationCenter') ?>
						</span>
					</h3>
				</div>
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div class="padding">
					<input type="submit" name="add_comment" value="<?= Lang::getTxt('mc_modreport_add_mod_comment', file: 'ModerationCenter') ?>" class="button">
					<input type="hidden" name="<?= Utils::$context['mod-reportC-add_token_var'] ?>" value="<?= Utils::$context['mod-reportC-add_token'] ?>">
				</div>
			</div>
			<br><?php $this->subTemplate('show_list', ['list_id' => 'moderation_actions_list']); ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form>
	</div><!-- #modcenter -->