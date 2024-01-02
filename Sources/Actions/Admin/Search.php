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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Search\SearchApi;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * The admin screen to change the search settings.
 */
class Search implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageSearch',
			'editWeights' => 'EditWeights',
			'editSearchMethod' => 'EditSearchMethod',
			'createMessageIndex' => 'CreateMessageIndex',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// code...

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'weights';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'settings' => 'settings',
		'weights' => 'weights',
		'method' => 'method',
		'createfulltext' => 'method',
		'removecustom' => 'method',
		'removefulltext' => 'method',
		'createmsgindex' => 'createmsgindex',
	];

	/*********************
	 * Internal properties
	 *********************/

	// code...

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Edit some general settings related to the search function.
	 * Called by ?action=admin;area=managesearch;sa=settings.
	 * Requires the admin_forum permission.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		Utils::$context['page_title'] = Lang::$txt['search_settings_title'];
		Utils::$context['sub_template'] = 'show_settings';

		// A form was submitted.
		if (isset($_REQUEST['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_search_settings');

			if (empty($_POST['search_results_per_page'])) {
				$_POST['search_results_per_page'] = !empty(Config::$modSettings['search_results_per_page']) ? Config::$modSettings['search_results_per_page'] : Config::$modSettings['defaultMaxMessages'];
			}

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;

			Utils::redirectexit('action=admin;area=managesearch;sa=settings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Prep the template!
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=managesearch;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['search_settings_title'];

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Edit the relative weight of the search factors.
	 * Called by ?action=admin;area=managesearch;sa=weights.
	 * Requires the admin_forum permission.
	 */
	public function weights()
	{
		Utils::$context['page_title'] = Lang::$txt['search_weights_title'];
		Utils::$context['sub_template'] = 'modify_weights';

		$factors = [
			'search_weight_frequency',
			'search_weight_age',
			'search_weight_length',
			'search_weight_subject',
			'search_weight_first_message',
			'search_weight_sticky',
		];

		IntegrationHook::call('integrate_modify_search_weights', [&$factors]);

		// A form was submitted.
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-msw');

			IntegrationHook::call('integrate_save_search_weights');

			$changes = [];

			foreach ($factors as $factor) {
				$changes[$factor] = (int) $_POST[$factor];
			}

			Config::updateModSettings($changes);
		}

		Utils::$context['relative_weights'] = ['total' => 0];

		foreach ($factors as $factor) {
			Utils::$context['relative_weights']['total'] += Config::$modSettings[$factor] ?? 0;
		}

		foreach ($factors as $factor) {
			Utils::$context['relative_weights'][$factor] = round(100 * (Config::$modSettings[$factor] ?? 0) / Utils::$context['relative_weights']['total'], 1);
		}

		SecurityToken::create('admin-msw');
	}

	/**
	 * Edit the search method and search index used.
	 * Calculates the size of the current search indexes in use.
	 * Allows to create and delete a fulltext index on the messages table.
	 * Allows to delete a custom index (that createmsgindex() created).
	 * Called by ?action=admin;area=managesearch;sa=method.
	 * Requires the admin_forum permission.
	 */
	public function method()
	{
		Utils::$context['page_title'] = Lang::$txt['search_method_title'];
		Utils::$context['sub_template'] = 'select_search_method';
		Utils::$context['supports_fulltext'] = Db::$db->search_support('fulltext');

		// Load any apis.
		Utils::$context['search_apis'] = SearchApi::detect();

		// Detect whether a fulltext index is set.
		if (Utils::$context['supports_fulltext']) {
			$this->detectFulltextIndex();
		}

		if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'createfulltext') {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-msm', 'get');

			if (Config::$db_type == 'postgresql') {
				Db::$db->query(
					'',
					'DROP INDEX IF EXISTS {db_prefix}messages_ftx',
					[
						'db_error_skip' => true,
					],
				);

				$language_ftx = Db::$db->search_language();

				Db::$db->query(
					'',
					'CREATE INDEX {db_prefix}messages_ftx ON {db_prefix}messages
					USING gin(to_tsvector({string:language},body))',
					[
						'language' => $language_ftx,
					],
				);
			} else {
				// Make sure it's gone before creating it.
				Db::$db->query(
					'',
					'ALTER TABLE {db_prefix}messages
					DROP INDEX body',
					[
						'db_error_skip' => true,
					],
				);

				Db::$db->query(
					'',
					'ALTER TABLE {db_prefix}messages
					ADD FULLTEXT body (body)',
					[
					],
				);
			}

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		} elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty(Utils::$context['fulltext_index'])) {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-msm', 'get');

			if (Config::$db_type == 'postgresql') {
				Db::$db->query(
					'',
					'DROP INDEX IF EXISTS {db_prefix}messages_ftx',
					[
						'db_error_skip' => true,
					],
				);
			} else {
				Db::$db->query(
					'',
					'ALTER TABLE {db_prefix}messages
					DROP INDEX ' . implode(',
					DROP INDEX ', Utils::$context['fulltext_index']),
					[
						'db_error_skip' => true,
					],
				);
			}

			// Go back to the default search method.
			if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'fulltext') {
				Config::updateModSettings([
					'search_index' => '',
				]);
			}

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		} elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removecustom') {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-msm', 'get');

			$tables = Db::$db->list_tables(false, Db::$db->prefix . 'log_search_words');

			if (!empty($tables)) {
				Db::$db->search_query(
					'drop_words_table',
					'
					DROP TABLE {db_prefix}log_search_words',
					[
					],
				);
			}

			Config::updateModSettings([
				'search_custom_index_config' => '',
				'search_custom_index_resume' => '',
			]);

			// Go back to the default search method.
			if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'custom') {
				Config::updateModSettings([
					'search_index' => '',
				]);
			}

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		} elseif (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-msmpost');

			Config::updateModSettings([
				'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], ['fulltext', 'custom']) && !isset(Utils::$context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
				'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
				'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
			]);

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		}

		Utils::$context['table_info'] = [
			'data_length' => 0,
			'index_length' => 0,
			'fulltext_length' => 0,
			'custom_index_length' => 0,
		];

		// Get some info about the messages table, to show its size and index size.
		if (Config::$db_type == 'mysql') {
			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'messages',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'messages',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) == 1) {
				// Only do this if the user has permission to execute this query.
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['data_length'] = $row['Data_length'];
				Utils::$context['table_info']['index_length'] = $row['Index_length'];
				Utils::$context['table_info']['fulltext_length'] = $row['Index_length'];
				Db::$db->free_result($request);
			}

			// Now check the custom index table, if it exists at all.
			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'log_search_words',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'log_search_words',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) == 1) {
				// Only do this if the user has permission to execute this query.
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['index_length'] += $row['Data_length'] + $row['Index_length'];
				Utils::$context['table_info']['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
				Db::$db->free_result($request);
			}
		} elseif (Config::$db_type == 'postgresql') {
			// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
			// $temp_tables = Db::$db->list_tables();
			// foreach ($temp_tables as $table)
			//	if ($table == Db::$db->prefix. 'messages' || $table == Db::$db->prefix. 'log_search_words')
			//		Db::$db->optimize_table($table);

			// PostGreSql has some hidden sizes.
			$request = Db::$db->query(
				'',
				'SELECT
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
				[
					'messages_ftx' => Db::$db->prefix . 'messages_ftx',
					'log_search_words' => Db::$db->prefix . 'log_search_words',
					'schema' => 'public',
				],
			);

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if ($row['indexname'] == Db::$db->prefix . 'messages_ftx') {
						Utils::$context['table_info']['data_length'] = (int) $row['table_size'];
						Utils::$context['table_info']['index_length'] = (int) $row['index_size'];
						Utils::$context['table_info']['fulltext_length'] = (int) $row['index_size'];
					} elseif ($row['indexname'] == Db::$db->prefix . 'log_search_words') {
						Utils::$context['table_info']['index_length'] = (int) $row['index_size'];
						Utils::$context['table_info']['custom_index_length'] = (int) $row['index_size'];
					}
				}
				Db::$db->free_result($request);
			} else {
				// Didn't work for some reason...
				Utils::$context['table_info'] = [
					'data_length' => Lang::$txt['not_applicable'],
					'index_length' => Lang::$txt['not_applicable'],
					'fulltext_length' => Lang::$txt['not_applicable'],
					'custom_index_length' => Lang::$txt['not_applicable'],
				];
			}
		} else {
			Utils::$context['table_info'] = [
				'data_length' => Lang::$txt['not_applicable'],
				'index_length' => Lang::$txt['not_applicable'],
				'fulltext_length' => Lang::$txt['not_applicable'],
				'custom_index_length' => Lang::$txt['not_applicable'],
			];
		}

		// Format the data and index length in kilobytes.
		foreach (Utils::$context['table_info'] as $type => $size) {
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size)) {
				break;
			}

			Utils::$context['table_info'][$type] = Lang::numberFormat(Utils::$context['table_info'][$type] / 1024) . ' ' . Lang::$txt['search_method_kilobytes'];
		}

		Utils::$context['custom_index'] = !empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['partial_custom_index'] = !empty(Config::$modSettings['search_custom_index_resume']) && empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['double_index'] = !empty(Utils::$context['fulltext_index']) && Utils::$context['custom_index'];

		SecurityToken::create('admin-msmpost');
		SecurityToken::create('admin-msm', 'get');
	}

	/**
	 * Create a custom search index for the messages table.
	 * Called by ?action=admin;area=managesearch;sa=createmsgindex.
	 * Linked from the method screen.
	 * Requires the admin_forum permission.
	 * Depending on the size of the message table, the process is divided in steps.
	 */
	public function createmsgindex()
	{
		// Scotty, we need more time...
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		Menu::$loaded['admin']['current_subsection'] = 'method';
		Utils::$context['page_title'] = Lang::$txt['search_index_custom'];

		$messages_per_batch = 50;

		$index_properties = [
			2 => [
				'column_definition' => 'small',
				'step_size' => 1000000,
			],
			4 => [
				'column_definition' => 'medium',
				'step_size' => 1000000,
				'max_size' => 16777215,
			],
			5 => [
				'column_definition' => 'large',
				'step_size' => 100000000,
				'max_size' => 2000000000,
			],
		];

		if (isset($_REQUEST['resume']) && !empty(Config::$modSettings['search_custom_index_resume'])) {
			Utils::$context['index_settings'] = Utils::jsonDecode(Config::$modSettings['search_custom_index_resume'], true);
			Utils::$context['start'] = (int) Utils::$context['index_settings']['resume_at'];

			unset(Utils::$context['index_settings']['resume_at']);

			Utils::$context['step'] = 1;
		} else {
			Utils::$context['index_settings'] = [
				'bytes_per_word' => isset($_REQUEST['bytes_per_word']) && isset($index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
			];

			Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
			Utils::$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;

			// admin timeouts are painful when building these long indexes - but only if we actually have such things enabled
			if (empty(Config::$modSettings['securityDisable']) && $_SESSION['admin_time'] + 3300 < time() && Utils::$context['step'] >= 1) {
				$_SESSION['admin_time'] = time();
			}
		}

		if (Utils::$context['step'] !== 0) {
			User::$me->checkSession('request');
		}

		// Step 0: let the user determine how they like their index.
		if (Utils::$context['step'] === 0) {
			Utils::$context['sub_template'] = 'create_index';
		}

		// Step 1: insert all the words.
		if (Utils::$context['step'] === 1) {
			Utils::$context['sub_template'] = 'create_index_progress';

			if (Utils::$context['start'] === 0) {
				$tables = Db::$db->list_tables(false, Db::$db->prefix . 'log_search_words');

				if (!empty($tables)) {
					Db::$db->search_query(
						'drop_words_table',
						'
						DROP TABLE {db_prefix}log_search_words',
						[
						],
					);
				}

				Db::$db->create_word_search($index_properties[Utils::$context['index_settings']['bytes_per_word']]['column_definition']);

				// Temporarily switch back to not using a search index.
				if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'custom') {
					Config::updateModSettings(['search_index' => '']);
				}

				// Don't let simultanious processes be updating the search index.
				if (!empty(Config::$modSettings['search_custom_index_config'])) {
					Config::updateModSettings(['search_custom_index_config' => '']);
				}
			}

			$num_messages = [
				'done' => 0,
				'todo' => 0,
			];

			$request = Db::$db->query(
				'',
				'SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
				FROM {db_prefix}messages
				GROUP BY todo',
				[
					'starting_id' => Utils::$context['start'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];
			}
			Db::$db->free_result($request);

			if (empty($num_messages['todo'])) {
				Utils::$context['step'] = 2;
				Utils::$context['percentage'] = 80;
				Utils::$context['start'] = 0;
			} else {
				// Number of seconds before the next step.
				$stop = time() + 3;

				while (time() < $stop) {
					$inserts = [];
					$forced_break = false;
					$number_processed = 0;

					$request = Db::$db->query(
						'',
						'SELECT id_msg, body
						FROM {db_prefix}messages
						WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
						LIMIT {int:limit}',
						[
							'starting_id' => Utils::$context['start'],
							'ending_id' => Utils::$context['start'] + $messages_per_batch - 1,
							'limit' => $messages_per_batch,
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						// In theory it's possible for one of these to take friggin ages so add more timeout protection.
						if ($stop < time()) {
							$forced_break = true;
							break;
						}

						$number_processed++;

						foreach (Utils::text2words($row['body'], Utils::$context['index_settings']['bytes_per_word'], true) as $id_word) {
							$inserts[] = [$id_word, $row['id_msg']];
						}
					}
					$num_messages['done'] += $number_processed;
					$num_messages['todo'] -= $number_processed;
					Db::$db->free_result($request);

					Utils::$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

					if (!empty($inserts)) {
						Db::$db->insert(
							'ignore',
							'{db_prefix}log_search_words',
							['id_word' => 'int', 'id_msg' => 'int'],
							$inserts,
							['id_word', 'id_msg'],
						);
					}

					if ($num_messages['todo'] === 0) {
						Utils::$context['step'] = 2;
						Utils::$context['start'] = 0;
						break;
					}

					Config::updateModSettings(['search_custom_index_resume' => Utils::jsonEncode(array_merge(Utils::$context['index_settings'], ['resume_at' => Utils::$context['start']]))]);
				}

				// Since there are still two steps to go, 80% is the maximum here.
				Utils::$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
			}
		}
		// Step 2: removing the words that occur too often and are of no use.
		elseif (Utils::$context['step'] === 2) {
			if (Utils::$context['index_settings']['bytes_per_word'] < 4) {
				Utils::$context['step'] = 3;
			} else {
				$stop_words = Utils::$context['start'] === 0 || empty(Config::$modSettings['search_stopwords']) ? [] : explode(',', Config::$modSettings['search_stopwords']);

				$stop = time() + 3;

				Utils::$context['sub_template'] = 'create_index_progress';

				$max_messages = ceil(60 * Config::$modSettings['totalMessages'] / 100);

				while (time() < $stop) {
					$request = Db::$db->query(
						'',
						'SELECT id_word, COUNT(id_word) AS num_words
						FROM {db_prefix}log_search_words
						WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
						GROUP BY id_word
						HAVING COUNT(id_word) > {int:minimum_messages}',
						[
							'starting_id' => Utils::$context['start'],
							'ending_id' => Utils::$context['start'] + $index_properties[Utils::$context['index_settings']['bytes_per_word']]['step_size'] - 1,
							'minimum_messages' => $max_messages,
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						$stop_words[] = $row['id_word'];
					}
					Db::$db->free_result($request);

					Config::updateModSettings(['search_stopwords' => implode(',', $stop_words)]);

					if (!empty($stop_words)) {
						Db::$db->query(
							'',
							'DELETE FROM {db_prefix}log_search_words
							WHERE id_word in ({array_int:stop_words})',
							[
								'stop_words' => $stop_words,
							],
						);
					}

					Utils::$context['start'] += $index_properties[Utils::$context['index_settings']['bytes_per_word']]['step_size'];

					if (Utils::$context['start'] > $index_properties[Utils::$context['index_settings']['bytes_per_word']]['max_size']) {
						Utils::$context['step'] = 3;
						break;
					}
				}

				Utils::$context['percentage'] = 80 + round(Utils::$context['start'] / $index_properties[Utils::$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
			}
		}

		// Step 3: remove words not distinctive enough.
		if (Utils::$context['step'] === 3) {
			Utils::$context['sub_template'] = 'create_index_done';

			Config::updateModSettings(['search_index' => 'custom', 'search_custom_index_config' => Utils::jsonEncode(Utils::$context['index_settings'])]);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}settings
				WHERE variable = {string:search_custom_index_resume}',
				[
					'search_custom_index_resume' => 'search_custom_index_resume',
				],
			);
		}
	}

	/**
	 * Checks if the message table already has a fulltext index created and returns the key name
	 * Determines if a db is capable of creating a fulltext index
	 */
	public function detectFulltextIndex()
	{
		if (Db::$db->title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT
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
				[
					'schema' => 'public',
					'messages_ftx' => Db::$db->prefix . 'messages_ftx',
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['fulltext_index'][] = $row['indexname'];
			}
			Db::$db->free_result($request);
		} else {
			Utils::$context['fulltext_index'] = [];

			$request = Db::$db->query(
				'',
				'SHOW INDEX
				FROM {db_prefix}messages',
				[
				],
			);

			if ($request !== false || Db::$db->num_rows($request) != 0) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT')) {
						Utils::$context['fulltext_index'][] = $row['Key_name'];
					}
				}
				Db::$db->free_result($request);

				if (is_array(Utils::$context['fulltext_index'])) {
					Utils::$context['fulltext_index'] = array_unique(Utils::$context['fulltext_index']);
				}
			}

			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'messages',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'messages',
					],
				);
			}

			if ($request !== false) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam' && !(strtolower($row['Engine']) == 'innodb' && version_compare(Db::$db->get_version(), '5.6.4', '>='))) {
						Utils::$context['cannot_create_fulltext'] = true;
					}
				}

				Db::$db->free_result($request);
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the search area.
	 */
	public static function getConfigVars(): array
	{
		// What are we editing anyway?
		$config_vars = [
			// Permission...
			['permissions', 'search_posts'],
			// Some simple settings.
			['int', 'search_results_per_page'],
			['int', 'search_max_results', 'subtext' => Lang::$txt['search_max_results_disable']],
			'',

			// Some limitations.
			['int', 'search_floodcontrol_time', 'subtext' => Lang::$txt['search_floodcontrol_time_desc'], 6, 'postinput' => Lang::$txt['seconds']],
		];

		IntegrationHook::call('integrate_modify_search_settings', [&$config_vars]);

		// Perhaps the search method wants to add some settings?
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'searchSettings'])) {
			call_user_func_array([$searchAPI, 'searchSettings'], [&$config_vars]);
		}

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the _____ sub-action.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	public function editSearchSettings($return_config = false)
	{
		self::load();
		self::$obj->subaction = '';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the weights sub-action.
	 */
	public static function editWeights(): void
	{
		self::load();
		self::$obj->subaction = 'weights';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the method sub-action.
	 */
	public static function editSearchMethod(): void
	{
		self::load();
		self::$obj->subaction = 'method';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the createmsgindex sub-action.
	 */
	public static function createMessageIndex(): void
	{
		self::load();
		self::$obj->subaction = 'createmsgindex';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		User::$me->isAllowedTo('admin_forum');

		Lang::load('Search');
		Theme::loadTemplate('ManageSearch');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['manage_search'],
			'help' => 'search',
			'description' => Lang::$txt['search_settings_desc'],
			'tabs' => [
				'weights' => [
					'description' => Lang::$txt['search_weights_desc'],
				],
				'method' => [
					'description' => Lang::$txt['search_method_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['search_settings_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_search', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		Utils::$context['sub_action'] = $this->subaction;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Search::exportStatic')) {
	Search::exportStatic();
}

?>