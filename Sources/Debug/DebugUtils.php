<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Debug;

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Forum;
use SMF\Lang;
use SMF\Utils;

/**
 * This class provides utilities for debugging, including functions for
 * highlighting JSON and SQL strings, and managing debug context entries.
 *
 * To add debug context:
 * - Use the `addDebugContext` method.
 * - Parameters:
 *   - $lang_key (string): The language key under which to store the information.
 *   - $value (DebugContextEntry): An instance of DebugContextEntry containing
 *     all the necessary data.
 *
 * Example:
 * ```php
 * DebugUtils::addDebugContext('my_key', new DebugContextEntry([...]));
 * ```
 *
 * To add a debug source:
 * - Use the `addDebugSource` method.
 * - Parameters:
 *   - $lang_key (string): The language key identifying the debug context entry
 *     to update.
 *   - $value (string): The value to append to the `source` array of the debug
 *     context entry.
 *
 * Example:
 * ```php
 * DebugUtils::addDebugSource('my_key', 'New debug source');
 * ```
 */
class DebugUtils
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 */
	public static array $logged = [
		'templates' => [],
		'sub_templates' => [],
		'language_files' => [],
		'sheets' => [],
		'javascript' => [],
		'hooks' => [],
		'instances' => [],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Holds the debug context information.
	 */
	private static array $debug_context = [];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Trims excess indentation off a string.
	 *
	 * Example: If both the first and the third lines are indented thrice but
	 * the second one has four indents, the returned string will have three
	 * fewer indents, where only the second line has any indentation left.
	 *
	 * Ignores lines with no leading whitrespace.
	 *
	 * @param string $string Query with indentation to remove.
	 * @return string Query without excess indentation.
	 */
	public static function trimIndent(string $string): string
	{
		preg_match_all('/^[ \t]+(?=\S)/m', $string, $matches);
		$min_indent = PHP_INT_MAX;

		foreach ($matches[0] as $match) {
			$min_indent = min($min_indent, \strlen($match));
		}

		if ($min_indent != PHP_INT_MAX) {
			$string = preg_replace('/^[ \t]{' . $min_indent . '}/m', '', $string);
		}

		return $string;
	}

	/**
	 * Highlights a well-formed JSON string as HTML.
	 *
	 * @param string $string Well-formed JSON.
	 * @return string Highlighted JSON.
	 */
	public static function highlightJson(string $string): string
	{
		$colors = [
			'STRING' => '#567A0D',
			'NUMBER' => '#015493',
			'NULL' => '#B75301',
			'KEY' => '#803378',
			'COMMENT' => '#666F78',
		];

		return preg_replace_callback(
			'/"[^"]+"(?(?=\s*:)(*MARK:KEY)|(*MARK:STRING))|\b(?:true|false|null)\b(*MARK:NULL)|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?(*MARK:NUMBER)/',
			fn(array $matches): string => '<span style=\'color:' . $colors[$matches['MARK']] . '\'>' . $matches[0] . '</span>',
			str_replace(['<', '>', '&'], ['&lt;', '&gt;', '&amp;'], $string),
		) ?? $string;
	}

	/**
	 * Highlights a SQL string as HTML.
	 *
	 * @param string $string SQL.
	 * @return string Highlighted SQL.
	 */
	public static function highlightSql(string $string): string
	{
		$keyword_regex = '(?>HAVING|GROUP(?> BY|)|MATCH|JOIN|KEY(?>S|)|PR(?>OCEDURE|AGMA|I(?>MARY(?> KEY|)|NT))|A(?>UTO_INCREMENT|DD(?> CONSTRAINT|)|L(?>TER(?> (?>COLUMN|TABLE)|)|L)|N(?>[DY])|S(?>C|))|B(?>ACKUP DATABASE|INARY|LOB|E(?>TWEEN|GIN)|Y)|C(?>URRENT_(?>DATE|TIME)|REATE(?> (?>OR REPLACE VIEW|UNIQUE INDEX|PROCEDURE|DATABASE|INDEX|TABLE|VIEW)|)|AS(?>CADE|E)|H(?>ECK|AR)|O(?>NSTRAINT|LUMN))|D(?>ISTINCT|ROP(?> (?>INDEX|TABLE|VIEW|CO(?>NSTRAINT|LUMN)|D(?>ATABASE|EFAULT))|)|AT(?>ABASE|ETIME)|E(?>CIMAL|FAULT|LETE|SC))|E(?>ACH|LSE(?>IF|)|N(?>GINE|D)|X(?>ISTS|EC))|F(?>ULL OUTER JOIN|ALSE|ROM|OR(?>EIGN KEY|))|I(?>F(?>NULL|)|N(?>NER JOIN|SERT(?> INTO(?> SELECT|)|)|DEX(?>_LIST|)|T(?>E(?>RVAL|GER)|O)|)|S(?> N(?>OT NULL|ULL)|))|L(?>ONGTEXT|E(?>ADING|FT(?> JOIN|))|I(?>MIT|KE))|N(?>ULL|OT(?> NULL|))|O(?>VERLAPS|PTION|UT(?>ER(?> JOIN|)|)|N|R(?>DER(?> BY|)|))|R(?>OWNUM|IGHT(?> JOIN|)|E(?>FERENCES|PLACE))|S(?>HOW|E(?>LECT(?> (?>DISTINCT|INTO|TOP)|)|T))|T(?>ABLE|EXT|HEN|I(?>MESTAMP|NY(?>BLOB|TEXT|INT))|O(?>P|)|R(?>AILING|U(?>NCATE TABLE|E)))|U(?>PDATE|N(?>SIGNED|I(?>QUE|ON(?> ALL|))))|V(?>IEW|A(?>LUES|R(?>BINARY|CHAR)))|W(?>ITH|HE(?>RE|N)))';

		$colors = [
			'STRING' => '#567A0D',
			'NUMBER' => '#015493',
			'FUNCTION' => '#015493',
			'OPERATOR' => '#B75301',
			'KEY' => '#803378',
			'COMMENT' => '#666F78',
		];

		return preg_replace_callback(
			'/(["\'])?(?(1)(?:(?!\1).)*+\1(*MARK:STRING)|(?:\b' . $keyword_regex . '\b(*MARK:KEY)|--.*$|\/\*[\s\S]*?\*\/(*MARK:COMMENT)|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?(*MARK:NUMBER)|[!=%\/*-,;:<>](*MARK:OPERATOR)|\w+\((?:[^)(]+|(?R))*\)(*MARK:FUNCTION)))/',
			fn(array $matches): string => '<span style=\'color:' . $colors[$matches['MARK']] . '\'>' . $matches[0] . '</span>',
			$string,
		) ?? $string;
	}

	/**
	 * Appends a value to the source array of a specific debug context entry.
	 *
	 * This method is used to dynamically add individual debug information to an
	 * existing entry in the debug context.
	 *
	 * **Important:** Ensure that all `addDebugInfo` calls are made before
	 * setting the final debug context via `addDebugContext`.
	 *
	 * @param string $lang_key The language key identifying the debug context
	 *    entry to update. This key corresponds to a specific debug entry stored
	 *    in `self::$debug_context`.
	 * @param string $value The value to append to the `source` array of the
	 *    debug context entry.
	 * @param ?string $key Optional string key under which to store `$value`
	 *    inside `self::$logged[$lang_key]`. If not set, a numeric key will be
	 *    used. Default: null.
	 */
	public static function addDebugSource(string $lang_key, string $value, ?string $key = null): void
	{
		if (isset($key)) {
			self::$logged[$lang_key][$key] = $value;
		} else {
			self::$logged[$lang_key][] = $value;
		}
	}

	/**
	 * Adds information to the debug context.
	 *
	 * @param string $lang_key The language key under which to store the
	 *    information. This key corresponds to the language strings used for
	 *    rendering debug details.
	 * @param DebugContextEntry $value The debug context entry containing all
	 *    the necessary data.
	 */
	public static function addDebugContext(string $lang_key, DebugContextEntry $value): void
	{
		self::$debug_context[$lang_key] = $value;
	}

	/**
	 * Displays debugging information if the configuration allows it.
	 */
	public static function displayDebug(): void
	{
		if (self::isDebugEnabled() && Forum::getCurrentAction()?->canShowDebuggingInfo() !== false) {
			$_SESSION['view_queries'] ??= 0;
			$files = self::getIncludedFilesInfo();
			$warnings = self::collectQueryWarnings();

			self::createDebugContext($files);
			self::outputDebugInformation($warnings);
		}
	}

	/**
	 * Determines if debug information should be displayed.
	 *
	 * @return bool
	 */
	public static function isDebugEnabled(): bool
	{
		return isset(Config::$db_show_debug) && Config::$db_show_debug === true;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Retrieves information about included files and their sizes.
	 *
	 * @return array
	 */
	private static function getIncludedFilesInfo(): array
	{
		$files = get_included_files();
		$total_size = 0;

		foreach ($files as &$file) {
			if (file_exists($file)) {
				$total_size += filesize($file);
			}

			$file = strtr($file, '\\', '/');
		}

		return ['files' => $files, 'total_size' => $total_size];
	}

	/**
	 * Collects query warnings from the database cache.
	 *
	 * @return int
	 */
	private static function collectQueryWarnings(): int
	{
		$warnings = 0;

		if (!empty(Db::$cache)) {
			foreach (Db::$cache as $query_data) {
				if (!empty($query_data['w'])) {
					$warnings += \count($query_data['w']);
				}
			}

			$_SESSION['debug'] = &Db::$cache;
		}

		return $warnings;
	}

	/**
	 * Creates the debug context array using the DebugContextEntry class.
	 *
	 * @param array $filesInfo
	 */
	private static function createDebugContext(array $filesInfo): void
	{
		self::addDebugContext('debug_browser', new DebugContextEntry(
			source: array_reverse(array_keys(Utils::$context['browser'], true)),
			toggle: false,
			extra_lang_params: [
				'browser_body_id' => Utils::$context['browser_body_id'],
			],
		));

		if (Forum::getCurrentAction() !== null) {
			self::addDebugContext('debug_action', new DebugContextEntry(
				extra_lang_params: [
					'name' => '<code>' . \get_class(Forum::getCurrentAction()) . '</code>',
					'restrict_guests' => '<code>' . json_encode(Forum::getCurrentAction()->isRestrictedGuestAccessAllowed()) . '</code>',
					'can_log' => '<code>' . json_encode(Forum::getCurrentAction()->canBeLogged()) . '</code>',
					'is_simple' => '<code>' . json_encode(Forum::getCurrentAction()->isSimpleAction()) . '</code>',
				],
			));
		}

		self::addDebugContext('debug_templates', new DebugContextEntry(
			source: self::$logged['templates'],
		));

		self::addDebugContext('debug_subtemplates', new DebugContextEntry(
			source: self::$logged['sub_templates'],
		));

		self::addDebugContext('debug_language_files', new DebugContextEntry(
			source: self::$logged['language_files'],
		));

		self::addDebugContext('debug_stylesheets', new DebugContextEntry(
			source: self::$logged['sheets'],
		));

		self::addDebugContext('debug_hooks', new DebugContextEntry(
			source: self::$logged['hooks'],
		));

		self::addDebugContext('debug_files_included', new DebugContextEntry(
			source: $filesInfo['files'],
			extra_lang_params: [
				'size' => round($filesInfo['total_size'] / 1024),
			],
		));

		if (\function_exists('memory_get_peak_usage')) {
			self::addDebugContext('debug_memory_use', new DebugContextEntry(
				num: (int) ceil(memory_get_peak_usage() / 1024),
			));
		}

		if (isset($_SESSION['token'])) {
			self::addDebugContext('debug_tokens', new DebugContextEntry(
				source: array_keys($_SESSION['token']),
			));
		}

		if (!empty(CacheApi::$enable) && !empty(CacheApi::$hits)) {
			$entries = [];
			$total_t = 0;
			$total_s = 0;

			foreach (CacheApi::$hits as $cache_hit) {
				$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . Lang::getTxt(
					'debug_cache_seconds_bytes',
					[
						'seconds' => $cache_hit['t'],
						'bytes' => $cache_hit['s'],
					],
				);
				$total_t += $cache_hit['t'];
				$total_s += $cache_hit['s'];
			}

			self::addDebugContext(
				'debug_cache_hits',
				new DebugContextEntry(
					source: $entries,
					extra_lang_params: [
						'seconds_bytes_total' => Lang::getTxt(
							'debug_cache_seconds_bytes_total',
							[
								'seconds' => $total_t,
								'bytes' => $total_s,
							],
						),
					],
				),
			);
		}

		if (!empty(CacheApi::$misses)) {
			$missed_entries = [];

			foreach (CacheApi::$misses as $missed) {
				$missed_entries[] = $missed['d'] . ' ' . $missed['k'];
			}

			self::addDebugContext(
				'debug_cache_misses',
				new DebugContextEntry(
					source: $missed_entries,
				),
			);
		}
	}

	/**
	 * Outputs the debug information.
	 *
	 * @param int $warnings
	 */
	private static function outputDebugInformation(int $warnings): void
	{
		// Gotta have valid HTML ;).
		$temp = ob_get_contents();
		ob_clean();

		echo preg_replace('~</body>\s*</html>~', '', $temp), '
		<div class="smalltext" style="text-align: left; margin: 1ex;">';

		/* @var DebugContextEntry $info */
		foreach (self::$debug_context as $lang_key => $info) {
			if (\is_null($info)) {
				continue;
			}

			if ($info->extra_before) {
				echo $info->extra_before;
			} elseif (empty($info->source) || ($info->toggle === false)) {
				echo '<div>';
			}

			unset($additional_info);

			if (!empty($info->source)) {
				$additional_info = \sprintf(
					'%1$s' . implode('%2$s%3$s%1$s', $info->source) . '%2$s',
					$info->before_source,
					$info->after_source,
					$info->glue_sources,
				);

				if ($info->toggle !== false) {
					echo '<details';

					if ($info->open) {
						echo ' open';
					}
					echo '><summary>';
					$additional_info = '</summary>' . $additional_info . '</details>';
				}
			}

			echo Lang::getTxt(
				$lang_key,
				[
					'num' => $info->num ?? \count($info->source ?? []),
					'additional_info' => $additional_info ?? '',
				] + ($info->extra_lang_params ?? []),
			);

			if ($info->extra_after) {
				echo $info->extra_after;
			} elseif (empty($info->source) || ($info->toggle === false)) {
				echo '</div>';
			}
			echo "\n\t\t";
		}

		echo '
		<a href="', Config::$scripturl, '?action=viewquery" target="_blank" rel="noopener">', $warnings == 0 ? Lang::getTxt('debug_queries_used', [(int) Db::$count]) : Lang::getTxt('debug_queries_used_and_warnings', [(int) Db::$count, $warnings]), '</a><br>
		<br>
		<a href="' . Config::$scripturl . '?action=viewquery;sa=hide">', Lang::$txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>';

		self::outputQueryDebugInfo();

		echo '
	</div></body></html>';
	}

	/**
	 * Outputs the query debug information if available.
	 */
	private static function outputQueryDebugInfo(): void
	{
		if ($_SESSION['view_queries'] == 1 && !empty(Db::$cache)) {
			foreach (Db::$cache as $q => $query_data) {
				// Fix the indentation...
				$query_data['q'] = self::trimIndent($query_data['q']);

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

				if ($is_select_query) {
					echo '
		<a href="' . Config::$scripturl . '?action=viewquery;qq=' . $q . '#qq' . $q . '" target="_blank" rel="noopener" style="font-weight: bold; text-decoration: none;">';
				}

				echo '
			<pre style="tab-size: 2;">', $query_data['q'], '</pre>';

				if ($is_select_query) {
					echo '
		</a>';
				}

				if (!empty($query_data['f']) && !empty($query_data['l'])) {
					echo Lang::getTxt('debug_query_in_line', ['file' => $query_data['f'], 'line' => $query_data['l']]);
				}

				if (isset($query_data['s'], $query_data['t'], Lang::$txt['debug_query_which_took_at'])) {
					echo Lang::getTxt('debug_query_which_took_at', [round($query_data['t'], 8), round($query_data['s'], 8)]) . '<br>';
				} elseif (isset($query_data['t'])) {
					echo Lang::getTxt('debug_query_which_took', [round($query_data['t'], 8)]) . '<br>';
				}

				echo '
		<br>';
			}
		}
	}
}
