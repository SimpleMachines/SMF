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
 * Retrieves info from the php_info function, scrubs and preps it for display
 */
?>
					<div id="admin_form_wrapper">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								<?= Lang::getTxt('phpinfo_settings', file: 'Admin') ?>
							</h3>
						</div>
<?php /* for each php info area */ ?>
<?php foreach (Utils::$context['pinfo'] as $area => $php_area): ?>
						<table id="<?= str_replace(' ', '_', $area) ?>" class="table_grid">
							<thead>
								<tr class="title_bar">
									<th class="equal_table" scope="col"></th>
									<th class="centercol equal_table" scope="col"><strong><?= $area ?></strong></th>
									<th class="equal_table" scope="col"></th>
								</tr>
							</thead>
							<tbody>
<?php
$localmaster = true;
// and for each setting in this category
?>
<?php foreach ($php_area as $key => $setting): ?>
<?php /* start of a local / master setting (3 col) */ ?>
<?php if (is_array($setting)): ?>
<?php if ($localmaster): ?>
<?php /* heading row for the settings section of this category's settings */ ?>
								<tr class="title_bar">
									<td class="equal_table"><strong><?= Lang::getTxt('phpinfo_itemsettings', file: 'Admin') ?></strong></td>
									<td class="equal_table"><strong><?= Lang::getTxt('phpinfo_localsettings', file: 'Admin') ?></strong></td>
									<td class="equal_table"><strong><?= Lang::getTxt('phpinfo_defaultsettings', file: 'Admin') ?></strong></td>
								</tr>
<?php $localmaster = false; ?>
<?php endif; ?>
								<tr class="windowbg">
									<td class="equal_table"><?= $key ?></td>
<?php foreach ($setting as $key_lm => $value): ?>
									<td class="equal_table"><?= $value ?></td>
<?php endforeach; ?>
								</tr>
<?php /* just a single setting (2 col) */ ?>
<?php else: ?>
								<tr class="windowbg">
									<td class="equal_table"><?= $key ?></td>
									<td colspan="2" class="word_break"><?= str_replace(',', ', ', $setting) ?></td>
								</tr>
<?php endif; ?>
<?php endforeach; ?>
							</tbody>
						</table>
						<br>
<?php endforeach; ?>
					</div><!-- #admin_form_wrapper -->