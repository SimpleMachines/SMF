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
 * Modify the search weights.
 */
function template_modify_weights()
{
	echo '
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=managesearch;sa=weights" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['search_weights'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_frequency" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a><label for="weight1_val">
					', Lang::$txt['search_weight_frequency'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_frequency" id="weight1_val" value="', empty(Config::$modSettings['search_weight_frequency']) ? '0' : Config::$modSettings['search_weight_frequency'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight1" class="search_weight">', Utils::$context['relative_weights']['search_weight_frequency'], '%</span>
				</dd>
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_age" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
					<label for="weight2_val">', Lang::$txt['search_weight_age'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_age" id="weight2_val" value="', empty(Config::$modSettings['search_weight_age']) ? '0' : Config::$modSettings['search_weight_age'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight2" class="search_weight">', Utils::$context['relative_weights']['search_weight_age'], '%</span>
				</dd>
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_length" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
					<label for="weight3_val">', Lang::$txt['search_weight_length'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_length" id="weight3_val" value="', empty(Config::$modSettings['search_weight_length']) ? '0' : Config::$modSettings['search_weight_length'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight3" class="search_weight">', Utils::$context['relative_weights']['search_weight_length'], '%</span>
				</dd>
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_subject" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
					<label for="weight4_val">', Lang::$txt['search_weight_subject'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_subject" id="weight4_val" value="', empty(Config::$modSettings['search_weight_subject']) ? '0' : Config::$modSettings['search_weight_subject'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight4" class="search_weight">', Utils::$context['relative_weights']['search_weight_subject'], '%</span>
				</dd>
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_first_message" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
					<label for="weight5_val">', Lang::$txt['search_weight_first_message'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_first_message" id="weight5_val" value="', empty(Config::$modSettings['search_weight_first_message']) ? '0' : Config::$modSettings['search_weight_first_message'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight5" class="search_weight">', Utils::$context['relative_weights']['search_weight_first_message'], '%</span>
				</dd>
				<dt>
					<a href="', Config::$scripturl, '?action=helpadmin;help=search_weight_sticky" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a>
					<label for="weight6_val">', Lang::$txt['search_weight_sticky'], ':</label>
				</dt>
				<dd>
					<span class="search_weight">
						<input type="text" name="search_weight_sticky" id="weight6_val" value="', empty(Config::$modSettings['search_weight_sticky']) ? '0' : Config::$modSettings['search_weight_sticky'], '" onchange="calculateNewValues()" size="3">
					</span>
					<span id="weight6" class="search_weight">', Utils::$context['relative_weights']['search_weight_sticky'], '%</span>
				</dd>
				<dt>
					<strong>', Lang::$txt['search_weights_total'], '</strong>
				</dt>
				<dd>
					<span id="weighttotal" class="search_weight">
						<strong>', Utils::$context['relative_weights']['total'], '</strong>
					</span>
					<span class="search_weight"><strong>100%</strong></span>
				</dd>
			</dl>
			<input type="submit" name="save" value="', Lang::$txt['search_weights_save'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-msw_token_var'], '" value="', Utils::$context['admin-msw_token'], '">
		</div><!-- .windowbg -->
	</form>';
}

/**
 * Select the search method.
 */
function template_select_search_method()
{
	echo '
	<div class="cat_bar">
		<h3 class="catbg">', Lang::$txt['search_method'], '</h3>
	</div>
	<div class="information">
		<div class="smalltext">
			<a href="', Config::$scripturl, '?action=helpadmin;help=search_why_use_index" onclick="return reqOverlayDiv(this.href);">', Lang::$txt['search_create_index_why'], '</a>
		</div>
	</div>
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=managesearch;sa=method" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['search_method'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">';

	if (!empty(Utils::$context['table_info']))
		echo '
				<dt>
					<strong>', Lang::$txt['search_method_messages_table_space'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['table_info']['data_length'], '
				</dd>
				<dt>
					<strong>', Lang::$txt['search_method_messages_index_space'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['table_info']['index_length'], '
				</dd>';
	echo '
			</dl>
			', Utils::$context['double_index'] ? '<div class="noticebox">
			' . Lang::$txt['search_double_index'] . '</div>' : '', '
			<fieldset class="search_settings floatleft">
				<legend>', Lang::$txt['search_index'], '</legend>
				<dl>
					<dt>
						<input type="radio" name="search_index" value=""', empty(Config::$modSettings['search_index']) ? ' checked' : '', '>
						', Lang::$txt['search_index_none'], '
					</dt>';

	if (Utils::$context['supports_fulltext'])
	{
		echo '
					<dt>
						<input type="radio" name="search_index" value="fulltext"', !empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'fulltext' ? ' checked' : '', empty(Utils::$context['fulltext_index']) ? ' onclick="alert(\'' . Lang::$txt['search_method_fulltext_warning'] . '\'); selectRadioByName(this.form.search_index, \'fulltext\');"' : '', '>
						', Lang::$txt['search_method_fulltext_index'], '
					</dt>
					<dd>
						<span class="smalltext">';

		if (empty(Utils::$context['fulltext_index']) && empty(Utils::$context['cannot_create_fulltext']))
			echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_no_index_exists'], ' [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=createfulltext;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';', Utils::$context['admin-msm_token_var'], '=', Utils::$context['admin-msm_token'], '">', Lang::$txt['search_method_fulltext_create'], '</a>]';

		elseif (empty(Utils::$context['fulltext_index']) && !empty(Utils::$context['cannot_create_fulltext']))
			echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_fulltext_cannot_create'];
		else
			echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_index_already_exists'], ' [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=removefulltext;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';', Utils::$context['admin-msm_token_var'], '=', Utils::$context['admin-msm_token'], '">', Lang::$txt['search_method_fulltext_remove'], '</a>]<br>
							<strong>', Lang::$txt['search_index_size'], ':</strong> ', Utils::$context['table_info']['fulltext_length'];
		echo '
						</span>
					</dd>';
	}

	echo '
					<dt>
						<input type="radio" name="search_index" value="custom"', !empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'custom' ? ' checked' : '', Utils::$context['custom_index'] ? '' : ' onclick="alert(\'' . Lang::$txt['search_index_custom_warning'] . '\'); selectRadioByName(this.form.search_method, \'1\');"', '>
						', Lang::$txt['search_index_custom'], '
					</dt>
					<dd>
						<span class="smalltext">';

	if (Utils::$context['custom_index'])
		echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_index_already_exists'], ' [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=removecustom;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';', Utils::$context['admin-msm_token_var'], '=', Utils::$context['admin-msm_token'], '">', Lang::$txt['search_index_custom_remove'], '</a>]<br>
							<strong>', Lang::$txt['search_index_size'], ':</strong> ', Utils::$context['table_info']['custom_index_length'];

	elseif (Utils::$context['partial_custom_index'])
		echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_index_partial'], ' [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=removecustom;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';', Utils::$context['admin-msm_token_var'], '=', Utils::$context['admin-msm_token'], '">', Lang::$txt['search_index_custom_remove'], '</a>] [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=createmsgindex;resume;', Utils::$context['session_var'], '=', Utils::$context['session_id'], ';', Utils::$context['admin-msm_token_var'], '=', Utils::$context['admin-msm_token'], '">', Lang::$txt['search_index_custom_resume'], '</a>]<br>
							<strong>', Lang::$txt['search_index_size'], ':</strong> ', Utils::$context['table_info']['custom_index_length'];
	else
		echo '
							<strong>', Lang::$txt['search_index_label'], ':</strong> ', Lang::$txt['search_method_no_index_exists'], ' [<a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=createmsgindex">', Lang::$txt['search_index_create_custom'], '</a>]';
	echo '
						</span>
					</dd>';

	foreach (Utils::$context['search_apis'] as $api)
	{
		if (empty($api['label']) || $api['has_template'])
			continue;

		echo '
					<dt>
						<input type="radio" name="search_index" value="', $api['setting_index'], '"', !empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == $api['setting_index'] ? ' checked' : '', '>
						', $api['label'], '
					</dt>';

		if ($api['desc'])
			echo '
					<dd>
						<span class="smalltext">', $api['desc'], '</span>
					</dd>';
	}

	echo '
				</dl>
			</fieldset>
			<fieldset class="search_settings floatright">
			<legend>', Lang::$txt['search_method'], '</legend>
				<input type="checkbox" name="search_force_index" id="search_force_index_check" value="1"', empty(Config::$modSettings['search_force_index']) ? '' : ' checked', '><label for="search_force_index_check">', Lang::$txt['search_force_index'], '</label><br>
				<input type="checkbox" name="search_match_words" id="search_match_words_check" value="1"', empty(Config::$modSettings['search_match_words']) ? '' : ' checked', '><label for="search_match_words_check">', Lang::$txt['search_match_words'], '</label>
			</fieldset>
			<br class="clear">
			<input type="submit" name="save" value="', Lang::$txt['search_method_save'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-msmpost_token_var'], '" value="', Utils::$context['admin-msmpost_token'], '">
		</div><!-- .windowbg -->
	</form>';
}

/**
 * Create a search index.
 */
function template_create_index()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=managesearch;sa=createmsgindex;step=1" method="post" accept-charset="', Utils::$context['character_set'], '" name="create_index">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['search_create_index'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<label for="predefine_select">', Lang::$txt['search_predefined'], ':</label>
				</dt>
				<dd>
					<select name="bytes_per_word" id="predefine_select">
						<option value="2">', Lang::$txt['search_predefined_small'], '</option>
						<option value="4" selected>', Lang::$txt['search_predefined_moderate'], '</option>
						<option value="5">', Lang::$txt['search_predefined_large'], '</option>
					</select>
				</dd>
			</dl>
			<hr>
			<input type="submit" name="save" value="', Lang::$txt['search_create_index_start'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</div>
	</form>';
}

/**
 * Display a progress page while creating a search index.
 */
function template_create_index_progress()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=managesearch;sa=createmsgindex;step=1" name="autoSubmit" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['search_create_index'], '</h3>
		</div>
		<div class="windowbg">
			<div>
				<p>', Lang::$txt['search_create_index_not_ready'], '</p>
				<div class="progress_bar">
					<span>', Utils::$context['percentage'], '%</span>
					<div class="bar" style="width: ', Utils::$context['percentage'], '%;"></div>
				</div>
			</div>
			<hr>
			<input type="submit" name="b" value="', Lang::$txt['search_create_index_continue'], '" class="button">
		</div>
		<input type="hidden" name="step" value="', Utils::$context['step'], '">
		<input type="hidden" name="start" value="', Utils::$context['start'], '">
		<input type="hidden" name="bytes_per_word" value="', Utils::$context['index_settings']['bytes_per_word'], '">
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<script>
		var countdown = 10;
		doAutoSubmit();

		function doAutoSubmit()
		{
			if (countdown == 0)
				document.forms.autoSubmit.submit();
			else if (countdown == -1)
				return;

			document.forms.autoSubmit.b.value = "', Lang::$txt['search_create_index_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	</script>';

}

/**
 * Done creating a search index.
 */
function template_create_index_done()
{
	echo '
	<div class="cat_bar">
		<h3 class="catbg">', Lang::$txt['search_create_index'], '</h3>
	</div>
	<div class="windowbg">
		<p>', Lang::$txt['search_create_index_done'], '</p>
		<p>
			<strong><a href="', Config::$scripturl, '?action=admin;area=managesearch;sa=method">', Lang::$txt['search_create_index_done_link'], '</a></strong>
		</p>
	</div>';
}

/**
 * Add or edit a search engine spider.
 */
function template_spider_edit()
{
	echo '
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=sengines;sa=editspiders;sid=', Utils::$context['spider']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<div class="information noup">
			', Lang::$txt['add_spider_desc'], '
		</div>
		<div class="windowbg noup">
			<dl class="settings">
				<dt>
					<strong><label for="spider_name">', Lang::$txt['spider_name'], ':</label></strong><br>
					<span class="smalltext">', Lang::$txt['spider_name_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="spider_name" id="spider_name" value="', Utils::$context['spider']['name'], '">
				</dd>
				<dt>
					<strong><label for="spider_agent">', Lang::$txt['spider_agent'], ':</label></strong><br>
					<span class="smalltext">', Lang::$txt['spider_agent_desc'], '</span>
				</dt>
				<dd>
					<input type="text" name="spider_agent" id="spider_agent" value="', Utils::$context['spider']['agent'], '">
				</dd>
				<dt>
					<strong><label for="spider_ip">', Lang::$txt['spider_ip_info'], ':</label></strong><br>
					<span class="smalltext">', Lang::$txt['spider_ip_info_desc'], '</span>
				</dt>
				<dd>
					<textarea name="spider_ip" id="spider_ip" rows="4" cols="20">', Utils::$context['spider']['ip_info'], '</textarea>
				</dd>
			</dl>
			<hr>
			<input type="submit" name="save" value="', Utils::$context['page_title'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-ses_token_var'], '" value="', Utils::$context['admin-ses_token'], '">
		</div><!-- .windowbg -->
	</form>';
}

/**
 * Show... spider... logs...
 */
function template_show_spider_logs()
{
	// Standard fields.
	template_show_list('spider_logs');

	echo '
	<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=sengines;sa=logs" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['spider_logs_delete'], '</h3>
		</div>
		<div class="windowbg">
			<p>
				', Lang::$txt['spider_logs_delete_older'], '
				<input type="text" name="older" id="older" value="7" size="3">
				', Lang::$txt['spider_logs_delete_day'], '
			</p>
			<input type="submit" name="delete_entries" value="', Lang::$txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes(Lang::$txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-sl_token_var'], '" value="', Utils::$context['admin-sl_token'], '">
		</div>
	</form>';
}

/**
 * Show... spider... stats...
 */
function template_show_spider_stats()
{
	// Standard fields.
	template_show_list('spider_stat_list');

	echo '
		<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=sengines;sa=stats" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['spider_logs_delete'], '</h3>
			</div>
			<div class="windowbg">
				<p>
					', sprintf(Lang::$txt['spider_stats_delete_older'], '<input type="text" name="older" id="older" value="90" size="3">'), '
				</p>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-ss_token_var'], '" value="', Utils::$context['admin-ss_token'], '">
				<input type="submit" name="delete_entries" value="', Lang::$txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes(Lang::$txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" class="button">
				<br>
			</div>
		</form>';
}

?>