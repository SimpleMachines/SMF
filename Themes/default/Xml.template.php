<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 *
 */
function template_sendbody()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message view="', $context['view'], '">', cleanXml($context['message']), '</message>
</smf>';
}

/**
 * This defines the XML for the AJAX quote feature
 */
function template_quotefast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<quote>', cleanXml($context['quote']['xml']), '</quote>
</smf>';
}

/**
 * This defines the XML for the inline edit feature
 */
function template_modifyfast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
	<message id="msg_', $context['message']['id'], '"><![CDATA[', cleanXml($context['message']['body']), ']]></message>
	<reason time="', $context['message']['reason']['time'], '" name="', $context['message']['reason']['name'], '"><![CDATA[', cleanXml($context['message']['reason']['text']), ']]></reason>
</smf>';

}

/**
 * The XML for handling things when you're done editing a post inline
 */
function template_modifydone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		// Build our string of info about when and why it was modified
		$modified = empty($context['message']['modified']['time']) ? '' : sprintf($txt['last_edit_by'], $context['message']['modified']['time'], $context['message']['modified']['name']);
		$modified .= empty($context['message']['modified']['reason']) ? '' : ' ' . sprintf($txt['last_edit_reason'], $context['message']['modified']['reason']);

		echo '
		<modified><![CDATA[', empty($modified) ? '' : cleanXml($modified), ']]></modified>
		<subject is_first="', $context['message']['first_in_topic'] ? '1' : '0', '"><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
		<body><![CDATA[', $context['message']['body'], ']]></body>
		<success><![CDATA[', $txt['quick_modify_message'], ']]></success>';
	}
	else
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '" in_body="', cleanXml($context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br />', $context['message']['errors']), ']]></error>';
	echo '
	</message>
</smf>';
}

/**
 * This handles things when editing a topic's subject from the messageindex.
 */
function template_modifytopicdone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		// Build our string of info about when and why it was modified
		$modified = empty($context['message']['modified']['time']) ? '' : sprintf($txt['last_edit_by'], $context['message']['modified']['time'], $context['message']['modified']['name']);
		$modified .= empty($context['message']['modified']['reason']) ? '' : sprintf($txt['last_edit_reason'], $context['message']['modified']['reason']);

		echo '
		<modified><![CDATA[', empty($modified) ? '' : cleanXml('&#171; <em>' . $modified . '</em>&#187;'), ']]></modified>';

		if (!empty($context['message']['subject']))
			echo '
		<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>';
	}
	else
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '"><![CDATA[', cleanXml(implode('<br />', $context['message']['errors'])), ']]></error>';
	echo '
	</message>
</smf>';
}

/**
 * The massive XML for previewing posts.
 */
function template_post()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';

	if (!empty($context['post_error']))
		foreach ($context['post_error'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';

	echo '
		<caption name="guestname" class="', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="evtitle" class="', isset($context['post_error']['no_event']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>
	<last_msg>', isset($context['topic_last_message']) ? $context['topic_last_message'] : '0', '</last_msg>';

	if (!empty($context['previous_posts']))
	{
		echo '
	<new_posts>';

		foreach ($context['previous_posts'] as $post)
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['time'], ']]></time>
			<poster><![CDATA[', cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', cleanXml($post['message']), ']]></message>
			<is_ignored>', $post['is_ignored'] ? '1' : '0', '</is_ignored>
		</post>';

		echo '
	</new_posts>';
	}

	echo '
</smf>';
}

/**
 * All the XML for previewing a PM
 */
function template_pm()
{
	global $context, $txt;

	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $txt['preview'], ' - ', !empty($context['preview_subject']) ? $context['preview_subject'] : $txt['no_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';

	echo '
		<caption name="to" class="', isset($context['post_error']['no_to']) ? 'error' : '', '" />
		<caption name="bbc" class="', isset($context['post_error']['no_bbc']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>';

	echo '
</smf>';
}

/**
 * The XML for previewing a warning
 */
function template_warning()
{
	global $context;

	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';

	echo '
	</errors>';

	echo '
</smf>';
}

/**
 * The XML for hiding/showing stats sections via AJAX
 */
function template_stats()
{
	global $context, $modSettings;

	if (empty($context['yearly']))
		return;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';
	foreach ($context['yearly'] as $year)
		foreach ($year['months'] as $month)
		{
			echo '
	<month id="', $month['date']['year'], $month['date']['month'], '">';

			foreach ($month['days'] as $day)
				echo '
		<day date="', $day['year'], '-', $day['month'], '-', $day['day'], '" new_topics="', $day['new_topics'], '" new_posts="', $day['new_posts'], '" new_members="', $day['new_members'], '" most_members_online="', $day['most_members_online'], '"', empty($modSettings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"', ' />';

			echo '
	</month>';
		}

	echo '
</smf>';
}

/**
 * The XML for selecting items to split
 */
function template_split()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<pageIndex section="not_selected" startFrom="', $context['not_selected']['start'], '"><![CDATA[', $context['not_selected']['page_index'], ']]></pageIndex>
	<pageIndex section="selected" startFrom="', $context['selected']['start'], '"><![CDATA[', $context['selected']['page_index'], ']]></pageIndex>';
	foreach ($context['changes'] as $change)
	{
		if ($change['type'] == 'remove')
			echo '
	<change id="', $change['id'], '" curAction="remove" section="', $change['section'], '" />';
		else
			echo '
	<change id="', $change['id'], '" curAction="insert" section="', $change['section'], '">
		<subject><![CDATA[', cleanXml($change['insert_value']['subject']), ']]></subject>
		<time><![CDATA[', cleanXml($change['insert_value']['time']), ']]></time>
		<body><![CDATA[', cleanXml($change['insert_value']['body']), ']]></body>
		<poster><![CDATA[', cleanXml($change['insert_value']['poster']), ']]></poster>
	</change>';
	}
	echo '
</smf>';
}

/**
 * This is just to hold off some errors if people are stupid.
 */
if (!function_exists('template_button_strip'))
{
	function template_button_strip($button_strip, $direction = 'top', $strip_options = array())
	{
	}

	function template_menu()
	{
	}

	function theme_linktree()
	{
	}
}

/**
 * XML for search results
 */
function template_results()
{
	global $context, $txt;
	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';

	if (empty($context['topics']))
		echo '
		<noresults>', $txt['search_no_results'], '</noresults>';
	else
	{
		echo '
		<results>';

		while ($topic = $context['get_topics']())
		{
			echo '
			<result>
				<id>', $topic['id'], '</id>
				<relevance>', $topic['relevance'], '</relevance>
				<board>
					<id>', $topic['board']['id'], '</id>
					<name>', cleanXml($topic['board']['name']), '</name>
					<href>', $topic['board']['href'], '</href>
				</board>
				<category>
					<id>', $topic['category']['id'], '</id>
					<name>', cleanXml($topic['category']['name']), '</name>
					<href>', $topic['category']['href'], '</href>
				</category>
				<messages>';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<message>
						<id>', $message['id'], '</id>
						<subject><![CDATA[', cleanXml($message['subject_highlighted'] != '' ? $message['subject_highlighted'] : $message['subject']), ']]></subject>
						<body><![CDATA[', cleanXml($message['body_highlighted'] != '' ? $message['body_highlighted'] : $message['body']), ']]></body>
						<time>', $message['time'], '</time>
						<timestamp>', $message['timestamp'], '</timestamp>
						<start>', $message['start'], '</start>

						<author>
							<id>', $message['member']['id'], '</id>
							<name>', cleanXml($message['member']['name']), '</name>
							<href>', $message['member']['href'], '</href>
						</author>
					</message>';
			}
			echo '
				</messages>
			</result>';
		}

		echo '
		</results>';
	}

	echo '
</smf>';
}

/**
 * The XML for the Jump To box
 */
function template_jump_to()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';

	foreach ($context['jump_to'] as $category)
	{
		echo '
	<item type="category" id="', $category['id'], '"><![CDATA[', cleanXml($category['name']), ']]></item>';

		foreach ($category['boards'] as $board)
			echo '
	<item type="board" id="', $board['id'], '" childlevel="', $board['child_level'], '"><![CDATA[', cleanXml($board['name']), ']]></item>';
	}
	echo '
</smf>';
}

/**
 * The XML for displaying a column of message icons and selecting one via AJAX
 */
function template_message_icons()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';

	foreach ($context['icons'] as $icon)
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', cleanXml($icon['name']), ']]></icon>';

	echo '
</smf>';
}

/**
 * The XML for instantly showing whether a username is valid on the registration page
 */
function template_check_username()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<username valid="', $context['valid_username'] ? 1 : 0, '">', cleanXml($context['checked_username']), '</username>
</smf>';
}

/**
 * This prints XML in its most generic form.
 */
function template_generic_xml()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>';

	// Show the data.
	template_generic_xml_recursive($context['xml_data'], 'smf', '', -1);
}

/**
 * Recursive function for displaying generic XML data.
 *
 * @param array $xml_data An array of XML data
 * @param string $parent_ident The parent tag
 * @param string $child_ident The child tag
 * @param int $level How many levels to indent the code
 */
function template_generic_xml_recursive($xml_data, $parent_ident, $child_ident, $level)
{
	// This is simply for neat indentation.
	$level++;

	echo "\n" . str_repeat("\t", $level), '<', $parent_ident, '>';

	foreach ($xml_data as $key => $data)
	{
		// A group?
		if (is_array($data) && isset($data['identifier']))
			template_generic_xml_recursive($data['children'], $key, $data['identifier'], $level);
		// An item...
		elseif (is_array($data) && isset($data['value']))
		{
			echo "\n", str_repeat("\t", $level), '<', $child_ident;

			if (!empty($data['attributes']))
				foreach ($data['attributes'] as $k => $v)
					echo ' ' . $k . '="' . $v . '"';
			echo '><![CDATA[', cleanXml($data['value']), ']]></', $child_ident, '>';
		}
	}

	echo "\n", str_repeat("\t", $level), '</', $parent_ident, '>';
}

?>