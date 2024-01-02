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
 * The admin member search form
 */
function template_search_members()
{
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', Utils::$context['character_set'], '" id="admin_form_wrapper">
			<input type="hidden" name="sa" value="query">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft">', Lang::$txt['search_for'], '</span>
					<span class="smalltext floatright">', Lang::$txt['wild_cards_allowed'], '</span>
				</h3>
			</div>
			<div class="windowbg">
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="mem_id">', Lang::$txt['member_id'], ':</label></strong>
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
								<strong><label for="age">', Lang::$txt['age'], ':</label></strong>
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
								<strong><label for="posts">', Lang::$txt['member_postcount'], ':</label></strong>
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
								<strong><label for="reg_date">', Lang::$txt['date_registered'], ':</label></strong>
								<select name="types[reg_date]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="reg_date" id="reg_date" value="" size="10"><span class="smalltext"></span>
							</dd>
							<dt class="righttext">
								<strong><label for="last_online">', Lang::$txt['viewmembers_online'], ':</label></strong>
								<select name="types[last_online]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="last_online" id="last_online" value="" size="10"><span class="smalltext"></span>
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="membername">', Lang::$txt['username'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="membername" id="membername" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="email">', Lang::$txt['email_address'], ':</label></strong>
							</dt>
							<dd>
								<input type="email" name="email" id="email" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="website">', Lang::$txt['website'], ':</label></strong>
							</dt>
							<dd>
								<input type="url" name="website" id="website" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="ip">', Lang::$txt['ip_address'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="ip" id="ip" value="">
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<fieldset>
							<legend>', Lang::$txt['activation_status'], '</legend>
							<label for="activated-0"><input type="checkbox" name="activated[]" value="1" id="activated-0" checked> ', Lang::$txt['activated'], '</label>&nbsp;&nbsp;
							<label for="activated-1"><input type="checkbox" name="activated[]" value="0" id="activated-1" checked> ', Lang::$txt['not_activated'], '</label>
						</fieldset>
					</div>
				</div><!-- .flow_hidden -->
			</div><!-- ..windowbg -->
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['member_part_of_these_membergroups'], '</h3>
			</div>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col">', Lang::$txt['membergroups'], '</th>
						<th scope="col" class="centercol quarter_table">', Lang::$txt['primary'], '</th>
						<th scope="col" class="centercol quarter_table">', Lang::$txt['additional'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['membergroups'] as $membergroup)
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
							<em>', Lang::$txt['check_all'], '</em>
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
							', Lang::$txt['membergroups_postgroups'], '
						</th>
						<th class="quarter_table"></th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['postgroups'] as $postgroup)
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
							<em>', Lang::$txt['check_all'], '</em>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, \'postgroups[]\');" checked>
						</td>
					</tr>
				</tbody>
			</table>
			<br>
			<input type="submit" value="', Lang::$txt['search'], '" class="button">
		</form>';
}

/**
 * The admin member list.
 */
function template_admin_browse()
{
	template_show_list('approve_list');

	// If we have lots of outstanding members try and make the admin's life easier.
	if (Utils::$context['approve_list']['total_num_items'] > 20)
	{
		echo '
		<br>
		<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', Utils::$context['character_set'], '" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['admin_browse_outstanding'], '</h3>
			</div>
			<script>
				function onOutstandingSubmit()
				{
					if (document.forms.postFormOutstanding.todo.value == "")
						return;

					var message = "";
					if (document.forms.postFormOutstanding.todo.value.indexOf("delete") != -1)
						message = "', Lang::$txt['admin_browse_w_delete'], '";
					else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") != -1)
						message = "', Lang::$txt['admin_browse_w_reject'], '";
					else if (document.forms.postFormOutstanding.todo.value == "remind")
						message = "', Lang::$txt['admin_browse_w_remind'], '";
					else
						message = "', Utils::$context['browse_type'] == 'approve' ? Lang::$txt['admin_browse_w_approve'] : Lang::$txt['admin_browse_w_activate'], '";

					if (confirm(message + " ', Lang::$txt['admin_browse_outstanding_warn'], '"))
						return true;
					else
						return false;
				}
			</script>

			<div class="windowbg">
				<dl class="settings">
					<dt>
						', Lang::$txt['admin_browse_outstanding_days_1'], ':
					</dt>
					<dd>
						<input type="text" name="time_passed" value="14" maxlength="4" size="3"> ', Lang::$txt['admin_browse_outstanding_days_2'], '.
					</dd>
					<dt>
						', Lang::$txt['admin_browse_outstanding_perform'], ':
					</dt>
					<dd>
						<select name="todo">
							', Utils::$context['browse_type'] == 'activate' ? '
							<option value="ok">' . Lang::$txt['admin_browse_w_activate'] . '</option>' : '', '
							<option value="okemail">', Utils::$context['browse_type'] == 'approve' ? Lang::$txt['admin_browse_w_approve'] : Lang::$txt['admin_browse_w_activate'], ' ', Lang::$txt['admin_browse_w_email'], '</option>', Utils::$context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . Lang::$txt['admin_browse_w_approve_require_activate'] . '</option>', '
							<option value="reject">', Lang::$txt['admin_browse_w_reject'], '</option>
							<option value="rejectemail">', Lang::$txt['admin_browse_w_reject'], ' ', Lang::$txt['admin_browse_w_email'], '</option>
							<option value="delete">', Lang::$txt['admin_browse_w_delete'], '</option>
							<option value="deleteemail">', Lang::$txt['admin_browse_w_delete'], ' ', Lang::$txt['admin_browse_w_email'], '</option>', Utils::$context['browse_type'] == 'activate' ? '
							<option value="remind">' . Lang::$txt['admin_browse_w_remind'] . '</option>' : '', '
						</select>
					</dd>
				</dl>
				<input type="submit" value="', Lang::$txt['admin_browse_outstanding_go'], '" class="button">
				<input type="hidden" name="type" value="', Utils::$context['browse_type'], '">
				<input type="hidden" name="sort" value="', Utils::$context['approve_list']['sort']['id'], '">
				<input type="hidden" name="start" value="', Utils::$context['approve_list']['start'], '">
				<input type="hidden" name="orig_filter" value="', Utils::$context['current_filter'], '">
				<input type="hidden" name="sa" value="approve">', !empty(Utils::$context['approve_list']['sort']['desc']) ? '
				<input type="hidden" name="desc" value="1">' : '', '
			</div><!-- .windowbg -->
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</form>';
	}
}

?>