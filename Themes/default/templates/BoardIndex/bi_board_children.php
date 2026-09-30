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
 * Outputs the board children for a standard board.
 *
 * @param array $board Current board information.
 */
?><?php /* Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...) */ ?><?php if (!empty($board['children'])): ?><?php
// Sort the links into an array with new boards bold so it can be imploded.
$children = [];
/* Each child in each board's children has:
			id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
?><?php foreach ($board['children'] as $child): ?><?php if (!$child['is_redirect']): ?><?php $child['link'] = '' . ($child['new'] ? '<a href="' . Config::$scripturl . '?action=unread;board=' . $child['id'] . '" title="' . Lang::getTxt('new_posts_stats', ['posts' => $child['posts'], 'topics' => $child['topics']], file: 'General') . '" class="new_posts">' . Lang::getTxt('new', file: 'General') . '</a> ' : '') . '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . Lang::getTxt($child['new'] ? 'new_posts_stats' : 'old_posts_stats', ['posts' => $child['posts'], 'topics' => $child['topics']], file: 'General') . '">' . $child['name'] . '</a>'; ?><?php else: ?><?php $child['link'] = '<a href="' . $child['href'] . '" title="' . Lang::getTxt('number_of_redirects', [$child['posts']], file: 'General') . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>'; ?><?php endif; ?><?php /* Has it posts awaiting approval? */ ?><?php if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics'])): ?><?php $child['link'] .= ' <a href="' . Config::$scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" title="' . Lang::getTxt('unapproved_posts', $child, file: 'General') . '" class="moderation_link amt">!</a>'; ?><?php endif; ?><?php $children[] = $child['new'] ? '<span class="strong">' . $child['link'] . '</span>' : '<span>' . $child['link'] . '</span>'; ?><?php endforeach; ?>

			<div id="board_<?= $board['id'] ?>_children" class="children">
				<p><?= Lang::getTxt(
					'sub_boards_list',
					[
						'id' => 'child_list_' . $board['id'],
						'num' => count($children),
						'list' => implode(' ', $children),
					],
					file: 'General',
				) ?></p>
			</div><?php endif; ?>
