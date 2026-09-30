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
 * This is the administration center home.
 */
?>

<?php /* Is there an update available? */ ?>
						<div id="update_section"></div>
						<div id="admin_main_section">
<?php /* Display the "live news" from simplemachines.org. */ ?>
							<div id="live_news" class="floatleft">
								<div class="cat_bar">
									<h3 class="catbg">
										<a href="<?= Config::$scripturl ?>?action=helpadmin;help=live_news" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?= Lang::getTxt('live', file: 'Admin') ?>
									</h3>
								</div>
								<div class="windowbg nopadding">
									<div id="smfAnnouncements"><?= Lang::getTxt('smf_news_cant_connect', file: 'Admin') ?></div>
								</div>
							</div>
<?php /* Show the user version information from their server. */ ?>
							<div id="support_info" class="floatright">
								<div class="cat_bar">
									<h3 class="catbg">
										<a href="<?= Config::$scripturl ?>?action=admin;area=credits"><?= Lang::getTxt('support_title', file: 'Admin') ?></a>
									</h3>
								</div>
								<div class="windowbg nopadding">
									<div id="version_details" class="padding">
										<strong><?= Lang::getTxt('support_versions', file: 'Admin') ?></strong>
										<ul>
											<li><?= Lang::getTxt('support_versions_forum', ['version' => Utils::$context['forum_version']], file: 'Admin') ?></li>
											<li><?= Lang::getTxt('support_versions_current', ['version' => '??'], file: 'Admin') ?></li>
											<li><?= Utils::$context['can_admin'] ? '<a href="' . Config::$scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . Lang::getTxt('version_check_more', file: 'Admin') . '</a>' : '' ?></li>
										</ul>
<?php /* Display all the members who can administrate the forum. */ ?>
										<br>
										<?= Lang::getTxt('administrators', ['list' => Lang::sentenceList(Utils::$context['administrators'])], file: 'Admin') ?>

<?php /* If we have lots of admins... don't show them all. */ ?>
<?php if (!empty(Utils::$context['more_admins_link'])): ?>
										(<?= Utils::$context['more_admins_link'] ?>)
<?php endif; ?>
									</div><!-- #version_details -->
								</div><!-- .windowbg -->
							</div><!-- #support_info -->
						</div><!-- #admin_main_section -->
<?php foreach (Utils::$context[Utils::$context['admin_menu_name']]['sections'] as $area_id => $area): ?>
						<fieldset id="group_<?= $area_id ?>" class="windowbg admin_group">
							<legend><?= $area['title'] ?></legend>
<?php foreach ($area['areas'] as $item_id => $item): ?>
<?php /* No point showing the 'home' page here, we're already on it! */ ?>
<?php if ($area_id == 'forum' && $item_id == 'index'): ?>
<?php continue; ?>
<?php endif; ?>
<?php $url = $item['url'] ?? Config::$scripturl . '?action=admin;area=' . $item_id . (!empty(Utils::$context[Utils::$context['admin_menu_name']]['extra_parameters']) ? Utils::$context[Utils::$context['admin_menu_name']]['extra_parameters'] : ''); ?>
<?php if (!empty($item['icon_file'])): ?>
							<a href="<?= $url ?>" class="admin_group<?= !empty($item['inactive']) ? ' inactive' : '' ?>"><img class="large_admin_menu_icon_file" src="<?= $item['icon_file'] ?>" alt=""><?= $item['label'] ?></a>
<?php else: ?>
							<a href="<?= $url ?>"><span class="large_<?= $item['icon_class'] ?><?= !empty($item['inactive']) ? ' inactive' : '' ?>"></span><?= $item['label'] ?></a>
<?php endif; ?>
<?php endforeach; ?>
						</fieldset>
<?php endforeach; ?>
<?php /* The below functions include all the scripts needed from the simplemachines.org site. The language and format are passed for internationalization. */ ?>
<?php if (empty(Config::$modSettings['disable_smf_js'])): ?>
					<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=current-version.js"></script>
					<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=latest-news.js"></script>
<?php endif; ?>
<?php /* This sets the announcements and current versions themselves ;). */ ?>
					<script>
						var oAdminIndex = new smf_AdminIndex({

							bLoadAnnouncements: true,
							sAnnouncementTemplate: <?= Utils::escapeJavaScript('
								<dl>
									%content%
								</dl>
							') ?>,
							sAnnouncementMessageTemplate: <?= Utils::escapeJavaScript('
								<dt><a href="%href%">%subject%</a>%time%</dt>
								<dd>
									%message%
								</dd>
							') ?>,
							sAnnouncementContainerId: 'smfAnnouncements',

							bLoadVersions: true,
							sSmfVersionContainerId: 'smfVersion',
							sYourVersionContainerId: 'yourVersion',
							sVersionOutdatedTemplate: <?= Utils::escapeJavaScript('
								<span class="alert">%currentVersion%</span>
							') ?>,

							bLoadUpdateNotification: true,
							sUpdateNotificationContainerId: 'update_section',
							sUpdateNotificationDefaultTitle: <?= Utils::escapeJavaScript(Lang::getTxt('update_available', file: 'Admin')) ?>,
							sUpdateNotificationDefaultMessage: <?= Utils::escapeJavaScript(Lang::getTxt('update_message', file: 'Admin')) ?>,
							sUpdateNotificationTemplate: <?= Utils::escapeJavaScript('
								<h3 id="update_title">
									%title%
								</h3>
								<div id="update_message" class="smalltext">
									%message%
								</div>
							') ?>,
							sUpdateNotificationLink: smf_scripturl + <?= Utils::escapeJavaScript('?action=admin;area=packages;pgdownload;auto;package=%package%;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']) ?>

						});
					</script>