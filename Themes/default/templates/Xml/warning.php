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
 * The XML for previewing a warning
 */
?><?php /* @todo something could be removed...otherwise it can be merged again with template_post */ ?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<preview>
		<subject><![CDATA[<?= Utils::$context['preview_subject'] ?>]]></subject>
		<body><![CDATA[<?= Utils::$context['preview_message'] ?>]]></body>
	</preview>
	<errors serious="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1' ?>"><?php if (!empty(Utils::$context['post_error']['messages'])): ?><?php foreach (Utils::$context['post_error']['messages'] as $message): ?>

		<error><![CDATA[<?= Utils::cleanXml($message) ?>]]></error><?php endforeach; ?><?php endif; ?>

	</errors>
</smf>