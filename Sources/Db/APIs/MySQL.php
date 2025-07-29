<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\APIs;

use SMF\Config;
use SMF\Db\DatabaseApi;
use SMF\Db\DatabaseApiInterface;
use SMF\ErrorHandler;
use SMF\IP;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Uuid;

/**
 * Interacts with MySQL databases.
 */
class MySQL extends DatabaseApi implements DatabaseApiInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $title = MYSQL_TITLE;

	/**
	 *
	 */
	public bool $sybase = false;

	/**
	 *
	 */
	public bool $mb4 = true;

	/**
	 *
	 */
	public bool $case_sensitive = false;

	/**
	 *
	 */
	public bool $support_ignore = true;

	/**
	 *
	 */
	public bool $supports_pcre = false;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * Temporary reference to a mysqli object.
	 * Might be the same as $this->connection, but might not be.
	 * Used to pass the correct connection to $this->replace__callback.
	 */
	protected $temp_connection;

	/**
	 * @var array
	 *
	 * Used to pass values to $this->replace__callback.
	 */
	protected $temp_values;

	/**
	 * @var object
	 *
	 * A prepared MySQL statement (a mysqli_stmt object).
	 */
	protected $error_data_prep;

	/**
	 * @var string
	 *
	 * Vendor of this particular variant of MySQL.
	 */
	protected $vendor;

	/**
	 * @var string
	 *
	 * MySQL version string.
	 */
	protected $version;

	/**
	 * @var array
	 *
	 * Available MySQL engines.
	 */
	protected $engines = [];

	/**
	 * @var bool
	 *
	 * Whether this version of MySQL has CTE support.
	 */
	protected $supports_cte;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function query(string $db_string, array $db_values = [], ?object $connection = null, ?string $identifier = null): object|bool
	{
		// Comments that are allowed in a query are preg_removed.
		$allowed_comments_from = [
			'~(?<![\'\\\\])\'\X*?(?<![\'\\\\])\'~',
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		];
		$allowed_comments_to = [
			' %s ',
			' ',
			'',
			'',
			'',
		];

		// Decide which connection to use.
		$connection = $connection ?? $this->connection;

		// One more query....
		self::$count++;

		if (!$this->disableQueryCheck && str_contains($db_string, '\'') && empty($db_values['security_override'])) {
			$this->error_backtrace('No direct access...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);
		}

		// Use "ORDER BY null" to prevent Mysql doing filesorts for Group By clauses without an Order By
		if (str_contains($db_string, 'GROUP BY') && !str_contains($db_string, 'ORDER BY') && preg_match('~^\s+SELECT~i', $db_string)) {
			// Add before LIMIT
			if ($pos = strpos($db_string, 'LIMIT ')) {
				$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
			} else {
				// Append it.
				$db_string .= "\n\t\t\tORDER BY null";
			}
		}

		// Inject the values passed to this function.
		if (empty($db_values['security_override']) && (!empty($db_values) || str_contains($db_string, '{db_prefix}'))) {
			$db_string = $this->quote($db_string, $db_values, $connection);
		}

		// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
		if (!$this->disableQueryCheck) {
			$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $db_string)));

			// Comments?  We don't use comments in our queries, we leave 'em outside!
			if (strpos($clean, '/*') > 2 || str_contains($clean, '--') || str_contains($clean, ';')) {
				$fail = true;
			}
			// Trying to change passwords, slow us down, or something?
			elseif (str_contains($clean, 'sleep') && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0) {
				$fail = true;
			} elseif (str_contains($clean, 'benchmark') && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0) {
				$fail = true;
			}

			if (!empty($fail) && function_exists('log_error')) {
				$this->error_backtrace('No direct access...', 'No direct access...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
			}
		}

		// Debugging.
		if ($this->show_debug) {
			// Get the file and line number this function was called.
			list($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

			if (!empty($_SESSION['debug_redirect'])) {
				self::$cache = array_merge($_SESSION['debug_redirect'], self::$cache);
				self::$count = count(self::$cache) + 1;
				$_SESSION['debug_redirect'] = [];
			}

			// Don't overload it.
			self::$cache[self::$count]['q'] = self::$count < 50 ? $db_string : '...';
			self::$cache[self::$count]['f'] = $file;
			self::$cache[self::$count]['l'] = $line;
			self::$cache[self::$count]['s'] = ($st = microtime(true)) - TIME_START;
		}

		$ret = @mysqli_query($connection, $db_string, self::$unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

		if ($ret === false && empty($db_values['db_error_skip'])) {
			list($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);
			$query_error = $this->error();

			// Nothing's defined yet... just die with it.
			if (empty(Utils::$context) || empty(Lang::$txt) || defined('SMF_INSTALLING')) {
				die($query_error);
			}

			// Show an error message, if possible.
			Utils::$context['error_title'] = Lang::getTxt('database_error', file: 'General');
			$error_message = Lang::getTxt('try_again', file: 'General');

			if (isset(User::$me) && User::$me->allowedTo('admin_forum')) {
				$error_message = nl2br($query_error) . '<br>' . Lang::getTxt('file', file: 'General') . ': ' . $file . '<br>' . Lang::getTxt('line', file: 'General') . ': ' . $line;

				if ($this->show_debug) {
					$error_message .= '<br><br>' . nl2br($db_string);
				}
			}

			ErrorHandler::log(Lang::getTxt('database_error', file: 'General') . ': ' . $query_error . (!empty(Config::$modSettings['enableErrorQueryLogging']) ? "\n\n{$db_string}" : ''), 'database', $file, $line);
			ErrorHandler::fatal($error_message, false);
		}

		// Debugging.
		if ($this->show_debug) {
			self::$cache[self::$count]['t'] = microtime(true) - $st;
		}

		return $ret;
	}

	/**
	 *
	 */
	public function quote(string $db_string, array $db_values, ?object $connection = null): string
	{
		// Only bother if there's something to replace.
		if (str_contains($db_string, '{')) {
			// This is needed by the callback function.
			$this->temp_values = $db_values;
			$this->temp_connection = $connection ?? $this->connection;

			// Do the quoting and escaping
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', [$this, 'replacement__callback'], $db_string);

			unset($this->temp_values, $this->temp_connection);
		}

		return $db_string;
	}

	/**
	 *
	 */
	public function fetch_row(object $result): array|false|null
	{
		$row = mysqli_fetch_row($result);

		if (is_array($row)) {
			foreach ($row as $key => $value) {
				$row[$key] = is_string($value) ? $this->restore_mb4($value) : $value;
			}
		}

		return $row;
	}

	/**
	 *
	 */
	public function fetch_assoc(object $result): array|false|null
	{
		$row = mysqli_fetch_assoc($result);

		if (is_array($row)) {
			foreach ($row as $key => $value) {
				$row[$key] = is_string($value) ? $this->restore_mb4($value) : $value;
			}
		}

		return $row;

	}

	/**
	 *
	 */
	public function fetch_all(object $request): array
	{
		$return = mysqli_fetch_all($request, MYSQLI_ASSOC);

		if (empty($return)) {
			return [];
		}

		foreach ($return as $row_num => $row) {
			if (is_array($row)) {
				foreach ($row as $key => $value) {
					$return[$row_num][$key] = is_string($value) ? $this->restore_mb4($value) : $value;
				}
			}
		}

		return $return;
	}

	/**
	 *
	 */
	public function fetch_object(object $result, string $class = 'stdClass', array $args = []): object|false|null
	{
		return mysqli_fetch_object($result, $class, $args);
	}

	/**
	 *
	 */
	public function free_result(object $result): bool
	{
		mysqli_free_result($result);

		return true;
	}

	/**
	 *
	 */
	public function insert(string $method, string $table, array $columns, array $data, array $keys, int $returnmode = 0, ?object $connection = null): int|array|null
	{
		$connection = $connection ?? $this->connection;

		$return_var = null;

		// With nothing to insert, simply return.
		if (empty($table) || empty($data)) {
			return null;
		}

		// Force method to lower case
		$method = strtolower($method);

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		$with_returning = false;

		if (!empty($keys) && (count($keys) > 0) && $returnmode > 0) {
			$with_returning = true;

			if ($returnmode == 2) {
				$return_var = [];
			}
		}

		// Ensure that $data is a multidimensional array.
		if (array_filter($data, fn($dataRow) => is_array($dataRow)) !== $data) {
			// If backward compatibility mode is enabled, quietly clean up after
			// old mods that did the wrong thing. Otherwise, trigger an error.
			if (!empty(Config::$backward_compatibility)) {
				$data = [$data];
			} else {
				$this->error_backtrace(
					'Invalid data structure sent to the database.',
					'',
					E_USER_ERROR,
					__FILE__,
					__LINE__,
				);
			}
		}

		// Create the mold for a single row insert.
		$insertData = '(';

		foreach ($columns as $columnName => $type) {
			// Are we restricting the length?
			if (str_contains($type, 'string-')) {
				$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
			} else {
				$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
			}
		}
		$insertData = substr($insertData, 0, -2) . ')';

		// Create an array consisting of only the columns.
		$indexed_columns = array_keys($columns);

		// Here's where the variables are injected to the query.
		$insertRows = [];

		foreach ($data as $dataRow) {
			$insertRows[] = $this->quote($insertData, array_combine($indexed_columns, $dataRow), $connection);
		}

		// Determine the method of insertion.
		switch ($method) {
			case 'replace':
				$queryTitle = 'REPLACE';

				// Sanity check for replace is key part of the columns array
				if (empty($keys)) {
					$this->error_backtrace(
						'When using the replace mode, the key column is a required entry.',
						'Change the method of db insert to insert or add the pk field to the key array',
						E_USER_ERROR,
						__FILE__,
						__LINE__,
					);
				}

				if (count(array_intersect_key($columns, array_flip($keys))) !== count($keys)) {
					$this->error_backtrace(
						'Primary Key field missing in insert call',
						'Change the method of db insert to insert or add the pk field to the columns array',
						E_USER_ERROR,
						__FILE__,
						__LINE__,
					);
				}

				break;

			case 'ignore':
				$queryTitle = 'INSERT IGNORE';
				break;

			default:
				$queryTitle = 'INSERT';
				break;
		}

		if (!$with_returning || $method != 'ignore') {
			// Do the insert.
			$this->query(
				$queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
				VALUES
					' . implode(',
					', $insertRows),
				[
					'security_override' => true,
					'db_error_skip' => $table === $this->prefix . 'log_errors',
				],
				$connection,
			);
		}
		// Special way for ignore method with returning
		else {
			$count = count($insertRows);
			$ai = 0;

			for ($i = 0; $i < $count; $i++) {
				$old_id = $this->insert_id($table);

				$this->query(
					$queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
					VALUES
						' . $insertRows[$i],
					[
						'security_override' => true,
						'db_error_skip' => $table === $this->prefix . 'log_errors',
					],
					$connection,
				);
				$new_id = $this->insert_id($table);

				// the inserted value was new
				if ($old_id != $new_id) {
					$ai = $new_id;
				}
				// the inserted value already exists we need to find the pk
				else {
					$where_string = [];

					foreach ($columns as $column_name => $type) {
						if (str_contains($type, 'string-')) {
							$where_string[] = $column_name . ' = ' . sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . ')', $column_name);
						} else {
							$where_string[] = $column_name . ' = ' . sprintf('{%1$s:%2$s}', $type, $column_name);
						}
					}

					$where_string = implode(' AND ', $where_string);

					$request = $this->query(
						'SELECT ' . $keys[0] . '
						FROM ' . $table . '
						WHERE ' . $where_string . '
						LIMIT 1',
						array_combine($indexed_columns, $data[$i]),
					);

					if ($request !== false && $this->num_rows($request) == 1) {
						$row = $this->fetch_assoc($request);
						$ai = (int) $row[$keys[0]];
					}
				}

				switch ($returnmode) {
					case 2:
						$return_var[] = $ai;
						break;

					default:
						$return_var = $ai;
						break;
				}
			}
		}

		if ($with_returning) {
			if ($returnmode == 1 && empty($return_var)) {
				$return_var = $this->insert_id($table, $keys[0]) + count($insertRows) - 1;
			} elseif ($returnmode == 2 && empty($return_var)) {
				$return_var = [];

				$count = count($insertRows);

				$start = $this->insert_id($table, $keys[0]);

				for ($i = 0; $i < $count; $i++) {
					$return_var[] = $start + $i;
				}
			}

			return $return_var;
		}

		return null;
	}

	/**
	 *
	 */
	public function insert_id(string $table, ?string $field = null, ?object $connection = null): int
	{
		// MySQL doesn't need the table or field information.
		return mysqli_insert_id($connection ?? $this->connection);
	}

	/**
	 *
	 */
	public function update_from(array $table, array $from_tables, string $set, string $where, array $db_values, ?object $connection = null): bool
	{
		if (empty($table['name']) || empty($table['alias']) || empty($set)) {
			return false;
		}

		$joins = [];

		foreach ($from_tables as $ft) {
			if (empty($ft['name']) || empty($ft['alias']) || empty($ft['condition'])) {
				continue;
			}

			$joins[] = 'JOIN ' . $ft['name'] . ' AS ' . $ft['alias'] . ' ON (' . $ft['condition'] . ')';
		}

		if (empty($joins)) {
			return false;
		}

		return $this->query(
			'UPDATE ' . $table['name'] . ' AS ' . $table['alias'] . '
				' . implode('
				', $joins) . '
			SET ' . $set . (!empty($where) ? '
			WHERE ' . $where : ''),
			$db_values,
			$connection,
		);
	}

	/**
	 *
	 */
	public function num_rows(object $result): int
	{
		return mysqli_num_rows($result);
	}

	/**
	 *
	 */
	public function data_seek(object $result, int $offset): bool
	{
		return mysqli_data_seek($result, $offset);
	}

	/**
	 *
	 */
	public function num_fields(object $result): int
	{
		return mysqli_num_fields($result);
	}

	/**
	 *
	 */
	public function escape_string(string $string, ?object $connection = null): string
	{
		return mysqli_real_escape_string($connection ?? $this->connection, $string);
	}

	/**
	 *
	 */
	public function unescape_string(string $string): string
	{
		return stripslashes($string);
	}

	/**
	 *
	 */
	public function fix_mb4(string $string): string
	{
		return $this->mb4 ? $string : mb_encode_numericentity($string, [0x010000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
	}

	/**
	 *
	 */
	public function server_info(?object $connection = null): string
	{
		return mysqli_get_server_info($connection ?? $this->connection);
	}

	/**
	 *
	 */
	public function affected_rows(?object $connection = null): int
	{
		return mysqli_affected_rows($connection ?? $this->connection);
	}

	/**
	 *
	 */
	public function transaction(string $type = 'commit', ?object $connection = null): bool
	{
		$type = strtoupper($type);

		if (in_array($type, ['BEGIN', 'ROLLBACK', 'COMMIT'])) {
			return @mysqli_query($connection ?? $this->connection, $type);
		}

		return false;
	}

	/**
	 *
	 */
	public function error(?object $connection = null): string
	{
		if (!(($connection ?? $this->connection) instanceof \mysqli)) {
			return '';
		}

		return mysqli_error($connection ?? $this->connection);
	}

	/**
	 *
	 */
	public function select(string $database, ?object $connection = null): bool
	{
		return mysqli_select_db($connection ?? $this->connection, $database);
	}

	/**
	 *
	 */
	public function get_engines(): array
	{
		if (empty($this->engines)) {
			$request = $this->query('SHOW ENGINES', []);

			while ($row = $this->fetch_assoc($request)) {
				if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT') {
					$this->engines[] = $row['Engine'];
				}
			}

			$this->free_result($request);
		}

		return $this->engines;
	}

	/**
	 *
	 */
	public function escape_wildcard_string(string $string, bool $translate_human_wildcards = false): string
	{
		$replacements = [
			'%' => '\%',
			'_' => '\_',
			'\\' => '\\\\',
		];

		if ($translate_human_wildcards) {
			$replacements += [
				'*' => '%',
			];
		}

		return strtr($string, $replacements);
	}

	/**
	 *
	 */
	public function is_resource(mixed $result): bool
	{
		return ($result instanceof \mysqli_result);
	}

	/**
	 *
	 */
	public function ping(?object $connection = null): bool
	{
		return mysqli_ping($connection ?? $this->connection);
	}

	/**
	 *
	 */
	public function error_insert(array $error_array): void
	{
		// Without a database we can't do anything.
		if (empty($this->connection)) {
			return;
		}

		// String keys are easier to work with.
		if (!isset($error_array['ip'])) {
			$error_array = array_combine(['id_member', 'log_time', 'ip', 'url', 'message', 'session', 'error_type', 'file', 'line', 'backtrace'], $error_array);
		}

		if (empty($this->error_data_prep)) {
			$this->error_data_prep = mysqli_prepare(
				$this->connection,
				'INSERT INTO ' . $this->prefix . 'log_errors
					(id_member, log_time, ip, url, message, session, error_type, file, line, backtrace)
				VALUES( ?, ?, unhex(?), ?, ?, ?, ?, ?, ?, ?)',
			);
		}

		if (filter_var($error_array['ip'], FILTER_VALIDATE_IP) !== false) {
			$error_array['ip'] = bin2hex(inet_pton($error_array['ip']));
		} else {
			$error_array['ip'] = null;
		}

		mysqli_stmt_bind_param(
			$this->error_data_prep,
			'iissssssis',
			$error_array['id_member'],
			$error_array['log_time'],
			$error_array['ip'],
			$error_array['url'],
			$error_array['message'],
			$error_array['session'],
			$error_array['error_type'],
			$error_array['file'],
			$error_array['line'],
			$error_array['backtrace'],
		);

		mysqli_stmt_execute($this->error_data_prep);
	}

	/**
	 *
	 */
	public function custom_order(string $field, array $array_values, bool $desc = false): string
	{
		$return = 'CASE ' . $field . ' ';
		$count = count($array_values);
		$then = ($desc ? ' THEN -' : ' THEN ');

		for ($i = 0; $i < $count; $i++) {
			$return .= 'WHEN ' . (int) $array_values[$i] . $then . $i . ' ';
		}

		$return .= 'END';

		return $return;
	}

	/**
	 *
	 */
	public function native_replace(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function cte_support(): bool
	{
		if (isset($this->supports_cte)) {
			return $this->supports_cte;
		}

		$this->get_version();

		$min_version = str_contains(strtolower($this->version), 'mariadb') ? '10.2.2' : '8.0.1';

		$this->supports_cte = version_compare($this->version, $min_version, '>=');

		return $this->supports_cte;
	}

	/**
	 *
	 */
	public function connect_error(): string
	{
		return mysqli_connect_error();
	}

	/**
	 *
	 */
	public function connect_errno(): int
	{
		return mysqli_connect_errno();
	}

	/**
	 *
	 */
	public function detect_charset(?string $table = null, ?string $column = null, bool $reset = false): string
	{
		static $detected;

		if ($reset) {
			$detected = null;
		}

		// MySQL has a default character set for the database, but tables can
		// use different character sets, and even columns within those tables
		// can use different character sets again. So figuring out the actual
		// character set used by any given table or column is complicated.
		if (!isset($detected)) {
			$request = $this->query(
				'SELECT
					s.DEFAULT_CHARACTER_SET_NAME,
					t.TABLE_NAME,
					a.CHARACTER_SET_NAME AS TABLE_CHARSET,
					c.COLUMN_NAME,
					c.CHARACTER_SET_NAME AS COLUMN_CHARSET
				FROM information_schema.TABLES AS t
					INNER JOIN information_schema.SCHEMATA AS s ON (s.SCHEMA_NAME = t.TABLE_SCHEMA)
					INNER JOIN information_schema.COLUMNS AS c ON (c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME)
					INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY AS a ON (t.TABLE_COLLATION = a.COLLATION_NAME)
				WHERE t.TABLE_SCHEMA = {string:db_name}
				ORDER BY t.TABLE_SCHEMA, t.TABLE_NAME, c.COLUMN_NAME',
				[
					'db_name' => $this->name,
				],
			);

			$detected = $this->fetch_all($request);
			$this->free_result($request);
		}

		// If no results were returned, the database doesn't exist yet.
		// Therefore, assume that it will be utf8mb4 once it is created.
		if (!isset($detected[0]['DEFAULT_CHARACTER_SET_NAME'])) {
			return 'utf8mb4';
		}

		$charset = $detected[0]['DEFAULT_CHARACTER_SET_NAME'];

		if (isset($table)) {
			$table = str_replace('{db_prefix}', Config::$db_prefix, $table);

			foreach ($detected as $row) {
				if (
					$row['TABLE_NAME'] === $table
					&& (
						!isset($column)
						|| $row['COLUMN_NAME'] === $column
					)
				) {
					$charset = isset($column) ? $row['COLUMN_CHARSET'] : $row['TABLE_CHARSET'];
					break;
				}
			}
		}

		return $charset;
	}

	/****************************************
	 * Methods that formerly lived in DbExtra
	 ****************************************/

	/**
	 *
	 */
	public function backup_table(string $table, string $backup_table): object|bool
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// First, get rid of the old table.
		$this->query(
			'DROP TABLE IF EXISTS {raw:backup_table}',
			[
				'backup_table' => $backup_table,
			],
		);

		// Can we do this the quick way?
		$result = $this->query(
			'CREATE TABLE {raw:backup_table} LIKE {raw:table}',
			[
				'backup_table' => $backup_table,
				'table' => $table,
			],
		);

		// If this failed, we go old school.
		if ($result) {
			$request = $this->query(
				'INSERT INTO {raw:backup_table}
				SELECT *
				FROM {raw:table}',
				[
					'backup_table' => $backup_table,
					'table' => $table,
				],
			);

			// Old school or no school?
			if ($request) {
				return $request;
			}
		}

		// At this point, the quick method failed.
		$result = $this->query(
			'SHOW CREATE TABLE {raw:table}',
			[
				'table' => $table,
			],
		);
		list(, $create) = $this->fetch_row($result);
		$this->free_result($result);

		$create = preg_split('/[\n\r]/', $create);

		$auto_inc = '';
		// Default engine type.
		$engine = 'InnoDB';
		$charset = '';
		$collate = '';

		foreach ($create as $k => $l) {
			// Get the name of the auto_increment column.
			if (strpos($l, 'auto_increment')) {
				$auto_inc = trim($l);
			}

			// For the engine type, see if we can work out what it is.
			if (str_contains($l, 'ENGINE') || str_contains($l, 'TYPE')) {
				// Extract the engine type.
				preg_match('~(ENGINE|TYPE)=(\w+)(\sDEFAULT)?(\sCHARSET=(\w+))?(\sCOLLATE=(\w+))?~', $l, $match);

				if (!empty($match[1])) {
					$engine = $match[1];
				}

				if (!empty($match[2])) {
					$engine = $match[2];
				}

				if (!empty($match[5])) {
					$charset = $match[5];
				}

				if (!empty($match[7])) {
					$collate = $match[7];
				}
			}

			// Skip everything but keys...
			if (!str_contains($l, 'KEY')) {
				unset($create[$k]);
			}
		}

		if (!empty($create)) {
			$create = '(
				' . implode('
				', $create) . ')';
		} else {
			$create = '';
		}

		$request = $this->query(
			'CREATE TABLE {raw:backup_table} {raw:create}
			ENGINE={raw:engine}' . (empty($charset) ? '' : ' CHARACTER SET {raw:charset}' . (empty($collate) ? '' : ' COLLATE {raw:collate}')) . '
			SELECT *
			FROM {raw:table}',
			[
				'backup_table' => $backup_table,
				'table' => $table,
				'create' => $create,
				'engine' => $engine,
				'charset' => empty($charset) ? '' : $charset,
				'collate' => empty($collate) ? '' : $collate,
			],
		);

		if ($auto_inc != '') {
			if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && str_ends_with($auto_inc, ',')) {
				$auto_inc = substr($auto_inc, 0, -1);
			}

			$this->query(
				'ALTER TABLE {raw:backup_table}
				CHANGE COLUMN {raw:column_detail} {raw:auto_inc}',
				[
					'backup_table' => $backup_table,
					'column_detail' => $match[1],
					'auto_inc' => $auto_inc,
				],
			);
		}

		return $request;
	}

	/**
	 *
	 */
	public function optimize_table(string $table): int|float
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// Get how much overhead there is.
		$request = $this->query(
			'SHOW TABLE STATUS LIKE {string:table_name}',
			[
				'table_name' => str_replace('_', '\\_', $table),
			],
		);
		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		$data_before = $row['Data_free'] ?? 0;
		$request = $this->query(
			'OPTIMIZE TABLE `{raw:table}`',
			[
				'table' => $table,
			],
		);

		if (!$request) {
			return -1;
		}

		// How much left?
		$request = $this->query(
			'SHOW TABLE STATUS LIKE {string:table}',
			[
				'table' => str_replace('_', '\\_', $table),
			],
		);
		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		$total_change = isset($row['Data_free']) && $data_before > $row['Data_free'] ? $data_before / 1024 : 0;

		return $total_change;
	}

	/**
	 *
	 */
	public function table_sql(string $table_name): string
	{
		$structure = $this->table_structure($table_name);

		// Drop it if it exists.
		$schema_create = 'DROP TABLE IF EXISTS ' . '`' . $structure['name'] . '`;';
		$schema_create .= "\n\n";

		// Start the create table...
		$schema_create .= 'CREATE TABLE ' . '`' . $structure['name'] . '` (';
		$schema_create .= "\n";

		$inner_lines = [];

		foreach ($structure['columns'] as $column) {
			$line = '  `' . $column['name'] . '` ' . $column['type'];

			if (is_numeric($column['size'])) {
				$line .= '(' . $column['size'] . ')';
			}

			if (!empty($column['unsigned'])) {
				$line .= ' unsigned';
			}

			if (!empty($column['generation_expression'])) {
				$line .= ' GENERATED ALWAYS AS (' . $this->unescape_string($column['generation_expression']) . ') ' . (!empty($column['stored']) ? 'STORED' : 'VIRTUAL');
			}

			if (!empty($column['not_null'])) {
				$line .= ' NOT NULL';
			}

			if (
				empty($column['generation_expression'])
				&& (
					!is_null($column['default'])
					|| empty($column['not_null'])
				)
			) {
				$line .= ' DEFAULT';

				if (is_null($column['default'])) {
					$line .= ' NULL';
				} elseif (is_numeric($column['default'])) {
					$line .= ' ' . $column['default'];
				} else {
					$line .= ' \'' . $column['default'] . '\'';
				}
			}

			if (!empty($column['auto'])) {
				$line .= ' AUTO_INCREMENT';
			}

			$inner_lines[] = $line;
		}

		foreach ($structure['indexes'] as $index) {
			$line = '  ';

			switch ($index['type']) {
				case 'primary':
					$line .= 'PRIMARY KEY';
					break;

				case 'unique':
					$line .= 'UNIQUE KEY `' . $index['name'] . '`';
					break;

				case 'fulltext':
					$line .= 'FULLTEXT KEY `' . $index['name'] . '`';
					break;

				default:
					$line .= 'KEY `' . $index['name'] . '`';
					break;
			 }

			 $line .= ' (`' . implode('`, `', $index['columns']) . '`)';

			 $inner_lines[] = $line;
		}

		$schema_create .= implode(",\n", $inner_lines) . "\n";
		$schema_create .= ')';

		if (!empty($structure['engine'])) {
			$schema_create .= ' ENGINE=' . $structure['engine'];
		}

		if (!empty($structure['row_format'])) {
			$schema_create .= ' ROW_FORMAT=' . $structure['row_format'];
		}

		if (!empty($structure['collation'])) {
			$schema_create .= ' COLLATE=' . $structure['collation'];
		}

		if (!empty($structure['comment'])) {
			$schema_create .= ' COMMENT="' . $structure['comment'] . '"';
		}

		$schema_create .= "\n";

		return $schema_create;
	}

	/**
	 *
	 */
	public function list_tables(string|bool $db = false, string|bool $filter = false): array
	{
		$db = $db == false ? $this->name : $db;
		$db = trim($db);
		$filter = $filter == false ? '' : ' LIKE \'' . $filter . '\'';

		$request = $this->query(
			'SHOW TABLES
			FROM `{raw:db}`
			{raw:filter}',
			[
				'db' => $db[0] == '`' ? strtr($db, ['`' => '']) : $db,
				'filter' => $filter,
			],
		);
		$tables = [];

		while ($row = $this->fetch_row($request)) {
			$tables[] = $row[0];
		}
		$this->free_result($request);

		return $tables;
	}

	/**
	 *
	 */
	public function get_version(): string
	{
		if (!empty($this->version)) {
			return $this->version;
		}

		$request = $this->query(
			'SELECT VERSION()',
			[
			],
		);
		list($this->version) = $this->fetch_row($request);
		$this->free_result($request);

		return $this->version;
	}

	/**
	 *
	 */
	public function get_vendor(): string
	{
		if (!empty($this->vendor)) {
			return $this->vendor;
		}

		$request = $this->query('SELECT @@version_comment');
		list($comment) = $this->fetch_row($request);
		$this->free_result($request);

		// Skip these if we don't have a comment.
		if (!empty($comment)) {
			if (stripos($comment, 'percona') !== false) {
				$this->vendor = 'Percona';
			} elseif (stripos($comment, 'mariadb') !== false) {
				$this->vendor = 'MariaDB';
			} else {
				$this->vendor = 'MySQL';
			}
		} else {
			return Lang::getTxt('unknown', file: 'General');
		}

		return $this->vendor;
	}

	/**
	 *
	 */
	public function allow_persistent(): bool
	{
		$value = ini_get('mysqli.allow_persistent');

		return (bool) (strtolower($value) == 'on' || strtolower($value) == 'true' || $value == '1');
	}

	/*****************************************
	 * Methods that formerly lived in DbSearch
	 *****************************************/

	/**
	 *
	 */
	public function search_query(string $db_string, array $db_values = [], ?object $connection = null, ?string $identifier = null): object|bool
	{
		return $this->query($db_string, $db_values, $connection, $identifier);
	}

	/**
	 *
	 */
	public function search_support(string $search_type): bool
	{
		$supported_types = ['fulltext'];

		return in_array($search_type, $supported_types);
	}

	/**
	 *
	 */
	public function create_word_search(string $size): void
	{
		if ($size == 'small') {
			$size = 'smallint(5)';
		} elseif ($size == 'medium') {
			$size = 'mediumint(8)';
		} else {
			$size = 'int(10)';
		}

		$this->query(
			'CREATE TABLE {db_prefix}log_search_words (
				id_word {raw:size} unsigned NOT NULL default {string:string_zero},
				id_msg int(10) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_word, id_msg)
			) ENGINE=InnoDB',
			[
				'string_zero' => '0',
				'size' => $size,
			],
		);
	}

	/**
	 *
	 */
	public function search_language(): ?string
	{
		return null;
	}

	/*******************************************
	 * Methods that formerly lived in DbPackages
	 *******************************************/

	/**
	 *
	 */
	public function add_column(string $table_name, array $column_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$column_info = array_change_key_case($column_info);

		// Log that we will want to uninstall this!
		self::$package_log[] = ['remove_column', $short_table_name, $column_info['name']];

		// Does it exist - if so don't add it again!
		$columns = $this->list_columns($table_name, false);

		foreach ($columns as $column) {
			if ($column == $column_info['name']) {
				// If we're going to overwrite then use change column.
				if ($if_exists == 'update') {
					return $this->change_column($table_name, $column_info['name'], $column_info);
				}

				return false;
			}
		}

		// Get the specifics...
		$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;

		// Now add the thing!
		$this->query(
			'ALTER TABLE ' . $short_table_name . '
			ADD ' . $this->create_query_column($column_info) . (empty($column_info['auto']) ? '' : ' primary key'),
			[
				'security_override' => true,
			],
		);

		return true;
	}

	/**
	 *
	 */
	public function add_index(string $table_name, array $index_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// No columns = no index.
		if (empty($index_info['columns'])) {
			return false;
		}

		// MySQL If it's a text column, we need to add a size.
		$cols = $this->list_columns($table_name, true);

		foreach ($index_info['columns'] as &$c) {
			if (is_array($c)) {
				$c = $c['name'];
			}

			$c = trim($c);
			$cols[$c]['size'] = isset($cols[$c]['size']) && is_numeric($cols[$c]['size']) ? $cols[$c]['size'] : null;
			list($type, $size) = $this->calculate_type($cols[$c]['type'], (int) $cols[$c]['size']);

			// If a size was already specified, we won't be able to match it anyways.
			if (
				!isset($cols[$c])
				|| !in_array($cols[$c]['type'], ['text', 'mediumtext', 'longtext', 'varchar', 'char'])
				|| (
					isset($size)
					&& $size <= 191
				)
			) {
				continue;
			}

			$c .= '(191)';
		}

		$columns = implode(',', $index_info['columns']);

		// No name - make it up!
		if (empty($index_info['name'])) {
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary') {
				$index_info['name'] = '';
			} else {
				$index_info['name'] = trim(implode('_', preg_replace('~(\(\d+\))~', '', $index_info['columns'])));
			}
		}

		// Log that we are going to want to remove this on uninstall!
		self::$package_log[] = ['remove_index', $short_table_name, $index_info['name']];

		// Let's get all our existing indexes.
		$existing_indexes = $this->list_indexes($table_name, true);

		// Special handling is needed if we are trying to replace the primary
		// key on a table where the current primary key refers to an
		// auto-increment column.
		if (
			($index_info['type'] ?? null) == 'primary'
			&& array_filter($existing_indexes, fn($idx) => $idx['type'] === 'primary') !== []
			&& array_filter($cols, fn($col) => !empty($col['auto'])) !== []
		) {
			$auto_col = current(array_filter($cols, fn($col) => !empty($col['auto'])));
			$auto_col['auto'] = false;
			$this->change_column($table_name, $auto_col['name'], $auto_col);
		}

		// If we want to overwrite simply remove the current one then continue.
		if ($if_exists == 'update') {
			// Do we already have it?
			foreach ($existing_indexes as $existing_index) {
				if (
					$existing_index['name'] == $index_info['name']
					|| (
						$existing_index['type'] == 'primary'
						&& ($index_info['type'] ?? null) == 'primary'
					)
				) {
					$this->remove_index($table_name, $index_info['name']);
				}
			}
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary') {
			$result = $this->query(
				'ALTER TABLE ' . $short_table_name . '
				ADD PRIMARY KEY (' . $columns . ')',
				[
					'security_override' => true,
				],
			);
		} else {
			$result = $this->query(
				'ALTER TABLE ' . $short_table_name . '
				ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')',
				[
					'security_override' => true,
				],
			);
		}

		// If necessary, restore the auto_increment status to the PK column.
		if (isset($auto_col)) {
			$auto_col['auto'] = true;
			$this->change_column($table_name, $auto_col['name'], $auto_col);
		}

		// Query returns a result or true if successful, false otherwise.
		return $result !== false;
	}

	/**
	 *
	 */
	public function calculate_type(string $type_name, ?int $type_size = null, bool $reverse = false): array
	{
		// MySQL is actually the generic baseline.

		$type_name = strtolower($type_name);

		// Generic => Specific.
		if (!$reverse) {
			$types = [
				'inet' => 'varbinary',
				'uuid' => 'binary',
			];
		} else {
			$types = [
				'varbinary' => 'inet',
				'binary' => 'uuid',
			];
		}

		// Got it? Change it!
		if (isset($types[$type_name])) {
			if ($type_name == 'inet' && !$reverse) {
				$type_size = 16;
				$type_name = 'varbinary';
			} elseif ($type_name == 'uuid' && !$reverse) {
				$type_size = 16;
				$type_name = 'binary';
			} elseif ($type_name == 'varbinary' && $reverse && $type_size == 16) {
				$type_name = 'inet';
				$type_size = null;
			} elseif ($type_name == 'binary' && $reverse && $type_size == 16) {
				$type_name = 'uuid';
				$type_size = null;
			} elseif ($type_name == 'varbinary') {
				$type_name = 'varbinary';
			} elseif ($type_name == 'binary') {
				$type_name = 'binary';
			} else {
				$type_name = $types[$type_name];
			}
		} elseif ($type_name == 'boolean') {
			$type_size = null;
		} elseif ($type_name === 'jsonb') {
			$type_name === 'json';
		}

		// We can't have a zero size, remove it.
		if ($type_size === 0) {
			$type_size = null;
		}

		return [$type_name, $type_size];
	}

	/**
	 *
	 */
	public function change_column(string $table_name, string $old_column, array $column_info): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$column_info = array_change_key_case($column_info);

		// Check it does exist!
		$columns = $this->list_columns($table_name, true);
		$old_info = null;

		foreach ($columns as $column) {
			if ($column['name'] == $old_column) {
				$old_info = $column;
			}
		}

		// Nothing?
		if ($old_info == null) {
			return false;
		}

		// backward compatibility
		if (isset($column_info['null']) && !isset($column_info['not_null'])) {
			$column_info['not_null'] = !$column_info['null'];
		}

		// Get the right bits.
		$column_info['drop_default'] = !empty($column_info['drop_default']);

		if (!isset($column_info['name'])) {
			$column_info['name'] = $old_column;
		}

		if (
			!array_key_exists('default', $column_info)
			&& array_key_exists('default', $old_info)
			&& !$column_info['drop_default']
		) {
			$column_info['default'] = $old_info['default'];
		}

		if (!isset($column_info['not_null'])) {
			$column_info['not_null'] = $old_info['not_null'];
		}

		if (!isset($column_info['auto'])) {
			$column_info['auto'] = $old_info['auto'];
		}

		if (!isset($column_info['type'])) {
			$column_info['type'] = $old_info['type'];
		}

		if (!isset($column_info['size']) || !is_numeric($column_info['size'])) {
			$column_info['size'] = $old_info['size'];
		}

		if (!isset($column_info['unsigned']) || !in_array($column_info['type'], ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
			$column_info['unsigned'] = '';
		}

		foreach (['generation_expression', 'stored'] as $key) {
			if (!array_key_exists($key, $column_info) && array_key_exists($key, $old_info)) {
				$column_info[$key] = $old_info[$key];
			}
		}

		// Default values and such are inapplicable to generated columns.
		if (isset($column_info['generation_expression'])) {
			$column_info['drop_default'] = true;
			unset($column_info['default'], $column_info['not_null'], $column_info['auto']);
		}

		// If truly unspecified, make that clear, otherwise, might be confused with NULL...
		// (Unspecified = no default whatsoever = column is not nullable with a value of null...)
		if (
			!empty($column_info['not_null'])
			&& empty($column_info['drop_default'])
			&& array_key_exists('default', $column_info)
			&& is_null($column_info['default'])
		) {
			unset($column_info['default']);
		}

		// These types cannot have a default value.
		if (in_array($column_info['type'], ['blob', 'text', 'json', 'geometry'])) {
			$column_info['drop_default'] = true;
			unset($column_info['default']);
		}

		list($type, $size) = $this->calculate_type($column_info['type'], (int) $column_info['size']);

		if ($size !== null) {
			$type .= '(' . $size . ')';
		}

		// Allow for unsigned integers (mysql only)
		$type .= in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']) && !empty($column_info['unsigned']) ? ' unsigned' : '';

		// If you need to drop the default, that needs its own thing...
		// Must be done first, in case the default type is inconsistent with the other changes.
		if ($column_info['drop_default']) {
			$this->query(
				'ALTER TABLE ' . $short_table_name . '
				ALTER COLUMN `' . $old_column . '` DROP DEFAULT',
				[
					'security_override' => true,
				],
			);
		}

		// Set the default clause.
		$default_clause = '';

		if (!$column_info['drop_default'] && array_key_exists('default', $column_info)) {
			if (is_null($column_info['default'])) {
				$default_clause = 'DEFAULT NULL';
			} elseif (is_numeric($column_info['default'])) {
				$default_clause = 'DEFAULT ' . (strpos((string) $column_info['default'], '.') ? floatval($column_info['default']) : intval($column_info['default']));
			} elseif (is_string($column_info['default'])) {
				$default_clause = 'DEFAULT \'' . $this->escape_string((string) $column_info['default']) . '\'';
			}
		}

		// Is this a generated column?
		$generated = !isset($column_info['generation_expression']) ? '' : ' GENERATED ALWAYS AS (' . $column_info['generation_expression'] . ') ' . (!empty($column_info['stored']) ? 'STORED' : 'VIRTUAL');

		$result = $this->query(
			'ALTER TABLE ' . $short_table_name . '
			CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . $generated . (!empty($column_info['not_null']) ? ' NOT NULL' : '') . ' ' .
				$default_clause . ' ' .
				(empty($column_info['auto']) ? '' : 'auto_increment') . ' ',
			[
				'security_override' => true,
			],
		);

		return $result !== false;
	}

	/**
	 *
	 */
	public function rename_index(string $table_name, string $old_name, string $new_name): bool
	{
		$result = false;

		$indexes = $this->list_indexes($table_name, false);

		if (in_array($old_name, $indexes) && !in_array($new_name, $indexes)) {
			$result = $this->query(
				'ALTER TABLE ' . str_replace('{db_prefix}', $this->prefix, $table_name) . '
				RENAME INDEX `' . $old_name . '` TO `' . $new_name . '`',
				[
					'security_override' => true,
				],
			);
		}

		return $result !== false;
	}

	/**
	 *
	 */
	public function create_table(string $table_name, array $columns, array $indexes = [], array $parameters = [], string $if_exists = 'ignore', string $error = 'fatal'): bool
	{
		$old_table_exists = false;

		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $this->prefix, $match) === 1 ? $match[3] : $this->prefix;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		// Do not overwrite $table_name, this causes issues if we pass it onto a helper function.
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// First - no way do we touch SMF tables.
		if (!defined('SMF_INSTALLING') && in_array(strtolower($short_table_name), $this->reservedTables)) {
			return false;
		}

		// Log that we'll want to remove this on uninstall.
		self::$package_log[] = ['remove_table', $short_table_name];

		// Slightly easier on MySQL than the others...
		$tables = $this->list_tables($database);

		if (in_array($full_table_name, $tables)) {
			// This is a sad day... drop the table? If not, return false (error) by default.
			if ($if_exists == 'overwrite') {
				$this->drop_table($table_name);
			} elseif ($if_exists == 'update') {
				$this->transaction('begin');
				$db_trans = true;
				$this->drop_table($short_table_name . '_old');
				$this->query(
					'RENAME TABLE ' . $short_table_name . ' TO ' . $short_table_name . '_old',
					[
						'security_override' => true,
					],
				);
				$old_table_exists = true;
			} else {
				return $if_exists == 'ignore';
			}
		}

		// Righty - let's do the damn thing!
		$table_query = 'CREATE TABLE ' . $short_table_name . "\n" . '(';

		foreach ($columns as $column) {
			$table_query .= "\n\t" . $this->create_query_column($column) . ',';
		}

		// Loop through the indexes next...
		foreach ($indexes as $index) {
			// MySQL If it's a text column, we need to add a size.
			foreach ($index['columns'] as &$c) {
				if (is_array($c)) {
					$c = $c['name'] . (isset($c['size']) ? '(' . $c['size'] . ')' : '');
				}

				$c = trim($c);

				// If a size was already specified, we won't be able to match it anyways.
				$key = array_search($c, array_column($columns, 'name'));
				$columns[$key]['size'] = isset($columns[$key]['size']) && is_numeric($columns[$key]['size']) ? $columns[$key]['size'] : null;
				list($type, $size) = $this->calculate_type($columns[$key]['type'], (int) $columns[$key]['size']);

				if (
					$key === false
					|| !isset($columns[$key])
					|| !in_array($columns[$key]['type'], ['text', 'mediumtext', 'longtext', 'varchar', 'char'])
					|| (
						isset($size)
						&& $size <= 191
					)
				) {
					continue;
				}

				$c .= '(191)';
			}

			$idx_columns = implode(',', $index['columns']);

			// Is it the primary?
			if (isset($index['type']) && $index['type'] == 'primary') {
				$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(',', $index['columns']) . '),';
			} else {
				if (empty($index['name'])) {
					$index['name'] = trim(implode('_', preg_replace('~(\(\d+\))~', '', $index['columns'])));
				}

				$table_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . $idx_columns . '),';
			}
		}

		// No trailing commas!
		if (str_ends_with($table_query, ',')) {
			$table_query = substr($table_query, 0, -1);
		}

		// Which engine do we want here?
		$this->get_engines();

		// If we don't have this engine, or didn't specify one, default to InnoDB or MyISAM
		// depending on which one is available
		if (!isset($parameters['engine']) || !in_array($parameters['engine'], $this->engines)) {
			$parameters['engine'] = in_array('InnoDB', $this->engines) ? 'InnoDB' : 'MyISAM';
		}

		$table_query .= ') ENGINE=' . $parameters['engine'];

		if (!empty($this->character_set) && str_starts_with($this->character_set, 'utf8')) {
			if ($this->mb4) {
				$table_query .= ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';
			} else {
				$table_query .= ' DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';
			}
		}

		// Which row format (if any) should be specified?
		switch ($parameters['engine']) {
			case 'InnoDB':
				if (!in_array(strtoupper($parameters['row_format'] ?? ''), ['REDUNDANT', 'COMPACT', 'DYNAMIC', 'COMPRESSED'])) {
					$parameters['row_format'] = 'DYNAMIC';
				}
				break;

			case 'MyISAM':
				if (!in_array(strtoupper($parameters['row_format'] ?? ''), ['FIXED', 'DYNAMIC', 'COMPRESSED'])) {
					unset($parameters['row_format']);
				}
				break;

			default:
				unset($parameters['row_format']);
				break;
		}

		if (isset($parameters['row_format'])) {
			$table_query .= ' ROW_FORMAT=' . $parameters['row_format'];
		}

		// Create the table!
		$this->query(
			$table_query,
			[
				'security_override' => true,
			],
		);

		// Fill the old data
		if ($old_table_exists) {
			$same_col = [];

			$request = $this->query(
				'SELECT count(*), column_name
				FROM information_schema.columns
				WHERE table_name in ({string:table1},{string:table2}) AND table_schema = {string:schema}
				GROUP BY column_name
				HAVING count(*) > 1',
				[
					'table1' => $short_table_name,
					'table2' => $short_table_name . '_old',
					'schema' => $this->name,
				],
			);

			while ($row = $this->fetch_assoc($request)) {
				$row = array_change_key_case($row, CASE_LOWER);
				$same_col[] = $row['column_name'];
			}

			$this->query(
				'INSERT INTO ' . $short_table_name . '('
				. implode(',', $same_col) .
				')
				SELECT ' . implode(',', $same_col) . '
				FROM ' . $short_table_name . '_old',
				[],
			);

			$this->drop_table($short_table_name . '_old');
		}

		return true;
	}

	/**
	 *
	 */
	public function drop_table(string $table_name, array $parameters = [], string $error = 'fatal'): bool
	{
		// After stripping away the database name, this is what's left.
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $this->prefix, $match) === 1 ? $match[3] : $this->prefix;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		// Do not overwrite $table_name, this causes issues if we pass it onto a helper function.
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($short_table_name), $this->reservedTables)) {
			return false;
		}

		// Does it exist?
		$tables = $this->list_tables($database);

		if (in_array($full_table_name, $tables)) {
			$query = 'DROP TABLE ' . $short_table_name;
			$this->query(
				$query,
				[
					'security_override' => true,
				],
			);

			return true;
		}

		// Otherwise do 'nout.
		return false;
	}

	/**
	 *
	 */
	public function table_structure(string $table_name): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// Find the table engine and add that to the info as well
		$table_status = $this->query(
			'SHOW TABLE STATUS
			IN {raw:db}
			LIKE {string:table}',
			[
				'db' => $database,
				'table' => $real_table_name,
			],
		);

		// Only one row, so no need for a loop...
		$row = $this->fetch_assoc($table_status);

		$this->free_result($table_status);

		return [
			'name' => $real_table_name,
			'columns' => is_null($row) ? [] : $this->list_columns($table_name, true),
			'indexes' => is_null($row) ? [] : $this->list_indexes($table_name, true),
			'engine' => is_null($row) ? '' : $row['Engine'],
			'row_format' => is_null($row) ? '' : $row['Row_format'],
			'collation' => is_null($row) ? '' : $row['Collation'],
			'comment' => is_null($row) ? '' : $row['Comment'],
		];
	}

	/**
	 *
	 */
	public function list_columns(string $table_name, bool $detail = false, array $parameters = []): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		$result = $this->query(
			'SELECT column_name "Field", COLUMN_TYPE "Type", is_nullable "Null", COLUMN_KEY "Key" , column_default "Default", extra "Extra", generation_expression "generation_expression"
			FROM information_schema.columns
			WHERE table_name = {string:table_name}
				AND table_schema = {string:db_name}
			ORDER BY ordinal_position',
			[
				'table_name' => $real_table_name,
				'db_name' => $this->name,
			],
		);
		$columns = [];

		while ($row = $this->fetch_assoc($result)) {
			if (!$detail) {
				$columns[] = $row['Field'];
			} else {
				// Is there an auto_increment?
				$auto = str_contains($row['Extra'], 'auto_increment') ? true : false;

				// Can we split out the size?
				if (preg_match('~^(.+?)\s*\((\d+)\)$~', $row['Type'], $matches)) {
					$type = $matches[1];
					$size = $matches[2];
				} elseif (preg_match('~^(.+?)\s+unsigned$~', $row['Type'], $matches)) {
					$type = $matches[1];
					$size = null;
					$unsigned = true;
				} else {
					$type = $row['Type'];
					$size = null;
				}

				$columns[$row['Field']] = [
					'name' => $row['Field'],
					'not_null' => $row['Null'] != 'YES',
					'null' => $row['Null'] == 'YES',
					'default' => $row['Default'] ?? null,
					'type' => $type,
					'size' => $size,
					'auto' => $auto,
				];

				if (isset($unsigned)) {
					$columns[$row['Field']]['unsigned'] = $unsigned;
					unset($unsigned);
				}

				if (str_contains($row['Extra'], 'GENERATED')) {
					$columns[$row['Field']]['generation_expression'] = $row['generation_expression'];
					$columns[$row['Field']]['stored'] = str_contains($row['Extra'], 'STORED');
				}
			}
		}
		$this->free_result($result);

		return $columns;
	}

	/**
	 *
	 */
	public function list_indexes(string $table_name, bool $detail = false, array $parameters = []): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		$result = $this->query(
			'SHOW KEYS
			FROM {raw:table_name}
			IN {raw:db}',
			[
				'db' => $database,
				'table_name' => $real_table_name,
			],
		);
		$indexes = [];

		while ($row = $this->fetch_assoc($result)) {
			if (!$detail) {
				$indexes[] = $row['Key_name'];
			} else {
				// What is the type?
				if ($row['Key_name'] == 'PRIMARY') {
					$type = 'primary';
				} elseif (empty($row['Non_unique'])) {
					$type = 'unique';
				} elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT') {
					$type = 'fulltext';
				} else {
					$type = 'index';
				}

				// This is the first column we've seen?
				if (empty($indexes[$row['Key_name']])) {
					$indexes[$row['Key_name']] = [
						'name' => $row['Key_name'],
						'type' => $type,
						'columns' => [],
					];
				}

				// Is it a partial index?
				if (!empty($row['Sub_part'])) {
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				} else {
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
				}
			}
		}
		$this->free_result($result);

		return $indexes;
	}

	/**
	 *
	 */
	public function remove_column(string $table_name, string $column_name, array $parameters = [], string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// Does it exist?
		$columns = $this->list_columns($table_name, true);

		foreach ($columns as $column) {
			if ($column['name'] == $column_name) {
				$this->query(
					'ALTER TABLE ' . $short_table_name . '
					DROP COLUMN ' . $column_name,
					[
						'security_override' => true,
					],
				);

				return true;
			}
		}

		// If here we didn't have to work - joy!
		return false;
	}

	/**
	 *
	 */
	public function remove_index(string $table_name, string $index_name, array $parameters = [], string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// Better exist!
		$indexes = $this->list_indexes($table_name, true);

		foreach ($indexes as $index) {
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary') {
				// Dropping primary key?
				$this->query(
					'ALTER TABLE ' . $short_table_name . '
					DROP PRIMARY KEY',
					[
						'security_override' => true,
					],
				);

				return true;
			}

			if ($index['name'] == $index_name) {
				// Drop the bugger...
				$this->query(
					'ALTER TABLE ' . $short_table_name . '
					DROP INDEX ' . $index_name,
					[
						'security_override' => true,
					],
				);

				return true;
			}
		}

		// Not to be found ;(
		return false;
	}

	/**************************************
	 * Methods used during installion, etc.
	 **************************************/

	/**
	 *
	 */
	public function getMinimumVersion(): string
	{
		return '8.0.35';
	}

	/**
	 *
	 */
	public function isSupported(): bool
	{
		return function_exists('mysqli_connect');
	}

	/**
	 *
	 */
	public function skipSelectDatabase(): bool
	{
		return false;
	}

	/**
	 *
	 */
	public function getDefaultUser(): string
	{
		return ini_get('mysql.default_user') === false ? '' : ini_get('mysql.default_user');
	}

	/**
	 *
	 */
	public function getDefaultPassword(): string
	{
		return ini_get('mysql.default_password') === false ? '' : ini_get('mysql.default_password');
	}

	/**
	 *
	 */
	public function getDefaultHost(): string
	{
		return ini_get('mysql.default_host') === false ? '' : ini_get('mysql.default_host');
	}

	/**
	 *
	 */
	public function getDefaultPort(): int
	{
		return ini_get('mysql.default_port') === false ? 3306 : (int) ini_get('mysql.default_port');
	}

	/**
	 *
	 */
	public function getDefaultName(): string
	{
		return 'smf';
	}

	/**
	 *
	 */
	public function checkConfiguration(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function hasPermissions(): bool
	{
		// Find database user privileges.
		$privs = [];
		$get_privs = self::$db->query('SHOW PRIVILEGES', []);

		while ($row = self::$db->fetch_assoc($get_privs)) {
			if ($row['Privilege'] == 'Alter') {
				$privs[] = $row['Privilege'];
			}
		}
		self::$db->free_result($get_privs);

		// Check for the ALTER privilege.
		return !(!in_array('Alter', $privs));
	}

	/**
	 *
	 */
	public function validatePrefix(&$value): bool
	{
		$value = preg_replace('~[^A-Za-z0-9_\$]~', '', $value);

		return true;
	}

	/**
	 *
	 */
	public function alwaysHasDb(): bool
	{
		return false;
	}

	/**
	 *
	 */
	public function setSqlMode(string $mode = 'default'): bool
	{
		$sql_mode = '';

		if ($mode === 'strict') {
			$sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT';
		}

		$this->query('SET SESSION sql_mode = {string:sql_mode}', [
			'sql_mode' => $sql_mode,
		]);

		return true;
	}

	/**
	 *
	 */
	public function processError(string $error_msg, string $query): mixed
	{
		$mysqli_errno = mysqli_errno($this->connection);

		$error_query = in_array(substr(trim($query), 0, 11), ['INSERT INTO', 'UPDATE IGNO', 'ALTER TABLE', 'DROP TABLE ', 'ALTER IGNOR', 'INSERT IGNO']);

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1050: Table already exists.
		//    1054: Unknown column name.
		//    1060: Duplicate column name.
		//    1061: Duplicate key name.
		//    1062: Duplicate entry for unique key.
		//    1068: Multiple primary keys.
		//    1072: Key column '%s' doesn't exist in table.
		//    1091: Can't drop key, doesn't exist.
		//    1146: Table doesn't exist.
		//    2013: Lost connection to server during query.

		if ($mysqli_errno == 1016) {
			if (preg_match('~\'([^\.\']+)~', $error_msg, $match) != 0 && !empty($match[1])) {
				mysqli_query($this->connection, 'REPAIR TABLE `' . $match[1] . '`');
				$result = mysqli_query($this->connection, $query);

				if ($result !== false) {
					return $result;
				}
			}
		} elseif ($mysqli_errno == 2013) {
			$this->connection = mysqli_connect($this->server, $this->user, $this->passwd);
			mysqli_select_db($this->connection, $this->name);

			if ($this->connection) {
				$result = mysqli_query($this->connection, $query);

				if ($result !== false) {
					return $result;
				}
			}
		}
		// Duplicate column name... should be okay ;).
		elseif (in_array($mysqli_errno, [1060, 1061, 1068, 1091])) {
			return false;
		}
		// Duplicate insert... make sure it's the proper type of query ;).
		elseif (in_array($mysqli_errno, [1054, 1062, 1146]) && $error_query) {
			return false;
		}
		// Creating an index on a non-existent column.
		elseif ($mysqli_errno == 1072) {
			return false;
		} elseif ($mysqli_errno == 1050 && substr(trim($query), 0, 12) == 'RENAME TABLE') {
			return false;
		}
		// Testing for legacy tables or columns? Needed for 1.0 & 1.1 scripts.
		elseif (in_array($mysqli_errno, [1054, 1146]) && in_array(substr(trim($query), 0, 7), ['SELECT ', 'SHOW CO'])) {
			return false;
		}

		// If a table already exists don't go potty.
		if (in_array(substr(trim($query), 0, 8), ['CREATE T', 'CREATE S', 'DROP TABL', 'ALTER TA', 'CREATE I', 'CREATE U'])) {
			if (strpos($error_msg, 'exist') !== false) {
				return false;
			}
		} elseif (strpos(trim($query), 'INSERT ') !== false) {
			if (strpos($error_msg, 'duplicate') !== false) {
				return false;
			}
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Prepares this instance for use.
	 *
	 * If $options is empty, correct settings will be determined automatically.
	 *
	 * @param array $options An array of database options.
	 */
	protected function initialize(array $options = []): void
	{
		if ($this !== DatabaseApi::$db) {
			return;
		}

		// If caller was explicit about non_fatal, respect that.
		$non_fatal = !empty($options['non_fatal']);

		// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
		if (SMF == 'SSI' && !empty(Config::$ssi_db_user) && !empty(Config::$ssi_db_passwd)) {
			if (empty($options)) {
				$options = ['non_fatal' => true, 'dont_select_db' => true];
			}

			$this->connect(Config::$ssi_db_user, Config::$ssi_db_passwd, $options);
		}

		// Either we aren't in SSI mode, or it failed.
		if (empty($this->connection)) {
			if (empty($options)) {
				$options = ['dont_select_db' => SMF == 'SSI'];
			}

			$this->connect(Config::$db_user, Config::$db_passwd, $options);
		}

		// Safe guard here, if there isn't a valid connection let's put a stop to it.
		if (empty($this->connection) && !$non_fatal) {
			ErrorHandler::displayDbError();
		}

		// If in SSI mode, fix up the prefix so it doesn't require the database to be selected.
		if (SMF == 'SSI') {
			$this->prefix = is_numeric(substr($this->prefix, 0, 1)) ? $this->name . '.' . $this->prefix : '`' . $this->name . '`.' . $this->prefix;

			// Redo the reserved table name prefixes.
			$this->prefixReservedTables();
		}

		// At this point, if we don't have a connection, nothing else can be done.
		if (empty($this->connection)) {
			return;
		}

		// For backward compatibility.
		if (!is_object(self::$db_connection)) {
			self::$db_connection = $this->connection;
		}

		$this->get_version();
		$this->supports_pcre = version_compare($this->version, str_contains($this->version, 'MariaDB') ? '10.0.5' : '8.0.4', '>=');

		$this->character_set = strtolower($this->detect_charset('messages', 'body'));
		$this->mb4 = $this->character_set === 'utf8mb4';

		// Ensure database has utf8mb4 as its default input/output charset.
		// Note: This just informs MySQL that input we send it will be in UTF-8
		// and that it should reply in UTF-8. This is an independent matter from
		// whatever charset MySQL uses to store the data.
		$this->query(
			'SET NAMES {string:db_character_set}',
			[
				'db_character_set' => 'utf8mb4',
			],
		);
	}

	/**
	 * Initiates a connection to a database.
	 *
	 * Resulting connection is stored as $this->connection.
	 *
	 * @param string $user The database username
	 * @param string $passwd The database password
	 * @param array $options An array of database options
	 */
	protected function connect(string $user, string $passwd, array $options = []): void
	{
		$server = ($this->persist ? 'p:' : '') . $this->server;

		// We are not going to make it very far without these.
		if (!function_exists('mysqli_init') || !function_exists('mysqli_real_connect')) {
			ErrorHandler::displayDbError();
		}

		// Ignore some errors and strict mode warnings when we are not debugging.
		mysqli_report(Config::$db_show_debug ? MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT : MYSQLI_REPORT_OFF);

		$success = false;

		if (($this->connection = mysqli_init())) {
			$success = @mysqli_real_connect(
				$this->connection,
				$server,
				$user,
				$passwd,
				null,
				$this->port,
				null,
				MYSQLI_CLIENT_FOUND_ROWS,
			);
		}

		// Something's wrong, show an error if it's fatal (which we assume it is)
		if ($success === false) {
			if (!empty($options['non_fatal'])) {
				$this->connection = null;

				return;
			}

			ErrorHandler::displayDbError();
		}

		// Select the database, unless told not to
		if (empty($options['dont_select_db']) && !@mysqli_select_db($this->connection, $this->name) && empty($options['non_fatal'])) {
			ErrorHandler::displayDbError();
		}

		$sql_mode = [
			'ONLY_FULL_GROUP_BY',
			'STRICT_TRANS_TABLES',
			'NO_ZERO_IN_DATE',
			'NO_ZERO_DATE',
			'ERROR_FOR_DIVISION_BY_ZERO',
			'NO_ENGINE_SUBSTITUTION',
			'PIPES_AS_CONCAT',
		];

		mysqli_query(
			$this->connection,
			'SET SESSION sql_mode = \'' . implode(',', $sql_mode) . '\'',
		);
	}

	/**
	 * Callback for preg_replace_callback on the query.
	 *
	 * It replaces on the fly a few pre-defined strings ('query_see_board',
	 * 'query_wanna_see_board', etc.) with their current values from User::$me.
	 *
	 * In addition, it performs checks and sanitization on the values sent to
	 * the database.
	 *
	 * @param array $matches The matches from preg_replace_callback
	 * @return string The appropriate string depending on $matches[1]
	 */
	protected function replacement__callback(array $matches): string
	{
		if (!is_object($this->temp_connection)) {
			ErrorHandler::displayDbError();
		}

		if ($matches[1] === 'db_prefix') {
			return $this->prefix;
		}

		if (isset(User::$me->{$matches[1]}) && str_contains($matches[1], 'query_')) {
			return User::$me->{$matches[1]};
		}

		if ($matches[1] === 'empty') {
			return '\'\'';
		}

		if (!isset($matches[2])) {
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		if ($matches[1] === 'literal') {
			return '\'' . mysqli_real_escape_string($this->temp_connection, $matches[2]) . '\'';
		}

		if (!isset($this->temp_values[$matches[2]])) {
			$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . Utils::htmlspecialchars($matches[2]), '', E_USER_ERROR, __FILE__, __LINE__);
		}

		$replacement = $this->temp_values[$matches[2]];

		switch ($matches[1]) {
			case 'int':
				if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement) {
					$this->error_backtrace('Wrong value type sent to the database. Integer expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				}

				return (string) (int) $replacement;

			case 'string':
			case 'text':
				return sprintf('\'%1$s\'', mysqli_real_escape_string($this->temp_connection, $this->fix_mb4((string) $replacement)));

			case 'array_int':
				if (is_array($replacement)) {
					if (empty($replacement)) {
						$this->error_backtrace('Database error, given array of integer values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
					}

					foreach ($replacement as $key => $value) {
						if (!is_numeric($value) || (string) $value !== (string) (int) $value) {
							$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						$replacement[$key] = (string) (int) $value;
					}

					return implode(', ', $replacement);
				}

				$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'array_string':
				if (is_array($replacement)) {
					if (empty($replacement)) {
						$this->error_backtrace('Database error, given array of string values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
					}

					foreach ($replacement as $key => $value) {
						$replacement[$key] = sprintf('\'%1$s\'', mysqli_real_escape_string($this->temp_connection, $this->fix_mb4((string) $value)));
					}

					return implode(', ', $replacement);
				}

				$this->error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'date':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1) {
					return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
				}

				$this->error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'time':
				if (preg_match('~^([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $time_matches) === 1) {
					return sprintf('\'%02d:%02d:%02d\'', $time_matches[1], $time_matches[2], $time_matches[3]);
				}

				$this->error_backtrace('Wrong value type sent to the database. Time expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'datetime':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d) ([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $datetime_matches) === 1) {
					return 'str_to_date(' .
						sprintf('\'%04d-%02d-%02d %02d:%02d:%02d\'', $datetime_matches[1], $datetime_matches[2], $datetime_matches[3], $datetime_matches[4], $datetime_matches[5], $datetime_matches[6]) .
						',\'%Y-%m-%d %h:%i:%s\')';
				}

				$this->error_backtrace('Wrong value type sent to the database. Datetime expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'float':
				if (!is_numeric($replacement)) {
					$this->error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				}

				return (string) (float) $replacement;

			case 'identifier':
				// Backticks inside identifiers are supported as of MySQL 4.1. We don't need them for SMF.
				return '`' . implode('`.`', array_filter(explode('.', strtr($replacement, ['`' => ''])), 'strlen')) . '`';

			case 'raw':
				return (string) $replacement;

			case 'uuid':
				if ($replacement instanceof Uuid) {
					return sprintf('UUID_TO_BIN(\'%1$s\')', strval($replacement));
				}

				$uuid = @Uuid::createFromString($replacement, false);

				if (in_array($replacement, [(string) $uuid, $uuid->getShortForm(), $uuid->getBinary()])) {
					return sprintf('UUID_TO_BIN(\'%1$s\')', (string) $uuid);
				}

				$this->error_backtrace('Wrong value type sent to the database. UUID expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'inet':
				if ($replacement == 'null' || $replacement == '') {
					return 'null';
				}

				$ip = new IP($replacement);

				if (!$ip->isValid()) {
					$this->error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				}

				// We don't use the native support of mysql > 5.6.2
				return sprintf('unhex(\'%1$s\')', $ip->toHex());

			case 'array_inet':
				if (is_array($replacement)) {
					if (empty($replacement)) {
						$this->error_backtrace('Database error, given array of IPv4 or IPv6 values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
					}

					foreach ($replacement as $key => $value) {
						if ($value == 'null' || $value == '') {
							$replacement[$key] = 'null';
						}

						$ip = new IP($value);

						if (!$ip->isValid()) {
							$this->error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						$replacement[$key] = sprintf('unhex(\'%1$s\')', $ip->toHex());
					}

					return implode(', ', $replacement);
				}

				$this->error_backtrace('Wrong value type sent to the database. Array of IPv4 or IPv6 expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			default:
				$this->error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
				break;
		}

		return '';
	}

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message The error message
	 * @param string $log_message The message to log
	 * @param string|int|bool $error_type What type of error this is
	 * @param null|string $file The file the error occurred in
	 * @param null|int $line What line of $file the code which generated the error is on
	 * @return array Returns an array with the file and line if $error_type is
	 *    'return'. Otherwise, just dies.
	 */
	protected function error_backtrace(string $error_message, string $log_message = '', string|int|bool $error_type = false, ?string $file = null, ?int $line = null): array
	{
		if (empty($log_message)) {
			$log_message = $error_message;
		}

		foreach (debug_backtrace() as $step) {
			// Found it?
			if (!str_contains($step['function'], 'query') && !in_array(substr($step['function'], 0, 7), ['smf_db_', 'preg_re', 'db_erro', 'call_us']) && !str_starts_with($step['function'], '__') && (empty($step['class']) || $step['class'] != $this::class)) {
				$log_message .= '<br>Function: ' . $step['function'];
				break;
			}

			if (isset($step['line'])) {
				$file = $step['file'];
				$line = $step['line'];
			}
		}

		// A special case - we want the file and line numbers for debugging.
		if ($error_type == 'return') {
			return [$file, $line];
		}

		// Is always a critical error.
		try {
			ErrorHandler::log($log_message, 'critical', $file, $line);
			ErrorHandler::fatal($error_message, false);
		} catch (\Throwable $e) {
			echo $error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : '');
		}

		die();
	}

	/**
	 * Creates a query for a column
	 *
	 * @param array $column An array of column info
	 * @return string The column definition
	 */
	protected function create_query_column(array $column): string
	{
		$column = array_change_key_case($column);

		// Is this a generated column?
		if (isset($column['generation_expression'])) {
			$generated = ' GENERATED ALWAYS AS (' . $column['generation_expression'] . ') ' . (!empty($column['stored']) ? 'STORED' : 'VIRTUAL');

			// These are never used for generated columns.
			unset($column['not_null'], $column['default'], $column['auto']);
		} else {
			$generated = '';
		}

		// Auto increment is easy here!
		if (!empty($column['auto'])) {
			$default = 'auto_increment';
		}
		// Make it null.
		elseif (array_key_exists('default', $column) && is_null($column['default'])) {
			$default = 'DEFAULT NULL';
		}
		// Numbers don't need quotes.
		elseif (isset($column['default']) && is_numeric($column['default'])) {
			$default = 'DEFAULT ' . (strpos((string) $column['default'], '.') ? floatval($column['default']) : intval($column['default']));
		}
		// Non empty string.
		elseif (isset($column['default'])) {
			$default = 'DEFAULT \'' . $this->escape_string((string) $column['default']) . '\'';
		} else {
			$default = '';
		}

		// Backwards compatible with the nullable column.
		if (isset($column['null']) && !isset($column['not_null'])) {
			$column['not_null'] = !$column['null'];
		}

		// Sort out the size... and stuff...
		$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
		list($type, $size) = $this->calculate_type($column['type'], (int) $column['size']);

		if ($size > 0) {
			$type .= '(' . $size . ')';
		}

		// Allow unsigned integers (mysql only)
		$type .= in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']) && !empty($column['unsigned']) ? ' unsigned' : '';

		// Now just put it together!
		return '`' . $column['name'] . '` ' . $type . ' ' . $generated . (!empty($column['not_null']) ? ' NOT NULL' : '') . ' ' . $default;
	}

	/**
	 * Converts entities for four-byte UTF-8 characters back to characters.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string A UTF-8 string.
	 */
	protected function restore_mb4(string $string): string
	{
		return str_contains($string, '&') ? mb_decode_numericentity($string, [0x010000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8') : $string;
	}
}
