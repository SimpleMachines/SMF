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
 * List files in a package
 */
?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('list_file', file: 'Packages') ?></h3>
		</div>
		<div class="title_bar">
			<h4 class="titlebg"><?= Lang::getTxt('files_package', Utils::$context, file: 'Packages') ?></h4>
		</div>
		<div class="windowbg">
			<ol>
<?php foreach (Utils::$context['files'] as $fileinfo): ?>
				<li><a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=examine;package=<?= Utils::$context['filename'] ?>;file=<?= $fileinfo['filename'] ?>" title="<?= Lang::getTxt('view', file: 'General') ?>"><?= $fileinfo['filename'] ?></a> <?= Lang::getTxt('package_bytes', $fileinfo, file: 'Packages') ?></li>
<?php endforeach; ?>
			</ol>
			<br>
			<a href="<?= Config::$scripturl ?>?action=admin;area=packages" class="button floatnone"><?= Lang::getTxt('back', file: 'General') ?></a>
		</div>