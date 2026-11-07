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
 * This contains the HTML for the menu bar at the top of the admin center.
 */
?><?php
// Which menu are we rendering?
Utils::$context['cur_menu_id'] = isset(Utils::$context['cur_menu_id']) ? Utils::$context['cur_menu_id'] + 1 : 1;
$menu_context = &Utils::$context['menu_data_' . Utils::$context['cur_menu_id']];
$menu_label = isset(Utils::$context['admin_menu_name']) ? Lang::getTxt('admin_center', file: 'General') : (isset(Utils::$context['moderation_menu_name']) ? Lang::getTxt('moderation_center', file: 'ModerationCenter') : '');
// Load the menu
// Add mobile menu as well
?>
	<nav id="genericmenu" aria-label="<?= Lang::getTxt('mobile_generic_menu', ['label' => $menu_label], file: 'General') ?>">
		<a class="mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>">
			<span class="menu_icon"></span>
			<span class="text_menu"><?= Lang::getTxt('mobile_generic_menu', ['label' => $menu_label], file: 'General') ?></span>
		</a>
		<div id="mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					<?= Lang::getTxt('mobile_generic_menu', ['label' => $menu_label], file: 'General') ?>
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<?php $this->subTemplate('generic_menu', ['menu_context' => $menu_context]); ?>
			</div>
		</div>
	</nav>
	<script>
		$( ".mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>" ).click(function() {
			$( "#mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>" ).show();
			});
		$( ".hide_popup" ).click(function() {
			$( "#mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>" ).hide();
		});
	</script><?php /* This is the main table - we need it so we can keep the content to the right of it. */ ?>
				<div id="admin_content"><?php
// It's possible that some pages have their own tabs they wanna force...
// 	if (!empty(Utils::$context['tabs']))
?><?php $this->subTemplate('generic_menu_tabs', ['menu_context' => $menu_context]); ?>
