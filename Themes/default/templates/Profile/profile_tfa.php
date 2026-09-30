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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Simple template for showing the 2FA area when editing a profile.
 */
?>
							<dt>
								<strong><?= Lang::getTxt('tfa_profile_label', file: 'Profile') ?></strong><br>
								<div class="smalltext"><?= Lang::getTxt('tfa_profile_desc', file: 'Profile') ?></div>
							</dt>
							<dd>
<?php if (!Utils::$context['tfa_enabled'] && Profile::$member->is_me): ?>
								<a href="<?= !empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl ?>?action=profile;area=tfasetup" id="enable_tfa"><?= Lang::getTxt('tfa_profile_enable', file: 'Profile') ?></a>
<?php elseif (!Utils::$context['tfa_enabled']): ?>
								<?= Lang::getTxt('tfa_profile_disabled', file: 'Profile') ?>

<?php else: ?>
								<?= Lang::getTxt('tfa_profile_enabled', ['url' => (!empty(Config::$modSettings['force_ssl']) ? strtr(Config::$scripturl, ['http://' => 'https://']) : Config::$scripturl) . '?action=profile;u=' . Utils::$context['id_member'] . ';area=tfadisable'], file: 'Profile') ?>

<?php endif; ?>
							</dd>