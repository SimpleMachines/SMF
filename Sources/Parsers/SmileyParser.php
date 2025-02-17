<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Parsers;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Parser;
use SMF\Utils;

/**
 * Converts smiley codes to HTML img tags.
 */
class SmileyParser extends Parser
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Regular expression to match smileys.
	 */
	protected string $smiley_preg_search;

	/**
	 * @var array
	 *
	 * Replacement values for smileys.
	 */
	protected array $smiley_preg_replacements;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Smiley data retrieved from the database.
	 */
	private static array $data;

	/**
	 * @var self
	 *
	 * A reference to an existing, reusable instance of this class.
	 */
	private static self $parser;

	/*****************
	 * Public methods.
	 *****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		if (self::$smiley_set !== 'none') {
			$data = self::loadData(self::$smiley_set);

			// The non-breaking-space is a complex thing...
			$non_breaking_space = self::$encoding === 'UTF-8' ? '\x{A0}' : '\xA0';

			$this->smiley_preg_replacements = [];
			$search_parts = [];
			$smileys_path = Utils::htmlspecialchars(self::$smileys_url . '/' . rawurlencode(self::$smiley_set) . '/');

			foreach ($data as $id => $smiley) {
				$special_chars = Utils::htmlspecialchars($smiley['code'], ENT_QUOTES);

				$smiley_code = '<img src="' . $smileys_path . Utils::htmlspecialchars($smiley['filename']) . '" alt="' . strtr($special_chars, [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']) . '" title="' . strtr(Utils::htmlspecialchars($smiley['description']), [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']) . '" class="smiley">';

				$this->smiley_preg_replacements[$smiley['code']] = $smiley_code;

				$search_parts[] = $smiley['code'];

				if ($smiley['code'] != $special_chars) {
					$this->smiley_preg_replacements[$special_chars] = $smiley_code;
					$search_parts[] = $special_chars;

					// Some 2.0 hex htmlchars are in there as 3 digits; allow for finding leading 0 or not
					$special_chars2 = preg_replace('/&#(\d{2});/', '&#0$1;', $special_chars);

					if ($special_chars2 != $special_chars) {
						$this->smiley_preg_replacements[$special_chars2] = $smiley_code;
						$search_parts[] = $special_chars2;
					}
				}
			}

			// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
			$this->smiley_preg_search = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|(?<![a-zA-Z0-9])\(|^)(' . Utils::buildRegex($search_parts, '~') . ')(?=[^[:alpha:]0-9]|$)~' . (self::$encoding === 'UTF-8' ? 'u' : '');

			// Maybe a mod wants to implement an alternative method for smileys
			// (e.g. emojis instead of images)
			IntegrationHook::call('integrate_smileys', [&$this->smiley_preg_search, &$this->smiley_preg_replacements]);
		}
	}

	/**
	 * Parse smileys in the passed string.
	 *
	 * The smiley parsing function which makes pretty faces appear :).
	 * If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
	 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * Caches the smileys from the database or array in memory.
	 *
	 * @param string $string The string to parse smileys in.
	 * @return string The string with smiley images inserted.
	 */
	public function parse(string $string): string
	{
		if (
			self::$smiley_set == 'none'
			|| !isset($this->smiley_preg_search)
			|| empty($this->smiley_preg_replacements)
			|| trim($string) == ''
		) {
			return $string;
		}

		// Don't parse smileys inside HTML or BBCode tags.
		$parts = preg_split('~(<[^>]*>|\[\/?' . BBCodeParser::load()->getAllTagsRegex() . '[^\]]*\])~u', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0; $i < count($parts); $i++) {
			if ($i % 2 === 0) {
				$parts[$i] = preg_replace_callback(
					$this->smiley_preg_search,
					fn($matches) => $this->smiley_preg_replacements[$matches[1]],
					$parts[$i],
				);
			}
		}

		return implode('', $parts);
	}

	/**
	 * Converts HTML img tags for smileys back into smiley text.
	 *
	 * @param string $string Text containing HTML.
	 * @return string The string with smiley images converted to text.
	 */
	public function unparse(string $string): string
	{
		$smiley_codes = array_map(fn($smiley) => $smiley['code'], self::loadData(''));

		return preg_replace_callback(
			'~(\h?)<img\b[^>]+alt="([^"]+)"[^>]+class="smiley"[^>]*>(\h?)~i',
			fn($match) => in_array(html_entity_decode($match[2]), $smiley_codes) ? $match[1] . html_entity_decode($match[2]) . $match[3] : $match[0],
			$string,
		);
	}

	/************************
	 * Public static methods.
	 ************************/

	/**
	 * Returns a reusable instance of this class.
	 *
	 * Using this method to get a SmileyParser instance saves memory by avoiding
	 * creating redundant instances.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$parser)) {
			self::$parser = new self();
		}

		return self::$parser;
	}

	/**
	 * Loads data for the requested smiley set from the database.
	 *
	 * @param string $set The name of the smiley set.
	 * @return array Data for all the smileys in the specified set.
	 */
	public static function loadData(string $set): array
	{
		if ($set === 'none') {
			return [];
		}

		if (isset(self::$data[$set])) {
			return self::$data[$set];
		}

		// Cache for longer when customized smiley codes aren't enabled
		$cache_time = !self::$custom_smileys_enabled ? 7200 : 480;
		$cache_key = 'parsing_smileys' . ($set !== '' ? '_' . $set : '');

		if (is_array($data = CacheApi::get($cache_key, $cache_time))) {
			self::$data[$set] = $data;

			return self::$data[$set];
		}

		// Load the smileys in reverse order by length so they don't get parsed incorrectly.
		self::$data[$set] = [];

		$request = Db::$db->query(
			'',
			'SELECT s.id_smiley, s.code, f.filename, s.description
			FROM {db_prefix}smileys AS s
				JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
			WHERE ' . ($set !== '' ? 'f.smiley_set = {string:smiley_set}' : '1=1') . (!self::$custom_smileys_enabled ? '
				AND s.code IN ({array_string:default_codes})' : '') . '
			ORDER BY LENGTH(s.code) DESC',
			[
				'default_codes' => ['>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)'],
				'smiley_set' => $set,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$data[$set][(int) $row['id_smiley']] = [
				'code' => $row['code'],
				'filename' => $row['filename'],
				'description' => !empty(Lang::$txt['icon_' . strtolower($row['description'])]) ? Lang::$txt['icon_' . strtolower($row['description'])] : $row['description'],
			];
		}

		Db::$db->free_result($request);

		CacheApi::put($cache_key, self::$data[$set], $cache_time);

		return self::$data[$set];
	}
}

?>