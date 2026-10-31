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
 * XML for search results
 */
?><?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<smf><?php if (empty(Utils::$context['topics'])): ?>
		<noresults><?= Lang::getTxt('search_no_results', file: 'General') ?></noresults><?php else: ?>
		<results><?php while ($topic = Utils::$context['get_topics']()): ?>
			<result>
				<id><?= $topic['id'] ?></id>
				<relevance><?= $topic['relevance'] ?></relevance>
				<board>
					<id><?= $topic['board']['id'] ?></id>
					<name><?= Utils::cleanXml($topic['board']['name']) ?></name>
					<href><?= $topic['board']['href'] ?></href>
				</board>
				<category>
					<id><?= $topic['category']['id'] ?></id>
					<name><?= Utils::cleanXml($topic['category']['name']) ?></name>
					<href><?= $topic['category']['href'] ?></href>
				</category>
				<messages><?php foreach ($topic['matches'] as $message): ?>
					<message>
						<id><?= $message['id'] ?></id>
						<subject><![CDATA[<?= Utils::cleanXml($message['subject_highlighted'] != '' ? $message['subject_highlighted'] : $message['subject']) ?>]]></subject>
						<body><![CDATA[<?= Utils::cleanXml($message['body_highlighted'] != '' ? $message['body_highlighted'] : $message['body']) ?>]]></body>
						<time><?= $message['time'] ?></time>
						<timestamp><?= $message['timestamp'] ?></timestamp>
						<start><?= $message['start'] ?></start>

						<author>
							<id><?= $message['member']['id'] ?></id>
							<name><?= Utils::cleanXml($message['member']['name']) ?></name>
							<href><?= $message['member']['href'] ?></href>
						</author>
					</message><?php endforeach; ?>
				</messages>
			</result><?php endwhile; ?>
		</results><?php endif; ?>

</smf>