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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The XML for handling things when you're done editing a post inline
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<message id="msg_<?= Utils::$context['message']['id'] ?>"><?php if (empty(Utils::$context['message']['errors'])): ?><?php
// Build our string of info about when and why it was modified
$modified = empty(Utils::$context['message']['modified']['time']) ? '' : Lang::getTxt('last_edit_by', ['time' => Utils::$context['message']['modified']['time'], 'member' => Utils::$context['message']['modified']['name']], file: 'General');
$modified .= empty(Utils::$context['message']['modified']['reason']) ? '' : ' ' . Lang::getTxt('last_edit_reason', ['reason' => Utils::$context['message']['modified']['reason']], file: 'General');
?>

		<modified><![CDATA[<?= empty($modified) ? '' : Utils::cleanXml($modified) ?>]]></modified>
		<subject is_first="<?= Utils::$context['message']['first_in_topic'] ? '1' : '0' ?>"><![CDATA[<?= Utils::cleanXml(Utils::$context['message']['subject']) ?>]]></subject>
		<body><![CDATA[<?= Utils::$context['message']['body'] ?>]]></body>
		<success><![CDATA[<?= Lang::getTxt('quick_modify_message', file: 'General') ?>]]></success><?php else: ?>

		<error in_subject="<?= Utils::$context['message']['error_in_subject'] ? '1' : '0' ?>" in_body="<?= Utils::cleanXml(Utils::$context['message']['error_in_body']) ? '1' : '0' ?>"><![CDATA[<?= implode('<br />', Utils::$context['message']['errors']) ?>]]></error><?php endif; ?>

	</message>
</smf>