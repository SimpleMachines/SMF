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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The XML for instantly showing whether a username is valid on the registration page
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<username valid="<?= Utils::$context['valid_username'] ? 1 : 0 ?>"><?= Utils::cleanXml(Utils::$context['checked_username']) ?></username>
</smf>