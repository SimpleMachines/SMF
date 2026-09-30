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
 * The XML for selecting items to split
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<pageIndex section="not_selected" startFrom="<?= Utils::$context['not_selected']['start'] ?>"><![CDATA[<?= Utils::$context['not_selected']['page_index'] ?>]]></pageIndex>
	<pageIndex section="selected" startFrom="<?= Utils::$context['selected']['start'] ?>"><![CDATA[<?= Utils::$context['selected']['page_index'] ?>]]></pageIndex><?php foreach (Utils::$context['changes'] as $change): ?><?php if ($change['type'] == 'remove'): ?>

	<change id="<?= $change['id'] ?>" curAction="remove" section="<?= $change['section'] ?>" /><?php else: ?>

	<change id="<?= $change['id'] ?>" curAction="insert" section="<?= $change['section'] ?>">
		<subject><![CDATA[<?= Utils::cleanXml($change['insert_value']['subject']) ?>]]></subject>
		<time><![CDATA[<?= Utils::cleanXml($change['insert_value']['time']) ?>]]></time>
		<body><![CDATA[<?= Utils::cleanXml($change['insert_value']['body']) ?>]]></body>
		<poster><![CDATA[<?= Utils::cleanXml($change['insert_value']['poster']) ?>]]></poster>
	</change><?php endif; ?><?php endforeach; ?>

</smf>