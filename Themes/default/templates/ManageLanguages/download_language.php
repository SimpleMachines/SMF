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
 * Download a new language file.
 */
?><?php /* Actually finished? */ ?><?php if (!empty(Utils::$context['install_complete'])): ?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt('languages_download_complete', file: 'ManageSettings') ?>
			</h3>
		</div>
		<div class="windowbg">
			<?= Utils::$context['install_complete'] ?>
		</div><?php return; ?><?php endif; ?><?php /* An error? */ ?><?php if (!empty(Utils::$context['error_message'])): ?>
	<div class="errorbox">
		<?= Utils::$context['error_message'] ?>
	</div><?php endif; ?><?php /* Provide something of an introduction... */ ?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=languages;sa=downloadlang;did=<?= Utils::$context['download_id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('languages_download', file: 'ManageSettings') ?>
				</h3>
			</div>
			<div class="windowbg">
				<p>
					<?= Lang::getTxt('languages_download_note', file: 'ManageSettings') ?>
				</p>
				<div class="smalltext">
					<?= Lang::getTxt('languages_download_info', file: 'ManageSettings') ?>
				</div>
			</div><?php /* Show the main files. */ ?><?php $this->subTemplate('show_list', ['list_id' => 'lang_main_files_list']); ?><?php
// Do we want some FTP baby?
// If the files are not writable, we might!
?><?php if (!empty(Utils::$context['still_not_writable'])): ?><?php if (!empty(Utils::$context['package_ftp']['error'])): ?>
			<div class="errorbox">
				<?= Utils::$context['package_ftp']['error'] ?>
			</div><?php endif; ?>
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('package_ftp_necessary', file: 'Packages') ?>
				</h3>
			</div>
			<div class="windowbg">
				<p><?= Lang::getTxt('package_ftp_why', file: 'Packages') ?></p>
				<dl class="settings">
					<dt
						<label for="ftp_server"><?= Lang::getTxt('package_ftp_server', file: 'Packages') ?></label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port">
								<?= Lang::getTxt('package_ftp_port', file: 'Packages') ?>
							</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="<?= Utils::$context['package_ftp']['port'] ?? (Config::$modSettings['package_port'] ?? '21') ?>">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="<?= Utils::$context['package_ftp']['server'] ?? (Config::$modSettings['package_server'] ?? 'localhost') ?>" style="width: 70%;">
					</dd>

					<dt>
						<label for="ftp_username"><?= Lang::getTxt('package_ftp_username', file: 'Packages') ?></label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="<?= Utils::$context['package_ftp']['username'] ?? (Config::$modSettings['package_username'] ?? '') ?>">
					</dd>

					<dt>
						<label for="ftp_password"><?= Lang::getTxt('package_ftp_password', file: 'Packages') ?></label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password">
					</dd>

					<dt>
						<label for="ftp_path"><?= Lang::getTxt('package_ftp_path', file: 'Packages') ?></label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="<?= Utils::$context['package_ftp']['path'] ?>">
					</dd>
				</dl>
			</div><!-- .windowbg --><?php endif; ?><?php /* Install? */ ?>
			<div class="righttext padding">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-dlang_token_var'] ?>" value="<?= Utils::$context['admin-dlang_token'] ?>">
				<input type="submit" name="do_install" value="<?= Lang::getTxt('add_language_smf_install', file: 'ManageSettings') ?>" class="button">
			</div>
		</form>