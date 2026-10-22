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
 * Display a like button and info about how many people liked something
 */
?>
	<ul class="floatleft">
<?php if (!empty(Utils::$context['data']['can_like'])): ?>
		<li class="smflikebutton" id="<?= Utils::$context['data']['type'] ?>_<?= Utils::$context['data']['id_content'] ?>_likes">
			<a href="<?= Config::$scripturl ?>?action=likes;ltype=<?= Utils::$context['data']['type'] ?>;sa=like;like=<?= Utils::$context['data']['id_content'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="<?= Utils::$context['data']['type'] ?>_like"><span class="main_icons <?= Utils::$context['data']['already_liked'] ? 'unlike' : 'like' ?>"></span> <?= Lang::getTxt(Utils::$context['data']['already_liked'] ? 'unlike' : 'like', file: 'General') ?></a>
		</li>
<?php endif; ?>
<?php if (!empty(Utils::$context['data']['count'])): ?>
<?php
Utils::$context['some_likes'] = true;
$count = Utils::$context['data']['count'];
$base = 'likes_count';
?>
<?php if (Utils::$context['data']['already_liked']): ?>
<?php
$base = 'you_' . $base;
$count--;
?>
<?php endif; ?>
		<li class="like_count smalltext"><?= Lang::getTxt($base, ['url' => Config::$scripturl . '?action=likes;sa=view;ltype=' . Utils::$context['data']['type'] . ';js=1;like=' . Utils::$context['data']['id_content'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], 'num' => $count], file: 'General') ?></li>
<?php endif; ?>
	</ul>