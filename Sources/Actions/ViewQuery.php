<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\DebugUtils;
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
	use ActionTrait;

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

			if (str_contains($_SESSION['old_url'], 'action=viewquery')) {
				Utils::redirectexit();
			} else {
				Utils::redirectexit($_SESSION['old_url']);
			}
		}

		IntegrationHook::call('integrate_egg_nog');

		$query_id = (int) ($_REQUEST['qq'] ?? 0);

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
			$query_data['q'] = DebugUtils::trimIndent($query_data['q']);

			// Make the filenames look a bit better.
			if (isset($query_data['f'])) {
				$query_data['f'] = preg_replace('/^' . preg_quote(Config::$boarddir, '/') . '/', '...', strtr($query_data['f'], '\\', '/'));
			}

			$is_select_query = preg_match('/^\s*(?:SELECT|WITH)/i', $query_data['q']) != 0;

			if ($is_select_query) {
				$select = $query_data['q'];
			} elseif (preg_match('/^\s*(?:INSERT(?: IGNORE)? INTO \w+|CREATE TEMPORARY TABLE .+?)\KSELECT .+$/is', trim($query_data['q']), $matches) != 0) {
				$is_select_query = true;
				$select = $matches[0];
			}

			// Temporary tables created in earlier queries are not explainable.
			if ($is_select_query && preg_match('/log_topics_unread|topics_posted_in|tmp_log_search_(?:topics|messages)/i', $select) != 0) {
				$is_select_query = false;
			}

			echo '
		<div id="qq', $q, '" style="margin-bottom: 2ex;">';

			if ($is_select_query) {
				echo '
			<a href="' . Config::$scripturl . '?action=viewquery;qq=' . $q . '#qq' . $q . '" style="font-weight: bold; text-decoration: none;">';
			}

			echo '
				<pre style="tab-size: 2;">', DebugUtils::highlightSql($query_data['q']), '</pre>';

			if ($is_select_query) {
				echo '
			</a>';
			}

			if (!empty($query_data['f']) && !empty($query_data['l'])) {
				echo Lang::getTxt('debug_query_in_line', ['file' => $query_data['f'], 'line' => $query_data['l']]);
			}

			if (isset($query_data['s'], $query_data['t'], Lang::$txt['debug_query_which_took_at'])) {
				echo Lang::getTxt('debug_query_which_took_at', [round($query_data['t'], 8), round($query_data['s'], 8)]);
			} else {
				echo Lang::getTxt('debug_query_which_took', [round($query_data['t'], 8)]);
			}

			echo '
		</div>';

			// Explain the query.
			if ($query_id == $q && $is_select_query) {
				$result = Db::$db->query('', 'EXPLAIN ' . $select);

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

			$vendor = Db::$db->get_vendor();

			if ($vendor == 'MariaDB') {
				$result = Db::$db->query('', 'ANALYZE FORMAT=JSON ' . $select);
			} else {
				$result = Db::$db->query(
					'',
					'EXPLAIN ' . ($vendor == 'PostgreSQL' ? '(ANALYZE, FORMAT JSON) ' : 'ANALYZE FORMAT=JSON ') . $select,
				);
			}

			echo '
		<pre>' . DebugUtils::highlightJson(Db::$db->fetch_row($result)[0]) . '</pre>';
			}
		}

		echo '
		</div>
	</body>
</html>';

		Utils::obExit(false);
	}
}

?>