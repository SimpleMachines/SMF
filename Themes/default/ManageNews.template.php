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

// Form for editing current news on the site.
function template_edit_news()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=editnews" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify">
			<table class="table_grid" width="100%">
				<thead>
					<tr class="catbg">
						<th class="first_th" width="50%">', $txt['admin_edit_news'], '</th>
						<th align="left" width="45%">', $txt['preview'], '</th>
						<th class="last_th" align="center" width="5%"><input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" /></th>
					</tr>
				</thead>
				<tbody>';

	// Loop through all the current news items so you can edit/remove them.
	foreach ($context['admin_current_news'] as $admin_news)
		echo '
					<tr class="windowbg2">
						<td align="center">

							<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" name="news[]" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';">', $admin_news['unparsed'], '</textarea></div>
						</td><td align="left" valign="top">
							<div style="overflow: auto; width: 100%; height: 10ex;">', $admin_news['parsed'], '</div>
						</td><td align="center">
							<input type="checkbox" name="remove[]" value="', $admin_news['id'], '" class="input_check" />
						</td>
					</tr>';

	// This provides an empty text box to add a news item to the site.
	echo '
					<tr id="moreNews" class="windowbg2" style="display: none;">
						<td align="center">
							<div id="moreNewsItems"></div>
						</td>
						<td align="center">
						</td>
						<td align="center">
						</td>
					</tr>
				</tbody>
			</table>
			<div class="floatleftpadding">
				<div id="moreNewsItems_link" style="display: none;"><a href="javascript:void(0);" onclick="addNewsItem(); return false;">', $txt['editnews_clickadd'], '</a></div>
				<script type="text/javascript"><!-- // --><![CDATA[
					document.getElementById("moreNewsItems_link").style.display = "";
					function addNewsItem()
					{
						document.getElementById("moreNews").style.display = "";
						setOuterHTML(document.getElementById("moreNewsItems"), \'<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" name="news[]" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';"><\' + \'/textarea><\' + \'/div><div id="moreNewsItems"><\' + \'/div>\');
					}
				// ]]></script>
				<noscript>
					<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';" name="news[]"></textarea></div>
				</noscript>
			</div>
			<div class="floatrightpadding">
				<input type="submit" name="save_items" value="', $txt['save'], '" class="button_submit" /> <input type="submit" name="delete_selection" value="', $txt['editnews_remove_selected'], '" onclick="return confirm(\'', $txt['editnews_remove_confirm'], '\');" class="button_submit" />
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

function template_email_members()
{
	global $context, $settings, $options, $txt, $scripturl;

	// This is some javascript for the simple/advanced toggling stuff.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		function toggleAdvanced(mode)
		{
			// What styles are we doing?
			var divStyle = mode ? "" : "none";

			document.getElementById("advanced_settings_div").style.display = divStyle;
			document.getElementById("gosimple").style.display = divStyle;
			document.getElementById("goadvanced").style.display = mode ? "none" : "";
		}
	// ]]></script>';

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingcompose" method="post" class="flow_hidden" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['admin_newsletters'], '</h3>
			</div>
			<div class="information">
				', $txt['admin_news_select_recipients'], '
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['admin_news_select_group'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_group_desc'], '</span>
						</dt>
						<dd>';

	foreach ($context['groups'] as $group)
				echo '
							<label for="groups_', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups_', $group['id'], '" value="', $group['id'], '" checked="checked" class="input_check" /> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em><br />';

	echo '
							<br />
							<label for="checkAllGroups"><input type="checkbox" id="checkAllGroups" checked="checked" onclick="invertAll(this, this.form, \'groups\');" class="input_check" /> <em>', $txt['check_all'], '</em></label>';

	echo '
						</dd>
					</dl><br class="clear" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<br />

			<div class="cat_bar">
				<h3 class="catbg" id="advanced_select_div" style="display: none;">
					<span class="ie6_header floatleft">
						<a href="#" onclick="toggleAdvanced(1); return false;" id="goadvanced"><img src="', $settings['images_url'], '/selected.gif" alt="', $txt['advanced'], '" />&nbsp;<strong>', $txt['advanced'], '</strong></a>
						<a href="#" onclick="toggleAdvanced(0); return false;" id="gosimple" style="display: none;"><img src="', $settings['images_url'], '/sort_down.gif" alt="', $txt['simple'], '" />&nbsp;<strong>', $txt['simple'], '</strong></a>
					</span>
				</h3>
			</div>

			<div class="windowbg2" id="advanced_settings_div" style="display: none;">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['admin_news_select_email'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_email_desc'], '</span>
						</dt>
						<dd>
							<textarea name="emails" rows="5" cols="30" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 98%; min-width: 98%' : 'width: 98%') . ';"></textarea>
						</dd>
						<dt>
							<strong>', $txt['admin_news_select_members'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_members_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="members" id="members" value="" size="30" class="input_text" />
							<span id="members_container"></span>
						</dd>
					</dl>
					<hr class="bordercolor" />
					<dl class="settings">
						<dt>
							<strong>', $txt['admin_news_select_excluded_groups'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_excluded_groups_desc'], '</span>
						</dt>
						<dd>';

	foreach ($context['groups'] as $group)
				echo '
							<label for="exclude_groups_', $group['id'], '"><input type="checkbox" name="exclude_groups[', $group['id'], ']" id="exclude_groups_', $group['id'], '" value="', $group['id'], '" class="input_check" /> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em><br />';

	echo '
							<br />
							<label for="checkAllGroupsExclude"><input type="checkbox" id="checkAllGroupsExclude" onclick="invertAll(this, this.form, \'exclude_groups\');" class="input_check" /> <em>', $txt['check_all'], '</em></label><br />
						</dd>
						<dt>
							<strong>', $txt['admin_news_select_excluded_members'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_excluded_members_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="exclude_members" id="exclude_members" value="" size="30" class="input_text" />
							<span id="exclude_members_container"></span>
						</dd>
					</dl>
					<hr class="bordercolor" />
					<dl class="settings">
						<dt>
							<label for="email_force"><strong>', $txt['admin_news_select_override_notify'], ':</strong></label><br />
							<span class="smalltext">', $txt['email_force'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="email_force" id="email_force" value="1" class="input_check" />
						</dd>
					</dl><br class="clear" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<div class="righttext">
				<input type="submit" value="', $txt['admin_next'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>
	<br class="clear" />';

	// Make the javascript stuff visible.
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		document.getElementById("advanced_select_div").style.display = "";
		var oMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oMemberSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'members\',
			sControlId: \'members\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'member_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			sItemListContainerId: \'members_container\',
			aListItems: []
		});
		var oExcludeMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oExcludeMemberSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'exclude_members\',
			sControlId: \'exclude_members\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'exclude_member_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			sItemListContainerId: \'exclude_members_container\',
			aListItems: []
		});
	// ]]></script>';
}

function template_email_members_compose()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=email_members" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" class="icon" /></a> ', $txt['admin_newsletters'], '
				</h3>
			</div>
			<div class="information">
				', $txt['email_variables'], '
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						<input type="text" name="subject" size="60" value="', $context['default_subject'], '" class="input_text" />
					</p>
					<p>
						<textarea cols="70" rows="9" name="message" class="editor">', $context['default_message'], '</textarea>
					</p>
					<ul class="reset">
						<li><label for="send_pm"><input type="checkbox" name="send_pm" id="send_pm" class="input_check" onclick="if (this.checked && ', $context['total_emails'], ' != 0 && !confirm(\'', $txt['admin_news_cannot_pm_emails_js'], '\')) return false; this.form.parse_html.disabled = this.checked; this.form.send_html.disabled = this.checked; " /> ', $txt['email_as_pms'], '</label></li>
						<li><label for="send_html"><input type="checkbox" name="send_html" id="send_html" class="input_check" onclick="this.form.parse_html.disabled = !this.checked;" /> ', $txt['email_as_html'], '</label></li>
						<li><label for="parse_html"><input type="checkbox" name="parse_html" id="parse_html" checked="checked" disabled="disabled" class="input_check" /> ', $txt['email_parsed_html'], '</label></li>
					</ul>
					<p>
						<input type="submit" value="', $txt['sendtopic_send'], '" class="button_submit" />
					</p>
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="email_force" value="', $context['email_force'], '" />
			<input type="hidden" name="total_emails" value="', $context['total_emails'], '" />
			<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '" />';

	foreach ($context['recipients'] as $key => $values)
		echo '
			<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '" />';

	echo '
		</form>
	</div>
	<br class="clear" />';
}

function template_email_members_send()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="', $context['character_set'], '" name="autoSubmit" id="autoSubmit">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=email_members" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" align="top" /></a> ', $txt['admin_newsletters'], '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						<strong>', $context['percentage_done'], '% ', $txt['email_done'], '</strong>
					</p>
					<input type="submit" name="b" value="', $txt['email_continue'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="subject" value="', $context['subject'], '" />
					<input type="hidden" name="message" value="', $context['message'], '" />
					<input type="hidden" name="start" value="', $context['start'], '" />
					<input type="hidden" name="total_emails" value="', $context['total_emails'], '" />
					<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '" />
					<input type="hidden" name="send_pm" value="', $context['send_pm'], '" />
					<input type="hidden" name="send_html" value="', $context['send_html'], '" />
					<input type="hidden" name="parse_html" value="', $context['parse_html'], '" />';

	// All the things we must remember!
	foreach ($context['recipients'] as $key => $values)
		echo '
					<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '" />';

	echo '
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />
	<script type="text/javascript"><!-- // --><![CDATA[
		var countdown = 2;
		doAutoSubmit();

		function doAutoSubmit()
		{
			if (countdown == 0)
				document.forms.autoSubmit.submit();
			else if (countdown == -1)
				return;

			document.forms.autoSubmit.b.value = "', $txt['email_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	// ]]></script>';
}

?>