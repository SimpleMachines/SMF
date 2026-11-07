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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for managing ignored boards
 */
?><?php /* The main containing header. */ ?>
	<form action="<?= Config::$scripturl ?>?action=profile;area=ignoreboards;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt('profile', file: 'General') ?>
			</h3>
		</div>
		<p class="information"><?= Lang::getTxt('ignoreboards_info', file: 'Profile') ?></p>
		<div class="windowbg">
			<div class="flow_hidden boardslist">
				<ul><?php foreach (Utils::$context['categories'] as $category): ?>
					<li>
						<a href="javascript:void(0);" onclick="selectBoards([<?= implode(', ', $category['child_ids']) ?>], 'creator'); return false;"><?= $category['name'] ?></a>
						<ul><?php $cat_boards = array_values($category['boards']); ?><?php foreach ($cat_boards as $key => $board): ?>
							<li>
								<label for="brd<?= $board['id'] ?>">
									<input type="checkbox" id="brd<?= $board['id'] ?>" name="brd[<?= $board['id'] ?>]" value="<?= $board['id'] ?>"<?= $board['selected'] ? ' checked' : '' ?>>
									<?= $board['name'] ?>
								</label><?php
// Nest child boards inside another list.
$curr_child_level = $board['child_level'];
$next_child_level = $cat_boards[$key + 1]['child_level'] ?? 0;
?><?php if ($next_child_level > $curr_child_level): ?>
								<ul style="margin-inline-start: 2.5ch;"><?php else: ?><?php
// Close child board lists until we reach a common level
// with the next board.
?><?php while ($next_child_level < $curr_child_level--): ?>
									</li>
								</ul><?php endwhile; ?>
							</li><?php endif; ?><?php endforeach; ?>
						</ul>
					</li><?php endforeach; ?>
				</ul>
			</div><!-- .flow_hidden boardslist --><?php /* Show the standard "Save Settings" profile button. */ ?><?php $this->subTemplate('profile_save'); ?>
		</div><!-- .windowbg -->
	</form>
	<br>