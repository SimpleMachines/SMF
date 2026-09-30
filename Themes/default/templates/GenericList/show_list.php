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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This template handles displaying a list
 *
 * @param string $list_id The list ID. If null, uses Utils::$context['default_list'].
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['list_id' => null], EXTR_SKIP);
?><?php
// Get a shortcut to the current list.
$list_id = $list_id === null ? (!empty(Utils::$context['default_list']) ? Utils::$context['default_list'] : '') : $list_id;
?><?php if (empty($list_id) || empty(Utils::$context[$list_id])): ?><?php return; ?><?php endif; ?><?php $cur_list = &Utils::$context[$list_id]; ?><?php if (isset($cur_list['form'])): ?>
	<form action="<?= $cur_list['form']['href'] ?>" method="post"<?= empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"' ?> accept-charset="UTF-8"><?php endif; ?><?php /* Show the title of the table (if any). */ ?><?php if (!empty($cur_list['title'])): ?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= $cur_list['title'] ?>
			</h3>
		</div><?php endif; ?><?php if (isset($cur_list['additional_rows']['after_title'])): ?>
		<div class="information flow_hidden"><?php $this->subTemplate('additional_rows', ['row_position' => 'after_title', 'cur_list' => $cur_list]); ?>
		</div><?php endif; ?><?php if (isset($cur_list['additional_rows']['top_of_list'])): ?><?php $this->subTemplate('additional_rows', ['row_position' => 'top_of_list', 'cur_list' => $cur_list]); ?><?php endif; ?><?php if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['above_column_headers'])): ?><?php /* Show the page index (if this list doesn't intend to show all items). */ ?><?php if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])): ?>
		<div class="pagesection floatleft">
			<div class="pagelinks"><?= $cur_list['page_index'] ?></div>
		</div><?php endif; ?><?php if (isset($cur_list['additional_rows']['above_column_headers'])): ?><?php $this->subTemplate('additional_rows', ['row_position' => 'above_column_headers', 'cur_list' => $cur_list]); ?><?php endif; ?><?php endif; ?>
		<table class="table_grid" <?= !empty($list_id) ? 'id="' . $list_id . '"' : '' ?> <?= !empty($cur_list['width']) ? ' style="width:' . $cur_list['width'] . '"' : '' ?>><?php
// Show the column headers.
$header_count = count($cur_list['headers']);
?><?php if (!($header_count < 2 && empty($cur_list['headers'][0]['label']))): ?>
			<thead>
				<tr class="title_bar"><?php /* Loop through each column and add a table header. */ ?><?php foreach ($cur_list['headers'] as $col_header): ?>
					<th scope="col" id="header_<?= $list_id ?>_<?= $col_header['id'] ?>" class="<?= $col_header['id'] ?><?= empty($col_header['class']) ? '' : ' ' . $col_header['class'] ?>"<?= empty($col_header['style']) ? '' : ' style="' . $col_header['style'] . '"' ?><?= empty($col_header['colspan']) ? '' : ' colspan="' . $col_header['colspan'] . '"' ?>>
						<?= empty($col_header['href']) ? '' : '<a href="' . $col_header['href'] . '" rel="nofollow">' ?><?= empty($col_header['label']) ? '' : $col_header['label'] ?><?= empty($col_header['href']) ? '' : (empty($col_header['sort_image']) ? '</a>' : ' <span class="main_icons sort_' . $col_header['sort_image'] . '"></span></a>') ?>
					</th><?php endforeach; ?>
				</tr>
			</thead><?php endif; ?>
			<tbody><?php /* Show a nice message informing there are no items in this list. */ ?><?php if (empty($cur_list['rows']) && !empty($cur_list['no_items_label'])): ?>
				<tr class="windowbg">
					<td colspan="<?= $cur_list['num_columns'] ?>" class="<?= !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'centertext' ?>">
						<?= $cur_list['no_items_label'] ?>
					</td>
				</tr><?php /* Show the list rows. */ ?><?php elseif (!empty($cur_list['rows'])): ?><?php foreach ($cur_list['rows'] as $id => $row): ?>
				<tr class="<?= empty($row['class']) ? 'windowbg' : $row['class'] ?>"<?= empty($row['style']) ? '' : ' style="' . $row['style'] . '"' ?> id="list_<?= $list_id ?>_<?= $id ?>"><?php if (!empty($row['data'])): ?><?php foreach ($row['data'] as $row_id => $row_data): ?>
					<td class="<?= $row_id ?><?= empty($row_data['class']) ? '' : ' ' . $row_data['class'] . '' ?>"<?= empty($row_data['style']) ? '' : ' style="' . $row_data['style'] . '"' ?>>
						<?= Utils::adjustHeadingLevels($row_data['value'], 3) ?>
					</td><?php endforeach; ?><?php endif; ?>
				</tr><?php endforeach; ?><?php endif; ?>
			</tbody>
		</table><?php if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data'])): ?>
		<div class="flow_auto"><?php /* Show the page index (if this list doesn't intend to show all items). */ ?><?php if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])): ?>
			<div class="pagesection floatleft">
				<div class="pagelinks"><?= $cur_list['page_index'] ?></div>
			</div><?php endif; ?><?php if (isset($cur_list['additional_rows']['below_table_data'])): ?><?php $this->subTemplate('additional_rows', ['row_position' => 'below_table_data', 'cur_list' => $cur_list]); ?><?php endif; ?>
		</div><?php endif; ?><?php if (isset($cur_list['additional_rows']['bottom_of_list'])): ?><?php $this->subTemplate('additional_rows', ['row_position' => 'bottom_of_list', 'cur_list' => $cur_list]); ?><?php endif; ?><?php if (isset($cur_list['form'])): ?><?php foreach ($cur_list['form']['hidden_fields'] as $name => $value): ?>
		<input type="hidden" name="<?= $name ?>" value="<?= $value ?>"><?php endforeach; ?><?php if (isset($cur_list['form']['token'])): ?>
		<input type="hidden" name="<?= Utils::$context[$cur_list['form']['token'] . '_token_var'] ?>" value="<?= Utils::$context[$cur_list['form']['token'] . '_token'] ?>"><?php endif; ?>
	</form><?php endif; ?><?php if (isset($cur_list['javascript'])): ?>
	<script>
		<?= $cur_list['javascript'] ?>

	</script><?php endif; ?>
