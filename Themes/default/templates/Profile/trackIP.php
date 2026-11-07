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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The template for trackIP, allowing the admin to see where/who a certain IP has been used.
 */
?><?php
// This function always defaults to the last IP used by a member but can be set to track any IP.
// The first table in the template gives an input box to allow the admin to enter another IP to track.
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('trackIP', file: 'Profile') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Utils::$context['base_url'] ?>" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt>
						<label for="searchip"><strong><?= Lang::getTxt('enter_ip', file: 'Profile') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="searchip" id="searchip" value="<?= Utils::$context['ip'] ?>">
					</dd>
				</dl>
				<input type="submit" value="<?= Lang::getTxt('trackIP', file: 'Profile') ?>" class="button">
			</form>
		</div>
		<br><?php /* The table inbetween the first and second table shows links to the whois server for every region. */ ?><?php if (Utils::$context['single_ip']): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('whois_title', Utils::$context, file: 'Profile') ?></h3>
		</div>
		<div class="windowbg"><?php foreach (Utils::$context['whois_servers'] as $server): ?>
			<a href="<?= $server['url'] ?>" target="_blank" rel="noopener"><?= $server['name'] ?></a><br><?php endforeach; ?>
		</div>
		<br><?php endif; ?><?php /* The second table lists all the members who have been logged as using this IP address. */ ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('members_from_ip', Utils::$context, file: 'Profile') ?></h3>
		</div><?php if (empty(Utils::$context['ips'])): ?>
		<p class="windowbg description">
			<em><?= Lang::getTxt('no_members_from_ip', file: 'Profile') ?></em>
		</p><?php else: ?>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th scope="col"><?= Lang::getTxt('ip_address', file: 'General') ?></th>
					<th scope="col"><?= Lang::getTxt('display_name', file: 'General') ?></th>
				</tr>
			</thead>
			<tbody><?php /* Loop through each of the members and display them. */ ?><?php foreach (Utils::$context['ips'] as $ip => $memberlist): ?>
				<tr class="windowbg">
					<td><a href="<?= Utils::$context['base_url'] ?>;searchip=<?= $ip ?>"><?= $ip ?></a></td>
					<td><?= implode(', ', $memberlist) ?></td>
				</tr><?php endforeach; ?>
			</tbody>
		</table><?php endif; ?>
		<br><?php $this->subTemplate('show_list', ['list_id' => 'track_message_list']); ?><br><?php $this->subTemplate('show_list', ['list_id' => 'track_user_list']); ?><?php /* 3rd party integrations may have added additional tracking. */ ?><?php if (!empty(Utils::$context['additional_track_lists'])): ?><?php foreach (Utils::$context['additional_track_lists'] as $list): ?><br><?php $this->subTemplate('show_list', ['list_id' => $list]); ?><?php endforeach; ?><?php endif; ?>
