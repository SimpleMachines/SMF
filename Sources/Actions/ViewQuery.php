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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Provides a way to view database queries. Used for debugging.
 */
class ViewQuery implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewQuery',
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
	 * Show the database queries for debugging.
	 *
	 * - Toggles the session variable 'view_queries'.
	 * - Views a list of queries and analyzes them.
	 * - Requires the admin_forum permission.
	 * - Is accessed via ?action=viewquery.
	 */
	public function execute(): void
	{
		// We should have debug mode enabled, as well as something to display!
		if (!isset(Config::$db_show_debug) || Config::$db_show_debug !== true || !isset($_SESSION['debug'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Don't allow except for administrators.
		User::$me->isAllowedTo('admin_forum');

		// If we're just hiding/showing, do it now.
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide') {
			$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;

			if (strpos($_SESSION['old_url'], 'action=viewquery') !== false) {
				Utils::redirectexit();
			} else {
				Utils::redirectexit($_SESSION['old_url']);
			}
		}

		IntegrationHook::call('integrate_egg_nog');

		$query_id = isset($_REQUEST['qq']) ? (int) $_REQUEST['qq'] - 1 : -1;

		echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', Utils::$context['forum_name_html_safe'], '</title>
		<link rel="stylesheet" href="', Theme::$current->settings['theme_url'], '/css/index', Utils::$context['theme_variant'], '.css?alp21">
		<style>
			body
			{
				margin: 1ex;
			}
			body, td, th, .normaltext
			{
				font-size: x-small;
			}
			.smalltext
			{
				font-size: xx-small;
			}
		</style>
	</head>
	<body id="help_popup">
		<div class="tborder windowbg description">';

		foreach ($_SESSION['debug'] as $q => $query_data) {
			// Fix the indentation....
			$query_data['q'] = ltrim(str_replace("\r", '', $query_data['q']), "\n");
			$query = explode("\n", $query_data['q']);
			$min_indent = 0;

			foreach ($query as $line) {
				preg_match('/^(\t*)/', $line, $temp);

				if (strlen($temp[0]) < $min_indent || $min_indent == 0) {
					$min_indent = strlen($temp[0]);
				}
			}

			foreach ($query as $l => $dummy) {
				$query[$l] = substr($dummy, $min_indent);
			}

			$query_data['q'] = implode("\n", $query);

			// Make the filenames look a bit better.
			if (isset($query_data['f'])) {
				$query_data['f'] = preg_replace('~^' . preg_quote(Config::$boarddir, '~') . '~', '...', $query_data['f']);
			}

			$is_select_query = substr(trim($query_data['q']), 0, 6) == 'SELECT' || substr(trim($query_data['q']), 0, 4) == 'WITH';

			if ($is_select_query) {
				$select = $query_data['q'];
			} elseif (preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+(SELECT .+)$~s', trim($query_data['q']), $matches) != 0) {
				$is_select_query = true;
				$select = $matches[1];
			} elseif (preg_match('~^CREATE TEMPORARY TABLE .+?(SELECT .+)$~s', trim($query_data['q']), $matches) != 0) {
				$is_select_query = true;
				$select = $matches[1];
			}

			// Temporary tables created in earlier queries are not explainable.
			if ($is_select_query) {
				foreach (['log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages'] as $tmp) {
					if (strpos($select, $tmp) !== false) {
						$is_select_query = false;
						break;
					}
				}
			}

			echo '
		<div id="qq', $q, '" style="margin-bottom: 2ex;">
			<a', $is_select_query ? ' href="' . Config::$scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '"' : '', ' style="font-weight: bold; text-decoration: none;">
				', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', Utils::htmlspecialchars($query_data['q']))), '
			</a><br>';

			if (!empty($query_data['f']) && !empty($query_data['l'])) {
				echo sprintf(Lang::$txt['debug_query_in_line'], $query_data['f'], $query_data['l']);
			}

			if (isset($query_data['s'], $query_data['t'], Lang::$txt['debug_query_which_took_at'])) {
				echo sprintf(Lang::$txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8));
			} else {
				echo sprintf(Lang::$txt['debug_query_which_took'], round($query_data['t'], 8));
			}

			echo '
		</div>';

			// Explain the query.
			if ($query_id == $q && $is_select_query) {
				$result = Db::$db->query(
					'',
					'EXPLAIN ' . (Db::$db->title === POSTGRE_TITLE ? 'ANALYZE ' : '') . $select,
					[
					],
				);

				if ($result === false) {
					echo '
		<table>
			<tr><td>', Db::$db->error(), '</td></tr>
		</table>';

					continue;
				}

				echo '
		<table>';

				$row = Db::$db->fetch_assoc($result);

				echo '
			<tr>
				<th>' . implode('</th>
				<th>', array_keys($row)) . '</th>
			</tr>';

				Db::$db->data_seek($result, 0);

				while ($row = Db::$db->fetch_assoc($result)) {
					echo '
			<tr>
				<td>' . implode('</td>
				<td>', $row) . '</td>
			</tr>';
				}
				Db::$db->free_result($result);

				echo '
		</table>';
			}
		}

		echo '
		</div>
	</body>
</html>';

		Utils::obExit(false);
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ViewQuery::exportStatic')) {
	ViewQuery::exportStatic();
}

?>