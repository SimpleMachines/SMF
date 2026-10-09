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
 * List all packages
 */
?>
		<div id="update_section"></div>
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('packages_adding_title', file: 'Packages') ?>
				</h3>
			</div>
			<div class="information">
				<?= Lang::getTxt('packages_adding', file: 'Packages') ?>
			</div>

			<script>
				window.smfForum_scripturl = smf_scripturl;
				window.smfForum_sessionid = smf_session_id;
				window.smfForum_sessionvar = smf_session_var;<?php /* Make a list of already installed mods so nothing is listed twice ;). */ ?>

				window.smfInstalledPackages = ["<?= implode('", "', Utils::$context['installed_mods']) ?>"];
				window.smfVersion = "<?= Utils::$context['forum_version'] ?>";
			</script>
			<div id="yourVersion" style="display:none"><?= Utils::$context['forum_version'] ?></div><?php if (empty(Config::$modSettings['disable_smf_js'])): ?>
			<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=latest-news.js"></script><?php endif; ?><?php /* This sets the announcements and current versions themselves ;). */ ?>
			<script>
				var oAdminIndex = new smf_AdminIndex({
					bLoadAnnouncements: false,
					bLoadVersions: false,
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
		</div><!-- #admin_form_wrapper --><?php if (Utils::$context['available_packages'] == 0): ?>
		<div class="noticebox"><?= Lang::getTxt('no_packages', file: 'Packages') ?></div><?php else: ?><?php foreach (Utils::$context['modification_types'] as $type): ?><?php if (!empty(Utils::$context['packages_lists_' . $type]['rows'])): ?><?php $this->subTemplate('show_list', ['list_id' => 'packages_lists_' . $type]); ?><?php endif; ?><?php endforeach; ?>
		<br><?php endif; ?><?php /* The advanced (emulation) box, collapsed by default */ ?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=packages;sa=browse" method="get">
			<div id="advanced_box">
				<div class="cat_bar">
					<h3 class="catbg">
						<a href="#" id="advanced_panel_link"><?= Lang::getTxt('package_advanced_button', file: 'Packages') ?></a>
					</h3>
					<span id="advanced_panel_toggle" class="toggle_down" style="display: none;"></span>
				</div>
				<div id="advanced_panel_div" class="windowbg">
					<p>
						<?= Lang::getTxt('package_emulate_desc', file: 'Packages') ?>
					</p>
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('package_emulate', file: 'Packages') ?></strong><br>
							<span class="smalltext">
								<a href="#" onclick="return revert();"><?= Lang::getTxt('package_emulate_revert', file: 'Packages') ?></a>
							</span>
						</dt>
						<dd>
							<a id="revert" name="revert"></a>
							<select name="version_emulate" id="ve"><?php foreach (Utils::$context['emulation_versions'] as $version): ?>
								<option value="<?= $version ?>"<?= ($version == Utils::$context['selected_version'] ? ' selected="selected"' : '') ?>><?= $version ?></option><?php endforeach; ?>
							</select>
						</dd>
					</dl>
					<div class="righttext padding">
						<input type="submit" value="<?= Lang::getTxt('package_apply', file: 'Packages') ?>" class="button">
					</div>
				</div><!-- #advanced_panel_div -->
			</div><!-- #advanced_box -->
			<input type="hidden" name="action" value="admin">
			<input type="hidden" name="area" value="packages">
			<input type="hidden" name="sa" value="browse">
		</form>
	<script>
		var oAdvancedPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				'advanced_panel_div'
			],
			aSwapImages: [
				{
					sId: 'advanced_panel_toggle',
					altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
					altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

				}
			],
			aSwapLinks: [
				{
					sId: 'advanced_panel_link',
					msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('package_advanced_button', file: 'Packages')) ?>,
					msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('package_advanced_button', file: 'Packages')) ?>

				}
			]
		});
		function revert()
		{
			var default_version = "<?= Utils::$context['default_version'] ?>";
			$("#ve").find("option").filter(function(index) {
				return default_version === $(this).text();
			}).attr("selected", "selected");
			return false;
		}
	</script>