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
 * This handles things when editing a topic's subject from the messageindex.
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<message id="msg_<?= Utils::$context['message']['id'] ?>"><?php if (empty(Utils::$context['message']['errors'])): ?><?php
// Build our string of info about when and why it was modified
$modified = empty(Utils::$context['message']['modified']['time']) ? '' : Lang::getTxt('last_edit_by', ['time' => Utils::$context['message']['modified']['time'], 'member' => Utils::$context['message']['modified']['name']], file: 'General');
$modified .= empty(Utils::$context['message']['modified']['reason']) ? '' : Lang::getTxt('last_edit_reason', ['reason' => Utils::$context['message']['modified']['reason']], file: 'General');
?>
		<modified><![CDATA[<?= empty($modified) ? '' : Utils::cleanXml('<em>' . $modified . '</em>') ?>]]></modified><?php if (!empty(Utils::$context['message']['subject'])): ?>
		<subject><![CDATA[<?= Utils::cleanXml(Utils::$context['message']['subject']) ?>]]></subject><?php endif; ?><?php else: ?>
		<error in_subject="<?= Utils::$context['message']['error_in_subject'] ? '1' : '0' ?>"><![CDATA[<?= Utils::cleanXml(implode('<br />', Utils::$context['message']['errors'])) ?>]]></error><?php endif; ?>
	</message>
</smf>