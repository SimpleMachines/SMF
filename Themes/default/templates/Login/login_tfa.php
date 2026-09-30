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
 * TFA authentication form
 */
?>

		<div class="login">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('tfa_profile_label', file: 'Profile') ?>

				</h3>
			</div>
			<div class="roundframe">
<?php if (!empty(Utils::$context['tfa_error']) || !empty(Utils::$context['tfa_backup_error'])): ?>
				<div class="error">
					<?= Lang::getTxt('tfa_' . (!empty(Utils::$context['tfa_error']) ? 'code_' : 'backup_') . 'invalid', file: 'Profile') ?>

				</div>
<?php endif; ?>
				<form action="<?= Utils::$context['tfa_url'] ?>" method="post" id="frmTfa">
					<div id="tfaCode">
						<p style="margin-bottom: 0.5em"><?= Lang::getTxt('tfa_login_desc', file: 'Profile') ?></p>
						<div class="centertext">
							<strong><?= Lang::getTxt('tfa_code', file: 'Profile') ?></strong>
							<input type="text" name="tfa_code" value="<?= !empty(Utils::$context['tfa_value']) ? Utils::$context['tfa_value'] : '' ?>">
							<input type="submit" class="button" name="submit" value="<?= Lang::getTxt('login', file: 'General') ?>">
						</div>
						<hr>
						<div class="centertext">
							<input type="button" class="button" name="backup" value="<?= Lang::getTxt('tfa_backup', file: 'Profile') ?>">
						</div>
					</div>
					<div id="tfaBackup" style="display: none;">
						<p style="margin-bottom: 0.5em"><?= Lang::getTxt('tfa_backup_desc', file: 'Profile') ?></p>
						<div class="centertext">
							<strong><?= Lang::getTxt('tfa_backup_code', file: 'Profile') ?></strong>
							<input type="text" name="tfa_backup" value="<?= !empty(Utils::$context['tfa_backup']) ? Utils::$context['tfa_backup'] : '' ?>">
							<input type="submit" class="button" name="submit" value="<?= Lang::getTxt('login', file: 'General') ?>">
						</div>
					</div>
				</form>
				<script>
					form = $("#frmTfa");
<?php if (!empty(Utils::$context['from_ajax'])): ?>
					form.submit(function(e) {
						// If we are submitting backup code, let normal workflow follow since it redirects a couple times into a different page
						if (form.find("input[name=tfa_backup]:first").val().length > 0)
							return true;

						e.preventDefault();
						e.stopPropagation();

						$.ajax({
							url: form.prop("action") + (form.prop("action").indexOf("?") !== -1 ? ";" : "?") + "ajax",
							method: "POST",
							headers: {
								"X-SMF-AJAX": 1
							},
							xhrFields: {
								withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
							},
							data: form.serialize(),
							success: function(data) {
								if (data.indexOf("<bo" + "dy") > -1) {
<?php if (empty(Utils::$context['valid_cors_found']) || Utils::$context['valid_cors_found'] == 'same'): ?>
									document.location = <?= Utils::escapeJavaScript(!empty($_SESSION['login_url']) ? $_SESSION['login_url'] : Config::$scripturl) ?>;
<?php else: ?>
									window.location.reload();
<?php endif; ?>
								}
								else {
									window.location.reload();
								}
							},
							error: function(xhr) {
								var data = xhr.responseText;
								if (data.indexOf("<bo" + "dy") > -1) {
									document.open();
									document.write(data);
									document.close();
								}
								else
									form.parent().html($(data).filter("#fatal_error").html());
							}
						});

						return false;
					});
<?php endif; ?>
					form.find("input[name=backup]").click(function(e) {
						$("#tfaBackup").show();
						$("#tfaCode").hide();
					});
				</script>
			</div><!-- .roundframe -->
		</div><!-- .login -->