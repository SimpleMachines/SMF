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
 * Displays a sortable listing of all members registered on the forum.
 */
?>
	<div class="main_section" id="memberlist">
		<div class="pagesection">
			<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div>
			<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['memberlist_buttons'], 'direction' => 'right']); ?>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft"><?= Lang::getTxt('members_list', file: 'General') ?></span>
			</h3>
		</div>
		<div id="mlist">
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
<?php /* Display each of the column headers of the table. */ ?>
<?php foreach (Utils::$context['columns'] as $key => $column): ?>
<?php /* @TODO maybe find something nicer? */ ?>
<?php if ($key == 'email_address' && !Utils::$context['can_send_email']): ?>
<?php continue; ?>
<?php endif; ?>
<?php /* This is a selected column, so underline it or some such. */ ?>
<?php if ($column['selected']): ?>
						<th scope="col" class="<?= $key ?><?= isset($column['class']) ? ' ' . $column['class'] : '' ?> selected" style="width: auto;"<?= (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') ?>>
							<a href="<?= $column['href'] ?>" rel="nofollow"><?= $column['label'] ?></a><span class="main_icons sort_<?= Utils::$context['sort_direction'] ?>"></span></th>
<?php /* This is just some column... show the link and be done with it. */ ?>
<?php else: ?>
						<th scope="col" class="<?= $key ?><?= isset($column['class']) ? ' ' . $column['class'] : '' ?>"<?= isset($column['width']) ? ' style="width: ' . $column['width'] . '"' : '' ?><?= isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '' ?>>
						<?= $column['link'] ?></th>
<?php endif; ?>
<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
<?php /* Assuming there are members loop through each one displaying their data. */ ?>
<?php if (!empty(Utils::$context['members'])): ?>
<?php foreach (Utils::$context['members'] as $member): ?>
					<tr class="windowbg">
						<td class="is_online centertext">
							<?= Utils::$context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '' ?><span class="<?= ($member['online']['is_online'] == 1 ? 'on' : 'off') ?>" title="<?= $member['online']['text'] ?>"></span><?= Utils::$context['can_send_pm'] ? '</a>' : '' ?>
						</td>
						<td class="real_name lefttext"><?= $member['link'] ?></td>
<?php if (!isset(Utils::$context['disabled_fields']['website'])): ?>
						<td class="website_url centertext"><?= $member['website']['url'] != '' ? '<a href="' . $member['website']['url'] . '" target="_blank" rel="noopener"><span class="main_icons www" title="' . $member['website']['title'] . '"></span></a>' : '' ?></td>
<?php endif; ?>
<?php /* Group and date. */ ?>
						<td class="id_group centertext"><?= empty($member['group']) ? $member['post_group'] : $member['group'] ?></td>
						<td class="registered centertext"><?= $member['registered_date'] ?></td>
<?php if (!isset(Utils::$context['disabled_fields']['posts'])): ?>
						<td class="post_count centertext">
<?php if (!empty($member['posts'])): ?>
							<div class="generic_bar">
								<div class="bar" style="width: <?= $member['post_percent'] ?>%;"></div>
								<span><?= $member['posts'] ?></span>
							</div>
<?php endif; ?>
						</td>
<?php endif; ?>
<?php /* Show custom fields marked to be shown here */ ?>
<?php if (!empty(Utils::$context['custom_profile_fields']['columns'])): ?>
<?php foreach (Utils::$context['custom_profile_fields']['columns'] as $key => $column): ?>
						<td class="<?= $key ?> centertext"><?= $member['options'][$key] ?></td>
<?php endforeach; ?>
<?php endif; ?>
					</tr>
<?php endforeach; ?>
<?php /* No members? */ ?>
<?php else: ?>
					<tr>
						<td colspan="<?= Utils::$context['colspan'] ?>" class="windowbg"><?= Lang::getTxt('search_no_results', file: 'General') ?></td>
					</tr>
<?php endif; ?>
				</tbody>
			</table>
		</div><!-- #mlist -->
<?php /* Show the page numbers again. (makes 'em easier to find!) */ ?>
		<div class="pagesection">
			<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div>
<?php /* If it is displaying the result of a search show a "search again" link to edit their criteria. */ ?>
<?php if (isset(Utils::$context['old_search'])): ?>
			<div class="buttonlist floatright">
				<a class="button" href="<?= Config::$scripturl ?>?action=mlist;sa=search;search=<?= Utils::$context['old_search_value'] ?>"><?= Lang::getTxt('mlist_search_again', file: 'General') ?></a>
			</div>
<?php endif; ?>
		</div>
	</div><!-- #memberlist -->