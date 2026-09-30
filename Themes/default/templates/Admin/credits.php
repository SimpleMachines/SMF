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
 * Show some support information and credits to those who helped make this.
 */
?><?php /* Show the user version information from their server. */ ?>
					<div class="roundframe noup">
						<div class="title_bar">
							<h3 class="titlebg">
								<?= Lang::getTxt('support_title', file: 'Admin') ?>
							</h3>
						</div>
						<div class="padding">
							<img src="<?= Theme::$current->settings['images_url'] ?>/smflogo.svg" class="floatright" alt="">
							<strong><?= Lang::getTxt('support_versions', file: 'Admin') ?></strong>
							<ul>
								<li><?= Lang::getTxt('support_versions_forum', ['version' => Utils::$context['forum_version']], file: 'Admin') ?><?= Utils::$context['can_admin'] ? ' <a href="' . Config::$scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . Lang::getTxt('version_check_more', file: 'Admin') . '</a>' : '' ?></li>
								<li><?= Lang::getTxt('support_versions_current', ['version' => '??'], file: 'Admin') ?></li>
							</ul><?php /* Display all the variables we have server information for. */ ?><?php foreach (Utils::$context['current_versions'] as $version): ?>
								<?= $version['title'] ?>:
							<em><?= $version['version'] ?></em><?php /* more details for this item, show them a link */ ?><?php if (Utils::$context['can_admin'] && isset($version['more'])): ?> <a href="<?= Config::$scripturl ?><?= $version['more'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('version_check_more', file: 'Admin') ?></a><?php endif; ?>
							<br><?php endforeach; ?>
						</div><!-- .padding --><?php /* Point the admin to common support resources. */ ?>
						<div id="support_resources" class="title_bar">
							<h3 class="titlebg">
								<?= Lang::getTxt('support_resources', file: 'Admin') ?>
							</h3>
						</div>
						<div class="padding">
							<p><?= Lang::getTxt('support_resources_p1', file: 'Admin') ?></p>
							<p><?= Lang::getTxt('support_resources_p2', file: 'Admin') ?></p>
						</div><?php /* The most important part - the credits :P. */ ?>
						<div id="credits_sections" class="title_bar">
							<h3 class="titlebg">
								<?= Lang::getTxt('admin_credits', file: 'Admin') ?>
							</h3>
						</div>
						<div id="support_credits_list" class="padding"><?php foreach (Utils::$context['credits'] as $section): ?><?php if (isset($section['pretext'])): ?>
							<p><?= $section['pretext'] ?></p>
							<hr><?php endif; ?>
							<dl><?php foreach ($section['groups'] as $group): ?><?php if (isset($group['title'])): ?>
								<dt>
									<strong><?= $group['title'] ?></strong>
								</dt><?php endif; ?>
								<dd><?= implode(', ', $group['members']) ?></dd><?php endforeach; ?>
							</dl><?php if (isset($section['posttext'])): ?>
							<hr>
							<p><?= $section['posttext'] ?></p><?php endif; ?><?php endforeach; ?>
						</div><!-- .padding -->
					</div><!-- #support_credits --><?php /* This makes all the support information available to the support script... */ ?>
					<script>
						var smfSupportVersions = {};

						smfSupportVersions.forum = "<?= Utils::$context['forum_version'] ?>";<?php /* Don't worry, none of this is logged, it's just used to give information that might be of use. */ ?><?php foreach (Utils::$context['current_versions'] as $variable => $version): ?>

						smfSupportVersions.<?= $variable ?> = "<?= $version['version'] ?>";<?php endforeach; ?><?php /* Now we just have to include the script and wait ;). */ ?>

					</script>
					<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=current-version.js"></script>
					<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=latest-news.js"></script><?php /* This sets the latest support stuff. */ ?>
					<script>
						function smfCurrentVersion()
						{
							var smfVer, yourVer;

							if (!window.smfVersion)
								return;

							smfVer = document.getElementById("smfVersion");
							yourVer = document.getElementById("yourVersion");

							setInnerHTML(smfVer, window.smfVersion);

							var currentVersion = getInnerHTML(yourVer);
							if (currentVersion != window.smfVersion)
								setInnerHTML(yourVer, "<span class=\"alert\">" + currentVersion + "</span>");
						}
						addLoadEvent(smfCurrentVersion)
					</script>