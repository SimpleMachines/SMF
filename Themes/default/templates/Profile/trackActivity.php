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
 * This template shows an admin information on a users IP addresses used and errors attributed to them.
 */
?><?php /* The first table shows IP information about the user. */ ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('view_ips_by', ['member' => Utils::$context['member']['name']], file: 'Profile') ?></h3>
		</div><?php /* The last IP the user used. */ ?>
		<div id="tracking" class="windowbg">
			<dl class="settings noborder">
				<dt>
					<?= Lang::getTxt('most_recent_ip', file: 'Profile') ?>
					<?= (empty(Utils::$context['last_ip2']) ? '' : '<br>
					<span class="smalltext"><a href="' . Config::$scripturl . '?action=helpadmin;help=whytwoip" onclick="return reqOverlayDiv(this.href);">' . Lang::getTxt('why_two_ip_address', file: 'Profile') . '</a></span>') ?>
				</dt>
				<dd>
					<a href="<?= Config::$scripturl ?>?action=profile;area=tracking;sa=ip;searchip=<?= Utils::$context['last_ip'] ?>;u=<?= Utils::$context['member']['id'] ?>"><?= Utils::$context['last_ip'] ?></a><?php /* Second address detected? */ ?><?php if (!empty(Utils::$context['last_ip2'])): ?>
					, <a href="<?= Config::$scripturl ?>?action=profile;area=tracking;sa=ip;searchip=<?= Utils::$context['last_ip2'] ?>;u=<?= Utils::$context['member']['id'] ?>"><?= Utils::$context['last_ip2'] ?></a><?php endif; ?>
				</dd><?php /* Lists of IP addresses used in messages / error messages. */ ?>
				<dt><?= Lang::getTxt('ips_in_messages', file: 'Profile') ?></dt>
				<dd>
					<?= (count(Utils::$context['ips']) > 0 ? Lang::sentenceList(Utils::$context['ips']) : Lang::getTxt('none', file: 'General')) ?>
				</dd>
				<dt><?= Lang::getTxt('ips_in_errors', file: 'Profile') ?></dt>
				<dd>
					<?= (count(Utils::$context['error_ips']) > 0 ? Lang::sentenceList(Utils::$context['error_ips']) : Lang::getTxt('none', file: 'General')) ?>
				</dd><?php /* List any members that have used the same IP addresses as the current member. */ ?>
				<dt><?= Lang::getTxt('members_in_range', file: 'Profile') ?></dt>
				<dd>
					<?= (count(Utils::$context['members_in_range']) > 0 ? Lang::sentenceList(Utils::$context['members_in_range']) : Lang::getTxt('none', file: 'General')) ?>
				</dd>
			</dl>
		</div><!-- #tracking --><?php /* Show the track user list. */ ?><?php $this->subTemplate('show_list', ['list_id' => 'track_user_list']); ?>
