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
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IP;
use SMF\Lang;
use SMF\PageIndex;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Shows a list of all errors that were logged on the forum,
 * and allows filtering and deleting them.
 */
class ErrorLog implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewErrorLog',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Info about the currently applied filter.
	 */
	public array $filter;

	/**
	 * @var array
	 *
	 * Basic info about the available filters.
	 */
	public array $filters = [
		'id_member' => [
			'txt' => 'username',
			'operator' => '=',
			'datatype' => 'int',
		],
		'ip' => [
			'txt' => 'ip_address',
			'operator' => '=',
			'datatype' => 'inet',
		],
		'session' => [
			'txt' => 'session',
			'operator' => 'LIKE',
			'datatype' => 'string',
		],
		'url' => [
			'txt' => 'error_url',
			'operator' => 'LIKE',
			'datatype' => 'string',
		],
		'message' => [
			'txt' => 'error_message',
			'operator' => 'LIKE',
			'datatype' => 'string',
		],
		'error_type' => [
			'txt' => 'error_type',
			'operator' => 'LIKE',
			'datatype' => 'string',
		],
		'file' => [
			'txt' => 'file',
			'operator' => 'LIKE',
			'datatype' => 'string',
		],
		'line' => [
			'txt' => 'line',
			'operator' => '=',
			'datatype' => 'int',
		],
	];

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
	 * Dispatcher to whichever method is necessary.
	 */
	public function execute(): void
	{
		// Check for the administrative permission to do this.
		User::$me->isAllowedTo('admin_forum');

		// Viewing contents of a file?
		if (isset($_GET['file'])) {
			$this->viewFile();
		}
		// Viewing contents of a backtrace?
		elseif (isset($_GET['backtrace'])) {
			$this->viewBacktrace();
		}
		// Viewing the log.
		else {
			$this->view();
		}
	}

	/**
	 * View the forum's error log.
	 *
	 * This function sets all the context up to show the error log for maintenance.
	 * It requires the maintain_forum permission.
	 * It is accessed from ?action=admin;area=logs;sa=errorlog.
	 */
	public function view(): void
	{
		// Set up the filtering...
		if (isset($_GET['value'], $_GET['filter'], $this->filters[$_GET['filter']])) {
			$this->filter = [
				'variable' => $_GET['filter'],
				'value' => [
					'sql' => in_array($_GET['filter'], ['message', 'url', 'file']) ? base64_decode(strtr($_GET['value'], [' ' => '+'])) : Db::$db->escape_wildcard_string($_GET['value']),
				],
				'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
				'entity' => $this->filters[$_GET['filter']]['txt'],
			];
		}

		// Deleting, are we?
		if (isset($_POST['delall']) || isset($_POST['delete'])) {
			$this->deleteErrors();
		}

		// Just how many errors are there?
		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_errors' . (isset($this->filter) ? '
			WHERE ' . $this->filter['variable'] . ' ' . $this->filters[$_GET['filter']]['operator'] . ' {' . $this->filters[$_GET['filter']]['datatype'] . ':filter}' : ''),
			[
				'filter' => isset($this->filter) ? $this->filter['value']['sql'] : '',
			],
		);
		list($num_errors) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// If this filter is empty...
		if ($num_errors == 0 && isset($this->filter)) {
			Utils::redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
		}

		// Clean up start.
		if (!isset($_GET['start']) || $_GET['start'] < 0) {
			$_GET['start'] = 0;
		}

		// Do we want to reverse error listing?
		Utils::$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

		// Set the page listing up.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=admin;area=logs;sa=errorlog' . (Utils::$context['sort_direction'] == 'down' ? ';desc' : '') . (isset($this->filter) ? $this->filter['href'] : ''), $_GET['start'], $num_errors, Config::$modSettings['defaultMaxListItems']);

		Utils::$context['start'] = $_GET['start'];

		// Update the error count
		if (!isset($this->filter)) {
			Utils::$context['num_errors'] = $num_errors;
		} else {
			// We want all errors, not just the number of filtered messages...
			$query = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}log_errors',
				[],
			);
			list(Utils::$context['num_errors']) = Db::$db->fetch_row($query);
			Db::$db->free_result($query);
		}

		// Find and sort out the errors.
		Utils::$context['errors'] = [];
		$members = [];

		$request = Db::$db->query(
			'',
			'SELECT id_error, id_member, ip, url, log_time, message, session, error_type, file, line
			FROM {db_prefix}log_errors' . (isset($this->filter) ? '
			WHERE ' . $this->filter['variable'] . ' ' . $this->filters[$_GET['filter']]['operator'] . ' {' . $this->filters[$_GET['filter']]['datatype'] . ':filter}' : '') . '
			ORDER BY id_error ' . (Utils::$context['sort_direction'] == 'down' ? 'DESC' : '') . '
			LIMIT {int:start}, {int:max}',
			[
				'filter' => isset($this->filter) ? $this->filter['value']['sql'] : '',
				'start' => $_GET['start'],
				'max' => Config::$modSettings['defaultMaxListItems'],
			],
		);

		for ($i = 0; $row = Db::$db->fetch_assoc($request); $i++) {
			$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', Db::$db->escape_wildcard_string($row['message']));

			if (isset($this->filter) && $search_message == $this->filter['value']['sql']) {
				$search_message = Db::$db->escape_wildcard_string($row['message']);
			}

			$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), ["\r" => '', '<br>' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;']), ["\n" => '<br>']);

			Utils::$context['errors'][$row['id_error']] = [
				'member' => [
					'id' => $row['id_member'],
					'ip' => isset($row['ip']) ? new IP($row['ip']) : null,
					'session' => $row['session'],
				],
				'time' => Time::create('@' . $row['log_time'])->format(),
				'timestamp' => $row['log_time'],
				'url' => [
					'html' => Utils::htmlspecialchars(strpos($row['url'], 'cron.php') === false ? (substr($row['url'], 0, 1) == '?' ? Config::$scripturl : '') . $row['url'] : $row['url']),
					'href' => base64_encode(Db::$db->escape_wildcard_string($row['url'])),
				],
				'message' => [
					'html' => $show_message,
					'href' => base64_encode($search_message),
				],
				'id' => $row['id_error'],
				'error_type' => [
					'type' => $row['error_type'],
					'name' => Lang::$txt['errortype_' . $row['error_type']] ?? $row['error_type'],
				],
				'file' => [],
			];

			if (!empty($row['file']) && !empty($row['line'])) {
				// Eval'd files rarely point to the right location and cause
				// havoc for linking, so don't link them.
				$linkfile = strpos($row['file'], 'eval') !== false && strpos($row['file'], '?') !== false;

				Utils::$context['errors'][$row['id_error']]['file'] = [
					'file' => $row['file'],
					'line' => $row['line'],
					'href' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'],
					'link' => $linkfile ? '<a href="' . Config::$scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'] . '" onclick="return reqWin(this.href, 600, 480, false);">' . $row['file'] . '</a>' : $row['file'],
					'search' => base64_encode($row['file']),
				];
			}

			// Make a list of members to load later.
			$members[$row['id_member']] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// Load the member data.
		if (!empty($members)) {
			// Get some additional member info...
			$request = Db::$db->query(
				'',
				'SELECT id_member, member_name, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT {int:members}',
				[
					'member_list' => $members,
					'members' => count($members),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$members[$row['id_member']] = $row;
			}
			Db::$db->free_result($request);

			// This is a guest...
			$members[0] = [
				'id_member' => 0,
				'member_name' => '',
				'real_name' => Lang::$txt['guest_title'],
			];

			// Go through each error and tack the data on.
			foreach (Utils::$context['errors'] as $id => &$error) {
				$memID = $error['member']['id'];

				$error['member']['username'] = $members[$memID]['member_name'];
				$error['member']['name'] = $members[$memID]['real_name'];
				$error['member']['href'] = empty($memID) ? '' : Config::$scripturl . '?action=profile;u=' . $memID;
				$error['member']['link'] = empty($memID) ? Lang::$txt['guest_title'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $memID . '">' . $error['member']['name'] . '</a>';
			}
		}

		// Filtering anything?
		if (isset($this->filter)) {
			Utils::$context['filter'] = &$this->filter;

			// Set the filtering context.
			if ($this->filter['variable'] == 'id_member') {
				$id = $this->filter['value']['sql'];

				User::load($id, self::LOAD_BY_ID, 'minimal');

				Utils::$context['filter']['value']['html'] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $id . '">' . (isset(User::$loaded[$id]) ? User::$loaded[$id]->name : Lang::$txt['guest']) . '</a>';
			} elseif ($this->filter['variable'] == 'url') {
				Utils::$context['filter']['value']['html'] = '\'' . strtr(Utils::htmlspecialchars((substr($this->filter['value']['sql'], 0, 1) == '?' ? Config::$scripturl : '') . $this->filter['value']['sql']), ['\\_' => '_']) . '\'';
			} elseif ($this->filter['variable'] == 'message') {
				Utils::$context['filter']['value']['html'] = '\'' . strtr(Utils::htmlspecialchars($this->filter['value']['sql']), ["\n" => '<br>', '&lt;br /&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\\_' => '_', '\\%' => '%', '\\\\' => '\\']) . '\'';

				Utils::$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', Utils::$context['filter']['value']['html']);
			} elseif ($this->filter['variable'] == 'error_type') {
				Utils::$context['filter']['value']['html'] = '\'' . strtr(Utils::htmlspecialchars($this->filter['value']['sql']), ["\n" => '<br>', '&lt;br /&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\\_' => '_', '\\%' => '%', '\\\\' => '\\']) . '\'';
			} else {
				Utils::$context['filter']['value']['html'] = &$this->filter['value']['sql'];
			}
		}

		Utils::$context['error_types'] = [];

		Utils::$context['error_types']['all'] = [
			'label' => Lang::$txt['errortype_all'],
			'error_type' => 'all',
			'description' => Lang::$txt['errortype_all_desc'] ?? '',
			'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog' . (Utils::$context['sort_direction'] == 'down' ? ';desc' : ''),
			'is_selected' => empty($this->filter),
		];

		// What type of errors do we have and how many do we have?
		$sum = 0;

		$request = Db::$db->query(
			'',
			'SELECT error_type, COUNT(*) AS num_errors
			FROM {db_prefix}log_errors
			GROUP BY error_type
			ORDER BY error_type = {string:critical_type} DESC, error_type ASC',
			[
				'critical_type' => 'critical',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Total errors so far?
			$sum += $row['num_errors'];

			Utils::$context['error_types'][$sum] = [
				'label' => (Lang::$txt['errortype_' . $row['error_type']] ?? $row['error_type']) . ' (' . $row['num_errors'] . ')',
				'error_type' => $row['error_type'],
				'description' => Lang::$txt['errortype_' . $row['error_type'] . '_desc'] ?? '',
				'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog' . (Utils::$context['sort_direction'] == 'down' ? ';desc' : '') . ';filter=error_type;value=' . $row['error_type'],
				'is_selected' => isset($this->filter) && $this->filter['value']['sql'] == Db::$db->escape_wildcard_string($row['error_type']),
			];
		}
		Db::$db->free_result($request);

		// Update the all errors tab with the total number of errors
		Utils::$context['error_types']['all']['label'] .= ' (' . $sum . ')';

		// Finally, work out what is the last tab!
		if (isset(Utils::$context['error_types'][$sum])) {
			Utils::$context['error_types'][$sum]['is_last'] = true;
		} else {
			Utils::$context['error_types']['all']['is_last'] = true;
		}

		// And this is pretty basic ;).
		Utils::$context['page_title'] = Lang::$txt['errorlog'];
		Utils::$context['has_filter'] = isset($this->filter);
		Utils::$context['sub_template'] = 'error_log';

		SecurityToken::create('admin-el');
	}

	/**
	 * View a file specified in $_REQUEST['file'], with PHP highlighting.
	 *
	 * Preconditions:
	 *  - file must be readable,
	 *  - full file path must be base64 encoded,
	 *  - user must have admin_forum permission.
	 *
	 * The line number number is specified by $_REQUEST['line']...
	 * This method will try to get the 20 lines before and after the specified line.
	 */
	public function viewFile(): void
	{
		// Decode the file and get the line
		$file = realpath(base64_decode($_REQUEST['file']));
		$real_board = realpath(Config::$boarddir);
		$real_source = realpath(Config::$sourcedir);
		$real_cache = realpath(Config::$cachedir);
		$basename = strtolower(basename($file));
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$line = isset($_REQUEST['line']) ? (int) $_REQUEST['line'] : 0;

		// Make sure the file we are looking for is one they are allowed to look at
		if (
			$ext != 'php'
			|| (
				strpos($file, $real_board) === false
				&& strpos($file, $real_source) === false
			)
			|| $basename == strtolower(basename(SMF_SETTINGS_FILE))
			|| $basename == strtolower(basename(SMF_SETTINGS_BACKUP_FILE))
			|| strpos($file, $real_cache) !== false
			|| !is_readable($file)
		) {
			ErrorHandler::fatalLang('error_bad_file', true, [Utils::htmlspecialchars($file)]);
		}

		// Get the min and max lines.
		// Max includes one additional line to make everything work out correctly.
		$min = max($line - 20, 1);
		$max = $line + 21;

		if ($max <= 0 || $min >= $max) {
			ErrorHandler::fatalLang('error_bad_line');
		}

		$file_data = explode('<br />', BBCodeParser::highlightPhpCode(Utils::htmlspecialchars(file_get_contents($file))));

		// We don't want to slice off too many so lets make sure we stop at the last one
		$max = min($max, max(array_keys($file_data)));

		$file_data = array_slice($file_data, $min - 1, $max - $min);

		Utils::$context['file_data'] = [
			'contents' => $file_data,
			'min' => $min,
			'target' => $line,
			'file' => strtr($file, ['"' => '\\"']),
		];

		Theme::loadTemplate('Errors');
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'show_file';
	}

	/**
	 * View a backtrace specified in $_REQUEST['backtrace'], with PHP highlighting.
	 *
	 * Preconditions:
	 *  - user must have admin_forum permission.
	 */
	public function viewBacktrace(): void
	{
		$id_error = (int) $_REQUEST['backtrace'];

		$request = Db::$db->query(
			'',
			'SELECT backtrace, error_type, message, file, line, url
			FROM {db_prefix}log_errors
			WHERE id_error = {int:id_error}',
			[
				'id_error' => $id_error,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['error_info'] = $row;
			Utils::$context['error_info']['url'] = Config::$scripturl . $row['url'];
			Utils::$context['error_info']['backtrace'] = Utils::jsonDecode($row['backtrace']);
		}
		Db::$db->free_result($request);

		Theme::loadCSSFile('admin.css', [], 'smf_admin');
		Theme::loadTemplate('Errors');
		Lang::load('ManageMaintenance');
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'show_backtrace';
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Templates, etc...
		Lang::load('ManageMaintenance');
		Theme::loadTemplate('Errors');

		foreach ($this->filters as &$filter) {
			$filter['txt'] = Lang::$txt[$filter['txt']];
		}
	}

	/**
	 * Delete all or some of the errors in the error log.
	 *
	 * It applies any necessary filters to deletion.
	 * It attempts to TRUNCATE the table to reset the auto_increment.
	 * Redirects back to the error log when done.
	 */
	protected function deleteErrors()
	{
		// Make sure the session exists and is correct; otherwise, might be a hacker.
		User::$me->checkSession();
		SecurityToken::validate('admin-el');

		// Delete all or just some?
		if (isset($_POST['delall']) && !isset($this->filter)) {
			Db::$db->query(
				'truncate_table',
				'TRUNCATE {db_prefix}log_errors',
				[
				],
			);
		}
		// Deleting all with a filter?
		elseif (isset($_POST['delall'], $this->filter)) {
			// IP addresses need a different placeholder type.
			$filter_type = $this->filter['variable'] == 'ip' ? 'inet' : 'string';
			$filter_op = $this->filter['variable'] == 'ip' ? '=' : 'LIKE';

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_errors
				WHERE ' . $this->filter['variable'] . ' ' . $filter_op . ' {' . $filter_type . ':filter}',
				[
					'filter' => $this->filter['value']['sql'],
				],
			);
		}
		// Just specific errors?
		elseif (!empty($_POST['delete'])) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_errors
				WHERE id_error IN ({array_int:error_list})',
				[
					'error_list' => array_unique($_POST['delete']),
				],
			);

			// Go back to where we were.
			Utils::redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $_GET['start'] . (isset($this->filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
		}

		// Back to the error log!
		Utils::redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ErrorLog::exportStatic')) {
	ErrorLog::exportStatic();
}

?>