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
 * The XML for displaying a column of message icons and selecting one via AJAX
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf><?php foreach (Utils::$context['icons'] as $icon): ?>
	<icon value="<?= $icon['value'] ?>" url="<?= $icon['url'] ?>"><![CDATA[<?= Utils::cleanXml($icon['name']) ?>]]></icon><?php endforeach; ?>

</smf>