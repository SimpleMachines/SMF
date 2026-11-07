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
 * This defines the XML for the inline edit feature
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<subject><![CDATA[<?= Utils::cleanXml(Utils::$context['message']['subject']) ?>]]></subject>
	<message id="msg_<?= Utils::$context['message']['id'] ?>"><![CDATA[<?= Utils::cleanXml(Utils::$context['message']['body']) ?>]]></message>
	<reason time="<?= Utils::$context['message']['reason']['time'] ?>" name="<?= Utils::$context['message']['reason']['name'] ?>"><![CDATA[<?= Utils::cleanXml(Utils::$context['message']['reason']['text']) ?>]]></reason>
</smf>