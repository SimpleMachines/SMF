<?php

/**
 * This file contains all the administration functions for subscriptions.
 * (and some more than that :P)
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main entrance point for the 'Paid Subscription' screen, calling
 * the right function based on the given sub-action.
 * It defaults to sub-action 'view'.
 * Accessed from ?action=admin;area=paidsubscribe.
 * It requires admin_forum permission for admin based actions.
 */
function ManagePaidSubscriptions()
{
	global $context, $txt, $modSettings;

	// Load the required language and template.
	loadLanguage('ManagePaid');
	loadTemplate('ManagePaid');

	if (!empty($modSettings['paid_enabled']))
		$subActions = array(
			'modify' => array('ModifySubscription', 'admin_forum'),
			'modifyuser' => array('ModifyUserSubscription', 'admin_forum'),
			'settings' => array('ModifySubscriptionSettings', 'admin_forum'),
			'view' => array('ViewSubscriptions', 'admin_forum'),
			'viewsub' => array('ViewSubscribedUsers', 'admin_forum'),
		);
	else
		$subActions = array(
			'settings' => array('ModifySubscriptionSettings', 'admin_forum'),
		);

	// Default the sub-action to 'view subscriptions', but only if they have already set things up..
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($modSettings['paid_currency_symbol']) && !empty($modSettings['paid_enabled']) ? 'view' : 'settings');

	// Make sure you can do this.
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	$context['page_title'] = $txt['paid_subscriptions'];

	// Tabs for browsing the different subscription functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['paid_subscriptions'],
		'help' => '',
		'description' => $txt['paid_subscriptions_desc'],
	);
	if (!empty($modSettings['paid_enabled']))
		$context[$context['admin_menu_name']]['tab_data']['tabs'] = array(
			'view' => array(
				'description' => $txt['paid_subs_view_desc'],
			),
			'settings' => array(
				'description' => $txt['paid_subs_settings_desc'],
			),
		);

	call_integration_hook('integrate_manage_subscriptions', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Set any setting related to paid subscriptions, i.e.
 * modify which payment methods are to be used.
 * It requires the moderate_forum permission
 * Accessed from ?action=admin;area=paidsubscribe;sa=settings.
 *
 * @param bool $return_config Whether or not to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 */
function ModifySubscriptionSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sourcedir, $smcFunc, $scripturl;

	if (!empty($modSettings['paid_enabled']))
	{
		// If the currency is set to something different then we need to set it to other for this to work and set it back shortly.
		$modSettings['paid_currency'] = !empty($modSettings['paid_currency_code']) ? $modSettings['paid_currency_code'] : '';
		if (!empty($modSettings['paid_currency_code']) && !in_array($modSettings['paid_currency_code'], array('usd', 'eur', 'gbp', 'cad', 'aud')))
			$modSettings['paid_currency'] = 'other';

		// These are all the default settings.
		$config_vars = array(
			array(
				'check',
				'paid_enabled'
			),
			'',

			array(
				'select',
				'paid_email',
				array(
					0 => $txt['paid_email_no'],
					1 => $txt['paid_email_error'],
					2 => $txt['paid_email_all']
				),
				'subtext' => $txt['paid_email_desc']
			),
			array(
				'email',
				'paid_email_to',
				'subtext' => $txt['paid_email_to_desc'],
				'size' => 60
			),
			'',

			'dummy_currency' => array(
				'select',
				'paid_currency',
				array(
					'usd' => $txt['usd'],
					'eur' => $txt['eur'],
					'gbp' => $txt['gbp'],
					'cad' => $txt['cad'],
					'aud' => $txt['aud'],
					'other' => $txt['other']
				),
				'javascript' => 'onchange="toggleOther();"'
			),
			array(
				'text',
				'paid_currency_code',
				'subtext' => $txt['paid_currency_code_desc'],
				'size' => 5,
				'force_div_id' => 'custom_currency_code_div'
			),
			array(
				'text',
				'paid_currency_symbol',
				'subtext' => $txt['paid_currency_symbol_desc'],
				'size' => 8,
				'force_div_id' => 'custom_currency_symbol_div'
			),
			array(
				'check',
				'paidsubs_test',
				'subtext' => $txt['paidsubs_test_desc'],
				'onclick' => 'return document.getElementById(\'paidsubs_test\').checked ? confirm(\'' . $txt['paidsubs_test_confirm'] . '\') : true;'
			),
		);

		// Now load all the other gateway settings.
		$gateways = loadPaymentGateways();
		foreach ($gateways as $gateway)
		{
			$gatewayClass = new $gateway['display_class']();
			$setting_data = $gatewayClass->getGatewaySettings();
			if (!empty($setting_data))
			{
				$config_vars[] = array('title', $gatewayClass->title, 'text_label' => (isset($txt['paidsubs_gateway_title_' . $gatewayClass->title]) ? $txt['paidsubs_gateway_title_' . $gatewayClass->title] : $gatewayClass->title));
				$config_vars = array_merge($config_vars, $setting_data);
			}
		}

		$context['settings_message'] = $txt['paid_note'];
		$context[$context['admin_menu_name']]['current_subsection'] = 'settings';
		$context['settings_title'] = $txt['settings'];

		// We want javascript for our currency options.
		addInlineJavaScript('
		function toggleOther()
		{
			var otherOn = document.getElementById("paid_currency").value == \'other\';
			var currencydd = document.getElementById("custom_currency_code_div_dd");

			if (otherOn)
			{
				document.getElementById("custom_currency_code_div").style.display = "";
				document.getElementById("custom_currency_symbol_div").style.display = "";

				if (currencydd)
				{
					document.getElementById("custom_currency_code_div_dd").style.display = "";
					document.getElementById("custom_currency_symbol_div_dd").style.display = "";
				}
			}
			else
			{
				document.getElementById("custom_currency_code_div").style.display = "none";
				document.getElementById("custom_currency_symbol_div").style.display = "none";

				if (currencydd)
				{
					document.getElementById("custom_currency_symbol_div_dd").style.display = "none";
					document.getElementById("custom_currency_code_div_dd").style.display = "none";
				}
			}
		}
		toggleOther();', true);
	}
	else
	{
		$config_vars = array(
			array('check', 'paid_enabled'),
		);
		$context['settings_title'] = $txt['paid_subscriptions'];
	}

	// Just searching?
	if ($return_config)
		return $config_vars;

	// Get the settings template fired up.
	require_once($sourcedir . '/ManageServer.php');

	// Some important context stuff
	$context['page_title'] = $txt['settings'];
	$context['sub_template'] = 'show_settings';

	// Get the final touches in place.
	$context['post_url'] = $scripturl . '?action=admin;area=paidsubscribe;save;sa=settings';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();

		$old = !empty($modSettings['paid_enabled']);
		$new = !empty($_POST['paid_enabled']);
		if ($old != $new)
		{
			// So we're changing this fundamental status. Great.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}scheduled_tasks
				SET disabled = {int:disabled}
				WHERE task = {string:task}',
				array(
					'disabled' => $new ? 0 : 1,
					'task' => 'paid_subscriptions',
				)
			);

			// This may well affect the next trigger, whether we're enabling or not.
			require_once($sourcedir . '/ScheduledTasks.php');
			CalculateNextTrigger('paid_subscriptions');
		}

		// Check the email addresses were actually email addresses.
		if (!empty($_POST['paid_email_to']))
		{
			$email_addresses = array();
			foreach (explode(',', $_POST['paid_email_to']) as $email)
			{
				$email = trim($email);
				if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
					$email_addresses[] = $email;
				$_POST['paid_email_to'] = implode(',', $email_addresses);
			}
		}

		// Can only handle this stuff if it's already enabled...
		if (!empty($modSettings['paid_enabled']))
		{
			// Sort out the currency stuff.
			if ($_POST['paid_currency'] != 'other')
			{
				$_POST['paid_currency_code'] = $_POST['paid_currency'];
				$_POST['paid_currency_symbol'] = $txt[$_POST['paid_currency'] . '_symbol'];
			}
			unset($config_vars['dummy_currency']);
		}

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;

		redirectexit('action=admin;area=paidsubscribe;sa=settings');
	}

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * View a list of all the current subscriptions
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=paidsubscribe;sa=view.
 */
function ViewSubscriptions()
{
	global $context, $txt, $modSettings, $sourcedir, $scripturl;

	// Not made the settings yet?
	if (empty($modSettings['paid_currency_symbol']))
		fatal_lang_error('paid_not_set_currency', false, $scripturl . '?action=admin;area=paidsubscribe;sa=settings');

	// Some basic stuff.
	$context['page_title'] = $txt['paid_subs_view'];
	loadSubscriptions();

	$listOptions = array(
		'id' => 'subscription_list',
		'title' => $txt['subscriptions'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=paidsubscribe;sa=view',
		'get_items' => array(
			'function' => function($start, $items_per_page) use ($context)
			{
				$subscriptions = array();
				$counter = 0;
				$start++;

				foreach ($context['subscriptions'] as $data)
				{
					if (++$counter < $start)
						continue;
					elseif ($counter == $start + $items_per_page)
						break;

					$subscriptions[] = $data;
				}
				return $subscriptions;
			},
		),
		'get_count' => array(
			'function' => function() use ($context)
			{
				return count($context['subscriptions']);
			},
		),
		'no_items_label' => $txt['paid_none_yet'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['paid_name'],
					'style' => 'width: 35%;',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=paidsubscribe;sa=viewsub;sid=%2$s">%3$s</a>', $scripturl, $rowData['id'], $rowData['name']);
					},
				),
			),
			'cost' => array(
				'header' => array(
					'value' => $txt['paid_cost'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return $rowData['flexible'] ? '<em>' . $txt['flexible'] . '</em>' : $rowData['cost'] . ' / ' . $rowData['length'];
					},
				),
			),
			'pending' => array(
				'header' => array(
					'value' => $txt['paid_pending'],
					'style' => 'width: 18%;',
					'class' => 'centercol',
				),
				'data' => array(
					'db_htmlsafe' => 'pending',
					'class' => 'centercol',
				),
			),
			'finished' => array(
				'header' => array(
					'value' => $txt['paid_finished'],
					'class' => 'centercol',
				),
				'data' => array(
					'db_htmlsafe' => 'finished',
					'class' => 'centercol',
				),
			),
			'total' => array(
				'header' => array(
					'value' => $txt['paid_active'],
					'class' => 'centercol',
				),
				'data' => array(
					'db_htmlsafe' => 'total',
					'class' => 'centercol',
				),
			),
			'is_active' => array(
				'header' => array(
					'value' => $txt['paid_is_active'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return '<span style="color: ' . ($rowData['active'] ? 'green' : 'red') . '">' . ($rowData['active'] ? $txt['yes'] : $txt['no']) . '</span>';
					},
					'class' => 'centercol',
				),
			),
			'modify' => array(
				'data' => array(
					'function' => function($rowData) use ($txt, $scripturl)
					{
						return '<a href="' . $scripturl . '?action=admin;area=paidsubscribe;sa=modify;sid=' . $rowData['id'] . '">' . $txt['modify'] . '</a>';
					},
					'class' => 'centercol',
				),
			),
			'delete' => array(
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return '<a href="' . $scripturl . '?action=admin;area=paidsubscribe;sa=modify;delete;sid=' . $rowData['id'] . '">' . $txt['delete'] . '</a>';
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=paidsubscribe;sa=modify',
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<input type="submit" name="add" value="' . $txt['paid_add_subscription'] . '" class="button">',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="add" value="' . $txt['paid_add_subscription'] . '" class="button">',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'subscription_list';
}

/**
 * Adding, editing and deleting subscriptions.
 * Accessed from ?action=admin;area=paidsubscribe;sa=modify.
 */
function ModifySubscription()
{
	global $context, $txt, $smcFunc;

	$context['sub_id'] = isset($_REQUEST['sid']) ? (int) $_REQUEST['sid'] : 0;
	$context['action_type'] = $context['sub_id'] ? (isset($_REQUEST['delete']) ? 'delete' : 'edit') : 'add';

	// Setup the template.
	$context['sub_template'] = $context['action_type'] == 'delete' ? 'delete_subscription' : 'modify_subscription';
	$context['page_title'] = $txt['paid_' . $context['action_type'] . '_subscription'];

	// Delete it?
	if (isset($_POST['delete_confirm']) && isset($_REQUEST['delete']))
	{
		checkSession();
		validateToken('admin-pmsd');

		// Before we delete the subscription we need to find out if anyone currently has said subscription.
		$request = $smcFunc['db_query']('', '
			SELECT ls.id_member, ls.old_id_group, mem.id_group, mem.additional_groups
			FROM {db_prefix}log_subscribed AS ls
				INNER JOIN {db_prefix}members AS mem ON (ls.id_member = mem.id_member)
			WHERE id_subscribe = {int:current_subscription}
				AND status = {int:is_active}',
			array(
				'current_subscription' => $context['sub_id'],
				'is_active' => 1,
			)
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$id_member = array_shift($row);
			$members[$id_member] = $row;
		}
		$smcFunc['db_free_result']($request);

		// If there are any members with this subscription, we have to do some more work before we go any further.
		if (!empty($members))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_group, add_groups
				FROM {db_prefix}subscriptions
				WHERE id_subscribe = {int:current_subscription}',
				array(
					'current_subscription' => $context['sub_id'],
				)
			);
			$id_group = 0;
			$add_groups = '';
			if ($smcFunc['db_num_rows']($request))
				list ($id_group, $add_groups) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$changes = array();

			// Is their group changing? This subscription may not have changed primary group.
			if (!empty($id_group))
			{
				foreach ($members as $id_member => $member_data)
				{
					// If their current primary group isn't what they had before the subscription, and their current group was
					// granted by the sub, remove it.
					if ($member_data['old_id_group'] != $member_data['id_group'] && $member_data['id_group'] == $id_group)
						$changes[$id_member]['id_group'] = $member_data['old_id_group'];
				}
			}

			// Did this subscription add secondary groups?
			if (!empty($add_groups))
			{
				$add_groups = explode(',', $add_groups);
				foreach ($members as $id_member => $member_data)
				{
					// First let's get their groups sorted.
					$current_groups = explode(',', $member_data['additional_groups']);
					$new_groups = implode(',', array_diff($current_groups, $add_groups));
					if ($new_groups != $member_data['additional_groups'])
						$changes[$id_member]['additional_groups'] = $new_groups;
				}
			}

			// We're going through changes...
			if (!empty($changes))
				foreach ($changes as $id_member => $new_values)
					updateMemberData($id_member, $new_values);
		}

		// Delete the subscription
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}subscriptions
			WHERE id_subscribe = {int:current_subscription}',
			array(
				'current_subscription' => $context['sub_id'],
			)
		);

		// And delete any subscriptions to it to clear the phantom data too.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_subscribe = {int:current_subscription}',
			array(
				'current_subscription' => $context['sub_id'],
			)
		);

		call_integration_hook('integrate_delete_subscription', array($context['sub_id']));

		redirectexit('action=admin;area=paidsubscribe;view');
	}

	// Saving?
	if (isset($_POST['save']))
	{
		checkSession();

		// Some cleaning...
		$isActive = isset($_POST['active']) ? 1 : 0;
		$isRepeatable = isset($_POST['repeatable']) ? 1 : 0;
		$allowpartial = isset($_POST['allow_partial']) ? 1 : 0;
		$reminder = isset($_POST['reminder']) ? (int) $_POST['reminder'] : 0;
		$emailComplete = strlen($_POST['emailcomplete']) > 10 ? trim($_POST['emailcomplete']) : '';

		// Is this a fixed one?
		if ($_POST['duration_type'] == 'fixed')
		{
			// There are sanity check limits on these things.
			$limits = array(
				'D' => 90,
				'W' => 52,
				'M' => 24,
				'Y' => 5,
			);
			if (empty($_POST['span_unit']) || empty($limits[$_POST['span_unit']]) || empty($_POST['span_value']) || $_POST['span_value'] < 1)
				fatal_lang_error('paid_invalid_duration', false);

			if ($_POST['span_value'] > $limits[$_POST['span_unit']])
				fatal_lang_error('paid_invalid_duration_' . $_POST['span_unit'], false);

			// Clean the span.
			$span = $_POST['span_value'] . $_POST['span_unit'];

			// Sort out the cost.
			$cost = array('fixed' => sprintf('%01.2f', strtr($_POST['cost'], ',', '.')));

			// There needs to be something.
			if (empty($_POST['span_value']) || empty($_POST['cost']))
				fatal_lang_error('paid_no_cost_value');
		}
		// Flexible is harder but more fun ;)
		else
		{
			$span = 'F';

			$cost = array(
				'day' => sprintf('%01.2f', strtr($_POST['cost_day'], ',', '.')),
				'week' => sprintf('%01.2f', strtr($_POST['cost_week'], ',', '.')),
				'month' => sprintf('%01.2f', strtr($_POST['cost_month'], ',', '.')),
				'year' => sprintf('%01.2f', strtr($_POST['cost_year'], ',', '.')),
			);

			if (empty($_POST['cost_day']) && empty($_POST['cost_week']) && empty($_POST['cost_month']) && empty($_POST['cost_year']))
				fatal_lang_error('paid_all_freq_blank');
		}
		$cost = $smcFunc['json_encode']($cost);

		// Having now validated everything that might throw an error, let's also now deal with the token.
		validateToken('admin-pms');

		// Yep, time to do additional groups.
		$addgroups = array();
		if (!empty($_POST['addgroup']))
			foreach ($_POST['addgroup'] as $id => $dummy)
				$addgroups[] = (int) $id;
		$addgroups = implode(',', $addgroups);

		// Is it new?!
		if ($context['action_type'] == 'add')
		{
			$id_subscribe = $smcFunc['db_insert']('',
				'{db_prefix}subscriptions',
				array(
					'name' => 'string-60', 'description' => 'string-255', 'active' => 'int', 'length' => 'string-4', 'cost' => 'string',
					'id_group' => 'int', 'add_groups' => 'string-40', 'repeatable' => 'int', 'allow_partial' => 'int', 'email_complete' => 'string',
					'reminder' => 'int',
				),
				array(
					$_POST['name'], $_POST['desc'], $isActive, $span, $cost,
					$_POST['prim_group'], $addgroups, $isRepeatable, $allowpartial, $emailComplete,
					$reminder,
				),
				array('id_subscribe'),
				1
			);
		}
		// Otherwise must be editing.
		else
		{
			// Don't do groups if there are active members
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}log_subscribed
				WHERE id_subscribe = {int:current_subscription}
					AND status = {int:is_active}',
				array(
					'current_subscription' => $context['sub_id'],
					'is_active' => 1,
				)
			);
			list ($disableGroups) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$smcFunc['db_query']('substring', '
				UPDATE {db_prefix}subscriptions
					SET name = SUBSTRING({string:name}, 1, 60), description = SUBSTRING({string:description}, 1, 255), active = {int:is_active},
					length = SUBSTRING({string:length}, 1, 4), cost = {string:cost}' . ($disableGroups ? '' : ', id_group = {int:id_group},
					add_groups = {string:additional_groups}') . ', repeatable = {int:repeatable}, allow_partial = {int:allow_partial},
					email_complete = {string:email_complete}, reminder = {int:reminder}
				WHERE id_subscribe = {int:current_subscription}',
				array(
					'is_active' => $isActive,
					'id_group' => !empty($_POST['prim_group']) ? $_POST['prim_group'] : 0,
					'repeatable' => $isRepeatable,
					'allow_partial' => $allowpartial,
					'reminder' => $reminder,
					'current_subscription' => $context['sub_id'],
					'name' => $_POST['name'],
					'description' => $_POST['desc'],
					'length' => $span,
					'cost' => $cost,
					'additional_groups' => !empty($addgroups) ? $addgroups : '',
					'email_complete' => $emailComplete,
				)
			);
		}
		call_integration_hook('integrate_save_subscription', array(($context['action_type'] == 'add' ? $id_subscribe : $context['sub_id']), $_POST['name'], $_POST['desc'], $isActive, $span, $cost, $_POST['prim_group'], $addgroups, $isRepeatable, $allowpartial, $emailComplete, $reminder));

		redirectexit('action=admin;area=paidsubscribe;view');
	}

	// Defaults.
	if ($context['action_type'] == 'add')
	{
		$context['sub'] = array(
			'name' => '',
			'desc' => '',
			'cost' => array(
				'fixed' => 0,
			),
			'span' => array(
				'value' => '',
				'unit' => 'D',
			),
			'prim_group' => 0,
			'add_groups' => array(),
			'active' => 1,
			'repeatable' => 1,
			'allow_partial' => 0,
			'duration' => 'fixed',
			'email_complete' => '',
			'reminder' => 0,
		);
	}
	// Otherwise load up all the details.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT name, description, cost, length, id_group, add_groups, active, repeatable, allow_partial, email_complete, reminder
			FROM {db_prefix}subscriptions
			WHERE id_subscribe = {int:current_subscription}
			LIMIT 1',
			array(
				'current_subscription' => $context['sub_id'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Sort the date.
			preg_match('~(\d*)(\w)~', $row['length'], $match);
			if (isset($match[2]))
			{
				$span_value = $match[1];
				$span_unit = $match[2];
			}
			else
			{
				$span_value = 0;
				$span_unit = 'D';
			}

			// Is this a flexible one?
			if ($row['length'] == 'F')
				$isFlexible = true;
			else
				$isFlexible = false;

			$context['sub'] = array(
				'name' => $row['name'],
				'desc' => $row['description'],
				'cost' => $smcFunc['json_decode']($row['cost'], true),
				'span' => array(
					'value' => $span_value,
					'unit' => $span_unit,
				),
				'prim_group' => $row['id_group'],
				'add_groups' => explode(',', $row['add_groups']),
				'active' => $row['active'],
				'repeatable' => $row['repeatable'],
				'allow_partial' => $row['allow_partial'],
				'duration' => $isFlexible ? 'flexible' : 'fixed',
				'email_complete' => $smcFunc['htmlspecialchars']($row['email_complete']),
				'reminder' => $row['reminder'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Does this have members who are active?
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_subscribed
			WHERE id_subscribe = {int:current_subscription}
				AND status = {int:is_active}',
			array(
				'current_subscription' => $context['sub_id'],
				'is_active' => 1,
			)
		);
		list ($context['disable_groups']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Load up all the groups.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND min_posts = {int:min_posts}',
		array(
			'moderator_group' => 3,
			'min_posts' => -1,
		)
	);
	$context['groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['groups'][$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);

	// This always happens.
	createToken($context['action_type'] == 'delete' ? 'admin-pmsd' : 'admin-pms');
}

/**
 * View all the users subscribed to a particular subscription.
 * Requires the admin_forum permission.
 * Accessed from ?action=admin;area=paidsubscribe;sa=viewsub.
 *
 * Subscription ID is required, in the form of $_GET['sid'].
 */
function ViewSubscribedUsers()
{
	global $context, $txt, $scripturl, $smcFunc, $sourcedir, $modSettings;

	// Setup the template.
	$context['page_title'] = $txt['viewing_users_subscribed'];

	// ID of the subscription.
	$context['sub_id'] = (int) $_REQUEST['sid'];

	// Load the subscription information.
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, name, description, cost, length, id_group, add_groups, active
		FROM {db_prefix}subscriptions
		WHERE id_subscribe = {int:current_subscription}',
		array(
			'current_subscription' => $context['sub_id'],
		)
	);
	// Something wrong?
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);
	// Do the subscription context.
	$row = $smcFunc['db_fetch_assoc']($request);
	$context['subscription'] = array(
		'id' => $row['id_subscribe'],
		'name' => $row['name'],
		'desc' => $row['description'],
		'active' => $row['active'],
	);
	$smcFunc['db_free_result']($request);

	// Are we searching for people?
	$search_string = isset($_POST['ssearch']) && !empty($_POST['sub_search']) ? ' AND COALESCE(mem.real_name, {string:guest}) LIKE {string:search}' : '';
	$search_vars = empty($_POST['sub_search']) ? array() : array('search' => '%' . $_POST['sub_search'] . '%', 'guest' => $txt['guest']);

	$listOptions = array(
		'id' => 'subscribed_users_list',
		'title' => sprintf($txt['view_users_subscribed'], $row['name']),
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id'],
		'default_sort_col' => 'name',
		'get_items' => array(
			'function' => 'list_getSubscribedUsers',
			'params' => array(
				$context['sub_id'],
				$search_string,
				$search_vars,
			),
		),
		'get_count' => array(
			'function' => 'list_getSubscribedUserCount',
			'params' => array(
				$context['sub_id'],
				$search_string,
				$search_vars,
			),
		),
		'no_items_label' => $txt['no_subscribers'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['who_member'],
					'style' => 'width: 20%;',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return $rowData['id_member'] == 0 ? $txt['guest'] : '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '">' . $rowData['name'] . '</a>';
					},
				),
				'sort' => array(
					'default' => 'name',
					'reverse' => 'name DESC',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['paid_status'],
					'style' => 'width: 10%;',
				),
				'data' => array(
					'db_htmlsafe' => 'status_text',
				),
				'sort' => array(
					'default' => 'status',
					'reverse' => 'status DESC',
				),
			),
			'payments_pending' => array(
				'header' => array(
					'value' => $txt['paid_payments_pending'],
					'style' => 'width: 15%;',
				),
				'data' => array(
					'db_htmlsafe' => 'pending',
				),
				'sort' => array(
					'default' => 'payments_pending',
					'reverse' => 'payments_pending DESC',
				),
			),
			'start_time' => array(
				'header' => array(
					'value' => $txt['start_date'],
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db_htmlsafe' => 'start_date',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'start_time',
					'reverse' => 'start_time DESC',
				),
			),
			'end_time' => array(
				'header' => array(
					'value' => $txt['end_date'],
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db_htmlsafe' => 'end_date',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'end_time',
					'reverse' => 'end_time DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'style' => 'width: 10%;',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return '<a href="' . $scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $rowData['id'] . '">' . $txt['modify'] . '</a>';
					},
					'class' => 'centercol',
				),
			),
			'delete' => array(
				'header' => array(
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="delsub[' . $rowData['id'] . ']">';
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;sid=' . $context['sub_id'],
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="add" value="' . $txt['add_subscriber'] . '" class="button">
					<input type="submit" name="finished" value="' . $txt['complete_selected'] . '" data-confirm="' . $txt['complete_are_sure'] . '" class="button you_sure">
					<input type="submit" name="delete" value="' . $txt['delete_selected'] . '" data-confirm="' . $txt['delete_are_sure'] . '" class="button you_sure">
				',
			),
			array(
				'position' => 'top_of_list',
				'value' => '
					<div class="flow_auto">
						<input type="submit" name="ssearch" value="' . $txt['search_sub'] . '" class="button" style="margin-top: 3px;">
						<input type="text" name="sub_search" value="" class="floatright">
					</div>
				',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'subscribed_users_list';
}

/**
 * Returns how many people are subscribed to a paid subscription.
 *
 * @todo refactor away
 *
 * @param int $id_sub The ID of the subscription
 * @param string $search_string A search string
 * @param array $search_vars An array of variables for the search string
 * @return int The number of subscribed users matching the given parameters
 */
function list_getSubscribedUserCount($id_sub, $search_string, $search_vars = array())
{
	global $smcFunc;

	// Get the total amount of users.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS total_subs
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_subscribe = {int:current_subscription} ' . $search_string . '
			AND (ls.end_time != {int:no_end_time} OR ls.payments_pending != {int:no_pending_payments})',
		array_merge($search_vars, array(
			'current_subscription' => $id_sub,
			'no_end_time' => 0,
			'no_pending_payments' => 0,
		))
	);
	list ($memberCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $memberCount;
}

/**
 * Return the subscribed users list, for the given parameters.
 *
 * @todo refactor outta here
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $id_sub The ID of the subscription
 * @param string $search_string A search string
 * @param array $search_vars The variables for the search string
 * @return array An array of information about the subscribed users matching the given parameters
 */
function list_getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars = array())
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT ls.id_sublog, COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, {string:guest}) AS name, ls.start_time, ls.end_time,
			ls.status, ls.payments_pending
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_subscribe = {int:current_subscription} ' . $search_string . '
			AND (ls.end_time != {int:no_end_time} OR ls.payments_pending != {int:no_payments_pending})
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array_merge($search_vars, array(
			'current_subscription' => $id_sub,
			'no_end_time' => 0,
			'no_payments_pending' => 0,
			'guest' => $txt['guest'],
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		))
	);
	$subscribers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$subscribers[] = array(
			'id' => $row['id_sublog'],
			'id_member' => $row['id_member'],
			'name' => $row['name'],
			'start_date' => timeformat($row['start_time'], false),
			'end_date' => $row['end_time'] == 0 ? 'N/A' : timeformat($row['end_time'], false),
			'pending' => $row['payments_pending'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] == 0 ? $txt['paid_finished'] : $txt['paid_pending']) : $txt['paid_active'],
		);
	$smcFunc['db_free_result']($request);

	return $subscribers;
}

/**
 * Edit or add a user subscription.
 * Accessed from ?action=admin;area=paidsubscribe;sa=modifyuser.
 */
function ModifyUserSubscription()
{
	global $context, $txt, $modSettings, $smcFunc;

	loadSubscriptions();

	$context['log_id'] = isset($_REQUEST['lid']) ? (int) $_REQUEST['lid'] : 0;
	$context['sub_id'] = isset($_REQUEST['sid']) ? (int) $_REQUEST['sid'] : 0;
	$context['action_type'] = $context['log_id'] ? 'edit' : 'add';

	// Setup the template.
	$context['sub_template'] = 'modify_user_subscription';
	$context['page_title'] = $txt[$context['action_type'] . '_subscriber'];

	// If we haven't been passed the subscription ID get it.
	if ($context['log_id'] && !$context['sub_id'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_subscribe
			FROM {db_prefix}log_subscribed
			WHERE id_sublog = {int:current_log_item}',
			array(
				'current_log_item' => $context['log_id'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);
		list ($context['sub_id']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	if (!isset($context['subscriptions'][$context['sub_id']]))
		fatal_lang_error('no_access', false);
	$context['current_subscription'] = $context['subscriptions'][$context['sub_id']];

	// Searching?
	if (isset($_POST['ssearch']))
	{
		return ViewSubscribedUsers();
	}
	// Saving?
	elseif (isset($_REQUEST['save_sub']))
	{
		checkSession();

		// Work out the dates...
		$starttime = mktime($_POST['hour'], $_POST['minute'], 0, $_POST['month'], $_POST['day'], $_POST['year']);
		$endtime = mktime($_POST['hourend'], $_POST['minuteend'], 0, $_POST['monthend'], $_POST['dayend'], $_POST['yearend']);

		// Status.
		$status = $_POST['status'];

		// New one?
		if (empty($context['log_id']))
		{
			// Find the user...
			$request = $smcFunc['db_query']('', '
				SELECT id_member, id_group
				FROM {db_prefix}members
				WHERE real_name = {string:name}
				LIMIT 1',
				array(
					'name' => $_POST['name'],
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('error_member_not_found');

			list ($id_member, $id_group) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Ensure the member doesn't already have a subscription!
			$request = $smcFunc['db_query']('', '
				SELECT id_subscribe
				FROM {db_prefix}log_subscribed
				WHERE id_subscribe = {int:current_subscription}
					AND id_member = {int:current_member}',
				array(
					'current_subscription' => $context['sub_id'],
					'current_member' => $id_member,
				)
			);
			if ($smcFunc['db_num_rows']($request) != 0)
				fatal_lang_error('member_already_subscribed');
			$smcFunc['db_free_result']($request);

			// Actually put the subscription in place.
			if ($status == 1)
				addSubscription($context['sub_id'], $id_member, 0, $starttime, $endtime);
			else
			{
				$smcFunc['db_insert']('',
					'{db_prefix}log_subscribed',
					array(
						'id_subscribe' => 'int', 'id_member' => 'int', 'old_id_group' => 'int', 'start_time' => 'int',
						'end_time' => 'int', 'status' => 'int', 'pending_details' => 'string-65534'
					),
					array(
						$context['sub_id'], $id_member, $id_group, $starttime,
						$endtime, $status, $smcFunc['json_encode'](array())
					),
					array('id_sublog')
				);

			}
		}
		// Updating.
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member, status
				FROM {db_prefix}log_subscribed
				WHERE id_sublog = {int:current_log_item}',
				array(
					'current_log_item' => $context['log_id'],
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('no_access', false);

			list ($id_member, $old_status) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Pick the right permission stuff depending on what the status is changing from/to.
			if ($old_status == 1 && $status != 1)
				removeSubscription($context['sub_id'], $id_member);
			elseif ($status == 1 && $old_status != 1)
			{
				addSubscription($context['sub_id'], $id_member, 0, $starttime, $endtime);
			}
			else
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_subscribed
					SET start_time = {int:start_time}, end_time = {int:end_time}, status = {int:status}
					WHERE id_sublog = {int:current_log_item}',
					array(
						'start_time' => $starttime,
						'end_time' => $endtime,
						'status' => $status,
						'current_log_item' => $context['log_id'],
					)
				);
			}
		}

		// Done - redirect...
		redirectexit('action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id']);
	}
	// Deleting?
	elseif (isset($_REQUEST['delete']) || isset($_REQUEST['finished']))
	{
		checkSession();

		// Do the actual deletes!
		if (!empty($_REQUEST['delsub']))
		{
			$toDelete = array();
			foreach ($_REQUEST['delsub'] as $id => $dummy)
				$toDelete[] = (int) $id;

			$request = $smcFunc['db_query']('', '
				SELECT id_subscribe, id_member
				FROM {db_prefix}log_subscribed
				WHERE id_sublog IN ({array_int:subscription_list})',
				array(
					'subscription_list' => $toDelete,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				removeSubscription($row['id_subscribe'], $row['id_member'], isset($_REQUEST['delete']));
			$smcFunc['db_free_result']($request);
		}
		redirectexit('action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id']);
	}

	// Default attributes.
	if ($context['action_type'] == 'add')
	{
		$context['sub'] = array(
			'id' => 0,
			'start' => array(
				'year' => (int) strftime('%Y', time()),
				'month' => (int) strftime('%m', time()),
				'day' => (int) strftime('%d', time()),
				'hour' => (int) strftime('%H', time()),
				'min' => (int) strftime('%M', time()) < 10 ? '0' . (int) strftime('%M', time()) : (int) strftime('%M', time()),
				'last_day' => 0,
			),
			'end' => array(
				'year' => (int) strftime('%Y', time()),
				'month' => (int) strftime('%m', time()),
				'day' => (int) strftime('%d', time()),
				'hour' => (int) strftime('%H', time()),
				'min' => (int) strftime('%M', time()) < 10 ? '0' . (int) strftime('%M', time()) : (int) strftime('%M', time()),
				'last_day' => 0,
			),
			'status' => 1,
		);
		$context['sub']['start']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
		$context['sub']['end']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));

		if (isset($_GET['uid']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:current_member}',
				array(
					'current_member' => (int) $_GET['uid'],
				)
			);
			list ($context['sub']['username']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
			$context['sub']['username'] = '';
	}
	// Otherwise load the existing info.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT ls.id_sublog, ls.id_subscribe, ls.id_member, start_time, end_time, status, payments_pending, pending_details,
				COALESCE(mem.real_name, {string:blank_string}) AS username
			FROM {db_prefix}log_subscribed AS ls
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
			WHERE ls.id_sublog = {int:current_subscription_item}
			LIMIT 1',
			array(
				'current_subscription_item' => $context['log_id'],
				'blank_string' => '',
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Any pending payments?
		$context['pending_payments'] = array();
		if (!empty($row['pending_details']))
		{
			$pending_details = $smcFunc['json_decode']($row['pending_details'], true);
			foreach ($pending_details as $id => $pending)
			{
				// Only this type need be displayed.
				if ($pending[3] == 'payback')
				{
					// Work out what the options were.
					$costs = $smcFunc['json_decode']($context['current_subscription']['real_cost'], true);

					if ($context['current_subscription']['real_length'] == 'F')
					{
						foreach ($costs as $duration => $cost)
						{
							if ($cost != 0 && $cost == $pending[1] && $duration == $pending[2])
								$context['pending_payments'][$id] = array(
									'desc' => sprintf($modSettings['paid_currency_symbol'], $cost . '/' . $txt[$duration]),
								);
						}
					}
					elseif ($costs['fixed'] == $pending[1])
					{
						$context['pending_payments'][$id] = array(
							'desc' => sprintf($modSettings['paid_currency_symbol'], $costs['fixed']),
						);
					}
				}
			}

			// Check if we are adding/removing any.
			if (isset($_GET['pending']))
			{
				foreach ($pending_details as $id => $pending)
				{
					// Found the one to action?
					if ($_GET['pending'] == $id && $pending[3] == 'payback' && isset($context['pending_payments'][$id]))
					{
						// Flexible?
						if (isset($_GET['accept']))
							addSubscription($context['current_subscription']['id'], $row['id_member'], $context['current_subscription']['real_length'] == 'F' ? strtoupper(substr($pending[2], 0, 1)) : 0);
						unset($pending_details[$id]);

						$new_details = $smcFunc['json_encode']($pending_details);

						// Update the entry.
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}log_subscribed
							SET payments_pending = payments_pending - 1, pending_details = {string:pending_details}
							WHERE id_sublog = {int:current_subscription_item}',
							array(
								'current_subscription_item' => $context['log_id'],
								'pending_details' => $new_details,
							)
						);

						// Reload
						redirectexit('action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $context['log_id']);
					}
				}
			}
		}

		$context['sub_id'] = $row['id_subscribe'];
		$context['sub'] = array(
			'id' => 0,
			'start' => array(
				'year' => (int) strftime('%Y', $row['start_time']),
				'month' => (int) strftime('%m', $row['start_time']),
				'day' => (int) strftime('%d', $row['start_time']),
				'hour' => (int) strftime('%H', $row['start_time']),
				'min' => (int) strftime('%M', $row['start_time']) < 10 ? '0' . (int) strftime('%M', $row['start_time']) : (int) strftime('%M', $row['start_time']),
				'last_day' => 0,
			),
			'end' => array(
				'year' => (int) strftime('%Y', $row['end_time']),
				'month' => (int) strftime('%m', $row['end_time']),
				'day' => (int) strftime('%d', $row['end_time']),
				'hour' => (int) strftime('%H', $row['end_time']),
				'min' => (int) strftime('%M', $row['end_time']) < 10 ? '0' . (int) strftime('%M', $row['end_time']) : (int) strftime('%M', $row['end_time']),
				'last_day' => 0,
			),
			'status' => $row['status'],
			'username' => $row['username'],
		);
		$context['sub']['start']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
		$context['sub']['end']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));
	}

	loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
}

/**
 * Reapplies all subscription rules for each of the users.
 *
 * @param array $users An array of user IDs
 */
function reapplySubscriptions($users)
{
	global $smcFunc;

	// Make it an array.
	if (!is_array($users))
		$users = array($users);

	// Get all the members current groups.
	$groups = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_group, additional_groups
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:user_list})',
		array(
			'user_list' => $users,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$groups[$row['id_member']] = array(
			'primary' => $row['id_group'],
			'additional' => explode(',', $row['additional_groups']),
		);
	}
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT ls.id_member, ls.old_id_group, s.id_group, s.add_groups
		FROM {db_prefix}log_subscribed AS ls
			INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
		WHERE ls.id_member IN ({array_int:user_list})
			AND ls.end_time > {int:current_time}',
		array(
			'user_list' => $users,
			'current_time' => time(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Specific primary group?
		if ($row['id_group'] != 0)
		{
			// If this is changing - add the old one to the additional groups so it's not lost.
			if ($row['id_group'] != $groups[$row['id_member']]['primary'])
				$groups[$row['id_member']]['additional'][] = $groups[$row['id_member']]['primary'];
			$groups[$row['id_member']]['primary'] = $row['id_group'];
		}

		// Additional groups.
		if (!empty($row['add_groups']))
			$groups[$row['id_member']]['additional'] = array_merge($groups[$row['id_member']]['additional'], explode(',', $row['add_groups']));
	}
	$smcFunc['db_free_result']($request);

	// Update all the members.
	foreach ($groups as $id => $group)
	{
		$group['additional'] = array_unique($group['additional']);
		foreach ($group['additional'] as $key => $value)
			if (empty($value))
				unset($group['additional'][$key]);
		$addgroups = implode(',', $group['additional']);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'primary_group' => $group['primary'],
				'current_member' => $id,
				'additional_groups' => $addgroups,
			)
		);
	}
}

/**
 * Add or extend a subscription of a user.
 *
 * @param int $id_subscribe The subscription ID
 * @param int $id_member The ID of the member
 * @param int|string $renewal 0 if we're forcing start/end time, otherwise a string indicating how long to renew the subscription for ('D', 'W', 'M' or 'Y')
 * @param int $forceStartTime If set, forces the subscription to start at the specified time
 * @param int $forceEndTime If set, forces the subscription to end at the specified time
 */
function addSubscription($id_subscribe, $id_member, $renewal = 0, $forceStartTime = 0, $forceEndTime = 0)
{
	global $context, $smcFunc;

	// Take the easy way out...
	loadSubscriptions();

	// Exists, yes?
	if (!isset($context['subscriptions'][$id_subscribe]))
		return;

	$curSub = $context['subscriptions'][$id_subscribe];

	// Grab the duration.
	$duration = $curSub['num_length'];

	// If this is a renewal change the duration to be correct.
	if (!empty($renewal))
	{
		switch ($renewal)
		{
			case 'D':
				$duration = 86400;
				break;
			case 'W':
				$duration = 604800;
				break;
			case 'M':
				$duration = 2629743;
				break;
			case 'Y':
				$duration = 31556926;
				break;
			default:
				break;
		}
	}

	// Firstly, see whether it exists, and is active. If so then this is meerly an extension.
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, end_time, start_time
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}
			AND status = {int:is_active}',
		array(
			'current_subscription' => $id_subscribe,
			'current_member' => $id_member,
			'is_active' => 1,
		)
	);

	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $smcFunc['db_fetch_row']($request);

		// If this has already expired but is active, extension means the period from now.
		if ($endtime < time())
			$endtime = time();
		if ($starttime == 0)
			$starttime = time();

		// Work out the new expiry date.
		$endtime += $duration;

		if ($forceEndTime != 0)
			$endtime = $forceEndTime;

		// As everything else should be good, just update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET end_time = {int:end_time}, start_time = {int:start_time}, reminder_sent = {int:no_reminder_sent}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'end_time' => $endtime,
				'start_time' => $starttime,
				'current_subscription_item' => $id_sublog,
				'no_reminder_sent' => 0,
			)
		);

		return;
	}
	$smcFunc['db_free_result']($request);

	// If we're here, that means we don't have an active subscription - that means we need to do some work!
	$request = $smcFunc['db_query']('', '
		SELECT m.id_group, m.additional_groups
		FROM {db_prefix}members AS m
		WHERE m.id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
		)
	);

	// Just in case the member doesn't exist.
	if ($smcFunc['db_num_rows']($request) == 0)
		return;

	list ($old_id_group, $additional_groups) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Prepare additional groups.
	$newAddGroups = explode(',', $curSub['add_groups']);
	$curAddGroups = explode(',', $additional_groups);

	$newAddGroups = array_merge($newAddGroups, $curAddGroups);

	// Simple, simple, simple - hopefully... id_group first.
	if ($curSub['prim_group'] != 0)
	{
		$id_group = $curSub['prim_group'];

		// Ensure their old privileges are maintained.
		if ($old_id_group != 0)
			$newAddGroups[] = $old_id_group;
	}
	else
		$id_group = $old_id_group;

	// Yep, make sure it's unique, and no empties.
	foreach ($newAddGroups as $k => $v)
		if (empty($v))
			unset($newAddGroups[$k]);
	$newAddGroups = array_unique($newAddGroups);
	$newAddGroups = implode(',', $newAddGroups);

	// Store the new settings.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
		WHERE id_member = {int:current_member}',
		array(
			'primary_group' => $id_group,
			'current_member' => $id_member,
			'additional_groups' => $newAddGroups,
		)
	);

	// Now log the subscription - maybe we have a dorment subscription we can restore?
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, end_time, start_time
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}',
		array(
			'current_subscription' => $id_subscribe,
			'current_member' => $id_member,
		)
	);

	/**
	 * @todo Don't really need to do this twice...
	 */
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $smcFunc['db_fetch_row']($request);

		// If this has already expired but is active, extension means the period from now.
		if ($endtime < time())
			$endtime = time();
		if ($starttime == 0)
			$starttime = time();

		// Work out the new expiry date.
		$endtime += $duration;

		if ($forceEndTime != 0)
			$endtime = $forceEndTime;

		// As everything else should be good, just update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET start_time = {int:start_time}, end_time = {int:end_time}, old_id_group = {int:old_id_group}, status = {int:is_active}, reminder_sent = {int:no_reminder_sent}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'start_time' => $starttime,
				'end_time' => $endtime,
				'old_id_group' => $old_id_group,
				'is_active' => 1,
				'no_reminder_sent' => 0,
				'current_subscription_item' => $id_sublog,
			)
		);

		return;
	}
	$smcFunc['db_free_result']($request);

	// Otherwise a very simple insert.
	$endtime = time() + $duration;
	if ($forceEndTime != 0)
		$endtime = $forceEndTime;

	if ($forceStartTime == 0)
		$starttime = time();
	else
		$starttime = $forceStartTime;

	$smcFunc['db_insert']('',
		'{db_prefix}log_subscribed',
		array(
			'id_subscribe' => 'int', 'id_member' => 'int', 'old_id_group' => 'int', 'start_time' => 'int',
			'end_time' => 'int', 'status' => 'int', 'pending_details' => 'string',
		),
		array(
			$id_subscribe, $id_member, $old_id_group, $starttime,
			$endtime, 1, '',
		),
		array('id_sublog')
	);
}

/**
 * Removes a subscription from a user, as in removes the groups.
 *
 * @param int $id_subscribe The ID of the subscription
 * @param int $id_member The ID of the member
 * @param bool $delete Whether to delete the subscription or just disable it
 */
function removeSubscription($id_subscribe, $id_member, $delete = false)
{
	global $context, $smcFunc;

	loadSubscriptions();

	// Load the user core bits.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_group, m.additional_groups
		FROM {db_prefix}members AS m
		WHERE m.id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
		)
	);

	// Just in case of errors.
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $id_member,
			)
		);
		return;
	}
	list ($id_group, $additional_groups) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Get all of the subscriptions for this user that are active - it will be necessary!
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, old_id_group
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:current_member}
			AND status = {int:is_active}',
		array(
			'current_member' => $id_member,
			'is_active' => 1,
		)
	);

	// These variables will be handy, honest ;)
	$removals = array();
	$allowed = array();
	$old_id_group = 0;
	$new_id_group = -1;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['subscriptions'][$row['id_subscribe']]))
			continue;

		// The one we're removing?
		if ($row['id_subscribe'] == $id_subscribe)
		{
			$removals = explode(',', $context['subscriptions'][$row['id_subscribe']]['add_groups']);
			if ($context['subscriptions'][$row['id_subscribe']]['prim_group'] != 0)
				$removals[] = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
			$old_id_group = $row['old_id_group'];
		}
		// Otherwise things we allow.
		else
		{
			$allowed = array_merge($allowed, explode(',', $context['subscriptions'][$row['id_subscribe']]['add_groups']));
			if ($context['subscriptions'][$row['id_subscribe']]['prim_group'] != 0)
			{
				$allowed[] = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
				$new_id_group = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
			}
		}
	}
	$smcFunc['db_free_result']($request);

	// Now, for everything we are removing check they definitely are not allowed it.
	$existingGroups = explode(',', $additional_groups);
	foreach ($existingGroups as $key => $group)
		if (empty($group) || (in_array($group, $removals) && !in_array($group, $allowed)))
			unset($existingGroups[$key]);

	// Finally, do something with the current primary group.
	if (in_array($id_group, $removals))
	{
		// If this primary group is actually allowed keep it.
		if (in_array($id_group, $allowed))
			$existingGroups[] = $id_group;

		// Either way, change the id_group back.
		if ($new_id_group < 1)
		{
			// If we revert to the old id-group we need to ensure it wasn't from a subscription.
			foreach ($context['subscriptions'] as $id => $group)
				// It was? Make them a regular member then!
				if ($group['prim_group'] == $old_id_group)
					$old_id_group = 0;

			$id_group = $old_id_group;
		}
		else
			$id_group = $new_id_group;
	}

	// Crazy stuff, we seem to have our groups fixed, just make them unique
	$existingGroups = array_unique($existingGroups);
	$existingGroups = implode(',', $existingGroups);

	// Update the member
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:primary_group}, additional_groups = {string:existing_groups}
		WHERE id_member = {int:current_member}',
		array(
			'primary_group' => $id_group,
			'current_member' => $id_member,
			'existing_groups' => $existingGroups,
		)
	);

	// Disable the subscription.
	if (!$delete)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET status = {int:not_active}
			WHERE id_member = {int:current_member}
				AND id_subscribe = {int:current_subscription}',
			array(
				'not_active' => 0,
				'current_member' => $id_member,
				'current_subscription' => $id_subscribe,
			)
		);
	// Otherwise delete it!
	else
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}
				AND id_subscribe = {int:current_subscription}',
			array(
				'current_member' => $id_member,
				'current_subscription' => $id_subscribe,
			)
		);
}

/**
 * This just kind of caches all the subscription data.
 */
function loadSubscriptions()
{
	global $context, $txt, $modSettings, $smcFunc;

	if (!empty($context['subscriptions']))
		return;

	// Make sure this is loaded, just in case.
	loadLanguage('ManagePaid');

	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, name, description, cost, length, id_group, add_groups, active, repeatable
		FROM {db_prefix}subscriptions',
		array(
		)
	);
	$context['subscriptions'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Pick a cost.
		$costs = $smcFunc['json_decode']($row['cost'], true);

		if ($row['length'] != 'F' && !empty($modSettings['paid_currency_symbol']) && !empty($costs['fixed']))
			$cost = sprintf($modSettings['paid_currency_symbol'], $costs['fixed']);
		else
			$cost = '???';

		// Do the span.
		preg_match('~(\d*)(\w)~', $row['length'], $match);
		if (isset($match[2]))
		{
			$num_length = $match[1];
			$length = $match[1] . ' ';
			switch ($match[2])
			{
				case 'D':
					$length .= $txt['paid_mod_span_days'];
					$num_length *= 86400;
					break;
				case 'W':
					$length .= $txt['paid_mod_span_weeks'];
					$num_length *= 604800;
					break;
				case 'M':
					$length .= $txt['paid_mod_span_months'];
					$num_length *= 2629743;
					break;
				case 'Y':
					$length .= $txt['paid_mod_span_years'];
					$num_length *= 31556926;
					break;
			}
		}
		else
			$length = '??';

		$context['subscriptions'][$row['id_subscribe']] = array(
			'id' => $row['id_subscribe'],
			'name' => $row['name'],
			'desc' => $row['description'],
			'cost' => $cost,
			'real_cost' => $row['cost'],
			'length' => $length,
			'num_length' => $num_length,
			'real_length' => $row['length'],
			'pending' => 0,
			'finished' => 0,
			'total' => 0,
			'active' => $row['active'],
			'prim_group' => $row['id_group'],
			'add_groups' => $row['add_groups'],
			'flexible' => $row['length'] == 'F' ? true : false,
			'repeatable' => $row['repeatable'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Do the counts.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_sublog) AS member_count, id_subscribe, status
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe, status',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$ind = $row['status'] == 0 ? 'finished' : 'total';

		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']][$ind] = $row['member_count'];
	}
	$smcFunc['db_free_result']($request);

	// How many payments are we waiting on?
	$request = $smcFunc['db_query']('', '
		SELECT SUM(payments_pending) AS total_pending, id_subscribe
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']]['pending'] = $row['total_pending'];
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Load all the payment gateways.
 * Checks the Sources directory for any files fitting the format of a payment gateway,
 * loads each file to check it's valid, includes each file and returns the
 * function name and whether it should work with this version of SMF.
 *
 * @return array An array of information about available payment gateways
 */
function loadPaymentGateways()
{
	global $sourcedir;

	$gateways = array();
	if ($dh = opendir($sourcedir))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (is_file($sourcedir . '/' . $file) && preg_match('~^Subscriptions-([A-Za-z\d]+)\.php$~', $file, $matches))
			{
				// Check this is definitely a valid gateway!
				$fp = fopen($sourcedir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '// SMF Payment Gateway: ' . strtolower($matches[1])) !== false)
				{
					require_once($sourcedir . '/' . $file);

					$gateways[] = array(
						'filename' => $file,
						'code' => strtolower($matches[1]),
						// Don't need anything snazzier than this yet.
						'valid_version' => class_exists(strtolower($matches[1]) . '_payment') && class_exists(strtolower($matches[1]) . '_display'),
						'payment_class' => strtolower($matches[1]) . '_payment',
						'display_class' => strtolower($matches[1]) . '_display',
					);
				}
			}
		}
	}
	closedir($dh);

	return $gateways;
}

?>