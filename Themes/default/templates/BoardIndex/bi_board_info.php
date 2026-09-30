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
 * Outputs the board info for a standard board or redirect.
 *
 * @param array $board Current board information.
 */
?>

		<a class="subject mobile_subject" href="<?= $board['href'] ?>" id="b<?= $board['id'] ?>">
			<?= $board['name'] ?>

		</a><?php /* Has it outstanding posts for approval? */ ?><?php if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics'])): ?>

		<a href="<?= Config::$scripturl ?>?action=moderate;area=postmod;sa=<?= ($board['unapproved_topics'] > 0 ? 'topics' : 'posts') ?>;brd=<?= $board['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" title="<?= Lang::getTxt('unapproved_posts', ['unapproved_topics' => $board['unapproved_topics'], 'unapproved_posts' => $board['unapproved_posts']], file: 'General') ?>" class="moderation_link amt">!</a><?php endif; ?>

		<div class="board_description"><?= $board['description'] ?></div><?php /* Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.) */ ?><?php if (!empty($board['link_moderators'])): ?>

		<p class="moderators"><?= Lang::getTxt('moderators_list', ['num' => count($board['link_moderators']), 'list' => Lang::sentenceList($board['link_moderators'])], file: 'General') ?></p><?php endif; ?>
