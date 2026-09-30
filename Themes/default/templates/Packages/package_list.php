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
 * List packages.
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
		</div>
		<div class="windowbg"><?php /* No packages, as yet. */ ?><?php if (empty(Utils::$context['package_list'])): ?>

			<ul>
				<li><?= Lang::getTxt('no_packages', file: 'Packages') ?></li>
			</ul><?php /* List out the packages... */ ?><?php else: ?>

			<ul id="package_list"><?php foreach (Utils::$context['package_list'] as $i => $packageSection): ?>

				<li>
					<strong><span id="ps_img_<?= $i ?>" class="toggle_up" alt="*" style="display: none;"></span> <?= $packageSection['title'] ?></strong><?php if (!empty($packageSection['text'])): ?>

					<div class="sub_bar">
						<h3 class="subbg"><?= $packageSection['text'] ?></h3>
					</div><?php endif; ?>

					<<?= Utils::$context['list_type'] ?> id="package_section_<?= $i ?>" class="packages"><?php foreach ($packageSection['items'] as $id => $package): ?>

						<li><?php /* Textual message. Could be empty just for a blank line... */ ?><?php if ($package['is_text']): ?>

							<?= empty($package['name']) ? '&nbsp;' : $package['name'] ?><?php /* This is supposed to be a rule.. */ ?><?php elseif ($package['is_line']): ?>

							<hr><?php /* A remote link. */ ?><?php elseif ($package['is_remote']): ?>

							<strong><?= $package['link'] ?></strong><?php /* A title? */ ?><?php elseif ($package['is_heading'] || $package['is_title']): ?>

							<strong><?= $package['name'] ?></strong><?php /* Otherwise, it's a package. */ ?><?php else: ?><?php /* 1. Some mod [ Download ]. */ ?>

						<strong><span id="ps_img_<?= $i ?>_pkg_<?= $id ?>" class="toggle_up" alt="*" style="display: none;"></span> <?= $package['can_install'] || !empty($package['can_emulate_install']) ? '<strong>' . $package['name'] . '</strong> <a href="' . $package['download']['href'] . '" class="button floatnone">' . Lang::getTxt('download', file: 'Packages') . '</a>' : $package['name'] ?></strong>
						<ul id="package_section_<?= $i ?>_pkg_<?= $id ?>" class="package_section"><?php /* Show the mod type? */ ?><?php if ($package['type'] != ''): ?>

							<li class="package_section">
								<?= Lang::getTxt('package_type', ['type' => Utils::ucwords(Utils::strtolower($package['type']))], file: 'Packages') ?>

							</li><?php endif; ?><?php /* Show the version number? */ ?><?php if ($package['version'] != ''): ?>

							<li class="package_section">
								<?= Lang::getTxt('package_version', $package, file: 'Packages') ?>

							</li><?php endif; ?><?php /* How 'bout the author? */ ?><?php if (!empty($package['author']) && $package['author']['name'] != '' && isset($package['author']['link'])): ?>

							<li class="package_section">
								<?= Lang::getTxt('package_author', ['author' => $package['author']['link']], file: 'Packages') ?>

							</li><?php endif; ?><?php /* The homepage... */ ?><?php if ($package['author']['website']['link'] != ''): ?>

							<li class="package_section">
								<?= Lang::getTxt('author_website', $package['author']['website'], file: 'Packages') ?>

							</li><?php endif; ?><?php
// Description: bleh bleh!
// Location of file: http://someplace/.
?>

							<li class="package_section">
								<?= Lang::getTxt('file_location', ['link' => '<a href="' . $package['href'] . '">' . $package['href'] . '</a>'], file: 'Packages') ?>

							</li>
							<li class="package_section">
								<?= Lang::getTxt('package_description', $package, file: 'Packages') ?>

							</li>
						</ul><?php endif; ?>

					</li><?php endforeach; ?>

				</<?= Utils::$context['list_type'] ?>>
				</li><?php endforeach; ?>

			</ul><?php endif; ?>

		</div><!-- .windowbg --><?php /* Now go through and turn off all the sections. */ ?><?php if (!empty(Utils::$context['package_list'])): ?><?php $section_count = count(Utils::$context['package_list']); ?>

	<script><?php foreach (Utils::$context['package_list'] as $section => $ps): ?>

		var oPackageServerToggle_<?= $section ?> = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: <?= count($ps['items']) == 1 || $section_count == 1 ? 'false' : 'true' ?>,
			aSwappableContainers: [
				'package_section_<?= $section ?>'
			],
			aSwapImages: [
				{
					sId: 'ps_img_<?= $section ?>',
					altExpanded: '*',
					altCollapsed: '*'
				}
			]
		});<?php foreach ($ps['items'] as $id => $package): ?><?php if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote']): ?>

		var oPackageToggle_<?= $section ?>_pkg_<?= $id ?> = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				'package_section_<?= $section ?>_pkg_<?= $id ?>'
			],
			aSwapImages: [
				{
					sId: 'ps_img_<?= $section ?>_pkg_<?= $id ?>',
					altExpanded: '*',
					altCollapsed: '*'
				}
			]
		});<?php endif; ?><?php endforeach; ?><?php endforeach; ?>

	</script><?php endif; ?>
