<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * This defines the XML for sending the body of a message
 */
function template_sendbody()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<message view="', Utils::$context['view'], '">', Utils::cleanXml(Utils::$context['message']), '</message>
</smf>';
}

/**
 * This defines the XML for the AJAX quote feature
 */
function template_quotefast()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<quote>', Utils::cleanXml(Utils::$context['quote']['xml']), '</quote>
</smf>';
}

/**
 * This defines the XML for the inline edit feature
 */
function template_modifyfast()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<subject><![CDATA[', Utils::cleanXml(Utils::$context['message']['subject']), ']]></subject>
	<message id="msg_', Utils::$context['message']['id'], '"><![CDATA[', Utils::cleanXml(Utils::$context['message']['body']), ']]></message>
	<reason time="', Utils::$context['message']['reason']['time'], '" name="', Utils::$context['message']['reason']['name'], '"><![CDATA[', Utils::cleanXml(Utils::$context['message']['reason']['text']), ']]></reason>
</smf>';

}

/**
 * The XML for handling things when you're done editing a post inline
 */
function template_modifydone()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<message id="msg_', Utils::$context['message']['id'], '">';
	if (empty(Utils::$context['message']['errors']))
	{
		// Build our string of info about when and why it was modified
		$modified = empty(Utils::$context['message']['modified']['time']) ? '' : sprintf(Lang::$txt['last_edit_by'], Utils::$context['message']['modified']['time'], Utils::$context['message']['modified']['name']);
		$modified .= empty(Utils::$context['message']['modified']['reason']) ? '' : ' ' . sprintf(Lang::$txt['last_edit_reason'], Utils::$context['message']['modified']['reason']);

		echo '
		<modified><![CDATA[', empty($modified) ? '' : Utils::cleanXml($modified), ']]></modified>
		<subject is_first="', Utils::$context['message']['first_in_topic'] ? '1' : '0', '"><![CDATA[', Utils::cleanXml(Utils::$context['message']['subject']), ']]></subject>
		<body><![CDATA[', Utils::$context['message']['body'], ']]></body>
		<success><![CDATA[', Lang::$txt['quick_modify_message'], ']]></success>';
	}
	else
		echo '
		<error in_subject="', Utils::$context['message']['error_in_subject'] ? '1' : '0', '" in_body="', Utils::cleanXml(Utils::$context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br />', Utils::$context['message']['errors']), ']]></error>';
	echo '
	</message>
</smf>';
}

/**
 * This handles things when editing a topic's subject from the messageindex.
 */
function template_modifytopicdone()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<message id="msg_', Utils::$context['message']['id'], '">';
	if (empty(Utils::$context['message']['errors']))
	{
		// Build our string of info about when and why it was modified
		$modified = empty(Utils::$context['message']['modified']['time']) ? '' : sprintf(Lang::$txt['last_edit_by'], Utils::$context['message']['modified']['time'], Utils::$context['message']['modified']['name']);
		$modified .= empty(Utils::$context['message']['modified']['reason']) ? '' : sprintf(Lang::$txt['last_edit_reason'], Utils::$context['message']['modified']['reason']);

		echo '
		<modified><![CDATA[', empty($modified) ? '' : Utils::cleanXml('<em>' . $modified . '</em>'), ']]></modified>';

		if (!empty(Utils::$context['message']['subject']))
			echo '
		<subject><![CDATA[', Utils::cleanXml(Utils::$context['message']['subject']), ']]></subject>';
	}
	else
		echo '
		<error in_subject="', Utils::$context['message']['error_in_subject'] ? '1' : '0', '"><![CDATA[', Utils::cleanXml(implode('<br />', Utils::$context['message']['errors'])), ']]></error>';
	echo '
	</message>
</smf>';
}

/**
 * The massive XML for previewing posts.
 */
function template_post()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', Utils::$context['preview_subject'], ']]></subject>
		<body><![CDATA[', Utils::$context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1', '" topic_locked="', Utils::$context['locked'] ? '1' : '0', '">';

	if (!empty(Utils::$context['post_error']))
		foreach (Utils::$context['post_error'] as $message)
			echo '
		<error><![CDATA[', Utils::cleanXml($message), ']]></error>';

	echo '
		<caption name="guestname" class="', isset(Utils::$context['post_error']['long_name']) || isset(Utils::$context['post_error']['no_name']) || isset(Utils::$context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset(Utils::$context['post_error']['no_email']) || isset(Utils::$context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="evtitle" class="', isset(Utils::$context['post_error']['no_event']) ? 'error' : '', '" />
		<caption name="subject" class="', isset(Utils::$context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset(Utils::$context['post_error']['no_question']) ? 'error' : '', '" />', isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>
	<last_msg>', isset(Utils::$context['topic_last_message']) ? Utils::$context['topic_last_message'] : '0', '</last_msg>';

	if (!empty(Utils::$context['previous_posts']))
	{
		echo '
	<new_posts>';

		foreach (Utils::$context['previous_posts'] as $post)
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['time'], ']]></time>
			<poster><![CDATA[', Utils::cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', Utils::cleanXml($post['message']), ']]></message>
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
	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', Lang::$txt['preview'], ' - ', !empty(Utils::$context['preview_subject']) ? Utils::$context['preview_subject'] : Lang::$txt['no_subject'], ']]></subject>
		<body><![CDATA[', Utils::$context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty(Utils::$context['post_error']['messages']))
		foreach (Utils::$context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', Utils::cleanXml($message), ']]></error>';

	echo '
		<caption name="to" class="', isset(Utils::$context['post_error']['no_to']) ? 'error' : '', '" />
		<caption name="bbc" class="', isset(Utils::$context['post_error']['no_bbc']) ? 'error' : '', '" />
		<caption name="subject" class="', isset(Utils::$context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset(Utils::$context['post_error']['no_question']) ? 'error' : '', '" />', isset(Utils::$context['post_error']['no_message']) || isset(Utils::$context['post_error']['long_message']) ? '
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
	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', Utils::$context['preview_subject'], ']]></subject>
		<body><![CDATA[', Utils::$context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty(Utils::$context['post_error']['messages']))
		foreach (Utils::$context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', Utils::cleanXml($message), ']]></error>';

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
	if (empty(Utils::$context['yearly']))
		return;

	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>';
	foreach (Utils::$context['yearly'] as $year)
		foreach ($year['months'] as $month)
		{
			echo '
	<month id="', $month['date']['year'], $month['date']['month'], '">';

			foreach ($month['days'] as $day)
				echo '
		<day date="', $day['year'], '-', $day['month'], '-', $day['day'], '" new_topics="', $day['new_topics'], '" new_posts="', $day['new_posts'], '" new_members="', $day['new_members'], '" most_members_online="', $day['most_members_online'], '"', empty(Config::$modSettings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"', ' />';

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
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<pageIndex section="not_selected" startFrom="', Utils::$context['not_selected']['start'], '"><![CDATA[', Utils::$context['not_selected']['page_index'], ']]></pageIndex>
	<pageIndex section="selected" startFrom="', Utils::$context['selected']['start'], '"><![CDATA[', Utils::$context['selected']['page_index'], ']]></pageIndex>';
	foreach (Utils::$context['changes'] as $change)
	{
		if ($change['type'] == 'remove')
			echo '
	<change id="', $change['id'], '" curAction="remove" section="', $change['section'], '" />';
		else
			echo '
	<change id="', $change['id'], '" curAction="insert" section="', $change['section'], '">
		<subject><![CDATA[', Utils::cleanXml($change['insert_value']['subject']), ']]></subject>
		<time><![CDATA[', Utils::cleanXml($change['insert_value']['time']), ']]></time>
		<body><![CDATA[', Utils::cleanXml($change['insert_value']['body']), ']]></body>
		<poster><![CDATA[', Utils::cleanXml($change['insert_value']['poster']), ']]></poster>
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
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>';

	if (empty(Utils::$context['topics']))
		echo '
		<noresults>', Lang::$txt['search_no_results'], '</noresults>';
	else
	{
		echo '
		<results>';

		while ($topic = Utils::$context['get_topics']())
		{
			echo '
			<result>
				<id>', $topic['id'], '</id>
				<relevance>', $topic['relevance'], '</relevance>
				<board>
					<id>', $topic['board']['id'], '</id>
					<name>', Utils::cleanXml($topic['board']['name']), '</name>
					<href>', $topic['board']['href'], '</href>
				</board>
				<category>
					<id>', $topic['category']['id'], '</id>
					<name>', Utils::cleanXml($topic['category']['name']), '</name>
					<href>', $topic['category']['href'], '</href>
				</category>
				<messages>';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<message>
						<id>', $message['id'], '</id>
						<subject><![CDATA[', Utils::cleanXml($message['subject_highlighted'] != '' ? $message['subject_highlighted'] : $message['subject']), ']]></subject>
						<body><![CDATA[', Utils::cleanXml($message['body_highlighted'] != '' ? $message['body_highlighted'] : $message['body']), ']]></body>
						<time>', $message['time'], '</time>
						<timestamp>', $message['timestamp'], '</timestamp>
						<start>', $message['start'], '</start>

						<author>
							<id>', $message['member']['id'], '</id>
							<name>', Utils::cleanXml($message['member']['name']), '</name>
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
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>';

	foreach (Utils::$context['jump_to'] as $category)
	{
		echo '
	<item type="category" id="', $category['id'], '"><![CDATA[', Utils::cleanXml($category['name']), ']]></item>';

		foreach ($category['boards'] as $board)
			echo '
	<item type="board" id="', $board['id'], '" childlevel="', $board['child_level'], '" is_redirect="', (int) !empty($board['redirect']), '"><![CDATA[', Utils::cleanXml($board['name']), ']]></item>';
	}
	echo '
</smf>';
}

/**
 * The XML for displaying a column of message icons and selecting one via AJAX
 */
function template_message_icons()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>';

	foreach (Utils::$context['icons'] as $icon)
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', Utils::cleanXml($icon['name']), ']]></icon>';

	echo '
</smf>';
}

/**
 * The XML for instantly showing whether a username is valid on the registration page
 */
function template_check_username()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>
<smf>
	<username valid="', Utils::$context['valid_username'] ? 1 : 0, '">', Utils::cleanXml(Utils::$context['checked_username']), '</username>
</smf>';
}

/**
 * This prints XML in its most generic form.
 */
function template_generic_xml()
{
	echo '<', '?xml version="1.0" encoding="', Utils::$context['character_set'], '"?', '>';

	// Show the data.
	template_generic_xml_recursive(Utils::$context['xml_data'], 'smf', '', -1);
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
			echo '><![CDATA[', Utils::cleanXml($data['value']), ']]></', $child_ident, '>';
		}
	}

	echo "\n", str_repeat("\t", $level), '</', $parent_ident, '>';
}

?>