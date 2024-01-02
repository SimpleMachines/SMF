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
 * Interacts with MySQL databases.
 */
class MySQL extends DatabaseApi implements DatabaseApiInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $title = MYSQL_TITLE;

	/**
	 * {@inheritDoc}
	 */
	public bool $sybase = false;

	/**
	 * {@inheritDoc}
	 */
	public bool $case_sensitive = false;

	/**
	 * {@inheritDoc}
	 */
	public bool $support_ignore = true;

	/**
	 * {@inheritDoc}
	 */
	public bool $supports_pcre = false;

	/********************
	 * Runtime properties
	 ********************/

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
	 * {@inheritDoc}
	 */
	public function query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool
	{
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

		// Decide which connection to use.
		$connection = $connection ?? $this->connection;

		// One more query....
		self::$count++;

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
			$db_string_1 = str_replace('\\\'', '', $db_string);

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

		// Debugging.
		if ($this->show_debug) {
			self::$cache[self::$count]['t'] = microtime(true) - $st;
		}

		return $ret;
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
		return mysqli_fetch_row($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_assoc(object $result): array|false|null
	{
		return mysqli_fetch_assoc($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_all(object $request): array
	{
		$return = mysqli_fetch_all($request, MYSQLI_ASSOC);

		return !empty($return) ? $return : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function free_result(object $result): bool
	{
		mysqli_free_result($result);

		return true;
	}

	/**
	 * {@inheritDoc}
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

		// Inserting data as a single row can be done as a single array.
		if (!is_array($data[array_rand($data)])) {
			$data = [$data];
		}

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
				'',
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
					'',
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
					$where_string = '';

					$count2 = count($keys);

					for ($x = 0; $x < $count2; $x++) {
						$keyPos = array_search($keys[$x], array_keys($columns));

						$where_string .= $keys[$x] . ' = ' . $data[$i][$keyPos];

						if (($x + 1) < $count2) {
							$where_string .= ' AND ';
						}
					}

					$request = $this->query(
						'',
						'SELECT `' . $keys[0] . '` FROM ' . $table . '
						WHERE ' . $where_string . ' LIMIT 1',
						[],
					);

					if ($request !== false && $this->num_rows($request) == 1) {
						$row = $this->fetch_assoc($request);
						$ai = $row[$keys[0]];
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
	 * {@inheritDoc}
	 */
	public function insert_id(string $table, ?string $field = null, ?object $connection = null): int
	{
		// MySQL doesn't need the table or field information.
		return mysqli_insert_id($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_rows(object $result): int
	{
		return mysqli_num_rows($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function data_seek(object $result, int $offset): bool
	{
		return mysqli_data_seek($result, $offset);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_fields(object $result): int
	{
		return mysqli_num_fields($result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape_string(string $string, ?object $connection = null): string
	{
		return mysqli_real_escape_string($connection ?? $this->connection, $string);
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
		return mysqli_get_server_info($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function affected_rows(?object $connection = null): int
	{
		return mysqli_affected_rows($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function error(object $connection): string
	{
		if ($connection === null && $this->connection === null) {
			return '';
		}

		if (!(($connection ?? $this->connection) instanceof \mysqli)) {
			return '';
		}

		return mysqli_error($connection ?? $this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function select(string $database, ?object $connection = null): bool
	{
		return mysqli_select_db($connection ?? $this->connection, $database);
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
		return ($result instanceof \mysqli_result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function ping(?object $connection = null): bool
	{
		return mysqli_ping($connection ?? $this->connection);
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
		if (isset($this->supports_cte)) {
			return $this->supports_cte;
		}

		$this->get_version();

		$min_version = strpos(strtolower($this->version), 'mariadb') !== false ? '10.2.2' : '8.0.1';

		$this->supports_cte = version_compare($this->version, $min_version, '>=');

		return $this->supports_cte;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect_error(): string
	{
		return mysqli_connect_error();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect_errno(): int
	{
		return mysqli_connect_errno();
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

		// First, get rid of the old table.
		$this->query(
			'',
			'DROP TABLE IF EXISTS {raw:backup_table}',
			[
				'backup_table' => $backup_table,
			],
		);

		// Can we do this the quick way?
		$result = $this->query(
			'',
			'CREATE TABLE {raw:backup_table} LIKE {raw:table}',
			[
				'backup_table' => $backup_table,
				'table' => $table,
			],
		);

		// If this failed, we go old school.
		if ($result) {
			$request = $this->query(
				'',
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
			'',
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
		$engine = 'MyISAM';
		$charset = '';
		$collate = '';

		foreach ($create as $k => $l) {
			// Get the name of the auto_increment column.
			if (strpos($l, 'auto_increment')) {
				$auto_inc = trim($l);
			}

			// For the engine type, see if we can work out what it is.
			if (strpos($l, 'ENGINE') !== false || strpos($l, 'TYPE') !== false) {
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
			if (strpos($l, 'KEY') === false) {
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
			'',
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
			if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && substr($auto_inc, -1, 1) == ',') {
				$auto_inc = substr($auto_inc, 0, -1);
			}

			$this->query(
				'',
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
	 * {@inheritDoc}
	 */
	public function optimize_table(string $table): int|float
	{
		$table = str_replace('{db_prefix}', $this->prefix, $table);

		// Get how much overhead there is.
		$request = $this->query(
			'',
			'SHOW TABLE STATUS LIKE {string:table_name}',
			[
				'table_name' => str_replace('_', '\\_', $table),
			],
		);
		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		$data_before = $row['Data_free'] ?? 0;
		$request = $this->query(
			'',
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
			'',
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
	 * {@inheritDoc}
	 */
	public function table_sql(string $tableName): string
	{
		$tableName = str_replace('{db_prefix}', $this->prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Drop it if it exists.
		$schema_create = 'DROP TABLE IF EXISTS `' . $tableName . '`;' . $crlf . $crlf;

		// Start the create table...
		$schema_create .= 'CREATE TABLE ' . '`' . $tableName . '` (' . $crlf;

		// Find all the fields.
		$result = $this->query(
			'',
			'SHOW FIELDS
			FROM `{raw:table}`',
			[
				'table' => $tableName,
			],
		);

		while ($row = $this->fetch_assoc($result)) {
			// Make the CREATE for this column.
			$schema_create .= ' `' . $row['Field'] . '` ' . $row['Type'] . ($row['Null'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (!empty($row['Default']) || $row['Null'] !== 'YES') {
				// Make a special case of auto-timestamp.
				if ($row['Default'] == 'CURRENT_TIMESTAMP') {
					$schema_create .= ' /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */';
				}
				// Text shouldn't have a default.
				elseif ($row['Default'] !== null) {
					// If this field is numeric the default needs no escaping.
					$type = strtolower($row['Type']);
					$isNumericColumn = strpos($type, 'int') !== false || strpos($type, 'bool') !== false || strpos($type, 'bit') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false;

					$schema_create .= ' default ' . ($isNumericColumn ? $row['Default'] : '\'' . $this->escape_string($row['Default']) . '\'');
				}
			}

			// And now any extra information. (such as auto_increment.)
			$schema_create .= ($row['Extra'] != '' ? ' ' . $row['Extra'] : '') . ',' . $crlf;
		}
		$this->free_result($result);

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		// Find the keys.
		$result = $this->query(
			'',
			'SHOW KEYS
			FROM `{raw:table}`',
			[
				'table' => $tableName,
			],
		);
		$indexes = [];

		while ($row = $this->fetch_assoc($result)) {
			// IS this a primary key, unique index, or regular index?
			$row['Key_name'] = $row['Key_name'] == 'PRIMARY' ? 'PRIMARY KEY' : (empty($row['Non_unique']) ? 'UNIQUE ' : ($row['Comment'] == 'FULLTEXT' || (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT') ? 'FULLTEXT ' : 'KEY ')) . '`' . $row['Key_name'] . '`';

			// Is this the first column in the index?
			if (empty($indexes[$row['Key_name']])) {
				$indexes[$row['Key_name']] = [];
			}

			// A sub part, like only indexing 15 characters of a varchar.
			if (!empty($row['Sub_part'])) {
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`(' . $row['Sub_part'] . ')';
			} else {
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`';
			}
		}
		$this->free_result($result);

		// Build the CREATEs for the keys.
		foreach ($indexes as $keyname => $columns) {
			// Ensure the columns are in proper order.
			ksort($columns);

			$schema_create .= ',' . $crlf . ' ' . $keyname . ' (' . implode(', ', $columns) . ')';
		}

		// Now just get the comment and engine... (MyISAM, etc.)
		$result = $this->query(
			'',
			'SHOW TABLE STATUS
			LIKE {string:table}',
			[
				'table' => strtr($tableName, ['_' => '\\_', '%' => '\\%']),
			],
		);
		$row = $this->fetch_assoc($result);
		$this->free_result($result);

		// Probably MyISAM.... and it might have a comment.
		$schema_create .= $crlf . ') ENGINE=' . $row['Engine'] . ($row['Comment'] != '' ? ' COMMENT="' . $row['Comment'] . '"' : '');

		return $schema_create;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_tables(string|bool $db = false, string|bool $filter = false): array
	{
		$db = $db == false ? $this->name : $db;
		$db = trim($db);
		$filter = $filter == false ? '' : ' LIKE \'' . $filter . '\'';

		$request = $this->query(
			'',
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
	 * {@inheritDoc}
	 */
	public function get_version(): string
	{
		if (!empty($this->version)) {
			return $this->version;
		}

		$request = $this->query(
			'',
			'SELECT VERSION()',
			[
			],
		);
		list($this->version) = $this->fetch_row($request);
		$this->free_result($request);

		return $this->version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_vendor(): string
	{
		if (!empty($this->vendor)) {
			return $this->vendor;
		}

		$request = $this->query('', 'SELECT @@version_comment');
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
			Lang::load('Admin');

			return Lang::$txt['unknown'];
		}

		return $this->vendor;
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function search_query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool
	{
		return $this->query($identifier, $db_string, $db_values, $connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_support(string $search_type): bool
	{
		$supported_types = ['fulltext'];

		return in_array($search_type, $supported_types);
	}

	/**
	 * {@inheritDoc}
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
			'',
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
	 * {@inheritDoc}
	 */
	public function search_language(): ?string
	{
		return null;
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

		// Now add the thing!
		$this->query(
			'',
			'ALTER TABLE ' . $short_table_name . '
			ADD ' . $this->create_query_column($column_info) . (empty($column_info['auto']) ? '' : ' primary key'),
			[
				'security_override' => true,
			],
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function add_index(string $table_name, array $index_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// No columns = no index.
		if (empty($index_info['columns'])) {
			return false;
		}

		// MySQL If its a text column, we need to add a size.
		$cols = $this->list_columns($table_name, true);

		foreach ($index_info['columns'] as &$c) {
			$c = trim($c);
			$cols[$c]['size'] = isset($cols[$c]['size']) && is_numeric($cols[$c]['size']) ? $cols[$c]['size'] : null;
			list($type, $size) = $this->calculate_type($cols[$c]['type'], $cols[$c]['size']);

			// If a size was already specified, we won't be able to match it anyways.
			if (
				!isset($cols[$c])
				|| !in_array($cols[$c]['type'], ['text', 'mediumntext', 'largetext', 'varchar', 'char'])
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

		// Log that we are going to want to remove this!
		self::$package_log[] = ['remove_index', $short_table_name, $index_info['name']];

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
				'ALTER TABLE ' . $short_table_name . '
				ADD PRIMARY KEY (' . $columns . ')',
				[
					'security_override' => true,
				],
			);
		} else {
			$result = $this->query(
				'',
				'ALTER TABLE ' . $short_table_name . '
				ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')',
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
		// MySQL is actually the generic baseline.

		$type_name = strtolower($type_name);

		// Generic => Specific.
		if (!$reverse) {
			$types = [
				'inet' => 'varbinary',
			];
		} else {
			$types = [
				'varbinary' => 'inet',
			];
		}

		// Got it? Change it!
		if (isset($types[$type_name])) {
			if ($type_name == 'inet' && !$reverse) {
				$type_size = 16;
				$type_name = 'varbinary';
			} elseif ($type_name == 'varbinary' && $reverse && $type_size == 16) {
				$type_name = 'inet';
				$type_size = null;
			} elseif ($type_name == 'varbinary') {
				$type_name = 'varbinary';
			} else {
				$type_name = $types[$type_name];
			}
		} elseif ($type_name == 'boolean') {
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

		list($type, $size) = $this->calculate_type($column_info['type'], $column_info['size']);

		// Allow for unsigned integers (mysql only)
		$unsigned = in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

		// If you need to drop the default, that needs it's own thing...
		// Must be done first, in case the default type is inconsistent with the other changes.
		if ($column_info['drop_default']) {
			$this->query(
				'',
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
				$default_clause = 'DEFAULT ' . (strpos($column_info['default'], '.') ? floatval($column_info['default']) : intval($column_info['default']));
			} elseif (is_string($column_info['default'])) {
				$default_clause = 'DEFAULT \'' . $this->escape_string($column_info['default']) . '\'';
			}
		}

		if ($size !== null) {
			$type = $type . '(' . $size . ')';
		}

		$result = $this->query(
			'',
			'ALTER TABLE ' . $short_table_name . '
			CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . ' ' .
				(!empty($unsigned) ? $unsigned : '') . (!empty($column_info['not_null']) ? 'NOT NULL' : '') . ' ' .
				$default_clause . ' ' .
				(empty($column_info['auto']) ? '' : 'auto_increment') . ' ',
			[
				'security_override' => true,
			],
		);

		return $result !== false;
	}

	/**
	 * {@inheritDoc}
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
		if (in_array(strtolower($short_table_name), $this->reservedTables)) {
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
					'',
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
			// MySQL If its a text column, we need to add a size.
			foreach ($index['columns'] as &$c) {
				$c = trim($c);

				// If a size was already specified, we won't be able to match it anyways.
				$key = array_search($c, array_column($columns, 'name'));
				$columns[$key]['size'] = isset($columns[$key]['size']) && is_numeric($columns[$key]['size']) ? $columns[$key]['size'] : null;
				list($type, $size) = $this->calculate_type($columns[$key]['type'], $columns[$key]['size']);

				if (
					$key === false
					|| !isset($columns[$key])
					|| !in_array($columns[$key]['type'], ['text', 'mediumntext', 'largetext', 'varchar', 'char'])
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
		if (substr($table_query, -1) == ',') {
			$table_query = substr($table_query, 0, -1);
		}

		// Which engine do we want here?
		if (empty($this->engines)) {
			// Figure out which engines we have
			$get_engines = $this->query('', 'SHOW ENGINES', []);

			while ($row = $this->fetch_assoc($get_engines)) {
				if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT') {
					$this->engines[] = $row['Engine'];
				}
			}

			$this->free_result($get_engines);
		}

		// If we don't have this engine, or didn't specify one, default to InnoDB or MyISAM
		// depending on which one is available
		if (!isset($parameters['engine']) || !in_array($parameters['engine'], $this->engines)) {
			$parameters['engine'] = in_array('InnoDB', $this->engines) ? 'InnoDB' : 'MyISAM';
		}

		$table_query .= ') ENGINE=' . $parameters['engine'];

		if (!empty($this->character_set) && $this->character_set == 'utf8') {
			$table_query .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
		}

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
					'schema' => $this->name,
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

			$this->drop_table($short_table_name . '_old');
		}

		return true;
	}

	/**
	 * {@inheritDoc}
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
				'',
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
	 * {@inheritDoc}
	 */
	public function table_structure(string $table_name): array
	{
		$parsed_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);
		$real_table_name = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $parsed_table_name, $match) === 1 ? $match[3] : $parsed_table_name;
		$database = !empty($match[2]) ? $match[2] : $this->name;

		// Find the table engine and add that to the info as well
		$table_status = $this->query(
			'',
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
			'name' => $parsed_table_name,
			'columns' => $this->list_columns($table_name, true),
			'indexes' => $this->list_indexes($table_name, true),
			'engine' => $row['Engine'],
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
			'SELECT column_name "Field", COLUMN_TYPE "Type", is_nullable "Null", COLUMN_KEY "Key" , column_default "Default", extra "Extra"
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
				$auto = strpos($row['Extra'], 'auto_increment') !== false ? true : false;

				// Can we split out the size?
				if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1) {
					$type = $matches[1];
					$size = $matches[2];

					if (!empty($matches[3]) && $matches[3] == 'unsigned') {
						$unsigned = true;
					}
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
	 * {@inheritDoc}
	 */
	public function remove_column(string $table_name, string $column_name, array $parameters = [], string $error = 'fatal'): bool
	{
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// Does it exist?
		$columns = $this->list_columns($table_name, true);

		foreach ($columns as $column) {
			if ($column['name'] == $column_name) {
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
		$short_table_name = str_replace('{db_prefix}', $this->prefix, $table_name);

		// Better exist!
		$indexes = $this->list_indexes($table_name, true);

		foreach ($indexes as $index) {
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary') {
				// Dropping primary key?
				$this->query(
					'',
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
					'',
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
		$this->supports_pcre = version_compare($this->version, strpos($this->version, 'MariaDB') !== false ? '10.0.5' : '8.0.4', '>=');

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
		$server = ($this->persist ? 'p:' : '') . $this->server;

		// We are not going to make it very far without these.
		if (!function_exists('mysqli_init') || !function_exists('mysqli_real_connect')) {
			ErrorHandler::displayDbError();
		}

		// This was the default prior to PHP 8.1, and all our code assumes it.
		mysqli_report(MYSQLI_REPORT_OFF);

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

		// Something's wrong, show an error if its fatal (which we assume it is)
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
				return sprintf('\'%1$s\'', mysqli_real_escape_string($this->temp_connection, (string) $replacement));

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
						$replacement[$key] = sprintf('\'%1$s\'', mysqli_real_escape_string($this->temp_connection, $value));
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
			ErrorHandler::fatal($error_message, false);

			// Cannot continue...
			exit;
		}

		if ($error_type) {
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		} else {
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
		}

		return null;
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
			$default = 'DEFAULT ' . (strpos($column['default'], '.') ? floatval($column['default']) : intval($column['default']));
		}
		// Non empty string.
		elseif (isset($column['default'])) {
			$default = 'DEFAULT \'' . $this->escape_string($column['default']) . '\'';
		} else {
			$default = '';
		}

		// Backwards compatible with the nullable column.
		if (isset($column['null']) && !isset($column['not_null'])) {
			$column['not_null'] = !$column['null'];
		}

		// Sort out the size... and stuff...
		$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
		list($type, $size) = $this->calculate_type($column['type'], $column['size']);

		// Allow unsigned integers (mysql only)
		$unsigned = in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']) && !empty($column['unsigned']) ? 'unsigned ' : '';

		if ($size !== null) {
			$type = $type . '(' . $size . ')';
		}

		// Now just put it together!
		return '`' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['not_null']) ? 'NOT NULL' : '') . ' ' . $default;
	}
}

?>