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
 * Template for picking a theme
 */
?>
							<dt>
								<strong><?= Lang::getTxt('current_theme', file: 'Profile') ?></strong>
							</dt>
							<dd>
								<?= Utils::$context['member']['theme']['name'] ?> <a class="button" href="<?= Config::$scripturl ?>?action=themechooser;u=<?= Utils::$context['id_member'] ?>"><?= Lang::getTxt('change', file: 'Profile') ?></a>
							</dd>