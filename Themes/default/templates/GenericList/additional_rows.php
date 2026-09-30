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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This template displays additional rows above or below the list.
 *
 * @param string $row_position The position ('top', 'bottom', etc.)
 * @param array $cur_list An array with the data for the current list
 */
?><?php foreach ($cur_list['additional_rows'][$row_position] as $row): ?>

			<div class="additional_row<?= empty($row['class']) ? '' : ' ' . $row['class'] ?>"<?= empty($row['style']) ? '' : ' style="' . $row['style'] . '"' ?>>
				<?= $row['value'] ?>

			</div><?php endforeach; ?>
