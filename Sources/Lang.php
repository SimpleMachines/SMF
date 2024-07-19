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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Localization\MessageFormatter;

/**
 * Handles the localizable strings shown in SMF's user interface.
 */
class Lang
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'prop_names' => [
			'txt' => 'txt',
			'tztxt' => 'tztxt',
			'editortxt' => 'editortxt',
			'helptxt' => 'helptxt',
			'txtBirthdayEmails' => 'txtBirthdayEmails',
			'forum_copyright' => 'forum_copyright',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Maps SMF 2.x language names to locales for SMF 3.0+.
	 * This is used to support upgrading from SMF 2.1 and below.
	 */
	public const LANG_TO_LOCALE = [
		'albanian' => 'sq_AL',
		// 001 is the region code the whole world, so this means modern standard Arabic.
		'arabic' => 'ar_001',
		'bulgarian' => 'bg_BG',
		'cambodian' => 'km_KH',
		'catalan' => 'ca_ES',
		'chinese-simplified' => 'zh_Hans',
		'chinese-traditional' => 'zh_Hant',
		'croatian' => 'hr_HR',
		'czech' => 'cs_CZ',
		// Since 'informal' is not a locale, we just map this to the 'root' locale.
		'czech_informal' => 'cs',
		'danish' => 'da_DK',
		'dutch' => 'nl_NL',
		'english' => 'en_US',
		'english_british' => 'en_GB',
		// english_pirate isn't a real language, so we use the _x_ to mark it as a 'private language'.
		'english_pirate' => 'en_x_pirate',
		'esperanto' => 'eo',
		'finnish' => 'fi_FI',
		'french' => 'fr_FR',
		'galician' => 'gl_ES',
		'german' => 'de_DE',
		// Since 'informal' is not a locale, we just map this to the 'root' locale.
		'german_informal' => 'de',
		'greek' => 'el_GR',
		'hebrew' => 'he_IL',
		'hungarian' => 'hu_HU',
		'indonesian' => 'id_ID',
		'italian' => 'it_IT',
		'japanese' => 'ja_JP',
		'lithuanian' => 'lt_LT',
		'macedonian' => 'mk_MK',
		'malay' => 'ms_MY',
		'norwegian' => 'nb_NO',
		'persian' => 'fa_IR',
		'polish' => 'pl_PL',
		'portuguese_brazilian' => 'pt_BR',
		'portuguese_pt' => 'pt_PT',
		'romanian' => 'ro_RO',
		'russian' => 'ru_RU',
		// Cyrl indicates Cyrillic script.
		'serbian_cyrillic' => 'sr_Cyrl',
		// Latn indicates Latin script.
		'serbian_latin' => 'sr_Latn',
		'slovak' => 'sk_SK',
		'slovenian' => 'sl_SI',
		'spanish_es' => 'es_ES',
		// 419 is the region code for Latin America.
		'spanish_latin' => 'es_419',
		'swedish' => 'sv_SE',
		'thai' => 'th_TH',
		'turkish' => 'tr_TR',
		'ukrainian' => 'uk_UA',
		'urdu' => 'ur_PK',
		'vietnamese' => 'vi_VN',
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var string
	 *
	 * Local copy of SMF\Config::$language
	 */
	public static string $default;

	/**
	 * @var string
	 *
	 * MessageFormat string to show the SMF copyright.
	 * The default value will be overwritten when a language is loaded.
	 */
	public static string $forum_copyright = '<a href="{scripturl}?action=credits" title="License" target="_blank" rel="noopener">{version} &copy; {year}</a>, <a href="https://www.simplemachines.org" title="Simple Machines" target="_blank" rel="noopener">Simple Machines</a>';

	/**
	 * @var array
	 *
	 * Array of localized strings for the UI.
	 */
	public static array $txt = [];

	/**
	 * @var array
	 *
	 * Array of localized strings for birthday emails.
	 */
	public static array $txtBirthdayEmails = [];

	/**
	 * @var array
	 *
	 * Array of localized strings for time zone "meta-zones".
	 */
	public static array $tztxt = [];

	/**
	 * @var array
	 *
	 * Array of localized strings for the editor UI.
	 */
	public static array $editortxt = [];

	/**
	 * @var array
	 *
	 * Array of localized strings for the admin help popup.
	 */
	public static array $helptxt = [];

	/**
	 * @var array
	 *
	 * Language file directories.
	 */
	public static array $dirs = [];

	/**
	 * @var string
	 *
	 * Decimal separator to use in Lang::numberFormat.
	 */
	public static string $decimal_separator;

	/**
	 * @var string
	 *
	 * Thousands separator to use in Lang::numberFormat.
	 */
	public static string $digit_group_separator;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Tracks which language files we have loaded.
	 */
	private static $already_loaded = [];

	/**
	 * @var array
	 *
	 * Tracks the value of $forum_copyright for different languages.
	 */
	private static array $localized_copyright = [];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Load a language file.
	 *
	 * Tries the current and default themes as well as the user and global languages.
	 *
	 * @param string $template_name The name of a template file.
	 * @param string $lang A specific language to load this file from.
	 * @param bool $fatal Whether to die with an error if it can't be loaded.
	 * @param bool $force_reload Whether to load the file again if it's already loaded.
	 * @return string The language actually loaded.
	 */
	public static function load(string $template_name, string $lang = '', bool $fatal = true, bool $force_reload = false): string
	{
		if (!isset(self::$default)) {
			self::$default = &Config::$language;
		}

		// Default to the user's language.
		if ($lang == '') {
			$lang = User::$me->language ?? self::$default;
		}

		if (empty(self::$dirs)) {
			self::addDirs();
		}

		// For each file open it up and write it out!
		foreach (explode('+', $template_name) as $template) {
			// Did we call the old index language file? Redirect.
			if ($template === 'index') {
				$template = 'General';
			}

			// Don't repeat this unnecessarily.
			if (!$force_reload && isset(self::$already_loaded[$template]) && self::$already_loaded[$template] == $lang) {
				continue;
			}

			$attempts = [];

			foreach (self::$dirs as $dir) {
				$attempts[] = [$dir, $template, $lang];
				$attempts[] = [$dir, $template, self::$default];
			}

			// Fall back to English if none of the preferred languages can be found.
			if (empty(Config::$modSettings['disable_language_fallback']) && !in_array('en_US', [$lang, self::$default])) {
				foreach (self::$dirs as $dir) {
					$attempts[] = [$dir, $template, 'en_US'];
				}
			}

			// Try to find the language file.
			$found = false;

			// Flip this around.
			$attempts = array_reverse($attempts);

			foreach ($attempts as $k => $file) {
				if (file_exists($file[0] . '/' . $file[2] . '/' . $file[1] . '.php')) {
					// Include it!
					// {DIR} / {locale} / {file} .php
					require $file[0] . '/' . $file[2] . '/' . $file[1] . '.php';

					// Note that we found it.
					$found = true;

					// Keep track of what we're up to, soldier.
					if (!empty(Config::$db_show_debug)) {
						Utils::$context['debug']['language_files'][implode('|', $file)] = (Config::$languagesdir == $file[0] ? basename($file[0]) : ltrim(str_replace(array_map('dirname', Theme::$current->settings['template_dirs']), '', $file[0]), '/')) . '/' . $file[2] . '/' . $file[1] . '.php';
					}

					// Load the strings into our properties.
					foreach (['txt', 'txtBirthdayEmails', 'tztxt', 'editortxt', 'helptxt'] as $var) {
						if (!isset(${$var})) {
							continue;
						}

						self::${$var} = array_merge(self::${$var}, ${$var});

						unset(${$var});
					}

					// Did this file define the $forum_copyright?
					if (!empty($forum_copyright)) {
						self::$localized_copyright[$file[2]] = $forum_copyright;

						self::$forum_copyright = self::$localized_copyright[$lang] ?? (self::$localized_copyright[self::$default] ?? (self::$localized_copyright['en_US'] ?? ''));

						unset($forum_copyright);
					}

					// setlocale is required for basename() & pathinfo() to work properly on the selected language
					if (!empty(self::$txt['lang_locale'])) {
						if (str_contains(self::$txt['lang_locale'], '.')) {
							$locale_variants = self::$txt['lang_locale'];
						} else {
							$locale_variants = array_unique(array_merge(
								!empty(Config::$modSettings['global_character_set']) ? [self::$txt['lang_locale'] . '.' . Config::$modSettings['global_character_set']] : [],
								!empty(Utils::$context['utf8']) ? [self::$txt['lang_locale'] . '.UTF-8', self::$txt['lang_locale'] . '.UTF8', self::$txt['lang_locale'] . '.utf-8', self::$txt['lang_locale'] . '.utf8'] : [],
								[self::$txt['lang_locale']],
							));
						}

						setlocale(LC_CTYPE, $locale_variants);
					}
				}
			}

			// Legacy language calls.
			/*
			 * Legacy language calls.
			 * Under normal conditions, we stop once we find it through the locale lookup.
			 * Modifications is a special case in which we allow it to be checked everywhere.
			 */
			if ((!$found || str_contains($template_name, 'Modifications') || str_contains($template_name, 'ThemeStrings')) && Config::$backward_compatibility) {
				$found = self::loadOld($attempts) || $found;
			}

			// That couldn't be found!  Log the error, but *try* to continue normally.
			if (!$found && $fatal) {
				ErrorHandler::log(self::formatText(self::$txt['theme_language_error'] ?? 'Unable to load the {filename} language file.', ['filename' => $lang . '/' . $template_name]), 'template');
				break;
			}

			// Copyright can't be empty.
			if (empty(self::$forum_copyright)) {
				$class_vars = get_class_vars(__CLASS__);
				self::$forum_copyright = $class_vars['forum_copyright'];
			}

			// For the sake of backward compatibility
			if (!empty(self::$txt['emails'])) {
				foreach (self::$txt['emails'] as $key => $value) {
					self::$txt[$key . '_subject'] = $value['subject'];
					self::$txt[$key . '_body'] = $value['body'];
				}
				self::$txt['emails'] = [];
			}

			// For sake of backward compatibility: $birthdayEmails is supposed to be
			// empty in a normal install. If it isn't it means the forum is using
			// something "old" (it may be the translation, it may be a mod) and this
			// code (like the piece above) takes care of converting it to the new format
			if (!empty($birthdayEmails)) {
				foreach ($birthdayEmails as $key => $value) {
					self::$txtBirthdayEmails[$key . '_subject'] = $value['subject'];
					self::$txtBirthdayEmails[$key . '_body'] = $value['body'];
					self::$txtBirthdayEmails[$key . '_author'] = $value['author'];
				}
				$birthdayEmails = [];
			}

			// Remember what we have loaded, and in which language.
			self::$already_loaded[$template] = $lang;
		}

		// Return the language actually loaded.
		return $lang;
	}

	/**
	 * Populates Lang::$dirs with paths to language directories.
	 *
	 * If $custom_dirs is empty, Lang::$dirs will be populated with the standard
	 * language directories in the current theme, the current theme's base theme
	 * (if applicable), and the default theme.
	 *
	 * If $custom_dirs is set to one or more directory paths, those paths will
	 * be prepended to Lang::$dirs.
	 *
	 * @param array|string $custom_dirs Optional custom directories to include.
	 */
	public static function addDirs(array|string $custom_dirs = []): void
	{
		// We only accept real directories.
		if (!empty($custom_dirs)) {
			$custom_dirs = array_filter(array_map('realpath', (array) $custom_dirs), 'is_dir');
		}

		if (!empty($custom_dirs)) {
			self::$dirs = array_merge($custom_dirs, self::$dirs);
		} else {
			self::$dirs[] = Config::$languagesdir;

			// Make sure we have Theme::$current->settings - if not we're in trouble and need to find it!
			if (empty(Theme::$current->settings['default_theme_dir'])) {
				Theme::loadEssential();
			}

			foreach (['theme_dir', 'base_theme_dir', 'default_theme_dir'] as $var) {
				if (isset(Theme::$current->settings[$var])) {
					self::$dirs[] = Theme::$current->settings[$var] . '/languages';
				}
			}

			// Don't count this as loading the theme.
			Utils::$context['theme_loaded'] = false;
		}

		self::$dirs = array_unique(self::$dirs);
	}

	/**
	 * Attempt to reload our known languages.
	 * It will try to choose only utf8 or non-utf8 languages.
	 *
	 * @param bool $use_cache Whether or not to use the cache
	 * @return array An array of information about available languages
	 */
	public static function get(bool $use_cache = true): array
	{
		// Either we don't use the cache, or its expired.
		if (!$use_cache || (Utils::$context['languages'] = CacheApi::get('known_languages', !empty(CacheApi::$enable) && CacheApi::$enable < 1 ? 86400 : 3600)) == null) {
			// If we don't have our theme information yet, let's get it.
			if (empty(Theme::$current->settings['default_theme_dir'])) {
				Theme::load(0, false);
			}

			// Default language directories to try.
			$language_directories = [
				Config::$languagesdir,
				Theme::$current->settings['default_theme_dir'] . '/languages',
			];

			if (!empty(Theme::$current->settings['actual_theme_dir']) && Theme::$current->settings['actual_theme_dir'] != Theme::$current->settings['default_theme_dir']) {
				$language_directories[] = Theme::$current->settings['actual_theme_dir'] . '/languages';
			}

			// We possibly have a base theme directory.
			if (!empty(Theme::$current->settings['base_theme_dir'])) {
				$language_directories[] = Theme::$current->settings['base_theme_dir'] . '/languages';
			}

			// Remove any duplicates.
			$language_directories = array_unique($language_directories);

			foreach ($language_directories as $language_dir) {
				// Can't look in here... doesn't exist!
				if (!file_exists($language_dir)) {
					continue;
				}

				$dir = dir($language_dir);

				while ($entry = $dir->read()) {
					// Languages are in a sub directory.
					if (!is_dir($language_dir . '/' . $entry) || !file_exists($language_dir . '/' . $entry . '/General.php')) {
						continue;
					}

					// Get the line we need.
					$fp = @fopen($language_dir . '/' . $entry . '/General.php', 'r');

					// Yay!
					if ($fp) {
						while (($line = fgets($fp)) !== false) {
							if (!str_contains($line, '$txt[\'native_name\']')) {
								continue;
							}

							preg_match('~\$txt\[\'native_name\'\]\s*=\s*\'([^\']+)\';~', $line, $matchNative);

							// Set the language's name.
							if (!empty($matchNative) && !empty($matchNative[1])) {
								// Don't mislabel the language if the translator missed this one.
								if ($entry !== 'en_US' && $matchNative[1] === 'English (US)') {
									break;
								}

								$langName = Utils::htmlspecialcharsDecode($matchNative[1]);

								break;
							}
						}

						fclose($fp);
					}

					// Build this language entry.
					Utils::$context['languages'][$entry] = [
						'name' => $langName ?? $entry,
						'selected' => false,
						'filename' => $entry,
						'location' => $language_dir . '/' . $entry . '/General.php',
					];
				}
				$dir->close();
			}

			// Let's cash in on this deal.
			if (!empty(CacheApi::$enable)) {
				CacheApi::put('known_languages', Utils::$context['languages'], !empty(CacheApi::$enable) && CacheApi::$enable < 1 ? 86400 : 3600);
			}
		}

		return Utils::$context['languages'];
	}

	/**
	 * High level method that retrieves language strings, inserts any arguments
	 * into them, and then returns the resulting finalized string.
	 *
	 * If no arguments are supplied in $args, this method simply returns the
	 * requested string.
	 *
	 * If arguments are supplied in $args, this method will insert them into the
	 * requested string. The method can handle argument substitution for both
	 * MessageFormat and sprintf format strings.
	 *
	 * @param string|array $txt_key The key of the Lang::$txt array that
	 *    contains the desired string. If this is an array, each item of the
	 *    array will be used as a sub-key to drill down into deeper levels of
	 *    the overall array.
	 * @param array $args Arguments to substitute into the Lang::$txt string.
	 * @param array $var Name of the array to search in. Default: 'txt'.
	 *    Other possible values are 'helptxt', 'editortxt', and 'tztxt'.
	 * @return string The string to display to the user.
	 */
	public static function getTxt(string|array $txt_key, array $args = [], string $var = 'txt'): string
	{
		// Validate $var.
		if (!in_array($var, ['txt', 'tztxt', 'editortxt', 'helptxt', 'txtBirthdayEmails'])) {
			throw new \ValueError();
		}

		// Don't waste time when getting a simple string.
		if ($args === [] && is_string($txt_key)) {
			return self::${$var}[$txt_key] ?? '';
		}

		// Trying to get something more complex...
		$txt_key = (array) $txt_key;

		$target = &self::${$var};

		// Drill down to the specified key.
		foreach ($txt_key as $key) {
			if (isset($target[$key])) {
				$target = &$target[$key];
			} else {
				// Can't do anything with a bad key.
				return '';
			}
		}

		if (!is_scalar($target)) {
			throw new \ValueError();
		}

		if (empty($args)) {
			return $target;
		}

		// Workaround for a CrowdIn limitation that won't allow translators to
		// change offset values in strings.
		if (
			count($txt_key) === 0
			&& in_array($txt_key[0], ['ordinal_last', 'ordinal_spellout_last'])
			&& isset(self::$txt['ordinal_last_offset'])
		) {
			$target = str_replace(
				'offset:0',
				'offset:' . self::$txt['ordinal_last_offset'],
				$target,
			);
		}

		return self::formatText($target, $args);
	}

	/**
	 * Replace all vulgar words with respective proper words. (substring or whole words..)
	 * What this function does:
	 *  - it censors the passed string.
	 *  - if the theme setting allow_no_censored is on, and the theme option
	 *	show_no_censored is enabled, does not censor, unless force is also set.
	 *  - it caches the list of censored words to reduce parsing.
	 *
	 * @param string &$text The text to censor
	 * @param bool $force Whether to censor the text regardless of settings
	 * @return string The censored text
	 */
	public static function censorText(string &$text, bool $force = false): string
	{
		static $censor_vulgar = null, $censor_proper;

		if ((!empty(Theme::$current->options['show_no_censored']) && !empty(Config::$modSettings['allow_no_censored']) && !$force) || empty(Config::$modSettings['censor_vulgar']) || !is_string($text) || trim($text) === '') {
			return $text;
		}

		IntegrationHook::call('integrate_word_censor', [&$text]);

		// Let SpoofDetector help us detect attempts to bypass the word censor.
		Unicode\SpoofDetector::enhanceWordCensor($text);

		// If they haven't yet been loaded, load them.
		if ($censor_vulgar == null) {
			$censor_vulgar = explode("\n", Config::$modSettings['censor_vulgar']);
			$censor_proper = explode("\n", Config::$modSettings['censor_proper']);

			$charset = empty(Config::$modSettings['global_character_set']) ? self::$txt['lang_character_set'] : Config::$modSettings['global_character_set'];

			// Quote them for use in regular expressions.
			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++) {
				// If a word is replaced with itself, just leave it as it is.
				// Why would the admin replace a word with itself, you ask?
				// If the spoof detector incorrectly censors an allowed word
				// because it happens to be visually confusable with a banned
				// word, the admin can create an entry to replace the allowed
				// word with itself in order to override the spoof detector.
				if ($censor_vulgar[$i] === $censor_proper[$i]) {
					$censor_proper[$i] = '$0';
				}

				$censor_vulgar[$i] = str_replace(['\\\\\\*', '\\*', '&', '\''], ['[*]', '[^\\s]*?', '&amp;', '&#039;'], preg_quote($censor_vulgar[$i], '/'));

				if (!empty(Config::$modSettings['censorWholeWord'])) {
					// Use the faster \b if we can, or something more complex if we can't
					$boundary_before = preg_match('/^\w/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?<![\p{L}\p{M}\p{N}_])' : '(?<!\w)');
					$boundary_after = preg_match('/\w$/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?![\p{L}\p{M}\p{N}_])' : '(?!\w)');
				} else {
					$boundary_before = $boundary_after = '';
				}

				$censor_vulgar[$i] = '/' . $boundary_before . $censor_vulgar[$i] . $boundary_after . '/' . (empty(Config::$modSettings['censorIgnoreCase']) ? '' : 'i') . ($charset === 'UTF-8' ? 'u' : '');
			}
		}

		// Censoring isn't so very complicated :P.
		foreach ($censor_vulgar as $i => $pattern) {
			$text = preg_replace_callback(
				$pattern,
				function ($matches) use ($censor_proper, $i) {
					// Special case to return the original word unchanged.
					if ($censor_proper[$i] === '$0') {
						return $matches[0];
					}

					// If the replacement contains any letters, try to match case.
					if (preg_match('/\p{L}/u', $censor_proper[$i])) {
						// If original was all uppercase, return uppercase.
						if (Utils::strtoupper($matches[0]) === $matches[0]) {
							return Utils::strtoupper($censor_proper[$i]);
						}

						// If original started with a capital letter, return with a capital letter.
						$first_vulgar_char = Utils::entitySubstr($matches[0], 0, 1);

						if (Utils::ucfirst($first_vulgar_char) === $first_vulgar_char) {
							return Utils::ucfirst($censor_proper[$i]);
						}
					}

					return $censor_proper[$i];
				},
				$text,
			);
		}

		return $text;
	}

	/**
	 * Replaces tokens in a string with values from Lang::$txt.
	 *
	 * Tokens take the form of '{key}', where 'key' is the key of some element
	 * in the Lang::$txt array.
	 *
	 * @param string $string The string in which to make replacements.
	 * @return string The updated string.
	 */
	public static function tokenTxtReplace(string $string = ''): string
	{
		return self::formatText($string, self::$txt);
	}

	/**
	 * Concatenates an array of strings into a grammatically correct sentence
	 * list.
	 *
	 * Uses formats defined in the language files to build the list according to
	 * the rules for the currently loaded language.
	 *
	 * @param array $list An array of strings to concatenate.
	 * @param string $type Either 'and', 'or', or 'xor'. Default: 'and'.
	 * @return string The localized sentence list.
	 */
	public static function sentenceList(array $list, string $type = 'and'): string
	{
		$list = array_map('strval', array_values($list));

		if (!isset(self::$txt['sentence_list_pattern'][$type])) {
			$type = 'and';
		}

		$separator = self::$txt['sentence_list_punct'] ?? ',';

		// Do we want the normal separator or the alternate?
		foreach ($list as $item) {
			if (str_contains($item, $separator)) {
				$type .= '_alt';
				break;
			}
		}

		// If we have a pattern for this exact number of items, use it.
		$args = array_merge(['list_pattern_part' => count($list)], $list);
		$sentence_list = self::formatText(self::$txt['sentence_list_pattern'][$type], $args);

		// Otherwise, build the list normally.
		if ($sentence_list === '') {
			// First insert the last two items into the "end" pattern.
			$args = array_merge(['list_pattern_part' => 'end'], array_splice($list, -2));
			$sentence_list = self::formatText(self::$txt['sentence_list_pattern'][$type], $args);

			// Then iteratively prepend items using the "middle" pattern.
			while (count($list) > 1) {
				$args = ['list_pattern_part' => 'middle', array_pop($list), $sentence_list];
				$sentence_list = self::formatText(self::$txt['sentence_list_pattern'][$type], $args);
			}

			// Finally, prepend the first item using the "start" pattern.
			$args = ['list_pattern_part' => 'start', array_pop($list), $sentence_list];
			$sentence_list = self::formatText(self::$txt['sentence_list_pattern'][$type], $args);
		}

		return $sentence_list;
	}

	/**
	 * Locale-aware replacement for number_format().
	 *
	 * Always uses the decimal separator and digit group separator for the
	 * current locale to format the number.
	 *
	 * @param int|float|string $number A number.
	 * @param int $decimals If set, will use the specified number of decimal
	 *    places. Otherwise it's automatically determined.
	 * @return string A formatted number
	 */
	public static function numberFormat(int|float|string $number, ?int $decimals = null): string
	{
		if (!is_numeric($number)) {
			throw new \ValueError();
		}

		if (is_string($number)) {
			$number = $number + 0;
		}

		self::$decimal_separator = self::$txt['decimal_separator'] ?? null;
		self::$digit_group_separator = self::$txt['digit_group_separator'] ?? null;

		// Not set for whatever reason?
		if (!isset(self::$decimal_separator)) {
			if (!empty(self::$txt['number_format']) && preg_match('~^1(\D*)234(\D*)(0*)$~', self::$txt['number_format'], $matches)) {
				self::$digit_group_separator = $matches[1];
				self::$decimal_separator = $matches[2];
			} else {
				self::$decimal_separator = '.';
				self::$digit_group_separator = ',';
			}
		}

		$skeleton = is_int($number) ? 'integer' : ':: .' . str_repeat('0', $decimals ?? 2);

		return MessageFormatter::formatMessage('{0, number, ' . $skeleton . '}', [$number]);
	}

	/**
	 * Formats a MessageFormat or sprintf string using the supplied arguments.
	 *
	 * @param string $message The MessageFormat or sprintf string.
	 * @param array $args Arguments to use in the string.
	 * @return string The formatted string.
	 */
	public static function formatText(string $message, array $args = []): string
	{
		if ($args === []) {
			return $message;
		}

		// First try formatting it as a MessageFormat string.
		// If it is not a MessageFormat string, nothing will be changed.
		$final = MessageFormatter::formatMessage($message, $args);

		// If nothing changed, try formatting it as a sprintf string.
		if ($final === $message) {
			$final = vsprintf($message, $args);
		}

		return $final;
	}

	/**
	 * Given an SMF 2.x language name, returns the locale code for SMF 3.0+.
	 *
	 * This is used to support upgrading from SMF 2.1 and below.
	 * This is also used to support compatibility for customizations.
	 *
	 * If $lang is already a supported locale, it will simply be returned.
	 *
	 * Languages can map to:
	 * - null: No translation. Language is removed and no upgrade is possible.
	 * - A locale: The locale code for the language.
	 *
	 * @param string $lang Language name
	 * @return ?string Locale is returned if found, null otherwise.
	 */
	public static function getLocaleFromLanguageName(string $lang): ?string
	{
		// Already a locale?
		// Note: we can't just do in_array($lang, self::LANG_TO_LOCALE) because
		// new language packs added after 2.1 won't be in self::LANG_TO_LOCALE.
		if (strlen($lang) === 2 || substr($lang, 2, 1) === '_') {
			return $lang;
		}

		return self::LANG_TO_LOCALE[$lang] ?? null;
	}

	/**
	 * A backward compatibility method for loading language files with old names.
	 * This is used to support backward compatibility with mods from SMF 2.1.
	 * Do not rely on this method to exist in future versions!
	 *
	 * @deprecated 3.0 Only used to support compatibility with old name formats.
	 * @param array $attempts The attempts to be made; see self::load().
	 * @return bool Whether we loaded anything or not.
	 */
	public static function loadOld(array $attempts): bool
	{
		if (empty($attempts)) {
			return false;
		}

		$locale_to_lang = array_flip(self::LANG_TO_LOCALE);

		$found = false;

		/**
		 * $file = [
		 *    0 => Directory
		 *    1 => File
		 *    2 => Locale
		 * ]
		 */
		foreach ($attempts as $k => $file) {
			$oldLanguage = $locale_to_lang[$file[2]] ?? false;

			if ($oldLanguage !== false && file_exists($file[0] . '/' . $file[1] . '.' . $oldLanguage . '.php')) {
				require $file[0] . '/' . $file[1] . '.' . $oldLanguage . '.php';

				// Note that we found it.
				$found = true;

				// Load the strings into our properties.
				foreach (['txt', 'txtBirthdayEmails', 'tztxt', 'editortxt', 'helptxt'] as $var) {
					if (!isset(${$var})) {
						continue;
					}

					self::${$var} = array_merge(self::${$var}, ${$var});

					unset(${$var});
				}

				// Keep track of what we're up to, soldier.
				if (!empty(Config::$db_show_debug)) {
					Utils::$context['debug']['language_files'][implode('|', $file)] = (Config::$languagesdir == $file[0] ? basename($file[0]) : ltrim(str_replace(array_map('dirname', Theme::$current->settings['template_dirs']), '', $file[0]), '/')) . '/' . $file[1] . '.' . $oldLanguage . '.php';
				}
			}
		}

		return $found;
	}
}

// Export properties to global namespace for backward compatibility.
if (is_callable([Lang::class, 'exportStatic'])) {
	Lang::exportStatic();
}

?>