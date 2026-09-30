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
use SMF\Url;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * List package servers
 */
?><?php if (!empty(Utils::$context['package_ftp']['error'])): ?>

	<div class="errorbox">
		<pre><?= Utils::$context['package_ftp']['error'] ?></pre>
	</div><?php endif; ?>

	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_upload_title', file: 'Packages') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=packages;get;sa=upload" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('package_upload_select', file: 'Packages') ?></strong>
					</dt>
					<dd>
						<input type="file" name="package" size="38">
					</dd>
				</dl>
				<input type="submit" value="<?= Lang::getTxt('upload', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<a class="download_new_package">
					<span class="toggle_down floatright" alt="*" title="<?= Lang::getTxt('show', file: 'General') ?>"></span>
					<?= Lang::getTxt('download_new_package', file: 'Packages') ?>

				</a>
			</h3>
		</div>
		<div class="new_package_content"><?php if (Utils::$context['package_download_broken']): ?>

			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('package_ftp_necessary', file: 'Packages') ?></h3>
			</div>
			<div class="windowbg">
				<p>
					<?= Lang::getTxt('package_ftp_why_download', file: 'Packages') ?>

				</p>
				<form action="<?= Config::$scripturl ?>?action=admin;area=packages;get" method="post" accept-charset="UTF-8">
					<dl class="settings">
						<dt>
							<label for="ftp_server"><?= Lang::getTxt('package_ftp_server', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="<?= Utils::$context['package_ftp']['server'] ?>">
							<label for="ftp_port"><?= Lang::getTxt('package_ftp_port', file: 'Packages') ?></label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="<?= Utils::$context['package_ftp']['port'] ?>">
						</dd>
						<dt>
							<label for="ftp_username"><?= Lang::getTxt('package_ftp_username', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="<?= Utils::$context['package_ftp']['username'] ?>">
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
					<div class="righttext">
						<input type="submit" value="<?= Lang::getTxt('package_proceed', file: 'Packages') ?>" class="button">
					</div>
				</form>
			</div><!-- .windowbg --><?php endif; ?>

			<div class="windowbg">
				<fieldset>
					<legend><?= Lang::getTxt('package_servers', file: 'Packages') ?></legend>
					<ul class="package_servers"><?php foreach (Utils::$context['servers'] as $server): ?>

						<li class="flow_auto">
							<span class="floatleft"><?= $server['name'] ?></span>
							<span class="package_server floatright"><a href="<?= Config::$scripturl ?>?action=admin;area=packages;get;sa=browse;server=<?= $server['id'] ?>" class="button"><?= Lang::getTxt('package_browse', file: 'Packages') ?></a></span>
							<?= (!str_ends_with((new Url($server['url']))->host, '.simplemachines.org') ? '<span class="package_server floatright"><a href="' . Config::$scripturl . '?action=admin;area=packages;get;sa=remove;server=' . $server['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" class="button">' . Lang::getTxt('delete', file: 'General') . '</a></span>' : '') ?>

						</li><?php endforeach; ?>

					</ul>
				</fieldset>
				<fieldset>
					<legend><?= Lang::getTxt('add_server', file: 'Packages') ?></legend>
					<form action="<?= Config::$scripturl ?>?action=admin;area=packages;get;sa=add" method="post" accept-charset="UTF-8">
						<dl class="settings">
							<dt>
								<strong><?= Lang::getTxt('server_name', file: 'Packages') ?></strong>
							</dt>
							<dd>
								<input type="text" name="servername" size="44" value="SMF">
							</dd>
							<dt>
								<strong><?= Lang::getTxt('serverurl', file: 'Packages') ?></strong>
							</dt>
							<dd>
								<input type="text" name="serverurl" size="44" value="https://">
							</dd>
						</dl>
						<div class="righttext">
							<input type="submit" value="<?= Lang::getTxt('add_server', file: 'Packages') ?>" class="button">
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						</div>
					</form>
				</fieldset>
				<fieldset>
					<legend><?= Lang::getTxt('package_download_by_url', file: 'Packages') ?></legend>
					<form action="<?= Config::$scripturl ?>?action=admin;area=packages;get;sa=download;byurl;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
						<dl class="settings">
							<dt>
								<strong><?= Lang::getTxt('serverurl', file: 'Packages') ?></strong>
							</dt>
							<dd>
								<input type="text" name="package" size="44" value="https://">
							</dd>
							<dt>
								<strong><?= Lang::getTxt('package_download_filename', file: 'Packages') ?></strong>
							</dt>
							<dd>
								<input type="text" name="filename" size="44"><br>
								<span class="smalltext"><?= Lang::getTxt('package_download_filename_info', file: 'Packages') ?></span>
							</dd>
						</dl>
						<input type="submit" value="<?= Lang::getTxt('download', file: 'Packages') ?>" class="button">
					</form>
				</fieldset>
			</div><!-- .windowbg -->
		</div><!-- .new_package_content -->
	</div><!-- #admin_form_wrapper -->