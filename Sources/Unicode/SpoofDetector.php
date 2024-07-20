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

namespace SMF\Unicode;

use SMF\Action\Admin\ACP;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

/**
 * Class SpoofDetector
 */
class SpoofDetector
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Get the "skeleton" of a string.
	 *
	 * See https://www.unicode.org/reports/tr39/#Confusable_Detection
	 *
	 * @param string $string The string
	 * @return string The skeleton string.
	 */
	public static function getSkeletonString(string $string): string
	{
		if (empty(Utils::$context['utf8'])) {
			return $string;
		}

		$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			return $string;
		}

		// Perform the steps for confusables detection according to UTS #39.
		// 1. Convert to NFD.
		$chars = Utf8String::decompose($chars, false);

		// 2. Replace confusable characters with their prototypes.
		require_once __DIR__ . '/Confusables.php';
		$substitutions = utf8_confusables();

		foreach ($chars as &$char) {
			$char = $substitutions[$char] ?? $char;
		}

		// 3. Concatenate and then reapply NFD.
		$string = (string) Utf8String::create(implode('', $chars))->normalize('d');

		return $string;
	}

	/**
	 * Get the resolved script set of a string.
	 *
	 * See http://www.unicode.org/reports/tr39/#Confusable_Detection
	 *
	 * @param string $string The string to analyze.
	 * @return array The resolved script set for $string.
	 */
	public static function resolveScriptSet(string $string): array
	{
		if (empty(Utils::$context['utf8'])) {
			return [];
		}

		$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			return [];
		}

		require_once __DIR__ . '/Confusables.php';

		$scripts_data = utf8_character_scripts();

		$script_set = array_fill_keys($chars, []);
		$ords = array_combine($chars, array_map('mb_ord', $chars));

		foreach ($scripts_data as $last_char => $scripts) {
			$last_ord = mb_ord($last_char);

			foreach ($ords as $char => &$ord) {
				if ($ord <= $last_ord) {
					$script_set[$char] = $scripts;
					$ord = 0;
				}
			}

			// Remove ones we've already done.
			$ords = array_filter($ords);

			if ($ords === []) {
				break;
			}
		}

		$resolved_script_set = [];
		$script_set_all = false;

		$i = 0;

		foreach ($script_set as $char => $scripts) {
			// Common and Inherited match all other scripts.
			if (array_intersect($scripts, ['Common', 'Inherited']) !== []) {
				$script_set_all = true;
				continue;
			}

			$script_set_all = false;

			if ($i++ === 0) {
				$resolved_script_set = $scripts;
			} else {
				$resolved_script_set = array_intersect($resolved_script_set, $scripts);
			}
		}

		if (empty($resolved_script_set) && $script_set_all) {
			$resolved_script_set = ['ALL'];
		}

		return $resolved_script_set;
	}

	/**
	 * Prevent spoof characters from bypassing the word censor.
	 *
	 * @param string $text The string being censored.
	 */
	public static function enhanceWordCensor(string $text): void
	{
		if (empty(Utils::$context['utf8']) || empty(Config::$modSettings['spoofdetector_censor'])) {
			return;
		}

		$vulgar_spoofs = [];
		$vulgar = explode("\n", Config::$modSettings['censor_vulgar']);
		$proper = explode("\n", Config::$modSettings['censor_proper']);

		if (!empty(Config::$modSettings['censorIgnoreCase'])) {
			$text = Utils::convertCase($text, 'fold');

			foreach ($vulgar as $i => $v) {
				$vulgar[$i] = Utils::convertCase($v, 'fold');
			}
		}

		$text_chars = preg_split('/(.)/su', $text, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		foreach ($text_chars as $text_char) {
			$text_skeletons[] = self::getSkeletonString($text_char);
		}

		foreach ($vulgar as $key => $word) {
			$word_skeleton = self::getSkeletonString($word);

			preg_match_all('~' . preg_quote($word_skeleton, '~') . '~u', implode('', $text_skeletons), $matches, PREG_OFFSET_CAPTURE);

			foreach ($matches as $match) {
				foreach ($match as $m) {
					$offset_reached = 0;

					$censored_word = '';
					$censored_word_skeleton = '';

					foreach ($text_skeletons as $char_num => $text_char_skeleton) {
						$offset_reached += mb_strlen($text_char_skeleton);

						if ($offset_reached <= $m[1]) {
							continue;
						}

						$censored_word_skeleton .= $text_char_skeleton;
						$censored_word .= $text_chars[$char_num];

						if ($censored_word_skeleton === $word_skeleton) {
							break;
						}
					}

					if ($censored_word !== $word) {
						$vulgar_spoofs[$key][] = $censored_word;
					}
				}
			}
		}

		if (!empty($vulgar_spoofs)) {
			foreach ($vulgar_spoofs as $key => $spoofwords) {
				foreach ($spoofwords as $spoofword) {
					// Skip if already defined. This allows overrides.
					if (in_array($spoofword, $vulgar) || in_array($spoofword, $proper)) {
						continue;
					}

					Config::$modSettings['censor_vulgar'] .= "\n" . $spoofword;
					Config::$modSettings['censor_proper'] .= "\n" . $proper[$key];
				}
			}
		}
	}

	/**
	 * Checks whether a name is a homograph for a reserved name.
	 *
	 * @param string $name The name to check.
	 * @param bool $fatal If true, die with a fatal error if a match is found.
	 *    Default: false.
	 * @return bool Whether $name is a homograph for a reserved name.
	 */
	public static function checkReservedName(string $name, bool $fatal = false): bool
	{
		$skeleton = self::getSkeletonString(html_entity_decode($name, ENT_QUOTES));

		// This will hold all the names that are similar to $name.
		$homograph_names = [];

		$reserved_names = explode("\n", Config::$modSettings['reserveNames']);

		// Check each name in the list...
		foreach ($reserved_names as $reserved) {
			if ($reserved == '') {
				continue;
			}

			// The admin might've used entities too, level the playing field.
			$reserved_check = html_entity_decode($reserved, ENT_QUOTES);

			// Case sensitive name?
			if (empty(Config::$modSettings['reserveCase'])) {
				$reserved_check = Utils::strtolower($reserved_check);
			}

			$reserved_skeleton = self::getSkeletonString($reserved_check);

			// Skeletons match.
			if ($skeleton == $reserved_skeleton) {
				$homograph_names[] = $reserved_check;
			}
			// Skeleton of the name includes skeleton of a reserved name.
			elseif (
				empty(Config::$modSettings['reserveWord'])
				&& Utils::entityStrpos($skeleton, $reserved_skeleton) !== false
			) {
				// First we need the skeletons of each character individually.
				$name_chars = preg_split('/(.)/su', $name, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

				foreach ($name_chars as $name_char) {
					$name_char_skeletons[] = self::getSkeletonString($name_char);
				}

				// Now find the reserved skeleton within the name skeleton.
				preg_match_all('~' . preg_quote($reserved_skeleton, '~') . '~u', $skeleton, $matches, PREG_OFFSET_CAPTURE);

				// Find the part of the name whose skeleton matches the reserved skeleton.
				foreach ($matches as $match) {
					foreach ($match as $m) {
						$offset_reached = 0;

						$partial_name = '';
						$partial_name_skeleton = '';

						foreach ($name_char_skeletons as $char_num => $name_char_skeleton) {
							$offset_reached += mb_strlen($name_char_skeleton);

							if ($offset_reached <= $m[1]) {
								continue;
							}

							$partial_name_skeleton .= $name_char_skeleton;
							$partial_name .= $name_chars[$char_num];

							if ($partial_name_skeleton === $reserved_skeleton) {
								break;
							}
						}

						if ($partial_name == '') {
							continue;
						}

						// If the partial is a homograph for a reserved name, reject.
						if (self::checkReservedName($partial_name, $fatal)) {
							return true;
						}
					}
				}
			}
		}

		if (!empty($homograph_names)) {
			return self::checkHomographNames($name, $homograph_names, $fatal);
		}

		return false;
	}

	/**
	 * Checks whether a name is a homograph for another existing member name.
	 *
	 * @param string $name The name to check.
	 * @param int $id_member ID of the member whose name this is.
	 * @param bool $fatal If true, die with a fatal error if a match is found.
	 *    Default: false.
	 * @return bool Whether $name is a homograph for an existing member name.
	 */
	public static function checkSimilarMemberName(string $name, int $id_member = 0, bool $fatal = false): bool
	{
		// This will hold all the names that are similar to $name.
		$homograph_names = [];

		// Find any similar names that belong to other members.
		$request = Db::$db->query(
			'',
			'SELECT real_name
			FROM {db_prefix}members
			WHERE spoofdetector_name = {string:skeleton}' . (empty($id_member) ? '' : '
				AND id_member != {int:current_member}') . '',
			[
				'current_member' => $id_member,
				'skeleton' => Utils::htmlspecialchars(self::getSkeletonString(html_entity_decode($name, ENT_QUOTES))),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$homograph_names[] = html_entity_decode($row['real_name'], ENT_QUOTES);
		}

		Db::$db->free_result($request);

		// Now check all the homograph names to see if this name should be rejected.
		if (!empty($homograph_names)) {
			return self::checkHomographNames($name, $homograph_names, $fatal);
		}

		return false;
	}

	/**
	 * Checks whether a name is a homograph for an existing membergroup name.
	 *
	 * @param string $name The name to check.
	 * @param bool $fatal If true, die with a fatal error if a match is found.
	 *    Default: false.
	 * @return bool Whether $name is a homograph for a membergroup name.
	 */
	public static function checkSimilarGroupName(string $name, bool $fatal = false): bool
	{
		$skeleton = self::getSkeletonString(html_entity_decode($name, ENT_QUOTES));

		// This will hold all the names that are similar to $name.
		$homograph_names = [];

		// Get all the membergroup names.
		$request = Db::$db->query(
			'',
			'SELECT group_name AS name
			FROM {db_prefix}membergroups',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($skeleton === self::getSkeletonString(html_entity_decode($row['name'], ENT_QUOTES))) {
				$homograph_names[] = $row['name'];
			}
		}
		Db::$db->free_result($request);

		// Now check all the homograph names to see if this name should be rejected.
		if (!empty($homograph_names)) {
			return self::checkHomographNames($name, $homograph_names, $fatal);
		}

		return false;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Checks whether $name is confusable with any string in $homograph_names.
	 *
	 * @param string $name The name to check.
	 * @param array $homograph_names Possibly similar names.
	 * @param bool $fatal If true, die with a fatal error on a match.
	 *    Default: false.
	 * @return bool Whether $name matched any string in $homograph_names.
	 */
	protected static function checkHomographNames(string $name, array $homograph_names, bool $fatal = false): bool
	{
		$name_script_set = self::resolveScriptSet($name);

		foreach ($homograph_names as $homograph_name) {
			$homograph_name_script_set = self::resolveScriptSet($homograph_name);

			// If they are mixed script confusables, reject.
			if (
				$name_script_set !== ['ALL']
				&& $homograph_name_script_set !== ['ALL']
				&& array_intersect($name_script_set, $homograph_name_script_set) === []
			) {
				if ($fatal) {
					ErrorHandler::fatalLang('username_reserved', 'password', [$homograph_name]);
				}

				return true;
			}

			// The names are same script confusables, so more analysis needed.
			// On the one hand, we don't want to allow both "ǉeto" and "ljeto",
			// or both "Bogden" (g = U+0067) and "Boɡden" (ɡ = U+0261).
			// But on the other hand, we want to allow both "Tom" and "Torn",
			// and both "lan" and "Ian".

			$name_kc = Utils::normalize($name, 'kc');
			$homograph_name_kc = Utils::normalize($homograph_name, 'kc');

			// If their NFKC forms are the same, reject.
			// This takes care of "ǉeto" vs. "ljeto".
			if ($name_kc === $homograph_name_kc) {
				if ($fatal) {
					ErrorHandler::fatalLang('username_reserved', 'password', [$homograph_name]);
				}

				return true;
			}

			require_once __DIR__ . '/Confusables.php';
			$regexes = utf8_regex_identifier_status();

			// If either string contains Identifier_Status=Restricted characters, reject.
			// This takes care of "Bogden" vs. "Boɡden".
			if (!preg_match('~^[' . $regexes['Allowed'] . ']*$~u', $name_kc) || !preg_match('~^[' . $regexes['Allowed'] . ']*$~u', $homograph_name_kc)) {
				if ($fatal) {
					ErrorHandler::fatalLang('username_reserved', 'password', [$homograph_name]);
				}

				return true;
			}

			// At this point we are down to strings like "Tom" vs. "Torn"
			// or "lan" vs. "Ian". So we allow by doing nothing further.
		}

		return false;
	}
}

?>