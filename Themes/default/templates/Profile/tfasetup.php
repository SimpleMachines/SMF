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
 * Template for setting up and managing Two-Factor Authentication.
 */
?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('tfa_title', file: 'Profile') ?></h3>
			</div>
			<div class="roundframe">
				<div>
<?php if (!empty(Utils::$context['tfa_backup'])): ?>
					<div class="smalltext error">
						<?= Lang::getTxt('tfa_backup_used_desc', file: 'Profile') ?>
					</div>
<?php elseif (Config::$modSettings['tfa_mode'] == 2): ?>
					<div class="smalltext">
						<strong><?= Lang::getTxt('tfa_forced_desc', file: 'Profile') ?></strong>
					</div>
<?php endif; ?>
					<div class="smalltext">
						<?= Lang::getTxt('tfa_desc', file: 'Profile') ?>
					</div>
					<div class="floatleft">
						<form action="<?= Config::$scripturl ?>?action=profile;area=tfasetup" method="post">
							<div class="block">
								<strong><?= Lang::getTxt('tfa_step1', file: 'Profile') ?></strong><br>
<?php if (!empty(Utils::$context['tfa_pass_error'])): ?>
								<div class="error smalltext">
									<?= Lang::getTxt('tfa_pass_invalid', file: 'Profile') ?>
								</div>
<?php endif; ?>
								<input type="password" name="oldpasswrd" size="25"<?= !empty(Utils::$context['password_auth_failed']) ? ' class="error"' : '' ?><?= !empty(Utils::$context['tfa_pass_value']) ? ' value="' . Utils::$context['tfa_pass_value'] . '"' : '' ?>>
							</div>
							<div class="block">
								<strong><?= Lang::getTxt('tfa_step2', file: 'Profile') ?></strong>
								<div class="smalltext"><?= Lang::getTxt('tfa_step2_desc', file: 'Profile') ?></div>
								<div class="tfacode"><?= Utils::$context['tfa_secret'] ?></div>
							</div>
							<div class="block">
								<strong><?= Lang::getTxt('tfa_step3', file: 'Profile') ?></strong><br>
<?php if (!empty(Utils::$context['tfa_error'])): ?>
								<div class="error smalltext">
									<?= Lang::getTxt('tfa_code_invalid', file: 'Profile') ?>
								</div>
<?php endif; ?>
								<input type="text" name="tfa_code" size="25"<?= !empty(Utils::$context['tfa_error']) ? ' class="error"' : '' ?><?= !empty(Utils::$context['tfa_value']) ? ' value="' . Utils::$context['tfa_value'] . '"' : '' ?>>
								<input type="submit" name="save" value="<?= Lang::getTxt('tfa_enable', file: 'Profile') ?>" class="button">
							</div>
							<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						</form>
					</div>
					<div class="floatright tfa_qrcode">
						<div id="qrcode"></div>
						<script type="text/javascript">
							new QRCode(document.getElementById("qrcode"), "<?= Utils::$context['tfa_qr_url'] ?>");
						</script>
					</div>
<?php if (!empty(Utils::$context['from_ajax'])): ?>
					<br>
					<a href="javascript:self.close();"></a>
<?php endif; ?>
				</div>
			</div><!-- .roundframe -->