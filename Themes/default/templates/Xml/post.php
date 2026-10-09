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
 * The massive XML for previewing posts.
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf>
	<preview>
		<subject><![CDATA[<?= Utils::$context['preview_subject'] ?>]]></subject>
		<body><![CDATA[<?= Utils::$context['preview_message'] ?>]]></body>
	</preview>
	<errors serious="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1' ?>" topic_locked="<?= Utils::$context['locked'] ? '1' : '0' ?>"><?php if (!empty(Utils::$context['post_error'])): ?><?php foreach (Utils::$context['post_error'] as $message): ?>
		<error><![CDATA[<?= Utils::cleanXml($message) ?>]]></error><?php endforeach; ?><?php endif; ?>
		<caption name="guestname" class="<?= isset(Utils::$context['post_error']['long_name']) || isset(Utils::$context['post_error']['no_name']) || isset(Utils::$context['post_error']['bad_name']) ? 'error' : '' ?>" />
		<caption name="email" class="<?= isset(Utils::$context['post_error']['no_email']) || isset(Utils::$context['post_error']['bad_email']) ? 'error' : '' ?>" />
		<caption name="evtitle" class="<?= isset(Utils::$context['post_error']['no_event']) ? 'error' : '' ?>" />
		<caption name="subject" class="<?= isset(Utils::$context['post_error']['no_subject']) ? 'error' : '' ?>" />
		<caption name="question" class="<?= isset(Utils::$context['post_error']['no_question']) ? 'error' : '' ?>" /><?= isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? '
		<post_error />' : '' ?>
	</errors>
	<last_msg><?= Utils::$context['topic_last_message'] ?? '0' ?></last_msg><?php if (!empty(Utils::$context['previous_posts'])): ?>
	<new_posts><?php foreach (Utils::$context['previous_posts'] as $post): ?>
		<post id="<?= $post['id'] ?>">
			<time><![CDATA[<?= $post['time'] ?>]]></time>
			<poster><![CDATA[<?= Utils::cleanXml($post['poster']) ?>]]></poster>
			<message><![CDATA[<?= Utils::cleanXml($post['message']) ?>]]></message>
			<is_ignored><?= $post['is_ignored'] ? '1' : '0' ?></is_ignored>
		</post><?php endforeach; ?>
	</new_posts><?php endif; ?>

</smf>