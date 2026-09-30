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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The XML for hiding/showing stats sections via AJAX
 */
?><?php if (empty(Utils::$context['yearly'])): ?><?php return; ?><?php endif; ?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf><?php foreach (Utils::$context['yearly'] as $year): ?><?php foreach ($year['months'] as $month): ?>

	<month id="<?= $month['date']['year'] ?><?= $month['date']['month'] ?>"><?php foreach ($month['days'] as $day): ?>

		<day date="<?= $day['year'] ?>-<?= $day['month'] ?>-<?= $day['day'] ?>" new_topics="<?= $day['new_topics'] ?>" new_posts="<?= $day['new_posts'] ?>" new_members="<?= $day['new_members'] ?>" most_members_online="<?= $day['most_members_online'] ?>"<?= empty(Config::$modSettings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"' ?> /><?php endforeach; ?>

	</month><?php endforeach; ?><?php endforeach; ?>

</smf>