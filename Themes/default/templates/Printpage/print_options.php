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
use SMF\Topic;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Displays the print page options
 */
?>

<?php
$url_text = Config::$scripturl . '?action=printpage;topic=' . Topic::$topic_id . '.0';
$url_images = $url_text . ';images';
?>
		<div class="print_options">
<?php /* Which option is set, text or text&images */ ?>
<?php if (isset($_REQUEST['images'])): ?>
			<a href="<?= $url_text ?>"><?= Lang::getTxt('print_page_text', file: 'General') ?></a> | <strong><a href="<?= $url_images ?>"><?= Lang::getTxt('print_page_images', file: 'General') ?></a></strong>
<?php else: ?>
			<strong><a href="<?= $url_text ?>"><?= Lang::getTxt('print_page_text', file: 'General') ?></a></strong> | <a href="<?= $url_images ?>"><?= Lang::getTxt('print_page_images', file: 'General') ?></a>
<?php endif; ?>
		</div><!-- .print_options -->