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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for listing all the current categories and boards.
 */
?>

<?php /* Table header. */ ?>
	<div id="manage_boards">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('boards_edit', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
<?php if (!empty(Utils::$context['move_board'])): ?>
			<div class="noticebox">
				<?= Utils::$context['move_title'] ?> [<a href="<?= Config::$scripturl ?>?action=admin;area=manageboards"><?= Lang::getTxt('mboards_cancel_moving', file: 'ManageBoards') ?></a>]
			</div>
<?php endif; ?>
<?php /* No categories so show a label. */ ?>
<?php if (empty(Utils::$context['categories'])): ?>
			<div class="windowbg centertext">
				<?= Lang::getTxt('mboards_no_cats', file: 'ManageBoards') ?>

			</div>
<?php endif; ?>
<?php /* Loop through every category, listing the boards in each as we go. */ ?>
<?php foreach (Utils::$context['categories'] as $category): ?>
<?php /* Link to modify the category. */ ?>
			<div class="sub_bar">
				<h3 class="subbg">
					<a href="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=cat;cat=<?= $category['id'] ?>"><?= $category['name'] ?></a> <a href="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=cat;cat=<?= $category['id'] ?>"><?= Lang::getTxt('cat_modify', file: 'ManageBoards') ?></a>
				</h3>
			</div>
<?php /* Boards table header. */ ?>
			<form action="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=newboard;cat=<?= $category['id'] ?>" method="post" accept-charset="UTF-8">
				<ul id="category_<?= $category['id'] ?>" class="nolist">
<?php if (!empty($category['move_link'])): ?>
					<li><a href="<?= $category['move_link']['href'] ?>" title="<?= $category['move_link']['label'] ?>"><span class="main_icons select_above"></span></a></li>
<?php endif; ?>
<?php
$recycle_board = '<a href="' . Config::$scripturl . '?action=admin;area=manageboards;sa=settings"> <img src="' . Theme::$current->settings['images_url'] . '/post/recycled.png" alt="' . Lang::getTxt('recycle_board', file: 'ManageBoards') . '" title="' . Lang::getTxt('recycle_board', file: 'ManageBoards') . '"></a>';
$redirect_board = '<img src="' . Theme::$current->settings['images_url'] . '/new_redirect.png" alt="' . Lang::getTxt('redirect_board_desc', file: 'ManageBoards') . '" title="' . Lang::getTxt('redirect_board_desc', file: 'ManageBoards') . '">';
// List through every board in the category, printing its name and link to modify the board.
?>
<?php foreach ($category['boards'] as $board): ?>
					<li<?= !empty(Config::$modSettings['recycle_board']) && !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $board['id'] ? ' id="recycle_board"' : ' ' ?> class="windowbg<?= $board['is_redirect'] ? ' redirect_board' : '' ?>" style="padding-inline-start: <?= 5 + 30 * $board['child_level'] ?>px;">
						<span class="floatleft"><a<?= $board['move'] ? ' class="red"' : '' ?> href="<?= Config::$scripturl ?>?board=<?= $board['id'] ?>.0"><?= $board['name'] ?></a><?= !empty(Config::$modSettings['recycle_board']) && !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $board['id'] ? $recycle_board : '' ?><?= $board['is_redirect'] ? $redirect_board : '' ?></span>
						<span class="floatright">
							<?= Utils::$context['can_manage_permissions'] ? '<a href="' . Config::$scripturl . '?action=admin;area=permissions;sa=index;pid=' . $board['permission_profile'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" class="button">' . Lang::getTxt('mboards_permissions', file: 'ManageBoards') . '</a>' : '' ?>

							<a href="<?= Config::$scripturl ?>?action=admin;area=manageboards;move=<?= $board['id'] ?>" class="button"><?= Lang::getTxt('mboards_move', file: 'ManageBoards') ?></a>
							<a href="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=board;boardid=<?= $board['id'] ?>" class="button"><?= Lang::getTxt('mboards_modify', file: 'ManageBoards') ?></a>
						</span><br style="clear: right;">
					</li>
<?php if (!empty($board['move_links'])): ?>
					<li class="windowbg" style="padding-inline-start: <?= 5 + 30 * $board['move_links'][0]['child_level'] ?>px;">
<?php foreach ($board['move_links'] as $link): ?>
						<a href="<?= $link['href'] ?>" class="move_links" title="<?= $link['label'] ?>"><span class="main_icons select_<?= $link['class'] ?>" title="<?= $link['label'] ?>"></span></a>
<?php endforeach; ?>
					</li>
<?php endif; ?>
<?php endforeach; ?>
<?php /* Button to add a new board. */ ?>
				</ul>
				<input type="submit" value="<?= Lang::getTxt('mboards_new_board', file: 'ManageBoards') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
<?php endforeach; ?>
		</div><!-- .windowbg -->
	</div><!-- #manage_boards -->