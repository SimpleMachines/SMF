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
 * This is the standard template for showing reports.
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('results', file: 'Reports') ?></h3>
		</div>
		<div id="report_buttons"><?php if (!empty(Utils::$context['report_buttons'])): ?><?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['report_buttons'], 'direction' => 'right']); ?><?php endif; ?>

		</div><?php if (count(Utils::$context['tables']) > 1): ?>

		<style><?php foreach (Utils::$context['tables'] as $i => $table): ?>

			body:has(#report_check_<?= $i + 1 ?>:not(:checked)) [data-id=report_<?= $i + 1 ?>] {
				display: none;
			}<?php endforeach; ?>

		</style>
		<form class="windowbg clear" id="report_filter">
			<fieldset><?php foreach (Utils::$context['tables'] as $i => $table): ?><?php if (!empty($table['title'])): ?>

			<label>
				<input type="checkbox" id="report_check_<?= $i + 1 ?>" checked>
				<?= $table['title'] ?>

			</label><?php endif; ?><?php endforeach; ?>

			</fieldset>
		</form><?php endif; ?><?php foreach (Utils::$context['tables'] as $i => $table): ?>

		<table class="table_grid report_result" data-id="report_<?= $i + 1 ?>"><?php if (!empty($table['title'])): ?>

			<thead>
				<tr class="title_bar">
					<th scope="col" colspan="<?= $table['column_count'] ?>"><?= $table['title'] ?></th>
				</tr>
			</thead>
			<tbody><?php endif; ?><?php
// Now do each row!
$row_number = 0;
?><?php foreach ($table['data'] as $row): ?><?php if ($row_number == 0 && !empty($table['shading']['top']) && empty(current($row)['header'])): ?>

				<tr class="windowbg table_caption"><?php else: ?>

				<tr class="<?= !empty(current($row)['separator']) || !empty(current($row)['header']) ? 'title_bar' : 'windowbg' ?>"><?php endif; ?><?php
// Now do each column.
$column_number = 0;
$th = false;
?><?php foreach ($row as $data): ?><?php /* If this is a special separator, skip over! */ ?><?php if (!empty($data['separator']) && $column_number == 0): ?>

					<th colspan="<?= $table['column_count'] ?>" class="smalltext">
						<?= $data['v'] ?>:
					</th><?php break; ?><?php endif; ?><?php /* These table cells shall be a heading if the first row says so. */ ?><?php if ($th || !empty($data['header'])): ?>

					<th>
						<?= $data['v'] ?>

					</th><?php
$th = true;
// Shaded?
?><?php elseif ($column_number == 0 && !empty($table['shading']['left'])): ?>

					<td class="table_caption <?= $table['align']['shaded'] ?>text"<?= $table['width']['shaded'] != 'auto' ? ' width="' . $table['width']['shaded'] . '"' : '' ?>>
						<?= $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')) ?>

					</td><?php else: ?>

					<td class="smalltext <?= $table['align']['normal'] ?>text" <?= $table['width']['normal'] != 'auto' ? ' width="' . $table['width']['normal'] . '"' : '' ?><?= !empty($data['style']) ? ' style="' . $data['style'] . '"' : '' ?>>
						<?= $data['v'] ?>

					</td><?php endif; ?><?php $column_number++; ?><?php endforeach; ?>

				</tr><?php $row_number++; ?><?php endforeach; ?>

			</tbody>
		</table><?php endforeach; ?>
