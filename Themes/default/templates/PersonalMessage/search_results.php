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
 * Displays results from a PM search
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_search_results', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="roundframe noup">
			<?= Lang::getTxt('pm_search_results_info', [Utils::$context['num_results'], Lang::sentenceList(Utils::$context['search_in'])], file: 'PersonalMessage') ?>

		</div>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php /* Complete results? */ ?><?php if (empty(Utils::$context['search_params']['show_complete']) && !empty(Utils::$context['personal_messages'])): ?>

		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext quarter_table"><?= Lang::getTxt('date', file: 'General') ?></th>
					<th class="lefttext half_table"><?= Lang::getTxt('subject', file: 'General') ?></th>
					<th class="lefttext quarter_table"><?= Lang::getTxt('from', file: 'General') ?></th>
				</tr>
			</thead>
			<tbody><?php endif; ?><?php /* Print each message out... */ ?><?php foreach (Utils::$context['personal_messages'] as $message): ?><?php /* Are we showing it all? */ ?><?php if (!empty(Utils::$context['search_params']['show_complete'])): ?><?php $this->subTemplate('single_pm', ['message' => $message]); ?><?php /* Otherwise just a simple list! */ ?><?php else: ?>

				<tr class="windowbg">
					<td><?= $message['time'] ?></td>
					<td><?= $message['link'] ?></td>
					<td><?= $message['member']['link'] ?></td>
				</tr><?php endif; ?><?php endforeach; ?><?php /* Finish off the page... */ ?><?php if (empty(Utils::$context['search_params']['show_complete']) && !empty(Utils::$context['personal_messages'])): ?>

			</tbody>
		</table><?php endif; ?><?php /* No results? */ ?><?php if (empty(Utils::$context['personal_messages'])): ?>

		<div class="windowbg">
			<p class="centertext"><?= Lang::getTxt('pm_search_none_found', file: 'PersonalMessage') ?></p>
		</div><?php endif; ?>

		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div>