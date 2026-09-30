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
 * All the XML for previewing a PM
 */
?><?php /* @todo something could be removed...otherwise it can be merged again with template_post */ ?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<preview>
		<subject><![CDATA[<?= Lang::getTxt('preview_subject', ['subject' => !empty(Utils::$context['preview_subject']) ? Utils::$context['preview_subject'] : Lang::getTxt('no_subject', file: 'General')]) ?>]]></subject>
		<body><![CDATA[<?= Utils::$context['preview_message'] ?>]]></body>
	</preview>
	<errors serious="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1' ?>"><?php if (!empty(Utils::$context['post_error']['messages'])): ?><?php foreach (Utils::$context['post_error']['messages'] as $message): ?>
		<error><![CDATA[<?= Utils::cleanXml($message) ?>]]></error><?php endforeach; ?><?php endif; ?>
		<caption name="to" class="<?= isset(Utils::$context['post_error']['no_to']) ? 'error' : '' ?>" />
		<caption name="bbc" class="<?= isset(Utils::$context['post_error']['no_bbc']) ? 'error' : '' ?>" />
		<caption name="subject" class="<?= isset(Utils::$context['post_error']['no_subject']) ? 'error' : '' ?>" />
		<caption name="question" class="<?= isset(Utils::$context['post_error']['no_question']) ? 'error' : '' ?>" /><?= isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? '
		<post_error />' : '' ?>
	</errors>
</smf>