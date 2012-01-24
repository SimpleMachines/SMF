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

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<div class="pagesection">
			<div>', $txt['pages'], ': ', $context['page_index'], '</div>
		</div>';

	foreach ($context['posts'] as $post)
	{
		// This is far from ideal, but oh well - create buttons for the post.
		$button_set = array();

		if ($post['can_delete'])
			$button_set['delete'] = array('text' => 'remove', 'image' => 'delete.gif', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . $txt['remove_message'] . '?\');"', 'url' => $scripturl . '?action=deletemsg;msg=' . $post['id'] . ';topic=' . $post['topic'] . ';recent;' . $context['session_var'] . '=' . $context['session_id']);
		if ($post['can_reply'])
			$button_set['reply'] = array('text' => 'reply', 'image' => 'reply_sm.gif', 'lang' => true, 'url' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start']);
		if ($post['can_quote'])
			$button_set['quote'] = array('text' => 'reply_quote', 'image' => 'quote.gif', 'lang' => true, 'url' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'] . ';quote=' . $post['id']);
		if ($post['can_mark_notify'])
			$button_set['notify'] = array('text' => 'notify_replies', 'image' => 'notify_sm.gif', 'lang' => true, 'url' => $scripturl . '?action=notify;topic=' . $post['topic'] . '.' . $post['start']);

		echo '
			<table width="100%" cellpadding="4" cellspacing="1" border="0" class="bordercolor">
				<tr class="titlebg2">
					<td class="middletext">
						<div class="floatleft" style="width: 3ex;">&nbsp;', $post['counter'], '&nbsp;</div>
							<div class="floatleft">&nbsp;', $post['category']['link'], ' / ', $post['board']['link'], ' / <strong>', $post['link'], '</strong></div>
							<div class="righttext">&nbsp;', $txt['on'], ': ', $post['time'], '&nbsp;</div>
					</td>
				</tr>
				<tr>
					<td class="catbg" colspan="3">
						<span class="middletext"> ', $txt['started_by'], ' ' . $post['first_poster']['link'] . ' - ' . $txt['last_post'] . ' ' . $txt['by'] . ' ' . $post['poster']['link'] . ' </span>
					</td>
				</tr>
				<tr>
					<td class="windowbg2" colspan="3" valign="top" height="80">
						<div class="post">' . $post['message'] . '</div>
					</td>
				</tr>';

		// Are we using tabs?
		if (!empty($settings['use_tabs']))
		{
			echo '
			</table>';

			if (!empty($button_set))
				echo '
			<div class="readbuttons clearfix marginbottom">
				', template_button_strip($button_set, 'top'), '
			</div>';
		}
		else
		{
			if (!empty($button_set))
				echo '
				<tr>
					<td class="catbg" colspan="3" align="right">
						<table><tr><td>
						', template_button_strip($button_set, 'top'), '
						</td></tr></table>
					</td>
				</tr>';

			echo '
			</table>';
		}

		echo '
			<br />';
	}

	echo '
		<div class="pagesection">
			<div class="floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>
		</div>
	</div>';
}

function template_unread()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$showCheckboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];

	if ($showCheckboxes)
		echo '
	<div id="recent" class="main_content">
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if ($settings['show_mark_read'])
	{
		// Generate the button strip.
		$mark_read = array(
			'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		if ($showCheckboxes)
			$mark_read['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.gif',
				'lang' => true,
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}

	echo '
	<div id="readbuttons_top" class="readbuttons clearfix margintop">
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], '</div>';

	if (!empty($mark_read) && !empty($settings['use_tabs']))
		template_button_strip($mark_read, 'bottom');

	echo '
	</div>';

	echo '
	<table border="0" width="100%" cellspacing="0" cellpadding="0" class="bordercolor">
		<tr><td>
			<table border="0" width="100%" cellspacing="1" cellpadding="4" class="bordercolor">
				<tr class="titlebg">';
	if (!empty($context['topics']))
	{
		echo '
					<td width="10%" colspan="2">&nbsp;</td>
					<td>
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a>
					</td><td width="14%">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'], $context['sort_by'] == 'starter' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a>
					</td><td width="4%" align="center">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a>
					</td><td width="4%" align="center">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a>
					</td><td width="24%">
						<a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a>
					</td>';
		if ($showCheckboxes)
			echo '
					<td>
						<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
					</td>';
	}
	else
		echo '
					<td width="100%" colspan="7">', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '</td>';
	echo '
				</tr>';

	foreach ($context['topics'] as $topic)
	{
		// Do we want to separate the sticky and lock status out?
		if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'sticky') !== false)
			$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_sticky'));
		if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'locked') !== false)
			$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_locked'));

		echo '
				<tr>
					<td class="windowbg2" valign="middle" align="center" width="6%">
						<img src="' . $settings['images_url'] . '/topic/' . $topic['class'] . '.gif" alt="" />
					</td><td class="windowbg2" valign="middle" align="center" width="4%">
						<img src="' . $topic['first_post']['icon_url'] . '" alt="" align="middle" />
					</td><td class="windowbg', $topic['is_sticky'] && !empty($settings['separate_sticky_lock']) ? '3' : '', '" width="48%" valign="middle">', $topic['is_locked'] && !empty($settings['separate_sticky_lock']) ? '
						<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" align="right" alt="" style="margin: 0;" />' : '', $topic['is_sticky'] && !empty($settings['separate_sticky_lock']) ? '
						<img src="' . $settings['images_url'] . '/icons/show_sticky.gif" align="right" alt="" style="margin: 0;" />' : '', $topic['first_post']['link'], ' <a href="', $topic['new_href'], '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a> <span class="smalltext">', $topic['pages'], ' ', $txt['in'], ' ', $topic['board']['link'], '</span></td>
					<td class="windowbg2" valign="middle" width="14%">
						', $topic['first_post']['member']['link'], '</td>
					<td class="windowbg" valign="middle" width="4%" align="center">
						', $topic['replies'], '</td>
					<td class="windowbg" valign="middle" width="4%" align="center">
						', $topic['views'], '</td>
					<td class="windowbg2" valign="middle" width="22%">
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" style="float: right;" /></a>
						<span class="smalltext">
							', $topic['last_post']['time'], '<br />
							', $txt['by'], ' ', $topic['last_post']['member']['link'], '
						</span>
					</td>';
			if ($showCheckboxes)
				echo '
					<td class="windowbg2" valign="middle" align="center">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
					</td>';

			echo '
				</tr>';
	}

	if (!empty($context['topics']) && !$context['showing_all_topics'])
		echo '
				<tr class="titlebg">
					<td colspan="', $showCheckboxes ? '8' : '7', '" align="right" class="middletext"><a href="', $scripturl, '?action=unread;all', $context['querystring_board_limits'], '">', $txt['unread_topics_all'], '</a></td>
				</tr>';

	if (empty($settings['use_tabs']) && !empty($mark_read))
		echo '
				<tr>
					<td class="catbg" colspan="', $showCheckboxes ? '8' : '7', '" align="right">
						<table><tr><td>
						', template_button_strip($mark_read, 'top'), '
						</td></tr></table>
					</td>
				</tr>';

	echo '
			</table>
		</td></tr>
	</table>
	<div class="readbuttons clearfix marginbottom">
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], '</div>';

	if (!empty($settings['use_tabs']) && !empty($mark_read))
		template_button_strip($mark_read, 'top');

	echo '
	</div>
	<br />';

	if ($showCheckboxes)
		echo '
		</form>';

	echo '
		<div class="tborder clearfix" id="topic_icons">
			<div class="titlebg2 clearfix">
				<div class="floatleft smalltext">
			<ul class="reset">
				', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<li><img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '</li>' : '', '
				<li><img src="' . $settings['images_url'] . '/topic/normal_post.gif" alt="" align="middle" /> ' . $txt['normal_topic'] . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/hot_post.gif" alt="" align="middle" /> ' . sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']) . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/veryhot_post.gif" alt="" align="middle" /> ' . sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']) . '</li>
			</ul>
			</div>
			<div class="floatleft smalltext">
			<ul class="reset">
				<li><img src="' . $settings['images_url'] . '/icons/quick_lock.gif" alt="" align="middle" /> ' . $txt['locked_topic'] . '</li>' . ($modSettings['enableStickyTopics'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '</li>' : '') . ($modSettings['pollMode'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : '') . '</li>
			</ul>
			</div>
			</div>
	</div>';
}

function template_replies()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$showCheckboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];

	if ($showCheckboxes)
		echo '
	<div id="recent">
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;">
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
			<input type="hidden" name="qaction" value="markread" />
			<input type="hidden" name="redirect_url" value="action=unreadreplies', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

	if (isset($context['topics_to_mark']) && !empty($settings['show_mark_read']))
	{
		// Generate the button strip.
		$mark_read = array(
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		if ($showCheckboxes)
			$mark_read['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.gif',
				'lang' => true,
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}
	if (!empty($settings['use_tabs']))
	{
		echo '
	<div id="readbuttons_top" class="readbuttons clearfix margintop">
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], '</div>';
		if (!empty($mark_read))
			template_button_strip($mark_read, 'bottom');

		echo '
	</div>';
	}

	echo '
	<table border="0" width="100%" cellspacing="0" cellpadding="0" class="bordercolor">
		<tr><td>
			<table border="0" width="100%" cellspacing="1" cellpadding="4" class="bordercolor">
				<tr class="titlebg">';
	if (!empty($context['topics']))
	{
			echo '
					<td width="10%" colspan="2">&nbsp;</td>
					<td><a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
					<td width="14%"><a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'], $context['sort_by'] == 'starter' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
					<td width="4%" align="center"><a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
					<td width="4%" align="center"><a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
					<td width="24%"><a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>';
		if ($showCheckboxes)
			echo '
					<td>
						<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
					</td>';
	}
	else
		echo '
					<td width="100%" colspan="7">' . $txt['msg_alert_none'] . '</td>';
	echo '
				</tr>';

	foreach ($context['topics'] as $topic)
	{
		// separate lock and sticky again?
		if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'sticky') !== false)
			$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_sticky'));
		if (!empty($settings['separate_sticky_lock']) && strpos($topic['class'], 'locked') !== false)
			$topic['class'] = substr($topic['class'], 0, strrpos($topic['class'], '_locked'));

		echo '
				<tr>
					<td class="windowbg2" valign="middle" align="center" width="6%">
						<img src="', $settings['images_url'], '/topic/', $topic['class'], '.gif" alt="" /></td>
					<td class="windowbg2" valign="middle" align="center" width="4%">
						<img src="', $topic['first_post']['icon_url'], '" alt="" align="middle" /></td>
					<td class="windowbg', $topic['is_sticky'] && !empty($settings['separate_sticky_lock']) ? '3' : '', '" width="48%" valign="middle">
						', $topic['is_locked'] && !empty($settings['separate_sticky_lock']) ? '<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" align="right" alt="" style="margin: 0;" />' : '', '
						', $topic['is_sticky'] && !empty($settings['separate_sticky_lock']) ? '<img src="' . $settings['images_url'] . '/icons/show_sticky.gif" align="right" alt="" style="margin: 0;" />' : '', ' ', $topic['first_post']['link'], ' <a href="', $topic['new_href'], '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a> <span class="smalltext">', $topic['pages'], '
						', $txt['in'], ' ', $topic['board']['link'], '</span></td>
					<td class="windowbg2" valign="middle" width="14%">
						', $topic['first_post']['member']['link'], '</td>
					<td class="windowbg" valign="middle" width="4%" align="center">
						', $topic['replies'], '</td>
					<td class="windowbg" valign="middle" width="4%" align="center">
						', $topic['views'], '</td>
					<td class="windowbg2" valign="middle" width="22%">
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" style="float: right;" /></a>
						<span class="smalltext">
								', $topic['last_post']['time'], '<br />
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
						</span>
					</td>';
		if ($showCheckboxes)
			echo '
					<td class="windowbg2" valign="middle" align="center">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
					</td>';

		echo '
				</tr>';
	}
	if (empty($settings['use_tabs']) && !empty($mark_read))
		echo '
				<tr>
					<td class="catbg" colspan="', $showCheckboxes ? '8' : '7', '" align="right">
						<table><tr><td>
							', template_button_strip($mark_read, 'top'), '
						</td></tr></table>
					</td>
				</tr>';

	echo '
			</table>
		</td></tr>
	</table>
	<div class="readbuttons clearfix marginbottom">
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], '</div>';

	if (!empty($settings['use_tabs']) && !empty($mark_read))
		template_button_strip($mark_read, 'top');

	echo '
	</div>
	<br />';

	if ($showCheckboxes)
		echo '
		</form>';

	echo '
		<div class="tborder clearfix" id="topic_icons">
			<div class="titlebg2 clearfix">
				<div class="floatleft smalltext">
			<ul class="reset">
				', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<li><img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '</li>' : '', '
				<li><img src="' . $settings['images_url'] . '/topic/normal_post.gif" alt="" align="middle" /> ' . $txt['normal_topic'] . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/hot_post.gif" alt="" align="middle" /> ' . sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']) . '</li>
				<li><img src="' . $settings['images_url'] . '/topic/veryhot_post.gif" alt="" align="middle" /> ' . sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']) . '</li>
			</ul>
			</div>
			<div class="floatleft smalltext">
			<ul class="reset">
				<li><img src="' . $settings['images_url'] . '/icons/quick_lock.gif" alt="" align="middle" /> ' . $txt['locked_topic'] . '</li>' . ($modSettings['enableStickyTopics'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '</li>' : '') . ($modSettings['pollMode'] == '1' ? '
				<li><img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : '') . '</li>
			</ul>
			</div>
			</div>
	</div>';
}

?>