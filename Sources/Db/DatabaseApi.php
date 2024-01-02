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

declare(strict_types=1);

namespace SMF\Db;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Utils;

/**
 * Class DatabaseApi
 */
abstract class DatabaseApi
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'loadDatabase',
			'extend' => 'db_extend',
		],
		'prop_names' => [
			'count' => 'db_count',
			'cache' => 'db_cache',
			'package_log' => 'db_package_log',
			'db_connection' => 'db_connection',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var object
	 *
	 * The database connection object (mysqli or PgSql\Connection).
	 */
	public $connection;

	/**
	 * @var string
	 *
	 * Value of the appropriate *_TITLE constant.
	 */
	public string $title;

	/**
	 * @var bool
	 *
	 * Describes the database's method for escaping single quotes.
	 * If false, escapes by using backslashes (i.e. `\'`).
	 * If true, escapes by doubling them (i.e. `''`).
	 */
	public bool $sybase;

	/**
	 * @var bool
	 *
	 * Whether database is case sensitive.
	 */
	public bool $case_sensitive;

	/**
	 * @var bool
	 *
	 * Whether the database translation layer can accept INSERT IGNORE queries.
	 * MySQL does natively. PostgreSQL does not natively, but our PostgreSQL
	 * layer can rewrite such queries into an alternative syntax, so this will
	 * be true for both.
	 */
	public bool $support_ignore;

	/**
	 * @var bool
	 *
	 * Whether the database supports PCRE regular expressions.
	 * Always true for PostgreSQL. Depends on version for MySQL.
	 */
	public bool $supports_pcre;

	/**
	 * @var string
	 *
	 * Local copy of Config::$db_server.
	 */
	public string $server;

	/**
	 * @var string
	 *
	 * Local copy of Config::$db_name.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * Local copy of Config::$db_prefix.
	 */
	public string $prefix;

	/**
	 * @var int
	 *
	 * Local copy of Config::$db_port.
	 */
	public int $port;

	/**
	 * @var bool
	 *
	 * Local copy of Config::$db_persist.
	 */
	public bool $persist;

	/**
	 * @var bool
	 *
	 * Whether the database supports 4-byte UTF-8 characters.
	 *
	 * For PostgreSQL, this will always be set to true.
	 * For MySQL, this will be set to the value of the Config::$db_mb4.
	 *
	 * @todo Use auto-detect for MySQL.
	 */
	public bool $mb4;

	/**
	 * @var string
	 *
	 * Local copy of Config::$db_character_set.
	 */
	public string $character_set;

	/**
	 * @var bool
	 *
	 * Local copy of Config::$db_show_debug.
	 */
	public bool $show_debug;

	/**
	 * @var bool
	 *
	 * Local copy of Config::$modSettings['disableQueryCheck'].
	 */
	public bool $disableQueryCheck;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var object
	 *
	 * A loaded instance of a child class of this class.
	 */
	public static object $db;

	/**
	 * @var int
	 *
	 * The number of queries that have been run.
	 *
	 * For backward compatibilty, also referenced as global $db_count.
	 */
	public static int $count = 0;

	/**
	 * @var array
	 *
	 * Records debugging info when $this->show_debug is true.
	 *
	 * For backward compatibilty, also referenced as global $db_cache.
	 */
	public static array $cache = [];

	/**
	 * @var array
	 *
	 * Tracks package install/uninstall actions as they are performed.
	 *
	 * For backward compatibilty, also referenced as global $db_package_log.
	 */
	public static array $package_log = [];

	/**
	 * @var object
	 *
	 * A static copy of $this->connection. This exists solely to facilitate
	 * backward compatibility by populating the global $db_connection variable.
	 * Once such backward compatibility is no longer required in future versions
	 * of SMF, this variable can be completely removed from this class.
	 *
	 * If multiple DatabaseApi instances are created, this static variable will
	 * always be a reference to the first instance's local $connection object.
	 * It will not be overwritten by later instances.
	 */
	public static $db_connection;

	/**
	 * @var bool
	 *
	 * Force unbuffered output. Only applicable to MySQL.
	 */
	public static bool $unbuffered = false;

	/**********************
	 * Protected properties
	 **********************/

	/**
	 * @var array
	 *
	 * SMF tables that can't be auto-removed - in case a mod writer cocks it up!
	 */
	protected array $reservedTables = [
		'admin_info_files',
		'approval_queue',
		'attachments',
		'background_tasks',
		'ban_groups',
		'ban_items',
		'board_permissions',
		'board_permissions_view',
		'boards',
		'calendar',
		'calendar_holidays',
		'categories',
		'custom_fields',
		'group_moderators',
		'log_actions',
		'log_activity',
		'log_banned',
		'log_boards',
		'log_comments',
		'log_digest',
		'log_errors',
		'log_floodcontrol',
		'log_group_requests',
		'log_mark_read',
		'log_member_notices',
		'log_notify',
		'log_online',
		'log_packages',
		'log_polls',
		'log_reported',
		'log_reported_comments',
		'log_scheduled_tasks',
		'log_search_messages',
		'log_search_results',
		'log_search_subjects',
		'log_search_topics',
		'log_spider_hits',
		'log_spider_stats',
		'log_subscribed',
		'log_topics',
		'mail_queue',
		'member_logins',
		'membergroups',
		'members',
		'mentions',
		'message_icons',
		'messages',
		'moderator_groups',
		'moderators',
		'package_servers',
		'permission_profiles',
		'permissions',
		'personal_messages',
		'pm_labeled_messages',
		'pm_labels',
		'pm_recipients',
		'pm_rules',
		'poll_choices',
		'polls',
		'qanda',
		'scheduled_tasks',
		'sessions',
		'settings',
		'smiley_files',
		'smileys',
		'spiders',
		'subscriptions',
		'themes',
		'topics',
		'user_alerts',
		'user_alerts_prefs',
		'user_drafts',
		'user_likes',
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static method used to instantiate the child class specified by Config::$db_type.
	 *
	 * If $options is empty, correct settings will be determined automatically.
	 *
	 * @param array $options An array of database options.
	 * @return object An instance of a child class of this class.
	 */
	final public static function load(array $options = []): DatabaseApi
	{
		if (isset(self::$db)) {
			return self::$db;
		}

		// Figure out what type of database we are using.
		$class = self::getClass(!empty(Config::$db_type) ? strtolower(Config::$db_type) : 'mysql');

		if (!class_exists(__NAMESPACE__ . '\\APIs\\' . $class)) {
			ErrorHandler::displayDbError();
		}

		$class = __NAMESPACE__ . '\\APIs\\' . $class;
		self::$db = new $class($options);

		// Double check that we found what we expected.
		if (!(self::$db instanceof DatabaseApi)) {
			unset(self::$db);
			ErrorHandler::displayDbError();
		}

		return self::$db;
	}

	public static function getClass(string $db_type): string
	{
		switch (strtolower($db_type)) {
			// PostgreSQL is known by many names.
			case 'postgresql':
			case 'postgres':
			case 'postgre':
			case 'pg':
				$class = POSTGRE_TITLE;
				break;

			// MySQL and its forks.
			case 'mysql':
			case 'mariadb':
			case 'percona':
				$class = MYSQL_TITLE;
				break;

			// Something else?
			default:
				$class = ucwords(Config::$db_type);

				// If the necessary class doesn't exist, fall back to MySQL.
				if (!class_exists(__NAMESPACE__ . '\\APIs\\' . $class)) {
					$class = MYSQL_TITLE;
				}

				break;
		}

		return $class;
	}

	/**
	 * Dummy method for backward compatibility.
	 */
	public static function extend(): void
	{
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Protected constructor to prevent multiple instances.
	 */
	protected function __construct()
	{
		if (!isset($this->server)) {
			$this->server = (string) Config::$db_server;
		}

		if (!isset($this->name)) {
			$this->name = (string) Config::$db_name;
		}

		if (!isset($this->prefix)) {
			$this->prefix = (string) Config::$db_prefix;
		}

		if (!isset($this->port)) {
			$this->port = !empty(Config::$db_port) ? (int) Config::$db_port : 0;
		}

		if (!isset($this->persist)) {
			$this->persist = !empty(Config::$db_persist);
		}

		if (!isset($this->mb4)) {
			$this->mb4 = !empty(Config::$db_mb4);
		}

		if (!isset($this->character_set)) {
			$this->character_set = (string) Config::$db_character_set;
		}

		if (!isset($this->show_debug)) {
			$this->show_debug = !empty(Config::$db_show_debug);
		}

		if (!isset($this->disableQueryCheck)) {
			$this->disableQueryCheck = !empty(Config::$modSettings['disableQueryCheck']);
		}

		$this->prefixReservedTables();

		// For backward compatibility.
		$this->mapToSmcFunc();
	}

	/**
	 * Appends the correct prefix to the reserved tables' names.
	 */
	protected function prefixReservedTables(): void
	{
		// Reset $resevedTables to default.
		$class_vars = get_class_vars(__CLASS__);
		$this->reservedTables = $class_vars['reservedTables'];

		// Prepend the prefix.
		foreach ($this->reservedTables as $k => $table_name) {
			$this->reservedTables[$k] = strtolower($this->prefix . $table_name);
		}
	}

	/**
	 * For backward compatibility, make various public methods available as
	 * Utils::$smcFunc functions.
	 */
	protected function mapToSmcFunc(): void
	{
		// Only do this once.
		if (isset(Utils::$smcFunc['db_fetch_assoc'])) {
			return;
		}

		// Scalar values to export.
		Utils::$smcFunc += [
			'db_title' => &$this->title,
			'db_sybase' => &$this->sybase,
			'db_case_sensitive' => &$this->case_sensitive,
			'db_mb4' => &$this->mb4,
			'db_support_ignore' => &$this->support_ignore,
			'db_supports_pcre' => &$this->supports_pcre,
		];

		// Methods to export.
		$methods = [
			// Basic
			'db_affected_rows' => 'affected_rows',
			'db_connect_errno' => 'connect_errno',
			'db_connect_error' => 'connect_error',
			'db_cte_support' => 'cte_support',
			'db_custom_order' => 'custom_order',
			'db_data_seek' => 'data_seek',
			'db_error' => 'error',
			'db_error_insert' => 'error_insert',
			'db_escape_string' => 'escape_string',
			'db_escape_wildcard_string' => 'escape_wildcard_string',
			'db_fetch_all' => 'fetch_all',
			'db_fetch_assoc' => 'fetch_assoc',
			'db_fetch_row' => 'fetch_row',
			'db_free_result' => 'free_result',
			'db_insert' => 'insert',
			'db_insert_id' => 'insert_id',
			'db_is_resource' => 'is_resource',
			'db_native_replace' => 'native_replace',
			'db_num_fields' => 'num_fields',
			'db_num_rows' => 'num_rows',
			'db_ping' => 'ping',
			'db_query' => 'query',
			'db_quote' => 'quote',
			'db_select_db' => 'select',
			'db_server_info' => 'server_info',
			'db_transaction' => 'transaction',
			'db_unescape_string' => 'unescape_string',

			// Extra
			'db_allow_persistent' => 'allow_persistent',
			'db_backup_table' => 'backup_table',
			'db_get_vendor' => 'get_vendor',
			'db_get_version' => 'get_version',
			'db_list_tables' => 'list_tables',
			'db_optimize_table' => 'optimize_table',
			'db_table_sql' => 'table_sql',

			// Packages
			'db_add_column' => 'add_column',
			'db_add_index' => 'add_index',
			'db_calculate_type' => 'calculate_type',
			'db_change_column' => 'change_column',
			'db_create_table' => 'create_table',
			'db_drop_table' => 'drop_table',
			'db_list_columns' => 'list_columns',
			'db_list_indexes' => 'list_indexes',
			'db_remove_column' => 'remove_column',
			'db_remove_index' => 'remove_index',
			'db_table_structure' => 'table_structure',

			// Search
			'db_create_word_search' => 'create_word_search',
			'db_search_language' => 'search_language',
			'db_search_query' => 'query',
			'db_search_support' => 'search_support',
		];

		// Wrap each method in a closure that calls it.
		foreach ($methods as $key => $method) {
			Utils::$smcFunc[$key] = function (...$args) use ($method) {
				return $this->$method(...$args);
			};
		}
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\DatabaseApi::exportStatic')) {
	DatabaseApi::exportStatic();
}

?>