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
 * This handles the Who's Online page
 */
?><?php /* Display the table header and linktree. */ ?>
	<div class="main_section" id="whos_online">
		<form action="<?= Config::$scripturl ?>?action=who" method="post" id="whoFilter" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('who_title', file: 'General') ?></h3>
			</div>
			<div id="mlist">
				<div class="pagesection">
					<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div>
					<div class="selectbox floatright" id="upper_show">
						<?= Lang::getTxt('who_show', file: 'Who') ?>
						<select name="show_top" onchange="document.forms.whoFilter.show.value = this.value; document.forms.whoFilter.submit();"><?php foreach (Utils::$context['show_methods'] as $value => $label): ?>
							<option value="<?= $value ?>" <?= $value == Utils::$context['show_by'] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
						</select>
						<noscript>
							<input type="submit" name="submit_top" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button">
						</noscript>
					</div>
				</div>
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th scope="col" class="lefttext" style="width: 40%;"><a href="<?= Config::$scripturl ?>?action=who;start=<?= Utils::$context['start'] ?>;show=<?= Utils::$context['show_by'] ?>;sort=user<?= Utils::$context['sort_direction'] != 'down' && Utils::$context['sort_by'] == 'user' ? '' : ';asc' ?>" rel="nofollow"><?= Lang::getTxt('who_user', file: 'Who') ?><?= Utils::$context['sort_by'] == 'user' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th>
							<th scope="col" class="lefttext time" style="width: 10%;"><a href="<?= Config::$scripturl ?>?action=who;start=<?= Utils::$context['start'] ?>;show=<?= Utils::$context['show_by'] ?>;sort=time<?= Utils::$context['sort_direction'] == 'down' && Utils::$context['sort_by'] == 'time' ? ';asc' : '' ?>" rel="nofollow"><?= Lang::getTxt('who_time', file: 'Who') ?><?= Utils::$context['sort_by'] == 'time' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th>
							<th scope="col" class="lefttext half_table"><?= Lang::getTxt('who_action', file: 'Who') ?></th>
						</tr>
					</thead>
					<tbody><?php foreach (Utils::$context['members'] as $member): ?>
						<tr class="windowbg">
							<td><?php /* Guests can't be messaged. */ ?><?php if (!$member['is_guest']): ?>
								<span class="contact_info floatright">
									<?= Utils::$context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . Lang::getTxt('pm_online', file: 'General') . '">' : '' ?><span class="main_icons im_<?= ($member['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= Lang::getTxt('pm_online', file: 'General') ?>"></span><?= Utils::$context['can_send_pm'] ? '</a>' : '' ?>
								</span><?php endif; ?>
								<span class="member<?= $member['is_hidden'] ? ' hidden' : '' ?>">
									<?= $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . Lang::getTxt('view_profile_of_username', ['name' => $member['name']], file: 'General') . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . ';"') . '>' . $member['name'] . '</a>' ?>
								</span><?php if (!empty($member['ip'])): ?>
								(<a href="<?= Config::$scripturl ?>?action=<?= ($member['is_guest'] ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $member['id']) ?>;searchip=<?= $member['ip'] ?>"><?= str_replace(':', ':&ZeroWidthSpace;', $member['ip']) ?></a>)<?php endif; ?>
							</td>
							<td class="time"><?= $member['time'] ?></td>
							<td><?php if (is_array($member['action'])): ?><?php $tag = !empty($member['action']['tag']) ? $member['action']['tag'] : 'span'; ?>
								<<?= $tag ?><?= !empty($member['action']['class']) ? ' class="' . $member['action']['class'] . '"' : '' ?>>
									<?= Lang::getTxt($member['action']['label'], file: 'Who') ?><?= (!empty($member['action']['error_message']) ? $member['action']['error_message'] : '') ?>
								</<?= $tag ?>><?php else: ?><?= $member['action'] ?><?php endif; ?>
							</td>
						</tr><?php endforeach; ?><?php /* No members? */ ?><?php if (empty(Utils::$context['members'])): ?>
						<tr class="windowbg">
							<td colspan="3">
							<?= Lang::getTxt('who_no_online_' . (Utils::$context['show_by'] == 'guests' || Utils::$context['show_by'] == 'spiders' ? Utils::$context['show_by'] : 'members'), file: 'Who') ?>
							</td>
						</tr><?php endif; ?>
					</tbody>
				</table>
				<div class="pagesection" id="lower_pagesection">
					<div class="pagelinks floatleft" id="lower_pagelinks"><?= Utils::$context['page_index'] ?></div>
					<div class="selectbox floatright">
						<?= Lang::getTxt('who_show', file: 'Who') ?>
						<select name="show" onchange="document.forms.whoFilter.submit();"><?php foreach (Utils::$context['show_methods'] as $value => $label): ?>
							<option value="<?= $value ?>" <?= $value == Utils::$context['show_by'] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
						</select>
						<noscript>
							<input type="submit" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button">
						</noscript>
					</div>
				</div><!-- #lower_pagesection -->
			</div><!-- #mlist -->
		</form>
	</div><!-- #whos_online -->