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
 * Installation options - FTP info and backup settings
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>

	<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_install_options', file: 'Packages') ?></h3>
		</div>
		<div class="information noup">
			<?= Lang::getTxt('package_install_options_ftp_why', file: 'Packages') ?>

		</div>
		<div class="windowbg noup">
			<form action="<?= Config::$scripturl ?>?action=admin;area=packages;sa=options" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt>
						<label for="pack_server"><strong><?= Lang::getTxt('package_install_options_ftp_server', file: 'Packages') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_server" id="pack_server" value="<?= Utils::$context['package_ftp_server'] ?>" size="30">
					</dd>
					<dt>
						<label for="pack_port"><strong><?= Lang::getTxt('package_install_options_ftp_port', file: 'Packages') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_port" id="pack_port" size="3" value="<?= Utils::$context['package_ftp_port'] ?>">
					</dd>
					<dt>
						<label for="pack_user"><strong><?= Lang::getTxt('package_install_options_ftp_user', file: 'Packages') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_user" id="pack_user" value="<?= Utils::$context['package_ftp_username'] ?>" size="30">
					</dd>
					<dt>
						<label for="package_make_backups"><?= Lang::getTxt('package_install_options_make_backups', file: 'Packages') ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_backups" id="package_make_backups" value="1"<?= Utils::$context['package_make_backups'] ? ' checked' : '' ?>>
					</dd>
					<dt>
						<label for="package_make_full_backups"><?= Lang::getTxt('package_install_options_make_full_backups', file: 'Packages') ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_full_backups" id="package_make_full_backups" value="1"<?= Utils::$context['package_make_full_backups'] ? ' checked' : '' ?>>
					</dd>
				</dl>

				<input type="submit" name="save" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
		</div><!-- .windowbg -->