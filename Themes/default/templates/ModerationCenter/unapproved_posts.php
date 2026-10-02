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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Show a list of all the unapproved posts
 */
?>

<?php /* Just a big table of it all really... */ ?>
	<div id="modcenter">
		<form action="<?= Config::$scripturl ?>?action=moderate;area=postmod;start=<?= Utils::$context['start'] ?>;sa=<?= Utils::$context['current_view'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar<?= !empty(Utils::$context['unapproved_items']) ? ' cat_bar_round' : '' ?>">
				<h3 class="catbg"><?= Lang::getTxt('mc_unapproved_posts', file: 'ModerationCenter') ?></h3>
			</div>
<?php /* No posts? */ ?>
<?php if (empty(Utils::$context['unapproved_items'])): ?>
			<div class="windowbg">
				<p class="centertext">
					<?= Lang::getTxt('mc_unapproved_' . Utils::$context['current_view'] . '_none_found', file: 'ModerationCenter') ?>
				</p>
			</div>
<?php else: ?>
			<div class="pagesection">
				<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
<?php if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1): ?>
				<ul class="buttonlist floatright">
					<li class="inline_mod_check">
						<input type="checkbox" onclick="invertAll(this, this.form, 'item[]');" checked>
					</li>
				</ul>
<?php endif; ?>
			</div>
<?php endif; ?>
<?php foreach (Utils::$context['unapproved_items'] as $item): ?>
<?php
// The buttons
$quickbuttons = [
	'approve' => [
		'label' => Lang::getTxt('approve', file: 'General'),
		'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=' . Utils::$context['current_view'] . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';approve=' . $item['id'],
		'icon' => 'approve',
	],
	'delete' => [
		'label' => Lang::getTxt('remove', file: 'General'),
		'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=' . Utils::$context['current_view'] . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';delete=' . $item['id'],
		'icon' => 'remove_button',
		'show' => $item['can_delete'],
	],
	'quickmod' => [
		'class' => 'inline_mod_check',
		'content' => '<input type="checkbox" name="item[]" value="' . $item['id'] . '" checked>',
		'show' => !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1,
	],
];
?>
			<div class="windowbg clear">
				<div class="page_number floatright"> #<?= $item['counter'] ?></div>
				<div class="topic_details">
					<h5>
						<strong><?= $item['category']['link'] ?> / <?= $item['board']['link'] ?> / <?= $item['link'] ?></strong>
					</h5>
					<span class="smalltext"><?= str_replace('<br>', ' ', Lang::getTxt('last_post_topic', ['post_link' => $item['time'], 'member_link' => '<strong>' . $item['poster']['link'] . '</strong>'], file: 'General')) ?></span>
				</div>
				<div class="list_posts">
					<div class="post"><?= Utils::adjustHeadingLevels($item['body'], 5) ?></div>
				</div>
				<?php $this->subTemplate('quickbuttons', ['list_items' => $quickbuttons, 'list_class' => 'unapproved_posts']); ?>
			</div><!-- .windowbg -->
<?php endforeach; ?>
			<div class="pagesection">
<?php if (!empty(Utils::$context['unapproved_items'])): ?>
				<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
<?php endif; ?>
<?php if (!empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1): ?>
				<div class="floatright">
					<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm('<?= Lang::getTxt('mc_unapproved_sure', file: 'ModerationCenter') ?>')) submit();">
						<option value="0"><?= Lang::getTxt('with_selected', file: 'ModerationCenter') ?>:</option>
						<option value="0" disabled>-------------------</option>
						<option value="approve">&nbsp;--&nbsp;<?= Lang::getTxt('approve', file: 'General') ?></option>
						<option value="delete">&nbsp;--&nbsp;<?= Lang::getTxt('delete', file: 'General') ?></option>
					</select>
					<noscript>
						<input type="submit" name="mc_go" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button">
					</noscript>
				</div>
<?php endif; ?>
			</div><!-- .pagesection -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form>
	</div><!-- #modcenter -->