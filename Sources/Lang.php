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

namespace SMF;

use SMF\Cache\CacheApi;

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
		'func_names' => [
			'load' => 'loadLanguage',
			'get' => 'getLanguages',
			'censorText' => 'censorText',
			'tokenTxtReplace' => 'tokenTxtReplace',
			'sentenceList' => 'sentence_list',
			'numberFormat' => 'comma_format',
		],
		'prop_names' => [
			'txt' => 'txt',
			'tztxt' => 'tztxt',
			'editortxt' => 'editortxt',
			'helptxt' => 'helptxt',
			'txtBirthdayEmails' => 'txtBirthdayEmails',
			'forum_copyright' => 'forum_copyright',
		],
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
	 * sprintf format string to show the SMF copyright.
	 * The default value will be overwritten when a language is loaded.
	 */
	public static string $forum_copyright = '<a href="%3$s?action=credits" title="License" target="_blank" rel="noopener">%1$s &copy; %2$s</a>, <a href="https://www.simplemachines.org" title="Simple Machines" target="_blank" rel="noopener">Simple Machines</a>';

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
	 * @var int
	 *
	 * Default number of decimal places to use for floats in Lang::numberFormat.
	 */
	public static int $decimals;

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
	public static string $thousands_separator;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Tracks which langauge files we have loaded.
	 */
	private static $already_loaded = [];

	/**
	 * @var array
	 *
	 * Tracks the value of $forum_copyright for different languages.
	 */
	private static $localized_copyright = [];

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
	public static function load(string $template_name, string $lang = '', bool $fatal = true, bool $force_reload = false)
	{
		if (!isset(self::$default)) {
			self::$default = &Config::$language;
		}

		// Default to the user's language.
		if ($lang == '') {
			$lang = User::$me->language ?? self::$default;
		}

		// Don't repeat this unnecessarily.
		if (!$force_reload && isset(self::$already_loaded[$template_name]) && self::$already_loaded[$template_name] == $lang) {
			return $lang;
		}

		if (empty(self::$dirs)) {
			self::addDirs();
		}

		// For each file open it up and write it out!
		foreach (explode('+', $template_name) as $template) {
			$attempts = [];

			foreach (self::$dirs as $dir) {
				$attempts[] = [$dir, $template, $lang];
				$attempts[] = [$dir, $template, self::$default];
			}

			// Fall back to English if none of the preferred languages can be found.
			if (empty(Config::$modSettings['disable_language_fallback']) && !in_array('english', [$lang, self::$default])) {
				foreach (self::$dirs as $dir) {
					$attempts[] = [$dir, $template, 'english'];
				}
			}

			// Try to find the language file.
			$found = false;

			foreach ($attempts as $k => $file) {
				if (file_exists($file[0] . '/' . $file[1] . '.' . $file[2] . '.php')) {
					// Include it!
					require $file[0] . '/' . $file[1] . '.' . $file[2] . '.php';

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

					// Did this file define the $forum_copyright?
					if (isset($forum_copyright)) {
						self::$localized_copyright[$file[2]] = $forum_copyright;

						self::$forum_copyright = self::$localized_copyright[$lang] ?? (self::$localized_copyright[self::$default] ?? (self::$localized_copyright['english'] ?? ''));

						unset($forum_copyright);
					}

					// setlocale is required for basename() & pathinfo() to work properly on the selected language
					if (!empty(self::$txt['lang_locale'])) {
						if (strpos(self::$txt['lang_locale'], '.') !== false) {
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

					break;
				}
			}

			// That couldn't be found!  Log the error, but *try* to continue normally.
			if (!$found && $fatal) {
				ErrorHandler::log(sprintf(self::$txt['theme_language_error'] ?? 'Unable to load the \'%1$s\' language file.', $template_name . '.' . $lang, 'template'));
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
		}

		// Keep track of what we're up to, soldier.
		if (!empty(Config::$db_show_debug)) {
			Utils::$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . basename(Theme::$current->settings['theme_url'] ?? 'unknown') . ')';
		}

		// Remember what we have loaded, and in which language.
		self::$already_loaded[$template_name] = $lang;

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
	public static function addDirs($custom_dirs = [])
	{
		// We only accept real directories.
		if (!empty($custom_dirs)) {
			$custom_dirs = array_filter(array_map('realpath', (array) $custom_dirs), 'is_dir');
		}

		if (!empty($custom_dirs)) {
			self::$dirs = array_merge($custom_dirs, self::$dirs);
		} else {
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
	public static function get($use_cache = true)
	{
		// Either we don't use the cache, or its expired.
		if (!$use_cache || (Utils::$context['languages'] = CacheApi::get('known_languages', !empty(CacheApi::$enable) && CacheApi::$enable < 1 ? 86400 : 3600)) == null) {
			// If we don't have our theme information yet, let's get it.
			if (empty(Theme::$current->settings['default_theme_dir'])) {
				Theme::load(0, false);
			}

			// Default language directories to try.
			$language_directories = [
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
					// Look for the index language file... For good measure skip any "index.language-utf8.php" files
					if (!preg_match('~^index\.((?:.(?!-utf8))+)\.php$~', $entry, $matches)) {
						continue;
					}

					$langName = Utils::ucwords(strtr($matches[1], ['_' => ' ']));

					if (($spos = strpos($langName, ' ')) !== false) {
						$langName = substr($langName, 0, ++$spos) . '(' . substr($langName, $spos) . ')';
					}

					// Get the line we need.
					$fp = @fopen($language_dir . '/' . $entry, 'r');

					// Yay!
					if ($fp) {
						while (($line = fgets($fp)) !== false) {
							if (strpos($line, '$txt[\'native_name\']') === false) {
								continue;
							}

							preg_match('~\$txt\[\'native_name\'\]\s*=\s*\'([^\']+)\';~', $line, $matchNative);

							// Set the language's name.
							if (!empty($matchNative) && !empty($matchNative[1])) {
								// Don't mislabel the language if the translator missed this one.
								if ($langName !== 'English' && $matchNative[1] === 'English') {
									break;
								}

								$langName = Utils::htmlspecialcharsDecode($matchNative[1]);

								break;
							}
						}

						fclose($fp);
					}

					// Build this language entry.
					Utils::$context['languages'][$matches[1]] = [
						'name' => $langName,
						'selected' => false,
						'filename' => $matches[1],
						'location' => $language_dir . '/index.' . $matches[1] . '.php',
					];
				}
				$dir->close();
			}

			// Avoid confusion when we have more than one English variant installed.
			// Honestly, our default English version should always have been called "English (US)"
			if (substr_count(implode(' ', array_keys(Utils::$context['languages'])), 'english') > 1 && Utils::$context['languages']['english']['name'] === 'English') {
				Utils::$context['languages']['english']['name'] = 'English (US)';
			}

			// Let's cash in on this deal.
			if (!empty(CacheApi::$enable)) {
				CacheApi::put('known_languages', Utils::$context['languages'], !empty(CacheApi::$enable) && CacheApi::$enable < 1 ? 86400 : 3600);
			}
		}

		return Utils::$context['languages'];
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
	public static function censorText(&$text, $force = false)
	{
		static $censor_vulgar = null, $censor_proper;

		if ((!empty(Theme::$current->options['show_no_censored']) && !empty(Config::$modSettings['allow_no_censored']) && !$force) || empty(Config::$modSettings['censor_vulgar']) || !is_string($text) || trim($text) === '') {
			return $text;
		}

		IntegrationHook::call('integrate_word_censor', [&$text]);

		// If they haven't yet been loaded, load them.
		if ($censor_vulgar == null) {
			$censor_vulgar = explode("\n", Config::$modSettings['censor_vulgar']);
			$censor_proper = explode("\n", Config::$modSettings['censor_proper']);

			// Quote them for use in regular expressions.
			if (!empty(Config::$modSettings['censorWholeWord'])) {
				$charset = empty(Config::$modSettings['global_character_set']) ? self::$txt['lang_character_set'] : Config::$modSettings['global_character_set'];

				for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++) {
					$censor_vulgar[$i] = str_replace(['\\\\\\*', '\\*', '&', '\''], ['[*]', '[^\\s]*?', '&amp;', '&#039;'], preg_quote($censor_vulgar[$i], '/'));

					// Use the faster \b if we can, or something more complex if we can't
					$boundary_before = preg_match('/^\w/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?<![\p{L}\p{M}\p{N}_])' : '(?<!\w)');
					$boundary_after = preg_match('/\w$/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?![\p{L}\p{M}\p{N}_])' : '(?!\w)');

					$censor_vulgar[$i] = '/' . $boundary_before . $censor_vulgar[$i] . $boundary_after . '/' . (empty(Config::$modSettings['censorIgnoreCase']) ? '' : 'i') . ($charset === 'UTF-8' ? 'u' : '');
				}
			}
		}

		// Censoring isn't so very complicated :P.
		if (empty(Config::$modSettings['censorWholeWord'])) {
			$func = !empty(Config::$modSettings['censorIgnoreCase']) ? 'str_ireplace' : 'str_replace';
			$text = $func($censor_vulgar, $censor_proper, $text);
		} else {
			$text = preg_replace($censor_vulgar, $censor_proper, $text);
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
		if (empty($string)) {
			return '';
		}

		$translatable_tokens = preg_match_all('/{(.*?)}/', $string, $matches);
		$toFind = [];
		$replaceWith = [];

		if (!empty($matches[1])) {
			foreach ($matches[1] as $token) {
				$toFind[] = '{' . $token . '}';
				$replaceWith[] = self::$txt[$token] ?? $token;
			}
		}

		return str_replace($toFind, $replaceWith, $string);
	}

	/**
	 * Concatenates an array of strings into a grammatically correct sentence
	 * list.
	 *
	 * Uses formats defined in the language files to build the list according to
	 * the rules for the currently loaded language.
	 *
	 * @param array $list An array of strings to concatenate.
	 * @return string The localized sentence list.
	 */
	public static function sentenceList(array $list): string
	{
		// Make sure the bare necessities are defined.
		if (empty(Lang::$txt['sentence_list_format']['n'])) {
			Lang::$txt['sentence_list_format']['n'] = '{series}';
		}

		if (!isset(Lang::$txt['sentence_list_separator'])) {
			Lang::$txt['sentence_list_separator'] = ', ';
		}

		if (!isset(Lang::$txt['sentence_list_separator_alt'])) {
			Lang::$txt['sentence_list_separator_alt'] = '; ';
		}

		// Which format should we use?
		$format = Lang::$txt['sentence_list_format'][count($list)] ?? Lang::$txt['sentence_list_format']['n'];

		// Do we want the normal separator or the alternate?
		$separator = Lang::$txt['sentence_list_separator'];

		foreach ($list as $item) {
			if (strpos($item, $separator) !== false) {
				$separator = Lang::$txt['sentence_list_separator_alt'];
				$format = strtr($format, trim(Lang::$txt['sentence_list_separator']), trim($separator));
				break;
			}
		}

		$replacements = [];

		// Special handling for the last items on the list.
		$i = 0;

		while (strpos($format, '{' . --$i . '}') !== false) {
			$replacements['{' . $i . '}'] = array_pop($list);
		}

		// Special handling for the first items on the list.
		$i = 0;

		while (strpos($format, '{' . ++$i . '}') !== false) {
			$replacements['{' . $i . '}'] = array_shift($list);
		}

		// Whatever is left.
		$replacements['{series}'] = implode($separator, $list);

		// Do the deed.
		return strtr($format, $replacements);
	}

	/**
	 * Wrapper for number_format() that uses Lang::$txt['number_format'] to
	 * figure out the parameters to pass to number_format().
	 *
	 * @param int|float $number A number.
	 * @param int $decimals If set, will use the specified number of decimal
	 *    places. Otherwise it's automatically determined.
	 * @return string A formatted number
	 */
	public static function numberFormat(int|float $number, ?int $decimals = null): string
	{
		// Cache these values...
		if (!isset(self::$decimal_separator)) {
			// Not set for whatever reason?
			if (empty(Lang::$txt['number_format']) || preg_match('~^1(\D*)234(\D*)(0*)$~', Lang::$txt['number_format'], $matches) != 1) {
				return (string) $number;
			}

			// Cache these each load...
			self::$thousands_separator = $matches[1];
			self::$decimal_separator = $matches[2];
			self::$decimals = strlen($matches[3]);
		}

		// Format the string with our friend, number_format.
		return number_format(
			$number,
			(float) $number === $number ? ($decimals ?? self::$decimals) : 0,
			self::$decimal_separator,
			self::$thousands_separator,
		);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Lang::exportStatic')) {
	Lang::exportStatic();
}

?>