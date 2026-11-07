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
 * Results page for an admin search.
 */
?>
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								<span id="quick_search_results">
									<?= Lang::getTxt('admin_search_results_desc', Utils::$context, file: 'Admin') ?>
								</span>
							</h3>
							<?php $this->subTemplate('admin_quick_search'); ?>
						</div><!-- #section_header -->
						<div class="windowbg generic_list_wrapper">
<?php if (empty(Utils::$context['search_results'])): ?>
							<p class="centertext">
								<strong><?= Lang::getTxt('admin_search_results_none', file: 'Admin') ?></strong>
							</p>
<?php else: ?>
							<ol class="search_results">
<?php foreach (Utils::$context['search_results'] as $result): ?>
<?php /* Is it a result from the online manual? */ ?>
<?php if (Utils::$context['search_type'] == 'online'): ?>
								<li>
									<p>
										<a href="<?= Utils::$context['doc_scripturl'] ?><?= str_replace(' ', '_', $result['title']) ?>" target="_blank" rel="noopener"><strong><?= $result['title'] ?></strong></a>
									</p>
									<p class="double_height">
										<?= $result['snippet'] ?>
									</p>
								</li>
<?php /* Otherwise it's... not! */ ?>
<?php else: ?>
								<li>
									<a href="<?= $result['url'] ?>"><strong><?= $result['name'] ?></strong></a> [<?= Lang::txtExists('admin_search_section_' . $result['type'], file: 'Admin') ? Lang::getTxt('admin_search_section_' . $result['type'], file: 'Admin') : $result['type'] ?>]
<?php if ($result['help']): ?>
									<p class="double_height"><?= $result['help'] ?></p>
<?php endif; ?>
								</li>
<?php endif; ?>
<?php endforeach; ?>
							</ol>
<?php endif; ?>
						</div><!-- .generic_list_wrapper -->