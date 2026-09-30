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
 * Confirmation page showing a package was uploaded/downloaded successfully.
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
		</div>
		<div class="windowbg">
			<p>
				<?= Lang::getTxt(empty(Utils::$context['package_server']) ? 'package_uploaded_successfully' : 'package_downloaded_successfully', file: 'Packages') ?>

			</p>
			<ul>
				<li>
					<span class="floatleft"><strong><?= Utils::$context['package']['name'] ?></strong></span>
					<span class="package_server floatright"><?= Utils::$context['package']['list_files']['link'] ?></span>
					<span class="package_server floatright"><?= Utils::$context['package']['install']['link'] ?></span>
				</li>
			</ul>
			<br><br>
			<p><a href="<?= Config::$scripturl ?>?action=admin;area=packages;get<?= (isset(Utils::$context['package_server']) ? ';sa=browse;server=' . Utils::$context['package_server'] : '') ?>" class="button floatnone"><?= Lang::getTxt('back', file: 'General') ?></a></p>
		</div>