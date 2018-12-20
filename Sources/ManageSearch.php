<?php

/**
 * The admin screen to change the search settings.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Main entry point for the admin search settings screen.
 * It checks permissions, and it forwards to the appropriate function based on
 * the given sub-action.
 * Defaults to sub-action 'settings'.
 * Called by ?action=admin;area=managesearch.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template.
 * @uses Search language file.
 */
function ManageSearch()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('Search');
	loadTemplate('ManageSearch');

	db_extend('search');

	$subActions = array(
		'settings' => 'EditSearchSettings',
		'weights' => 'EditWeights',
		'method' => 'EditSearchMethod',
		'createfulltext' => 'EditSearchMethod',
		'removecustom' => 'EditSearchMethod',
		'removefulltext' => 'EditSearchMethod',
		'createmsgindex' => 'CreateMessageIndex',
	);

	// Default the sub-action to 'edit search settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'weights';

	$context['sub_action'] = $_REQUEST['sa'];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manage_search'],
		'help' => 'search',
		'description' => $txt['search_settings_desc'],
		'tabs' => array(
			'weights' => array(
				'description' => $txt['search_weights_desc'],
			),
			'method' => array(
				'description' => $txt['search_method_desc'],
			),
			'settings' => array(
				'description' => $txt['search_settings_desc'],
			),
		),
	);

	call_integration_hook('integrate_manage_search', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Edit some general settings related to the search function.
 * Called by ?action=admin;area=managesearch;sa=settings.
 * Requires the admin_forum permission.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses ManageSearch template, 'modify_settings' sub-template.
 */
function EditSearchSettings($return_config = false)
{
	global $txt, $context, $scripturl, $sourcedir, $modSettings;

	// What are we editing anyway?
	$config_vars = array(
		// Permission...
		array('permissions', 'search_posts'),
		// Some simple settings.
		array('int', 'search_results_per_page'),
		array('int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']),
		'',

		// Some limitations.
		array('int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc'], 6, 'postinput' => $txt['seconds']),
	);

	call_integration_hook('integrate_modify_search_settings', array(&$config_vars));

	// Perhaps the search method wants to add some settings?
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'searchSettings')))
		call_user_func_array(array($searchAPI, 'searchSettings'), array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['search_settings_title'];
	$context['sub_template'] = 'show_settings';

	// We'll need this for the settings.
	require_once($sourcedir . '/ManageServer.php');

	// A form was submitted.
	if (isset($_REQUEST['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_search_settings');

		if (empty($_POST['search_results_per_page']))
			$_POST['search_results_per_page'] = !empty($modSettings['search_results_per_page']) ? $modSettings['search_results_per_page'] : $modSettings['defaultMaxMessages'];
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Prep the template!
	$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
	$context['settings_title'] = $txt['search_settings_title'];

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * Edit the relative weight of the search factors.
 * Called by ?action=admin;area=managesearch;sa=weights.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template, 'modify_weights' sub-template.
 */
function EditWeights()
{
	global $txt, $context, $modSettings;

	$context['page_title'] = $txt['search_weights_title'];
	$context['sub_template'] = 'modify_weights';

	$factors = array(
		'search_weight_frequency',
		'search_weight_age',
		'search_weight_length',
		'search_weight_subject',
		'search_weight_first_message',
		'search_weight_sticky',
	);

	call_integration_hook('integrate_modify_search_weights', array(&$factors));

	// A form was submitted.
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-msw');

		call_integration_hook('integrate_save_search_weights');

		$changes = array();
		foreach ($factors as $factor)
			$changes[$factor] = (int) $_POST[$factor];
		updateSettings($changes);
	}

	$context['relative_weights'] = array('total' => 0);
	foreach ($factors as $factor)
		$context['relative_weights']['total'] += isset($modSettings[$factor]) ? $modSettings[$factor] : 0;

	foreach ($factors as $factor)
		$context['relative_weights'][$factor] = round(100 * (isset($modSettings[$factor]) ? $modSettings[$factor] : 0) / $context['relative_weights']['total'], 1);

	createToken('admin-msw');
}

/**
 * Edit the search method and search index used.
 * Calculates the size of the current search indexes in use.
 * Allows to create and delete a fulltext index on the messages table.
 * Allows to delete a custom index (that CreateMessageIndex() created).
 * Called by ?action=admin;area=managesearch;sa=method.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template, 'select_search_method' sub-template.
 */
function EditSearchMethod()
{
	global $txt, $context, $modSettings, $smcFunc, $db_type, $db_prefix;

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_method_title'];
	$context['sub_template'] = 'select_search_method';
	$context['supports_fulltext'] = $smcFunc['db_search_support']('fulltext');

	// Load any apis.
	$context['search_apis'] = loadSearchAPIs();

	// Detect whether a fulltext index is set.
	if ($context['supports_fulltext'])
		detectFulltextIndex();

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'createfulltext')
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		if ($db_type == 'postgresql')
		{
			$smcFunc['db_query']('', '
				DROP INDEX IF EXISTS {db_prefix}messages_ftx',
				array(
					'db_error_skip' => true,
				)
			);

			$language_ftx = $smcFunc['db_search_language']();

			$smcFunc['db_query']('', '
				CREATE INDEX {db_prefix}messages_ftx ON {db_prefix}messages
				USING gin(to_tsvector({string:language},body))',
				array(
					'language' => $language_ftx
				)
			);
		}
		else
		{
			// Make sure it's gone before creating it.
			$smcFunc['db_query']('', '
				ALTER TABLE {db_prefix}messages
				DROP INDEX body',
				array(
					'db_error_skip' => true,
				)
			);

			$smcFunc['db_query']('', '
				ALTER TABLE {db_prefix}messages
				ADD FULLTEXT body (body)',
				array(
				)
			);
		}
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty($context['fulltext_index']))
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		$smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}messages
			DROP INDEX ' . implode(',
			DROP INDEX ', $context['fulltext_index']),
			array(
				'db_error_skip' => true,
			)
		);

		$context['fulltext_index'] = array();

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removecustom')
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		db_extend();
		$tables = $smcFunc['db_list_tables'](false, $db_prefix . 'log_search_words');
		if (!empty($tables))
		{
			$smcFunc['db_search_query']('drop_words_table', '
				DROP TABLE {db_prefix}log_search_words',
				array(
				)
			);
		}

		updateSettings(array(
			'search_custom_index_config' => '',
			'search_custom_index_resume' => '',
		));

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-msmpost');

		updateSettings(array(
			'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], array('fulltext', 'custom')) && !isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
			'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
			'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
		));
	}

	$context['table_info'] = array(
		'data_length' => 0,
		'index_length' => 0,
		'fulltext_length' => 0,
		'custom_index_length' => 0,
	);

	// Get some info about the messages table, to show its size and index size.
	if ($db_type == 'mysql')
	{
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);
		if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $smcFunc['db_fetch_assoc']($request);
			$context['table_info']['data_length'] = $row['Data_length'];
			$context['table_info']['index_length'] = $row['Index_length'];
			$context['table_info']['fulltext_length'] = $row['Index_length'];
			$smcFunc['db_free_result']($request);
		}

		// Now check the custom index table, if it exists at all.
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
				)
			);
		if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $smcFunc['db_fetch_assoc']($request);
			$context['table_info']['index_length'] += $row['Data_length'] + $row['Index_length'];
			$context['table_info']['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
			$smcFunc['db_free_result']($request);
		}
	}
	elseif ($db_type == 'postgresql')
	{
		// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
		//db_extend();
		//$temp_tables = $smcFunc['db_list_tables']();
		//foreach ($temp_tables as $table)
		//	if ($table == $db_prefix. 'messages' || $table == $db_prefix. 'log_search_words')
		//		$smcFunc['db_optimize_table']($table);

		// PostGreSql has some hidden sizes.
		$request = $smcFunc['db_query']('', '
			SELECT
				indexname,
				pg_relation_size(quote_ident(t.tablename)::text) AS table_size,
				pg_relation_size(quote_ident(indexrelname)::text) AS index_size
			FROM pg_tables t
				LEFT OUTER JOIN pg_class c ON t.tablename=c.relname
				LEFT OUTER JOIN
					(SELECT c.relname AS ctablename, ipg.relname AS indexname, indexrelname FROM pg_index x
						JOIN pg_class c ON c.oid = x.indrelid
						JOIN pg_class ipg ON ipg.oid = x.indexrelid
						JOIN pg_stat_all_indexes psai ON x.indexrelid = psai.indexrelid)
					AS foo
					ON t.tablename = foo.ctablename
			WHERE t.schemaname= {string:schema} and (
				indexname = {string:messages_ftx} OR indexname = {string:log_search_words} )',
			array(
				'messages_ftx' => $db_prefix . 'messages_ftx',
				'log_search_words' => $db_prefix . 'log_search_words',
				'schema' => 'public',
			)
		);

		if ($request !== false && $smcFunc['db_num_rows']($request) > 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if ($row['indexname'] == $db_prefix . 'messages_ftx')
				{
					$context['table_info']['data_length'] = (int) $row['table_size'];
					$context['table_info']['index_length'] = (int) $row['index_size'];
					$context['table_info']['fulltext_length'] = (int) $row['index_size'];
				}
				elseif ($row['indexname'] == $db_prefix . 'log_search_words')
				{
					$context['table_info']['index_length'] = (int) $row['index_size'];
					$context['table_info']['custom_index_length'] = (int) $row['index_size'];
				}
			}
			$smcFunc['db_free_result']($request);
		}
		else
			// Didn't work for some reason...
			$context['table_info'] = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);
	}
	else
		$context['table_info'] = array(
			'data_length' => $txt['not_applicable'],
			'index_length' => $txt['not_applicable'],
			'fulltext_length' => $txt['not_applicable'],
			'custom_index_length' => $txt['not_applicable'],
		);

	// Format the data and index length in kilobytes.
	foreach ($context['table_info'] as $type => $size)
	{
		// If it's not numeric then just break.  This database engine doesn't support size.
		if (!is_numeric($size))
			break;

		$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}

	$context['custom_index'] = !empty($modSettings['search_custom_index_config']);
	$context['partial_custom_index'] = !empty($modSettings['search_custom_index_resume']) && empty($modSettings['search_custom_index_config']);
	$context['double_index'] = !empty($context['fulltext_index']) && $context['custom_index'];

	createToken('admin-msmpost');
	createToken('admin-msm', 'get');
}

/**
 * Create a custom search index for the messages table.
 * Called by ?action=admin;area=managesearch;sa=createmsgindex.
 * Linked from the EditSearchMethod screen.
 * Requires the admin_forum permission.
 * Depending on the size of the message table, the process is divided in steps.
 *
 * @uses ManageSearch template, 'create_index', 'create_index_progress', and 'create_index_done'
 *  sub-templates.
 */
function CreateMessageIndex()
{
	global $modSettings, $context, $smcFunc, $db_prefix, $txt;

	// Scotty, we need more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_index_custom'];

	$messages_per_batch = 50;

	$index_properties = array(
		2 => array(
			'column_definition' => 'small',
			'step_size' => 1000000,
		),
		4 => array(
			'column_definition' => 'medium',
			'step_size' => 1000000,
			'max_size' => 16777215,
		),
		5 => array(
			'column_definition' => 'large',
			'step_size' => 100000000,
			'max_size' => 2000000000,
		),
	);

	if (isset($_REQUEST['resume']) && !empty($modSettings['search_custom_index_resume']))
	{
		$context['index_settings'] = $smcFunc['json_decode']($modSettings['search_custom_index_resume'], true);
		$context['start'] = (int) $context['index_settings']['resume_at'];
		unset($context['index_settings']['resume_at']);
		$context['step'] = 1;
	}
	else
	{
		$context['index_settings'] = array(
			'bytes_per_word' => isset($_REQUEST['bytes_per_word']) && isset($index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
		);
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;

		// admin timeouts are painful when building these long indexes - but only if we actually have such things enabled
		if (empty($modSettings['securityDisable']) && $_SESSION['admin_time'] + 3300 < time() && $context['step'] >= 1)
			$_SESSION['admin_time'] = time();
	}

	if ($context['step'] !== 0)
		checkSession('request');

	// Step 0: let the user determine how they like their index.
	if ($context['step'] === 0)
	{
		$context['sub_template'] = 'create_index';
	}

	// Step 1: insert all the words.
	if ($context['step'] === 1)
	{
		$context['sub_template'] = 'create_index_progress';

		if ($context['start'] === 0)
		{
			db_extend();
			$tables = $smcFunc['db_list_tables'](false, $db_prefix . 'log_search_words');
			if (!empty($tables))
			{
				$smcFunc['db_search_query']('drop_words_table', '
					DROP TABLE {db_prefix}log_search_words',
					array(
					)
				);
			}

			$smcFunc['db_create_word_search']($index_properties[$context['index_settings']['bytes_per_word']]['column_definition']);

			// Temporarily switch back to not using a search index.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
				updateSettings(array('search_index' => ''));

			// Don't let simultanious processes be updating the search index.
			if (!empty($modSettings['search_custom_index_config']))
				updateSettings(array('search_custom_index_config' => ''));
		}

		$num_messages = array(
			'done' => 0,
			'todo' => 0,
		);

		$request = $smcFunc['db_query']('', '
			SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
			FROM {db_prefix}messages
			GROUP BY todo',
			array(
				'starting_id' => $context['start'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];

		if (empty($num_messages['todo']))
		{
			$context['step'] = 2;
			$context['percentage'] = 80;
			$context['start'] = 0;
		}
		else
		{
			// Number of seconds before the next step.
			$stop = time() + 3;
			while (time() < $stop)
			{
				$inserts = array();
				$request = $smcFunc['db_query']('', '
					SELECT id_msg, body
					FROM {db_prefix}messages
					WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
					LIMIT {int:limit}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $messages_per_batch - 1,
						'limit' => $messages_per_batch,
					)
				);
				$forced_break = false;
				$number_processed = 0;
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					// In theory it's possible for one of these to take friggin ages so add more timeout protection.
					if ($stop < time())
					{
						$forced_break = true;
						break;
					}

					$number_processed++;
					foreach (text2words($row['body'], $context['index_settings']['bytes_per_word'], true) as $id_word)
					{
						$inserts[] = array($id_word, $row['id_msg']);
					}
				}
				$num_messages['done'] += $number_processed;
				$num_messages['todo'] -= $number_processed;
				$smcFunc['db_free_result']($request);

				$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

				if (!empty($inserts))
					$smcFunc['db_insert']('ignore',
						'{db_prefix}log_search_words',
						array('id_word' => 'int', 'id_msg' => 'int'),
						$inserts,
						array('id_word', 'id_msg')
					);
				if ($num_messages['todo'] === 0)
				{
					$context['step'] = 2;
					$context['start'] = 0;
					break;
				}
				else
					updateSettings(array('search_custom_index_resume' => $smcFunc['json_encode'](array_merge($context['index_settings'], array('resume_at' => $context['start'])))));
			}

			// Since there are still two steps to go, 80% is the maximum here.
			$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
		}
	}

	// Step 2: removing the words that occur too often and are of no use.
	elseif ($context['step'] === 2)
	{
		if ($context['index_settings']['bytes_per_word'] < 4)
			$context['step'] = 3;
		else
		{
			$stop_words = $context['start'] === 0 || empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
			$stop = time() + 3;
			$context['sub_template'] = 'create_index_progress';
			$max_messages = ceil(60 * $modSettings['totalMessages'] / 100);

			while (time() < $stop)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_word, COUNT(id_word) AS num_words
					FROM {db_prefix}log_search_words
					WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
					GROUP BY id_word
					HAVING COUNT(id_word) > {int:minimum_messages}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $index_properties[$context['index_settings']['bytes_per_word']]['step_size'] - 1,
						'minimum_messages' => $max_messages,
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$stop_words[] = $row['id_word'];
				$smcFunc['db_free_result']($request);

				updateSettings(array('search_stopwords' => implode(',', $stop_words)));

				if (!empty($stop_words))
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_search_words
						WHERE id_word in ({array_int:stop_words})',
						array(
							'stop_words' => $stop_words,
						)
					);

				$context['start'] += $index_properties[$context['index_settings']['bytes_per_word']]['step_size'];
				if ($context['start'] > $index_properties[$context['index_settings']['bytes_per_word']]['max_size'])
				{
					$context['step'] = 3;
					break;
				}
			}
			$context['percentage'] = 80 + round($context['start'] / $index_properties[$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
		}
	}

	// Step 3: remove words not distinctive enough.
	if ($context['step'] === 3)
	{
		$context['sub_template'] = 'create_index_done';

		updateSettings(array('search_index' => 'custom', 'search_custom_index_config' => $smcFunc['json_encode']($context['index_settings'])));
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:search_custom_index_resume}',
			array(
				'search_custom_index_resume' => 'search_custom_index_resume',
			)
		);
	}
}

/**
 * Get the installed Search API implementations.
 * This function checks for patterns in comments on top of the Search-API files!
 * In addition to filenames pattern.
 * It loads the search API classes if identified.
 * This function is used by EditSearchMethod to list all installed API implementations.
 */
function loadSearchAPIs()
{
	global $sourcedir, $txt;

	$apis = array();
	if ($dh = opendir($sourcedir))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (is_file($sourcedir . '/' . $file) && preg_match('~^SearchAPI-([A-Za-z\d_]+)\.php$~', $file, $matches))
			{
				// Check this is definitely a valid API!
				$fp = fopen($sourcedir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '* SearchAPI-' . $matches[1] . '.php') !== false)
				{
					require_once($sourcedir . '/' . $file);

					$index_name = strtolower($matches[1]);
					$search_class_name = $index_name . '_search';
					$searchAPI = new $search_class_name();

					// No Support?  NEXT!
					if (!$searchAPI->is_supported)
						continue;

					$apis[$index_name] = array(
						'filename' => $file,
						'setting_index' => $index_name,
						'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
						'label' => $index_name && isset($txt['search_index_' . $index_name]) ? $txt['search_index_' . $index_name] : '',
						'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? $txt['search_index_' . $index_name . '_desc'] : '',
					);
				}
			}
		}
	}
	closedir($dh);

	return $apis;
}

/**
 * Checks if the message table already has a fulltext index created and returns the key name
 * Determines if a db is capable of creating a fulltext index
 */
function detectFulltextIndex()
{
	global $smcFunc, $context, $db_prefix;

	// We need this for db_get_version
	db_extend();

	if ($smcFunc['db_title'] == 'PostgreSQL')
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				indexname
			FROM pg_tables t
				LEFT OUTER JOIN
					(SELECT c.relname AS ctablename, ipg.relname AS indexname, indexrelname FROM pg_index x
						JOIN pg_class c ON c.oid = x.indrelid
						JOIN pg_class ipg ON ipg.oid = x.indexrelid
						JOIN pg_stat_all_indexes psai ON x.indexrelid = psai.indexrelid)
					AS foo
					ON t.tablename = foo.ctablename
			WHERE t.schemaname= {string:schema} and indexname = {string:messages_ftx}',
			array(
				'schema' => 'public',
				'messages_ftx' => $db_prefix . 'messages_ftx',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['fulltext_index'][] = $row['indexname'];
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SHOW INDEX
			FROM {db_prefix}messages',
			array(
			)
		);
		$context['fulltext_index'] = array();
		if ($request !== false || $smcFunc['db_num_rows']($request) != 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
					$context['fulltext_index'][] = $row['Key_name'];
			$smcFunc['db_free_result']($request);

			if (is_array($context['fulltext_index']))
				$context['fulltext_index'] = array_unique($context['fulltext_index']);
		}

		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);

		if ($request !== false)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				if (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam' && !(strtolower($row['Engine']) == 'innodb' && version_compare($smcFunc['db_get_version'](), '5.6.4', '>=')))
					$context['cannot_create_fulltext'] = true;

			$smcFunc['db_free_result']($request);
		}
	}
}

?>