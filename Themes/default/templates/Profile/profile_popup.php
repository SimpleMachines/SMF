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
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing off the spiffy popup of the menu
 */
?>

<?php /* Unlike almost every other template, this is designed to be included into the HTML directly via $().load() */ ?>
		<div class="profile_user_avatar floatleft">
			<a href="<?= Config::$scripturl ?>?action=profile;u=<?= User::$me->id ?>"><?= Utils::$context['member']['avatar']['image'] ?></a>
		</div>
		<div class="profile_user_info floatleft">
			<span class="profile_username"><a href="<?= Config::$scripturl ?>?action=profile;u=<?= User::$me->id ?>"><?= User::$me->name ?></a></span>
			<span class="profile_group"><?= Utils::$context['member']['group'] ?></span>
		</div>
		<div class="profile_user_links">
			<ol>
<?php $menu_context = &Utils::$context[Utils::$context['profile_menu_name']]; ?>
<?php foreach (Utils::$context['profile_items'] as $item): ?>
<?php
$area = $menu_context['sections'][$item['menu']]['areas'][$item['area']];
$item_url = ($item['url'] ?? ($area['url'] ?? $menu_context['base_url'] . ';area=' . $item['area'])) . $menu_context['extra_parameters'];
?>
				<li>
					<?= $area['icon'] ?><a href="<?= $item_url ?>"><?= !empty($item['title']) ? $item['title'] : $area['label'] ?></a>
				</li>
<?php endforeach; ?>
			</ol>
		</div><!-- .profile_user_links -->