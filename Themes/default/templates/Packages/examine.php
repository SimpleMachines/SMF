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
 * Examine a single file within a package
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_examine_file', file: 'Packages') ?></h3>
		</div>
		<div class="title_bar">
			<h4 class="titlebg"><?= Lang::getTxt('package_file_contents', Utils::$context, file: 'Packages') ?></h4>
		</div>
		<div class="windowbg">
			<pre class="file_content"><?= Utils::$context['filedata'] ?></pre>
			<a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=list;package=<?= Utils::$context['package'] ?>" class="button floatnone"><?= Lang::getTxt('list_files', file: 'Packages') ?></a>
		</div>