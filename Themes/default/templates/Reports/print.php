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
 * The main print page
 */
?><?php /* Go through each table! */ ?><?php foreach (Utils::$context['tables'] as $table): ?>

		<div style="overflow: visible;<?= $table['max_width'] != 'auto' ? ' width: ' . $table['max_width'] . 'px;' : '' ?>">
			<table class="bordercolor"><?php if (!empty($table['title'])): ?>

				<tr class="title_bar">
					<td colspan="<?= $table['column_count'] ?>">
						<?= $table['title'] ?>

					</td>
				</tr><?php endif; ?><?php
// Now do each row!
$row_number = 0;
?><?php foreach ($table['data'] as $row): ?><?php if ($row_number == 0 && !empty($table['shading']['top'])): ?>

				<tr class="titlebg"><?php else: ?>

				<tr class="windowbg"><?php endif; ?><?php
// Now do each column!!
$column_number = 0;
?><?php foreach ($row as $data): ?><?php /* If this is a special separator, skip over! */ ?><?php if (!empty($data['separator']) && $column_number == 0): ?>

					<td colspan="<?= $table['column_count'] ?>" class="smalltext">
						<strong><?= $data['v'] ?>:</strong>
					</td><?php break; ?><?php endif; ?><?php /* Shaded? */ ?><?php if ($column_number == 0 && !empty($table['shading']['left'])): ?>

					<td class="titlebg <?= $table['align']['shaded'] ?>text"<?= $table['width']['shaded'] != 'auto' ? ' width="' . $table['width']['shaded'] . '"' : '' ?>>
						<?= $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')) ?>

					</td><?php else: ?>

					<td class="centertext" <?= $table['width']['normal'] != 'auto' ? ' width="' . $table['width']['normal'] . '"' : '' ?><?= !empty($data['style']) ? ' style="' . $data['style'] . '"' : '' ?>>
						<?= $data['v'] ?>

					</td><?php endif; ?><?php $column_number++; ?><?php endforeach; ?>

				</tr><?php $row_number++; ?><?php endforeach; ?>

			</table>
		</div>
		<br><?php endforeach; ?>
