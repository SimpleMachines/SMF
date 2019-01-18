<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * The admin member search form
 */
function template_search_members()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', $context['character_set'], '" id="admin_form_wrapper">
			<input type="hidden" name="sa" value="query">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft">', $txt['search_for'], '</span>
					<span class="smalltext floatright">', $txt['wild_cards_allowed'], '</span>
				</h3>
			</div>
			<div class="windowbg">
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="mem_id">', $txt['member_id'], ':</label></strong>
								<select name="types[mem_id]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="mem_id" id="mem_id" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="age">', $txt['age'], ':</label></strong>
								<select name="types[age]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="age" id="age" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="posts">', $txt['member_postcount'], ':</label></strong>
								<select name="types[posts]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="posts" id="posts" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="reg_date">', $txt['date_registered'], ':</label></strong>
								<select name="types[reg_date]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="reg_date" id="reg_date" value="" size="10"><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
							<dt class="righttext">
								<strong><label for="last_online">', $txt['viewmembers_online'], ':</label></strong>
								<select name="types[last_online]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="last_online" id="last_online" value="" size="10"><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="membername">', $txt['username'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="membername" id="membername" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="email">', $txt['email_address'], ':</label></strong>
							</dt>
							<dd>
								<input type="email" name="email" id="email" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="website">', $txt['website'], ':</label></strong>
							</dt>
							<dd>
								<input type="url" name="website" id="website" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="ip">', $txt['ip_address'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="ip" id="ip" value="">
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<fieldset>
							<legend>', $txt['activation_status'], '</legend>
							<label for="activated-0"><input type="checkbox" name="activated[]" value="1" id="activated-0" checked> ', $txt['activated'], '</label>&nbsp;&nbsp;
							<label for="activated-1"><input type="checkbox" name="activated[]" value="0" id="activated-1" checked> ', $txt['not_activated'], '</label>
						</fieldset>
					</div>
				</div><!-- .flow_hidden -->
			</div><!-- ..windowbg -->
			<div class="cat_bar">
				<h3 class="catbg">', $txt['member_part_of_these_membergroups'], '</h3>
			</div>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col">', $txt['membergroups'], '</th>
						<th scope="col" class="centercol quarter_table">', $txt['primary'], '</th>
						<th scope="col" class="centercol quarter_table">', $txt['additional'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['membergroups'] as $membergroup)
		echo '
					<tr class="windowbg">
						<td>', $membergroup['name'], '</td>
						<td class="centercol">
							<input type="checkbox" name="membergroups[1][]" value="', $membergroup['id'], '" checked>
						</td>
						<td class="centercol">
							', $membergroup['can_be_additional'] ? '<input type="checkbox" name="membergroups[2][]" value="' . $membergroup['id'] . '" checked>' : '', '
						</td>
					</tr>';

	echo '
					<tr class="windowbg">
						<td>
							<em>', $txt['check_all'], '</em>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[1]\');" checked>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[2]\');" checked>
						</td>
					</tr>
				</tbody>
			</table>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col">
							', $txt['membergroups_postgroups'], '
						</th>
						<th class="quarter_table"></th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['postgroups'] as $postgroup)
		echo '
					<tr class="windowbg">
						<td>
							', $postgroup['name'], '
						</td>
						<td class="centercol">
							<input type="checkbox" name="postgroups[]" value="', $postgroup['id'], '" checked>
						</td>
					</tr>';

	echo '
					<tr class="windowbg">
						<td>
							<em>', $txt['check_all'], '</em>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, \'postgroups[]\');" checked>
						</td>
					</tr>
				</tbody>
			</table>
			<br>
			<input type="submit" value="', $txt['search'], '" class="button">
		</form>';
}

/**
 * The admin member list.
 */
function template_admin_browse()
{
	global $context, $scripturl, $txt;

	template_show_list('approve_list');

	// If we have lots of outstanding members try and make the admin's life easier.
	if ($context['approve_list']['total_num_items'] > 20)
	{
		echo '
		<br>
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', $context['character_set'], '" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['admin_browse_outstanding'], '</h3>
			</div>
			<script>
				function onOutstandingSubmit()
				{
					if (document.forms.postFormOutstanding.todo.value == "")
						return;

					var message = "";
					if (document.forms.postFormOutstanding.todo.value.indexOf("delete") != -1)
						message = "', $txt['admin_browse_w_delete'], '";
					else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") != -1)
						message = "', $txt['admin_browse_w_reject'], '";
					else if (document.forms.postFormOutstanding.todo.value == "remind")
						message = "', $txt['admin_browse_w_remind'], '";
					else
						message = "', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], '";

					if (confirm(message + " ', $txt['admin_browse_outstanding_warn'], '"))
						return true;
					else
						return false;
				}
			</script>

			<div class="windowbg">
				<dl class="settings">
					<dt>
						', $txt['admin_browse_outstanding_days_1'], ':
					</dt>
					<dd>
						<input type="text" name="time_passed" value="14" maxlength="4" size="3"> ', $txt['admin_browse_outstanding_days_2'], '.
					</dd>
					<dt>
						', $txt['admin_browse_outstanding_perform'], ':
					</dt>
					<dd>
						<select name="todo">
							', $context['browse_type'] == 'activate' ? '
							<option value="ok">' . $txt['admin_browse_w_activate'] . '</option>' : '', '
							<option value="okemail">', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . $txt['admin_browse_w_approve_require_activate'] . '</option>', '
							<option value="reject">', $txt['admin_browse_w_reject'], '</option>
							<option value="rejectemail">', $txt['admin_browse_w_reject'], ' ', $txt['admin_browse_w_email'], '</option>
							<option value="delete">', $txt['admin_browse_w_delete'], '</option>
							<option value="deleteemail">', $txt['admin_browse_w_delete'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '
							<option value="remind">' . $txt['admin_browse_w_remind'] . '</option>' : '', '
						</select>
					</dd>
				</dl>
				<input type="submit" value="', $txt['admin_browse_outstanding_go'], '" class="button">
				<input type="hidden" name="type" value="', $context['browse_type'], '">
				<input type="hidden" name="sort" value="', $context['approve_list']['sort']['id'], '">
				<input type="hidden" name="start" value="', $context['approve_list']['start'], '">
				<input type="hidden" name="orig_filter" value="', $context['current_filter'], '">
				<input type="hidden" name="sa" value="approve">', !empty($context['approve_list']['sort']['desc']) ? '
				<input type="hidden" name="desc" value="1">' : '', '
			</div><!-- .windowbg -->
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
	}
}

?>