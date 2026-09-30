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
 * A page allowing people to search the member list.
 */
?>

<?php /* Start the submission form for the search! */ ?>
	<form action="<?= Config::$scripturl ?>?action=mlist;sa=search" method="post" accept-charset="UTF-8">
		<div id="memberlist">
			<div class="pagesection">
				<?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['memberlist_buttons'], 'direction' => 'right']); ?>

			</div>
			<div class="cat_bar">
				<h3 class="catbg mlist">
					<span class="main_icons filter"></span><?= Lang::getTxt('mlist_search', file: 'General') ?>

				</h3>
			</div>
			<div id="advanced_search" class="roundframe">
				<dl id="mlist_search" class="settings">
					<dt>
						<label for="search"><strong><?= Lang::getTxt('search_for', file: 'General') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="search" id="search" value="<?= Utils::$context['old_search'] ?>" size="40">
					</dd>
					<dt>
						<label><strong><?= Lang::getTxt('mlist_search_filter', file: 'General') ?></strong></label>
					</dt>
					<dd>
						<ul>
<?php foreach (Utils::$context['search_fields'] as $id => $title): ?>
							<li>
								<input type="checkbox" name="fields[]" id="fields-<?= $id ?>" value="<?= $id ?>"<?= in_array($id, Utils::$context['search_defaults']) ? ' checked' : '' ?>>
								<label for="fields-<?= $id ?>"><?= $title ?></label>
							</li>
<?php endforeach; ?>
						</ul>
					</dd>
				</dl>
				<input type="submit" name="submit" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button floatright">
			</div><!-- #advanced_search -->
		</div><!-- #memberlist -->
	</form>