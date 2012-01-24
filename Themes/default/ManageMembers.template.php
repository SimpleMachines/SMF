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

function template_search_members()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft">', $txt['search_for'], '</span>
					<span class="smalltext floatright">', $txt['wild_cards_allowed'], '</span>
				</h3>
			</div>
			<input type="hidden" name="sa" value="query" />
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<div class="flow_hidden">
						<div class="msearch_details floatleft">
							<dl class="settings right">
								<dt class="righttext">
									<strong>', $txt['member_id'], ':</strong>
									<select name="types[mem_id]">
										<option value="--">&lt;</option>
										<option value="-">&lt;=</option>
										<option value="=" selected="selected">=</option>
										<option value="+">&gt;=</option>
										<option value="++">&gt;</option>
									</select>
								</dt>
								<dd>
									<input type="text" name="mem_id" value="" size="6" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['age'], ':</strong>
									<select name="types[age]">
										<option value="--">&lt;</option>
										<option value="-">&lt;=</option>
										<option value="=" selected="selected">=</option>
										<option value="+">&gt;=</option>
										<option value="++">&gt;</option>
									</select>
								</dt>
								<dd>
									<input type="text" name="age" value="" size="6" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['member_postcount'], ':</strong>
									<select name="types[posts]">
										<option value="--">&lt;</option>
										<option value="-">&lt;=</option>
										<option value="=" selected="selected">=</option>
										<option value="+">&gt;=</option>
										<option value="++">&gt;</option>
									</select>
								</dt>
								<dd>
									<input type="text" name="posts" value="" size="6" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['date_registered'], ':</strong>
									<select name="types[reg_date]">
										<option value="--">&lt;</option>
										<option value="-">&lt;=</option>
										<option value="=" selected="selected">=</option>
										<option value="+">&gt;=</option>
										<option value="++">&gt;</option>
									</select>
								</dt>
								<dd>
									<input type="text" name="reg_date" value="" size="10" class="input_text" /><span class="smalltext">', $txt['date_format'], '</span>
								</dd>
								<dt class="righttext">
									<strong>', $txt['viewmembers_online'], ':</strong>
									<select name="types[last_online]">
										<option value="--">&lt;</option>
										<option value="-">&lt;=</option>
										<option value="=" selected="selected">=</option>
										<option value="+">&gt;=</option>
										<option value="++">&gt;</option>
									</select>
								</dt>
								<dd>
									<input type="text" name="last_online" value="" size="10" class="input_text" /><span class="smalltext">', $txt['date_format'], '</span>
								</dd>
							</dl>
						</div>
						<div class="msearch_details floatright">
							<dl class="settings right">
								<dt class="righttext">
									<strong>', $txt['username'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="membername" value="" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['email_address'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="email" value="" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['website'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="website" value="" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['location'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="location" value="" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['ip_address'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="ip" value="" class="input_text" />
								</dd>
								<dt class="righttext">
									<strong>', $txt['messenger_address'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="messenger" value="" class="input_text" />
								</dd>
							</dl>
						</div>
					</div>
					<div class="flow_hidden">
						<div class="msearch_details floatleft">
							<fieldset>
								<legend>', $txt['gender'], '</legend>
								<label for="gender-0"><input type="checkbox" name="gender[]" value="0" id="gender-0" checked="checked" class="input_check" /> ', $txt['undefined_gender'], '</label>&nbsp;&nbsp;
								<label for="gender-1"><input type="checkbox" name="gender[]" value="1" id="gender-1" checked="checked" class="input_check" /> ', $txt['male'], '</label>&nbsp;&nbsp;
								<label for="gender-2"><input type="checkbox" name="gender[]" value="2" id="gender-2" checked="checked" class="input_check" /> ', $txt['female'], '</label>
							</fieldset>
						</div>
						<div class="msearch_details floatright">
							<fieldset>
								<legend>', $txt['activation_status'], '</legend>
								<label for="activated-0"><input type="checkbox" name="activated[]" value="1" id="activated-0" checked="checked" class="input_check" /> ', $txt['activated'], '</label>&nbsp;&nbsp;
								<label for="activated-1"><input type="checkbox" name="activated[]" value="0" id="activated-1" checked="checked" class="input_check" /> ', $txt['not_activated'], '</label>
							</fieldset>
						</div>
					</div>
				</div>
				<span class="botslice clear_right"><span></span></span>
			</div>
			<br />
			<div class="title_bar">
				<h3 class="titlebg">', $txt['member_part_of_these_membergroups'], '</h3>
			</div>
			<div class="flow_hidden">
				<table width="49%" class="table_grid floatleft">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th">', $txt['membergroups'], '</th>
							<th scope="col">', $txt['primary'], '</th>
							<th scope="col" class="last_th">', $txt['additional'], '</th>
						</tr>
					</thead>
					<tbody>';

			foreach ($context['membergroups'] as $membergroup)
				echo '
						<tr class="windowbg2">
							<td>', $membergroup['name'], '</td>
							<td align="center">
								<input type="checkbox" name="membergroups[1][]" value="', $membergroup['id'], '" checked="checked" class="input_check" />
							</td>
							<td align="center">
								', $membergroup['can_be_additional'] ? '<input type="checkbox" name="membergroups[2][]" value="' . $membergroup['id'] . '" checked="checked" class="input_check" />' : '', '
							</td>
						</tr>';

			echo '
						<tr class="windowbg2">
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td align="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[1]\');" checked="checked" class="input_check" />
							</td>
							<td align="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[2]\');" checked="checked" class="input_check" />
							</td>
						</tr>
					</tbody>
				</table>

				<table width="49%" class="table_grid floatright">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th">
								', $txt['membergroups_postgroups'], '
							</th>
							<th class="last_th">&nbsp;</th>
						</tr>
					</thead>
					</tbody>';

			foreach ($context['postgroups'] as $postgroup)
				echo '
						<tr class="windowbg2">
							<td>
								', $postgroup['name'], '
							</td>
							<td width="40" align="center">
								<input type="checkbox" name="postgroups[]" value="', $postgroup['id'], '" checked="checked" class="input_check" />
							</td>
						</tr>';

			echo '
						<tr class="windowbg2">
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td align="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'postgroups[]\');" checked="checked" class="input_check" />
							</td>
						</tr>
					</tbody>
				</table>
			</div><br />
			<div class="righttext">
				<input type="submit" value="', $txt['search'], '" class="button_submit" />
			</div>
		</form>
	</div>
	<br class="clear" />';
}

function template_admin_browse()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	template_show_list('approve_list');

	// If we have lots of outstanding members try and make the admin's life easier.
	if ($context['approve_list']['total_num_items'] > 20)
	{
		echo '
		<br />
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="', $context['character_set'], '" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['admin_browse_outstanding'], '</h3>
			</div>
			<script type="text/javascript"><!-- // --><![CDATA[
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
			// ]]></script>

			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							', $txt['admin_browse_outstanding_days_1'], ':
						</dt>
						<dd>
							<input type="text" name="time_passed" value="14" maxlength="4" size="3" class="input_text" /> ', $txt['admin_browse_outstanding_days_2'], '.
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
					<input type="submit" value="', $txt['admin_browse_outstanding_go'], '" class="button_submit" />
					<input type="hidden" name="type" value="', $context['browse_type'], '" />
					<input type="hidden" name="sort" value="', $context['approve_list']['sort']['id'], '" />
					<input type="hidden" name="start" value="', $context['approve_list']['start'], '" />
					<input type="hidden" name="orig_filter" value="', $context['current_filter'], '" />
					<input type="hidden" name="sa" value="approve" />', !empty($context['approve_list']['sort']['desc']) ? '
					<input type="hidden" name="desc" value="1" />' : '', '
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
	}

	echo '
	</div>
	<br class="clear" />';
}

?>