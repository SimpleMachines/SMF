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
 * The XML for the Jump To box
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf><?php foreach (Utils::$context['jump_to'] as $category): ?>

	<item type="category" id="<?= $category['id'] ?>"><![CDATA[<?= Utils::cleanXml($category['name']) ?>]]></item><?php foreach ($category['boards'] as $board): ?>

	<item type="board" id="<?= $board['id'] ?>" childlevel="<?= $board['child_level'] ?>" is_redirect="<?= (int) !empty($board['redirect']) ?>"><![CDATA[<?= Utils::cleanXml($board['name']) ?>]]></item><?php endforeach; ?><?php endforeach; ?>

</smf>