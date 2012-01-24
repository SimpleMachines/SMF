<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_sendbody()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message view="', $context['view'], '">', cleanXml($context['message']), '</message>
</smf>';
}

function template_quotefast()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<quote>', cleanXml($context['quote']['xml']), '</quote>
</smf>';
}

function template_modifyfast()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
	<message id="msg_', $context['message']['id'], '"><![CDATA[', cleanXml($context['message']['body']), ']]></message>
</smf>';

}

function template_modifydone()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		echo '
		<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml('&#171; <em>' . $txt['last_edit'] . ': ' . $context['message']['modified']['time'] . ' ' . $txt['by'] . ' ' . $context['message']['modified']['name'] . '</em> &#187;'), ']]></modified>
		<subject is_first="', $context['message']['first_in_topic'] ? '1' : '0', '"><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
		<body><![CDATA[', $context['message']['body'], ']]></body>';
	}
	else
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '" in_body="', cleanXml($context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br />', $context['message']['errors']), ']]></error>';
	echo '
	</message>
</smf>';
}

function template_modifytopicdone()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		echo '
		<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml('&#171; <em>' . $txt['last_edit'] . ': ' . $context['message']['modified']['time'] . ' ' . $txt['by'] . ' ' . $context['message']['modified']['name'] . '</em> &#187;'), ']]></modified>';
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

function template_post()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';
	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
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

function template_stats()
{
	global $context, $settings, $options, $txt, $modSettings;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';
	foreach ($context['yearly'] as $year)
		foreach ($year['months'] as $month);
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

function template_split()
{
	global $context, $settings, $options;

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

// This is just to hold off some errors if people are stupid.
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

function template_results()
{
	global $context, $settings, $options, $txt;
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

function template_jump_to()
{
	global $context, $settings, $options;

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

function template_message_icons()
{
	global $context, $settings, $options;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>';
	foreach ($context['icons'] as $icon)
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', cleanXml($icon['name']), ']]></icon>';
	echo '
</smf>';
}

function template_check_username()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<smf>
	<username valid="', $context['valid_username'] ? 1 : 0, '">', cleanXml($context['checked_username']), '</username>
</smf>';
}

// This prints XML in it's most generic form.
function template_generic_xml()
{
	global $context, $settings, $options, $txt;

	echo '<', '?xml version="1.0" encoding="', $context['character_set'], '"?', '>';

	// Show the data.
	template_generic_xml_recursive($context['xml_data'], 'smf', '', -1);
}

// Recursive function for displaying generic XML data.
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

function template_webslice_header_above()
{
	global $settings;

	echo '
	<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/wireless.css" type="text/css" />';
}

function template_webslice_header_below()
{
}

// This shows a webslice of the recent posts.
function template_webslice_recent_posts()
{
	global $context, $scripturl, $txt;

	echo '
	<div style="width: 100%; height: 100%; border: 1px solid black; padding: 0; margin: 0 0 0 0; font: 100.01%/100% Verdana, Helvetica, sans-serif;">
		<div style="background-color: #080436; color: #ffffff; padding: 4px;">
			', cleanXml($txt['recent_posts']), '
		</div>';

	$alternate = 0;
	foreach ($context['recent_posts_data'] as $item)
	{
		echo '
		<div style="background-color: ', $alternate ? '#ECEDF3' : '#F6F6F6', '; font-size: 90%; padding: 2px;">
			<strong><a href="', $item['link'], '">', cleanXml($item['subject']), '</a></strong> ', cleanXml($txt['by']), ' ', cleanXml(!empty($item['poster']['link']) ? '<a href="' . $item['poster']['link'] . '">' . $item['poster']['name'] . '</a>' : $item['poster']['name']), '
		</div>';
		$alternate = !$alternate;
	}

	echo '
	</div>
	<div style="width: 100%; height: 100%; border: 0; padding: 0; margin: 0 0 0 0; font: 100.01%/100% Verdana, Helvetica, sans-serif;">
		<div style="font-size: xx-small;" class="righttext">';

	if ($context['user']['is_guest'])
		echo '
			<a href="', $scripturl, '?action=login">', $txt['login'], '</a>';
	else
		echo '
			', cleanXml($context['user']['name']), ', ', cleanXml($txt['msg_alert_you_have']), ' <a href="', $scripturl, '?action=pm">', cleanXml($context['user']['messages']), ' ', cleanXml($context['user']['messages'] != 1 ? $txt['msg_alert_messages'] : $txt['message_lowercase']), '</a>', cleanXml($txt['newmessages4'] . ' ' . $context['user']['unread_messages']), ' ', cleanXml($context['user']['unread_messages'] == 1 ? $txt['newmessages0'] : $txt['newmessages1']);

	echo '
		</div>
	</div>';
}

?>