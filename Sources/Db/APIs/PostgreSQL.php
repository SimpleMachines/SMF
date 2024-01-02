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
 * Interacts with PostgreSQL databases.
 */
class PostgreSQL extends DatabaseApi implements DatabaseApiInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $title = POSTGRE_TITLE;

	/**
	 * {@inheritDoc}
	 */
	public bool $sybase = true;

	/**
	 * {@inheritDoc}
	 */
	public bool $mb4 = true;

	/**
	 * {@inheritDoc}
	 */
	public bool $case_sensitive = true;

	/**
	 * {@inheritDoc}
	 */
	public bool $support_ignore = true;

	/**
	 * {@inheritDoc}
	 */
	public bool $supports_pcre = true;

	/********************
	 * Runtime properties
	 ********************/

	/**
	 * @var object
	 *
	 * Temporary reference to a \PgSQL\Connection object.
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
	 * A prepared PostgreSQL statement (a PgSql\Result object).
	 */
	protected $error_data_prep;

	/**
	 * @var string
	 *
	 * Fulltext search language.
	 */
	protected $language_ftx;

	/**
	 * @var object
	 *
	 * Result of most recent query (a PgSql\Result object).
	 */
	protected $last_result;

	/**
	 * @var mixed
	 *
	 * If not empty, overrides the returned value of $this->affected_rows().
	 * @todo This never seem to be used. Remove it?
	 */
	protected $replace_result;

	/**
	 * @var bool
	 *
	 * Whether we are currently in the middle of a transaction or not.
	 */
	protected $inTransaction;

	/**
	 * @var string
	 *
	 * Error message produced by a failed connection attempt.
	 */
	protected $connect_error;

	/**
	 * @var int
	 *
	 * Error code produced by a failed connection attempt.
	 */
	protected $connect_errno;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool
	{
		// Decide which connection to use.
		$connection = $connection ?? $this->connection;

		// Special queries that need processing.
		$replacements = [
			'profile_board_stats' => [
				'~COUNT\(\*\) \/ MAX\(b.num_posts\)~' => 'CAST(COUNT(*) AS DECIMAL) / CAST(b.num_posts AS DECIMAL)',
			],
		];

		// Special optimizer Hints
		$query_opt = [
			'load_board_info' => [
				'join_collapse_limit' => 1,
			],
			'calendar_get_events' => [
				'enable_seqscan' => 'off',
			],
		];

		if (isset($replacements[$identifier])) {
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
		}

		// Limits need to be a little different.
		$db_string = preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})\s*$~i', 'LIMIT $2 OFFSET $1', $db_string);

		if (trim($db_string) == '') {
			return false;
		}

		// Comments that are allowed in a query are preg_removed.
		$allowed_comments_from = [
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		];
		$allowed_comments_to = [
			' ',
			'',
			'',
			'',
		];

		// One more query....
		self::$count++;

		$this->replace_result = 0;

		if (!$this->disableQueryCheck && strpos($db_string, '\'') !== false && empty($db_values['security_override'])) {
			$this->error_backtrace('No direct access...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);
		}

		// Use "ORDER BY null" to prevent Mysql doing filesorts for Group By clauses without an Order By
		if (strpos($db_string, 'GROUP BY') !== false && strpos($db_string, 'ORDER BY') === false && preg_match('~^\s+SELECT~i', $db_string)) {
			// Add before LIMIT
			if ($pos = strpos($db_string, 'LIMIT ')) {
				$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
			} else {
				// Append it.
				$db_string .= "\n\t\t\tORDER BY null";
			}
		}

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false)) {
			$this->temp_values = $db_values;
			$this->temp_connection = $connection;

			// Inject the values passed to this function.
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', [$this, 'replacement__callback'], $db_string);

			unset($this->temp_values, $this->temp_connection);
		}

		// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
		if (!$this->disableQueryCheck) {
			$clean = '';
			$old_pos = 0;
			$pos = -1;
			// Remove the string escape for better runtime
			$db_string_1 = str_replace('\'\'', '', $db_string);

			while (true) {
				$pos = strpos($db_string_1, '\'', $pos + 1);

				if ($pos === false) {
					break;
				}
				$clean .= substr($db_string_1, $old_pos, $pos - $old_pos);

				while (true) {
					$pos1 = strpos($db_string_1, '\'', $pos + 1);
					$pos2 = strpos($db_string_1, '\\', $pos + 1);

					if ($pos1 === false) {
						break;
					}

					if ($pos2 === false || $pos2 > $pos1) {
						$pos = $pos1;
						break;
					}

					$pos = $pos2 + 1;
				}
				$clean .= ' %s ';

				$old_pos = $pos + 1;
			}
			$clean .= substr($db_string_1, $old_pos);
			$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

			// Comments?  We don't use comments in our queries, we leave 'em outside!
			if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false) {
				$fail = true;
			}
			// Trying to change passwords, slow us down, or something?
			elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0) {
				$fail = true;
			} elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0) {
				$fail = true;
			}

			if (!empty($fail) && function_exists('log_error')) {
				$this->error_backtrace('No direct access...', 'No direct access...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
			}
		}

		// Set optimize stuff
		if (isset($query_opt[$identifier])) {
			$query_hints = $query_opt[$identifier];
			$query_hints_set = '';

			if (isset($query_hints['join_collapse_limit'])) {
				$query_hints_set .= 'SET LOCAL join_collapse_limit = ' . $query_hints['join_collapse_limit'] . ';';
			}

			if (isset($query_hints['enable_seqscan'])) {
				$query_hints_set .= 'SET LOCAL enable_seqscan = ' . $query_hints['enable_seqscan'] . ';';
			}

			$db_string = $query_hints_set . $db_string;
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

		$this->last_result = @pg_query($connection, $db_string);

		// Debugging.
		if ($this->show_debug) {
			self::$cache[self::$count]['t'] = microtime(true) - $st;
		}

		return $this->last_result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function quote(string $db_string, array $db_values, ?object $connection = null): string
	{
		// Only bother if there's something to replace.
		if (strpos($db_string, '{') !== false) {
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
	 * {@inheritDoc}
	 */
	public function fetch_row(object $result): array|false|null
	{
		return pg_fetch_row($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_assoc(object $result): array|false|null
	{
		return pg_fetch_assoc($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_all(object $request): array
	{
		$return = @pg_fetch_all($request);

		return !empty($return) ? $return : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function free_result(object $result): bool
	{
		return pg_free_result($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert(string $method, string $table, array $columns, array $data, array $keys, int $returnmode = 0, ?object $connection = null): int|array|null
	{
		$connection = $connection ?? $this->connection;

		$replace = '';

		// With nothing to insert, simply return.
		if (empty($table) || empty($data)) {
			return null;
		}

		// Force method to lower case
		$method = strtolower($method);

		if (!is_array($data[array_rand($data)])) {
			$data = [$data];
		}

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// Sanity check for replace is key part of the columns array
		if ($method == 'replace') {
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
		}

		// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
		if ($method == 'replace' || $method == 'ignore') {
			$key_str = '';
			$col_str = '';

			$count = 0;
			$count_pk = 0;

			foreach ($columns as $columnName => $type) {
				// Check pk field.
				if (in_array($columnName, $keys)) {
					$key_str .= ($count_pk > 0 ? ',' : '');
					$key_str .= $columnName;
					$count_pk++;
				}
				// Normal field.
				elseif ($method == 'replace') {
					$col_str .= ($count > 0 ? ',' : '');
					$col_str .= $columnName . ' = EXCLUDED.' . $columnName;
					$count++;
				}
			}

			if ($method == 'replace') {
				$replace = ' ON CONFLICT (' . $key_str . ') DO UPDATE SET ' . $col_str;
			} else {
				$replace = ' ON CONFLICT (' . $key_str . ') DO NOTHING';
			}
		}

		$returning = '';
		$with_returning = false;

		// Let's build the returning string. (MySQL allows this only in normal mode)
		if (!empty($keys) && (count($keys) > 0) && $returnmode > 0) {
			// We only take the first key.
			$returning = ' RETURNING ' . $keys[0];
			$with_returning = true;
		}

		if (!empty($data)) {
			// Create the mold for a single row insert.
			$insertData = '(';

			foreach ($columns as $columnName => $type) {
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false) {
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

			// Do the insert.
			$request = $this->query(
				'',
				'INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
				VALUES
					' . implode(',
					', $insertRows) . $replace . $returning,
				[
					'security_override' => true,
					'db_error_skip' => $method == 'ignore' || $table === $this->prefix . 'log_errors',
				],
				$connection,
			);

			if ($with_returning && $request !== false) {
				if ($returnmode === 2) {
					$return_var = [];
				}

				while (($row = $this->fetch_row($request)) && $with_returning) {
					if (is_numeric($row[0])) { // try to emulate mysql limitation
						switch ($returnmode) {
							case 2:
								$return_var[] = (int) $row[0];
								break;

							default:
								$return_var = (int) $row[0];
								break;
						}
					} else {
						$with_returning = false;
						Lang::load('Errors');
						trigger_error(Lang::$txt['postgres_id_not_int'], E_USER_ERROR);
					}
				}
			}
		}

		if ($with_returning && !empty($return_var)) {
			return $return_var;
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_id(string $table, ?string $field = null, ?object $connection = null): int
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// Try get the last ID for the auto increment field.
		$request = $this->query(
			'',
			'SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
			[],
		);

		if (!$request) {
			return 0;
		}
		list($lastID) = $this->fetch_row($request);
		$this->free_result($request);

		return (int) $lastID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_rows(object $result): int
	{
		return pg_num_rows($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function data_seek(object $result, int $offset): bool
	{
		return pg_result_seek($result, $offset);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_fields(object $result): int
	{
		return pg_num_fields($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape_string(string $string, ?object $connection = null): string
	{
		return pg_escape_string($connection ?? $this->connection, $string);
	}

	/**
	 * {@inheritDoc}
	 */
	public function unescape_string(string $string): string
	{
		return stripslashes($string);
	}

	/**
	 * {@inheritDoc}
	 */
	public function server_info(?object $connection = null): string
	{
		$version = pg_version();

		return $version['client'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function affected_rows(?object $connection = null): int
	{
		if ($this->replace_result) {
			return $this->replace_result;
		}

		if ($connection === null && !$this->last_result) {
			return 0;
		}

		return pg_affected_rows($connection === null ? $this->last_result : $connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function transaction(string $type = 'commit', ?object $connection = null): bool
	{
		$type = strtoupper($type);

		if (in_array($type, ['BEGIN', 'ROLLBACK', 'COMMIT'])) {
			$this->inTransaction = $type === 'BEGIN';

			$return = @pg_query($connection ?? $this->connection, $type);

			if (is_bool($return)) {
				return $return;
			}

				return is_a($return, 'PgSql\Result');

		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(object $connection): string
	{
		if ($connection === null && $this->connection === null) {
			return '';
		}

		if (!(($connection ?? $this->connection) instanceof \PgSql\Connection)) {
			return '';
		}

		return pg_last_error($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function select(string $database, ?object $connection = null): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function is_resource(mixed $result): bool
	{
		return is_resource($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function ping(?object $connection = null): bool
	{
		return pg_ping($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
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

		if (filter_var($error_array['ip'], FILTER_VALIDATE_IP) === false) {
			$error_array['ip'] = null;
		}

		// If we are in a transaction, abort.
		if (!empty($inTransaction)) {
			$this->transaction('rollback');
		}

		// Without pooling.
		if (empty($this->persist)) {
			if (empty($this->error_data_prep)) {
				$this->error_data_prep = pg_prepare(
					$this->connection,
					'smf_log_errors',
					'INSERT INTO ' . $this->prefix . 'log_errors
						(id_member, log_time, ip, url, message, session, error_type, file, line, backtrace)
					VALUES( $1, $2, $3, $4, $5, $6, $7, $8,	$9, $10)',
				);
			}

			pg_execute($this->connection, 'smf_log_errors', $error_array);
		}
		// With pooling.
		else {
			$this->error_data_prep = pg_prepare(
				$this->connection,
				'',
				'INSERT INTO ' . $this->prefix . 'log_errors
					(id_member, log_time, ip, url, message, session, error_type, file, line, backtrace)
				VALUES( $1, $2, $3, $4, $5, $6, $7, $8,	$9, $10)',
			);

			pg_execute($this->connection, '', $error_array);
		}
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function native_replace(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cte_support(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect_error(): string
	{
		if (empty($this->connect_error)) {
			$this->connect_error = '';
		}

		return $this->connect_error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect_errno(): int
	{
		return (int) $this->connect_errno;
	}

	/****************************************
	 * Methods that formerly lived in DbExtra
	 ****************************************/

	/**
	 * {@inheritDoc}
	 */
	public function backup_table(string $table, string $backup_table): object
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// Do we need to drop it first?
		$tables = $this->list_tables(false, $backup_table);

		if (!empty($tables)) {
			$this->query(
				'',
				'DROP TABLE {raw:backup_table}',
				[
					'backup_table' => $backup_table,
				],
			);
		}

		/**
		 * @todo Should we create backups of sequences as well?
		 */
		$result = $this->query(
			'',
			'CREATE TABLE {raw:backup_table}
			(
				LIKE {raw:table}
				INCLUDING DEFAULTS
			)',
			[
				'backup_table' => $backup_table,
				'table' => $table,
			],
		);

		$this->query(
			'',
			'INSERT INTO {raw:backup_table}
			SELECT * FROM {raw:table}',
			[
				'backup_table' => $backup_table,
				'table' => $table,
			],
		);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function optimize_table(string $table): int|float
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		$pg_tables = ['pg_catalog', 'information_schema'];

		$request = $this->query(
			'',
			'SELECT pg_relation_size(C.oid) AS "size"
			FROM pg_class C
				LEFT JOIN pg_namespace N ON (N.oid = C.relnamespace)
			WHERE nspname NOT IN ({array_string:pg_tables})
				AND relname = {string:table}',
			[
				'table' => $table,
				'pg_tables' => $pg_tables,
			],
		);

		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		$old_size = $row['size'];

		$request = $this->query(
			'',
			'VACUUM FULL ANALYZE {raw:table}',
			[
				'table' => $table,
			],
		);

		if (!$request) {
			return -1;
		}

		$request = $this->query(
			'',
			'SELECT pg_relation_size(C.oid) AS "size"
			FROM pg_class C
				LEFT JOIN pg_namespace N ON (N.oid = C.relnamespace)
			WHERE nspname NOT IN ({array_string:pg_tables})
				AND relname = {string:table}',
			[
				'table' => $table,
				'pg_tables' => $pg_tables,
			],
		);

		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		if (isset($row['size'])) {
			return ($old_size - $row['size']) / 1024;
		}

		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function table_sql(string $tableName): string
	{
		$tableName = str_replace('{db_prefix}', $this->prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Drop it if it exists.
		$schema_create = 'DROP TABLE IF EXISTS ' . $tableName . ';' . $crlf . $crlf;

		// Start the create table...
		$schema_create .= 'CREATE TABLE ' . $tableName . ' (' . $crlf;
		$index_create = '';
		$seq_create = '';

		// Find all the fields.
		$result = $this->query(
			'',
			'SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
			FROM information_schema.columns
			WHERE table_name = {string:table}
			ORDER BY ordinal_position',
			[
				'table' => $tableName,
			],
		);

		while ($row = $this->fetch_assoc($result)) {
			if ($row['data_type'] == 'character varying') {
				$row['data_type'] = 'varchar';
			} elseif ($row['data_type'] == 'character') {
				$row['data_type'] = 'char';
			}

			if ($row['character_maximum_length']) {
				$row['data_type'] .= '(' . $row['character_maximum_length'] . ')';
			}

			// Make the CREATE for this column.
			$schema_create .= ' "' . $row['column_name'] . '" ' . $row['data_type'] . ($row['is_nullable'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (trim($row['column_default']) != '') {
				$schema_create .= ' default ' . $row['column_default'] . '';

				// Auto increment?
				if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0) {
					// Get to find the next variable first!
					$count_req = $this->query(
						'',
						'SELECT MAX("{raw:column}")
						FROM {raw:table}',
						[
							'column' => $row['column_name'],
							'table' => $tableName,
						],
					);
					list($max_ind) = $this->fetch_row($count_req);
					$this->free_result($count_req);
					// Get the right bloody start!
					$seq_create .= 'CREATE SEQUENCE ' . $matches[1] . ' START WITH ' . ($max_ind + 1) . ';' . $crlf . $crlf;
				}
			}

			$schema_create .= ',' . $crlf;
		}
		$this->free_result($result);

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		$result = $this->query(
			'',
			'SELECT pg_get_indexdef(i.indexrelid) AS inddef
			FROM pg_class AS c
				INNER JOIN pg_index AS i ON (i.indrelid = c.oid)
				INNER JOIN pg_class AS c2 ON (c2.oid = i.indexrelid)
			WHERE c.relname = {string:table} AND i.indisprimary is {raw:pk}',
			[
				'table' => $tableName,
				'pk'	=> 'false',
			],
		);

		while ($row = $this->fetch_assoc($result)) {
			$index_create .= $crlf . $row['inddef'] . ';';
		}
		$this->free_result($result);

		$result = $this->query(
			'',
			'SELECT pg_get_constraintdef(c.oid) as pkdef
			FROM pg_constraint as c
			WHERE c.conrelid::regclass::text = {string:table} AND
				c.contype = {string:constraintType}',
			[
				'table' 			=> $tableName,
				'constraintType'	=> 'p',
			],
		);

		while ($row = $this->fetch_assoc($result)) {
			$index_create .= $crlf . 'ALTER TABLE ' . $tableName . ' ADD ' . $row['pkdef'] . ';';
		}
		$this->free_result($result);

		// Finish it off!
		$schema_create .= $crlf . ');';

		return $seq_create . $schema_create . $index_create;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_tables(string|bool $db = false, string|bool $filter = false): array
	{
		$tables = [];

		$request = $this->query(
			'',
			'SELECT tablename
			FROM pg_tables
			WHERE schemaname = {string:schema_public}' . ($filter == false ? '' : '
				AND tablename LIKE {string:filter}') . '
			ORDER BY tablename',
			[
				'schema_public' => 'public',
				'filter' => $filter,
			],
		);

		while ($row = $this->fetch_row($request)) {
			$tables[] = $row[0];
		}
		$this->free_result($request);

		return $tables;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_version(): string
	{
		$version = pg_version();

		return $version['server'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_vendor(): string
	{
		return $this->title;
	}

	/**
	 * {@inheritDoc}
	 */
	public function allow_persistent(): bool
	{
		$value = ini_get('pgsql.allow_persistent');

		return (bool) (strtolower($value) == 'on' || strtolower($value) == 'true' || $value == '1');
	}

	/*****************************************
	 * Methods that formerly lived in DbSearch
	 *****************************************/

	/**
	 * {@inheritDoc}
	 */
	public function search_query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool
	{
		$replacements = [
			'create_tmp_log_search_topics' => [
				'~ENGINE=MEMORY~i' => '',
			],
			'create_tmp_log_search_messages' => [
				'~ENGINE=MEMORY~i' => '',
			],
			'insert_into_log_messages_fulltext' => [
				'/NOT\sLIKE/' => 'NOT ILIKE',
				'/\bLIKE\b/' => 'ILIKE',
				'/NOT RLIKE/' => '!~*',
				'/RLIKE/' => '~*',
			],
			'insert_log_search_results_subject' => [
				'/NOT\sLIKE/' => 'NOT ILIKE',
				'/\bLIKE\b/' => 'ILIKE',
				'/NOT RLIKE/' => '!~*',
				'/RLIKE/' => '~*',
			],
			'insert_log_search_topics' => [
				'/NOT\sLIKE/' => 'NOT ILIKE',
				'/\bLIKE\b/' => 'ILIKE',
				'/NOT RLIKE/' => '!~*',
				'/RLIKE/' => '~*',
			],
			'insert_log_search_results_no_index' => [
				'/NOT\sLIKE/' => 'NOT ILIKE',
				'/\bLIKE\b/' => 'ILIKE',
				'/NOT RLIKE/' => '!~*',
				'/RLIKE/' => '~*',
			],
		];

		if (isset($replacements[$identifier])) {
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
		}

		if (preg_match('~^\s*INSERT\s+IGNORE\b~i', $db_string) != 0) {
			$db_string = preg_replace('~^\s*INSERT\s+IGNORE\b~i', 'INSERT', $db_string);

			if ($this->support_ignore) {
				// pg style "INSERT INTO.... ON CONFLICT DO NOTHING"
				$db_string = $db_string . ' ON CONFLICT DO NOTHING';
			} else {
				// Don't error on multi-insert.
				$db_values['db_error_skip'] = true;
			}
		}

		// Fix double quotes.
		if ($identifier == 'insert_into_log_messages_fulltext') {
			$db_string = str_replace('"', "'", $db_string);
		}

		$return = $this->query(
			'',
			$db_string,
			$db_values,
			$this->connection,
		);

		return $return;
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_support(string $search_type): bool
	{
		$supported_types = ['custom', 'fulltext'];

		return in_array($search_type, $supported_types);
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_word_search($size): void
	{
		$size = 'int';

		$this->query(
			'',
			'CREATE TABLE {db_prefix}log_search_words (
				id_word {raw:size} NOT NULL default {string:string_zero},
				id_msg int NOT NULL default {string:string_zero},
				PRIMARY KEY (id_word, id_msg)
			)',
			[
				'size' => $size,
				'string_zero' => '0',
			],
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_language(): ?string
	{
		if (!empty(Config::$modSettings['search_language'])) {
			$this->language_ftx = Config::$modSettings['search_language'];
		}

		if (empty($this->language_ftx)) {
			$request = $this->query(
				'',
				'SELECT cfgname FROM pg_ts_config WHERE oid = current_setting({string:default_language})::regconfig',
				[
					'default_language' => 'default_text_search_config',
				],
			);

			if ($request !== false && $this->num_rows($request) == 1) {
				$row = $this->fetch_assoc($request);
				$this->language_ftx = $row['cfgname'];

				$this->insert(
					'replace',
					'{db_prefix}settings',
					['variable' => 'string', 'value' => 'string'],
					['search_language', $this->language_ftx],
					['variable'],
				);
			}
		}

		if (empty($this->language_ftx)) {
			$this->language_ftx = 'english';
		}

		return $this->language_ftx;
	}

	/*******************************************
	 * Methods that formerly lived in DbPackages
	 *******************************************/

	/**
	 * {@inheritDoc}
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

		list($type, $size) = $this->calculate_type($column_info['type'], $column_info['size']);

		if ($size !== null) {
			$type = $type . '(' . $size . ')';
		}

		// Now add the thing!
		$this->query(
			'',
			'ALTER TABLE ' . $short_table_name . '
			ADD COLUMN ' . $column_info['name'] . ' ' . $type,
			[
				'security_override' => true,
			],
		);

		// If there's more attributes they need to be done via a change on PostgreSQL.
		unset($column_info['type'], $column_info['size']);

		if (count($column_info) != 1) {
			return $this->change_column($table_name, $column_info['name'], $column_info);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function add_index(string $table_name, array $index_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;

		// No columns = no index.
		if (empty($index_info['columns'])) {
			return false;
		}

		// MySQL you can do a "column_name (length)", postgresql does not allow this.  Strip it.
		$cols = $this->list_columns($table_name, true);

		foreach ($index_info['columns'] as &$c) {
			$c = preg_replace('~\s+(\(\d+\))~', '', $c);
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

		// Log that we are going to want to remove this!
		self::$package_log[] = ['remove_index', $parsed_table_name, $index_info['name']];

		// Let's get all our indexes.
		$indexes = $this->list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index) {
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary')) {
				// If we want to overwrite simply remove the current one then continue.
				if ($if_exists != 'update' || $index['type'] == 'primary') {
					return false;
				}

				$this->remove_index($table_name, $index_info['name']);
			}
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary') {
			$result = $this->query(
				'',
				'ALTER TABLE ' . $real_table_name . '
				ADD PRIMARY KEY (' . $columns . ')',
				[
					'security_override' => true,
				],
			);
		} else {
			$result = $this->query(
				'',
				'CREATE ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $real_table_name . '_' . $index_info['name'] . ' ON ' . $real_table_name . ' (' . $columns . ')',
				[
					'security_override' => true,
				],
			);
		}

		// Query returns a result or true if succesfull, false otherwise.
		return $result !== false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function calculate_type(string $type_name, ?int $type_size = null, bool $reverse = false): array
	{
		// Let's be sure it's lowercase MySQL likes both, others no.
		$type_name = strtolower($type_name);

		// Generic => Specific.
		if (!$reverse) {
			$types = [
				'varchar' => 'character varying',
				'char' => 'character',
				'mediumint' => 'int',
				'tinyint' => 'smallint',
				'tinytext' => 'character varying',
				'mediumtext' => 'text',
				'largetext' => 'text',
				'inet' => 'inet',
				'time' => 'time without time zone',
				'datetime' => 'timestamp without time zone',
				'timestamp' => 'timestamp without time zone',
			];
		} else {
			$types = [
				'character varying' => 'varchar',
				'character' => 'char',
				'integer' => 'int',
				'inet' => 'inet',
				'time without time zone' => 'time',
				'timestamp without time zone' => 'datetime',
				'numeric' => 'decimal',
			];
		}

		// Got it? Change it!
		if (isset($types[$type_name])) {
			if ($type_name == 'tinytext') {
				$type_size = 255;
			}
			$type_name = $types[$type_name];
		}

		// Only char fields got size
		if (strpos($type_name, 'char') === false) {
			$type_size = null;
		}

		return [$type_name, $type_size];
	}

	/**
	 * {@inheritDoc}
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
		if (isset($column_info['drop_default']) && !empty($column_info['drop_default'])) {
			$column_info['drop_default'] = true;
		} else {
			$column_info['drop_default'] = false;
		}

		if (!isset($column_info['name'])) {
			$column_info['name'] = $old_column;
		}

		if (!array_key_exists('default', $column_info) && array_key_exists('default', $old_info) && empty($column_info['drop_default'])) {
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

		// If truly unspecified, make that clear, otherwise, might be confused with NULL...
		// (Unspecified = no default whatsoever = column is not nullable with a value of null...)
		if (($column_info['not_null'] === true) && !$column_info['drop_default'] && array_key_exists('default', $column_info) && is_null($column_info['default'])) {
			unset($column_info['default']);
		}

		// If you need to drop the default, that needs it's own thing...
		// Must be done first, in case the default type is inconsistent with the other changes.
		if ($column_info['drop_default']) {
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				ALTER COLUMN ' . $old_column . ' DROP DEFAULT',
				[
					'security_override' => true,
				],
			);
		}

		// Now we check each bit individually and ALTER as required.
		if (isset($column_info['name']) && $column_info['name'] != $old_column) {
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				RENAME COLUMN ' . $old_column . ' TO ' . $column_info['name'],
				[
					'security_override' => true,
				],
			);
		}

		// What about a change in type?
		if (isset($column_info['type']) && ($column_info['type'] != $old_info['type'] || (isset($column_info['size']) && $column_info['size'] != $old_info['size']))) {
			$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;
			list($type, $size) = $this->calculate_type($column_info['type'], $column_info['size']);

			if ($size !== null) {
				$type = $type . '(' . $size . ')';
			}

			// The alter is a pain.
			$this->transaction('begin');
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				ADD COLUMN ' . $column_info['name'] . '_tempxx ' . $type,
				[
					'security_override' => true,
				],
			);
			$this->query(
				'',
				'UPDATE ' . $short_table_name . '
				SET ' . $column_info['name'] . '_tempxx = CAST(' . $column_info['name'] . ' AS ' . $type . ')',
				[
					'security_override' => true,
				],
			);
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				DROP COLUMN ' . $column_info['name'],
				[
					'security_override' => true,
				],
			);
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				RENAME COLUMN ' . $column_info['name'] . '_tempxx TO ' . $column_info['name'],
				[
					'security_override' => true,
				],
			);
			$this->transaction('commit');
		}

		// Different default?
		// Just go ahead & honor the setting.  Type changes above introduce defaults that we might need to override here...
		if (!$column_info['drop_default'] && array_key_exists('default', $column_info)) {
			// Fix the default.
			$default = '';

			if (is_null($column_info['default'])) {
				$default = 'NULL';
			} elseif (isset($column_info['default']) && is_numeric($column_info['default'])) {
				$default = strpos($column_info['default'], '.') ? floatval($column_info['default']) : intval($column_info['default']);
			} else {
				$default = '\'' . $this->escape_string($column_info['default']) . '\'';
			}

			$action = 'SET DEFAULT ' . $default;
			$this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				ALTER COLUMN ' . $column_info['name'] . ' ' . $action,
				[
					'security_override' => true,
				],
			);
		}

		// Is it null - or otherwise?
		// Just go ahead & honor the setting.  Type changes above introduce defaults that we might need to override here...
		if ($column_info['not_null']) {
			$action = 'SET NOT NULL';
		} else {
			$action = 'DROP NOT NULL';
		}

		$this->query(
			'',
			'ALTER TABLE ' . $short_table_name . '
			ALTER COLUMN ' . $column_info['name'] . ' ' . $action,
			[
				'security_override' => true,
			],
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_table(string $table_name, array $columns, array $indexes = [], array $parameters = [], string $if_exists = 'ignore', string $error = 'fatal'): bool
	{
		$db_trans = false;
		$old_table_exists = false;

		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $this->prefix, $match) === 1 ? $match[3] : $this->prefix;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// First - no way do we touch SMF tables.
		if (in_array(strtolower($short_table_name), $this->reservedTables)) {
			return false;
		}

		// Log that we'll want to remove this on uninstall.
		self::$package_log[] = ['remove_table', $short_table_name];

		// This... my friends... is a function in a half - let's start by checking if the table exists!
		$tables = $this->list_tables($database);

		if (in_array($full_table_name, $tables)) {
			// This is a sad day... drop the table? If not, return false (error) by default.
			if ($if_exists == 'overwrite') {
				$this->drop_table($table_name);
			} elseif ($if_exists == 'update') {
				$this->drop_table($table_name . '_old');
				$this->transaction('begin');
				$db_trans = true;
				$this->query(
					'',
					'ALTER TABLE ' . $short_table_name . ' RENAME TO ' . $short_table_name . '_old',
					[
						'security_override' => true,
					],
				);
				$old_table_exists = true;
			} else {
				return $if_exists == 'ignore';
			}
		}

		// If we've got this far - good news - no table exists. We can build our own!
		if (!$db_trans) {
			$this->transaction('begin');
		}
		$table_query = 'CREATE TABLE ' . $short_table_name . "\n" . '(';

		foreach ($columns as $column) {
			$column = array_change_key_case($column);

			// If we have an auto increment do it!
			if (!empty($column['auto'])) {
				if (!$old_table_exists) {
					$this->query(
						'',
						'DROP SEQUENCE IF EXISTS ' . $short_table_name . '_seq',
						[
							'security_override' => true,
						],
					);
				}

				if (!$old_table_exists) {
					$this->query(
						'',
						'CREATE SEQUENCE ' . $short_table_name . '_seq',
						[
							'security_override' => true,
						],
					);
				}
				$default = 'default nextval(\'' . $short_table_name . '_seq\')';
			} elseif (isset($column['default']) && $column['default'] !== null) {
				$default = 'default \'' . $this->escape_string($column['default']) . '\'';
			} else {
				$default = '';
			}

			// Sort out the size...
			$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
			list($type, $size) = $this->calculate_type($column['type'], $column['size']);

			if ($size !== null) {
				$type = $type . '(' . $size . ')';
			}

			// backward compatibility
			if (isset($column['null']) && !isset($column['not_null'])) {
				$column['not_null'] = !$column['null'];
			}

			// Now just put it together!
			$table_query .= "\n\t\"" . $column['name'] . '" ' . $type . ' ' . (!empty($column['not_null']) ? 'NOT NULL' : '') . ' ' . $default . ',';
		}

		// Loop through the indexes next...
		$index_queries = [];

		foreach ($indexes as $index) {
			// MySQL you can do a "column_name (length)", postgresql does not allow this.  Strip it.
			foreach ($index['columns'] as &$c) {
				$c = preg_replace('~\s+(\(\d+\))~', '', $c);
			}

			$idx_columns = implode(',', $index['columns']);

			// Is it the primary?
			if (isset($index['type']) && $index['type'] == 'primary') {
				$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(',', $index['columns']) . '),';
			} else {
				if (empty($index['name'])) {
					$index['name'] = trim(implode('_', preg_replace('~(\(\d+\))~', '', $index['columns'])));
				}

				$index_queries[] = 'CREATE ' . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $short_table_name . '_' . $index['name'] . ' ON ' . $short_table_name . ' (' . $idx_columns . ')';
			}
		}

		// No trailing commas!
		if (substr($table_query, -1) == ',') {
			$table_query = substr($table_query, 0, -1);
		}

		$table_query .= ')';

		// Create the table!
		$this->query(
			'',
			$table_query,
			[
				'security_override' => true,
			],
		);

		// Fill the old data
		if ($old_table_exists) {
			$same_col = [];

			$request = $this->query(
				'',
				'SELECT count(*), column_name
				FROM information_schema.columns
				WHERE table_name in ({string:table1},{string:table2}) AND table_schema = {string:schema}
				GROUP BY column_name
				HAVING count(*) > 1',
				[
					'table1' => $short_table_name,
					'table2' => $short_table_name . '_old',
					'schema' => 'public',
				],
			);

			while ($row = $this->fetch_assoc($request)) {
				$same_col[] = $row['column_name'];
			}

			$this->query(
				'',
				'INSERT INTO ' . $short_table_name . '('
				. implode(',', $same_col) .
				')
				SELECT ' . implode(',', $same_col) . '
				FROM ' . $short_table_name . '_old',
				[],
			);
		}

		// And the indexes...
		foreach ($index_queries as $query) {
			$this->query(
				'',
				$query,
				[
					'security_override' => true,
				],
			);
		}

		// Go, go power rangers!
		$this->transaction('commit');

		if ($old_table_exists) {
			$this->drop_table($table_name . '_old');
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function drop_table(string $table_name, array $parameters = [], string $error = 'fatal'): bool
	{
		// After stripping away the database name, this is what's left.
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $this->prefix, $match) === 1 ? $match[3] : $this->prefix;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($table_name), $this->reservedTables)) {
			return false;
		}

		// Does it exist?
		$tables = $this->list_tables($database);

		if (in_array($full_table_name, $tables)) {
			// We can then drop the table.
			$this->transaction('begin');

			// the table
			$table_query = 'DROP TABLE ' . $short_table_name;

			// and the assosciated sequence, if any
			$sequence_query = 'DROP SEQUENCE IF EXISTS ' . $short_table_name . '_seq';

			// drop them
			$this->query(
				'',
				$table_query,
				[
					'security_override' => true,
				],
			);
			$this->query(
				'',
				$sequence_query,
				[
					'security_override' => true,
				],
			);

			$this->transaction('commit');

			return true;
		}

		// Otherwise do 'nout.
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function table_structure(string $table_name): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;

		return [
			'name' => $real_table_name,
			'columns' => $this->list_columns($table_name, true),
			'indexes' => $this->list_indexes($table_name, true),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_columns(string $table_name, bool $detail = false, array $parameters = []): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		$result = $this->query(
			'',
			'SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
			FROM information_schema.columns
			WHERE table_schema = {string:schema_public}
				AND table_name = {string:table_name}
			ORDER BY ordinal_position',
			[
				'schema_public' => 'public',
				'table_name' => $real_table_name,
			],
		);
		$columns = [];

		while ($row = $this->fetch_assoc($result)) {
			if (!$detail) {
				$columns[] = $row['column_name'];
			} else {
				$auto = false;
				$default = null;

				// What is the default?
				if ($row['column_default'] !== null) {
					if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0) {
						$auto = true;
					} elseif (substr($row['column_default'], 0, 4) != 'NULL' && trim($row['column_default']) != '') {
						$pos = strpos($row['column_default'], '::');
						$default = trim($pos === false ? $row['column_default'] : substr($row['column_default'], 0, $pos), '\'');
					}
				}

				// Make the type generic.
				list($type, $size) = $this->calculate_type($row['data_type'], $row['character_maximum_length'], true);

				$columns[$row['column_name']] = [
					'name' => $row['column_name'],
					'not_null' => $row['is_nullable'] != 'YES',
					'null' => $row['is_nullable'] == 'YES',
					'default' => $default,
					'type' => $type,
					'size' => $size,
					'auto' => $auto,
				];
			}
		}
		$this->free_result($result);

		return $columns;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_indexes(string $table_name, bool $detail = false, array $parameters = []): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		$result = $this->query(
			'',
			'SELECT CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS is_primary,
				CASE WHEN i.indisunique THEN 1 ELSE 0 END AS is_unique,
				c2.relname AS name,
				pg_get_indexdef(i.indexrelid) AS inddef
			FROM pg_class AS c, pg_class AS c2, pg_index AS i
			WHERE c.relname = {string:table_name}
				AND c.oid = i.indrelid
				AND i.indexrelid = c2.oid',
			[
				'table_name' => $real_table_name,
			],
		);
		$indexes = [];

		while ($row = $this->fetch_assoc($result)) {
			// Try get the columns that make it up.
			if (preg_match('~\(([^\)]+?)\)~i', $row['inddef'], $matches) == 0) {
				continue;
			}

			$columns = explode(',', $matches[1]);

			if (empty($columns)) {
				continue;
			}

			foreach ($columns as $k => $v) {
				$columns[$k] = trim($v);
			}

			// Fix up the name to be consistent cross databases
			if (substr($row['name'], -5) == '_pkey' && $row['is_primary'] == 1) {
				$row['name'] = 'PRIMARY';
			} else {
				$row['name'] = str_replace($real_table_name . '_', '', $row['name']);
			}

			if (!$detail) {
				$indexes[] = $row['name'];
			} else {
				$indexes[$row['name']] = [
					'name' => $row['name'],
					'type' => $row['is_primary'] ? 'primary' : ($row['is_unique'] ? 'unique' : 'index'),
					'columns' => $columns,
				];
			}
		}
		$this->free_result($result);

		return $indexes;
	}

	/**
	 * {@inheritDoc}
	 */
	public function remove_column(string $table_name, string $column_name, array $parameters = [], string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// Does it exist?
		$columns = $this->list_columns($table_name, true);

		foreach ($columns as $column) {
			if (strtolower($column['name']) == strtolower($column_name)) {
				// If there is an auto we need remove it!
				if ($column['auto']) {
					$this->query(
						'',
						'DROP SEQUENCE IF EXISTS ' . $short_table_name . '_seq',
						[
							'security_override' => true,
						],
					);
				}

				$this->query(
					'',
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
	 * {@inheritDoc}
	 */
	public function remove_index(string $table_name, string $index_name, array $parameters = [], string $error = 'fatal'): bool
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;

		// Better exist!
		$indexes = $this->list_indexes($table_name, true);

		// Do not add the table name to the index if it is arleady there.
		if ($index_name != 'primary' && strpos($index_name, $real_table_name) !== false) {
			$index_name = str_replace($real_table_name . '_', '', $index_name);
		}

		foreach ($indexes as $index) {
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary') {
				// Dropping primary key?
				$this->query(
					'',
					'ALTER TABLE ' . $real_table_name . '
					DROP CONSTRAINT ' . $index['name'],
					[
						'security_override' => true,
					],
				);

				return true;
			}

			if ($index['name'] == $index_name) {
				// Drop the bugger...
				$this->query(
					'',
					'DROP INDEX ' . $real_table_name . '_' . $index_name,
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor.
	 *
	 * If $options is empty, correct settings will be determined automatically.
	 *
	 * @param array $options An array of database options.
	 */
	protected function __construct(array $options = [])
	{
		parent::__construct();

		// If caller was explict about non_fatal, respect that.
		$non_fatal = !empty($options['non_fatal']);

		// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
		if (SMF == 'SSI' && !empty(Config::$ssi_db_user) && !empty(Config::$ssi_db_passwd)) {
			if (empty($options)) {
				$options = ['non_fatal' => true, 'dont_select_db' => true];
			}

			$this->initiate(Config::$ssi_db_user, Config::$ssi_db_passwd, $options);
		}

		// Either we aren't in SSI mode, or it failed.
		if (empty($this->connection)) {
			if (empty($options)) {
				$options = ['dont_select_db' => SMF == 'SSI'];
			}

			$this->initiate(Config::$db_user, Config::$db_passwd, $options);
		}

		// Safe guard here, if there isn't a valid connection let's put a stop to it.
		if (empty($this->connection) && !$non_fatal) {
			ErrorHandler::displayDbError();
		}

		// At this point, if we don't have a connection, nothing else can be done.
		if (empty($this->connection)) {
			return;
		}

		// For backward compatibility.
		if (!is_object(self::$db_connection)) {
			self::$db_connection = $this->connection;
		}

		// Ensure database has UTF-8 as its default input charset.
		$this->query(
			'',
			'SET NAMES {string:db_character_set}',
			[
				'db_character_set' => $this->character_set,
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
	protected function initiate(string $user, string $passwd, array $options = []): void
	{
		// We are not going to make it very far without this.
		if (!function_exists('pg_pconnect')) {
			ErrorHandler::displayDbError();
		}

		// We need to escape ' and \
		$passwd = str_replace(['\\', '\''], ['\\\\', '\\\''], $passwd);

		// Since pg_connect doesn't feed error info to pg_last_error, we have to catch issues with a try/catch.
		set_error_handler(
			function ($errno, $errstr) {
				throw new \ErrorException($errstr, $errno);
			},
		);

		try {
			$connection_string = (empty($this->server) ? '' : 'host=' . $this->server . ' ') . 'dbname=' . $this->name . ' user=\'' . $user . '\' password=\'' . $passwd . '\'' . (empty($options['port']) ? '' : ' port=\'' . $options['port'] . '\'');

			if (!empty($options['persist'])) {
				$this->connection = @pg_pconnect($connection_string);
			} else {
				$this->connection = @pg_connect($connection_string);
			}
		} catch (\Exception $e) {
			// Make error info available to calling processes
			$this->connect_error = $e->getMessage();
			$this->connect_errno = $e->getCode();
			$this->connection = null;
		}
		restore_error_handler();

		// Something's wrong, show an error if its fatal (which we assume it is)
		if (empty($this->connection) && empty($options['non_fatal'])) {
			ErrorHandler::displayDbError();
		}
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

		if (isset(User::$me->{$matches[1]}) && strpos($matches[1], 'query_') !== false) {
			return User::$me->{$matches[1]};
		}

		if ($matches[1] === 'empty') {
			return '\'\'';
		}

		if (!isset($matches[2])) {
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		if ($matches[1] === 'literal') {
			return '\'' . pg_escape_string($this->connection, $matches[2]) . '\'';
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
				return sprintf('\'%1$s\'', pg_escape_string($this->connection, (string) $replacement));

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
						$replacement[$key] = sprintf('\'%1$s\'', pg_escape_string($this->connection, $value));
					}

					return implode(', ', $replacement);
				}

				$this->error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'date':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1) {
					return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]) . '::date';
				}

				$this->error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'time':
				if (preg_match('~^([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $time_matches) === 1) {
					return sprintf('\'%02d:%02d:%02d\'', $time_matches[1], $time_matches[2], $time_matches[3]) . '::time';
				}

				$this->error_backtrace('Wrong value type sent to the database. Time expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'datetime':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d) ([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $datetime_matches) === 1) {
					return 'to_timestamp(' .
						sprintf('\'%04d-%02d-%02d %02d:%02d:%02d\'', $datetime_matches[1], $datetime_matches[2], $datetime_matches[3], $datetime_matches[4], $datetime_matches[5], $datetime_matches[6]) .
						',\'YYYY-MM-DD HH24:MI:SS\')';
				}

				$this->error_backtrace('Wrong value type sent to the database. Datetime expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				break;

			case 'float':
				if (!is_numeric($replacement)) {
					$this->error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				}

				return (string) (float) $replacement;

			case 'identifier':
				return '"' . implode('"."', array_filter(explode('.', strtr($replacement, ['`' => ''])), 'strlen')) . '"';

			case 'raw':
				return (string) $replacement;

			case 'uuid':
				if ($replacement instanceof Uuid) {
					return sprintf('\'%1$s\'::uuid', (string) $replacement);
				}

				$uuid = @Uuid::createFromString($replacement, false);

				if (in_array($replacement, [(string) $uuid, $uuid->getShortForm(), $uuid->getBinary()])) {
					return sprintf('\'%1$s\'::uuid', (string) $uuid);
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

				return sprintf('\'%1$s\'::inet', pg_escape_string($this->connection, strval($ip)));

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
							$this->error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected.(' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						$replacement[$key] = sprintf('\'%1$s\'::inet', pg_escape_string($this->connection, strval($ip)));
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
	 * @param string $file The file the error occurred in
	 * @param int $line What line of $file the code which generated the error is on
	 * @return void|array Returns an array with the file and line if $error_type is 'return'
	 */
	protected function error_backtrace(string $error_message, string $log_message = '', string|int|bool $error_type = false, ?string $file = null, ?int $line = null): ?array
	{
		if (empty($log_message)) {
			$log_message = $error_message;
		}

		foreach (debug_backtrace() as $step) {
			// Found it?
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), ['smf_db_', 'preg_re', 'db_erro', 'call_us']) && strpos($step['function'], '__') !== 0 && (empty($step['class']) || $step['class'] != $this::class)) {
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
		if (function_exists('log_error')) {
			ErrorHandler::log($log_message, 'critical', $file, $line);
		}

		if (function_exists('fatal_error')) {
			ErrorHandler::fatal($error_message, $error_type);

			// Cannot continue...
			exit;
		}

		if ($error_type) {
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), (int) $error_type);
		} else {
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
		}

		return null;
	}
}

?>