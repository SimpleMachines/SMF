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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The who's online section of the info center
 */
?><?php /* "Users online" - in order of activity. */ ?>
			<div class="sub_bar">
				<h4 class="subbg">
					<?= Utils::$context['show_who'] ? '<a href="' . Config::$scripturl . '?action=who">' : '' ?><span class="main_icons people"></span> <?= Lang::getTxt('online_users', file: 'General') ?><?= Utils::$context['show_who'] ? '</a>' : '' ?>
				</h4>
			</div>
			<p class="inline">
				<?= Utils::$context['show_who'] ? '<a href="' . Config::$scripturl . '?action=who">' : '' ?><strong><?= Lang::getTxt('online', file: 'General') ?>: </strong><?= empty(Config::$modSettings['no_guest_logging']) ? Lang::getTxt('number_of_guests', [Utils::$context['num_guests']], file: 'General') . ', ' : '' ?><?= Lang::getTxt('number_of_members', [Utils::$context['num_users_online']], file: 'General') ?><?php
// Handle hidden users and buddies.
$bracketList = [];
?><?php if (Utils::$context['show_buddies']): ?><?php $bracketList[] = Lang::getTxt('number_of_buddies', [Utils::$context['num_buddies']], file: 'General'); ?><?php endif; ?><?php if (!empty(Utils::$context['num_spiders'])): ?><?php $bracketList[] = Lang::getTxt('number_of_spiders', [Utils::$context['num_spiders']], file: 'General'); ?><?php endif; ?><?php if (!empty(Utils::$context['num_users_hidden'])): ?><?php $bracketList[] = Lang::getTxt('number_of_hidden_members', [Utils::$context['num_users_hidden']], file: 'General'); ?><?php endif; ?><?php $bracketList = array_filter($bracketList, 'strlen'); ?><?php if (!empty($bracketList)): ?> (<?= Lang::sentenceList($bracketList) ?>)<?php endif; ?><?= Utils::$context['show_who'] ? '</a>' : '' ?>


				&nbsp;-&nbsp;<?= Lang::getTxt('most_online_today', file: 'General') ?>: <strong><?= Lang::numberFormat(Config::$modSettings['mostOnlineToday']) ?></strong>&nbsp;-&nbsp;
				<?= Lang::getTxt('most_online_ever', file: 'General') ?>: <?= Lang::numberFormat(Config::$modSettings['mostOnline']) ?> (<?= Time::create('@' . Config::$modSettings['mostDate'])->format() ?>)<br><?php /* Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link. */ ?><?php if (!empty(Utils::$context['users_online'])): ?>
				<?= Lang::getTxt('users_active', ['minutes' => Config::$modSettings['lastActive'], 'list' => Lang::sentenceList(Utils::$context['list_users_online'])], file: 'General') ?><?php /* Showing membergroups? */ ?><?php if (!empty(Theme::$current->settings['show_group_key']) && !empty(Utils::$context['membergroups'])): ?>
				<span class="membergroups"><?= implode(', ', Utils::$context['membergroups']) ?></span><?php endif; ?><?php endif; ?>
			</p>