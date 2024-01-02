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

namespace SMF\Unicode;

use SMF\BackwardCompatibility;

use SMF\Config;
use SMF\Lang;
use SMF\User;

/**
 * A class for manipulating UTF-8 strings.
 *
 * This class is intended to be called from the string manipulation methods in
 * SMF\Utils. It is generally better (and easier) to use those methods rather
 * than creating instances of this class directly.
 */
class Utf8String implements \Stringable
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'decompose' => 'utf8_decompose',
			'compose' => 'utf8_compose',
			'utf8_strtolower' => 'utf8_strtolower',
			'utf8_strtoupper' => 'utf8_strtoupper',
			'utf8_casefold' => 'utf8_casefold',
			'utf8_convert_case' => 'utf8_convert_case',
			'utf8_normalize_d' => 'utf8_normalize_d',
			'utf8_normalize_kd' => 'utf8_normalize_kd',
			'utf8_normalize_c' => 'utf8_normalize_c',
			'utf8_normalize_kc' => 'utf8_normalize_kc',
			'utf8_normalize_kc_casefold' => 'utf8_normalize_kc_casefold',
			'utf8_is_normalized' => 'utf8_is_normalized',
			'utf8_sanitize_invisibles' => 'utf8_sanitize_invisibles',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The scalar string.
	 */
	public string $string;

	/**
	 * @var string
	 *
	 * The two-character locale code for the language of this string.
	 * E.g. 'de', 'en', 'fr', 'zh', etc.
	 */
	public string $language;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var bool
	 *
	 * Whether we can use the intl extension's Normalizer class.
	 */
	protected static bool $use_intl_normalizer;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $string The string.
	 * @param string $language Two-character locale code for the language of
	 *    this string. If null, assumes the language currently in use by SMF
	 *    (meaning the user's language, falling back to the forum's default).
	 */
	public function __construct(string $string, ?string $language = null)
	{
		$this->string = $string;

		$this->language = substr($language ?? User::$me->language ?? Lang::$default ?? Config::$language ?? Lang::$txt['lang_locale'] ?? '', 0, 2);

		// Can we use the intl extension's Normalizer class?
		if (!isset(self::$use_intl_normalizer)) {
			require_once __DIR__ . '/Metadata.php';

			self::$use_intl_normalizer = extension_loaded('intl') && version_compare(implode('.', \IntlChar::getUnicodeVersion()), SMF_UNICODE_VERSION, '>=');
		}
	}

	/**
	 * Returns $this->string.
	 *
	 * @return string The string.
	 */
	public function __toString(): string
	{
		return $this->string;
	}

	/**
	 * Converts the case of this UTF-8 string.
	 *
	 * Updates the value of $this->string. On failure, $this->string will be
	 * unset.
	 *
	 * The supported cases are as follows:
	 *
	 *  - upper:   Converts all letters to their upper case version.
	 *  - lower:   Converts all letters to their lower case version.
	 *  - fold:    Converts all letters to their default case version. For most
	 *             languages that means lower case, but not all.
	 *  - title:   Capitalizes the first letter of each word, and converts all
	 *             other letters to lower case.
	 *  - ucwords: Like title case, except that letters that do not start a word
	 *             are left as they are.
	 *  - ucfirst: Like ucwords, except that it acts only on the first word in
	 *             the string.
	 *
	 * Special conditional casing rules are applied for letters in certain
	 * languages that need them. These conditional casing rules are defined in
	 * the Unicode standard and implemented according to those instructions.
	 *
	 * It is also worth noting that for certain letters in some languages, the
	 * capitalized form of the letter used for upper case may differ from the
	 * capitalized form use for title case. For example, the lower case
	 * character 'ǳ' becomes 'Ǳ' in upper case, but 'ǲ' in title case. All such
	 * special title case rules are specified in the Unicode data files and are
	 * applied automatically.
	 *
	 * @param string $case One of 'upper', 'lower', 'fold', 'title', 'ucwords',
	 *    or 'ucfirst'.
	 * @param bool $simple If true, use simple maps instead of full maps.
	 *    Default: false.
	 * @return object A reference to this object for method chaining.
	 */
	public function convertCase(string $case, bool $simple = false): object
	{
		// The main case conversion logic
		if (in_array($case, ['upper', 'lower', 'fold'])) {
			$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

			if ($chars === false) {
				unset($this->string);

				return $this;
			}

			switch ($case) {
				case 'upper':
					require_once __DIR__ . '/CaseUpper.php';
					$substitutions = $simple ? utf8_strtoupper_simple_maps() : utf8_strtoupper_maps();

					// Turkish & Azeri conditional casing, part 1.
					if (in_array($this->language, ['tr', 'az'])) {
						$substitutions['i'] = 'İ';
					}

					break;

				case 'lower':
					require_once __DIR__ . '/CaseLower.php';
					$substitutions = $simple ? utf8_strtolower_simple_maps() : utf8_strtolower_maps();

					// Turkish & Azeri conditional casing, part 1.
					if (in_array($this->language, ['tr', 'az'])) {
						$substitutions['İ'] = 'i';
						$substitutions['I' . "\xCC\x87"] = 'i';
						$substitutions['I'] = 'ı';
					}

					break;

				case 'fold':
					require_once __DIR__ . '/CaseFold.php';
					$substitutions = $simple ? utf8_casefold_simple_maps() : utf8_casefold_maps();
					break;
			}

			foreach ($chars as &$char) {
				$char = $substitutions[$char] ?? $char;
			}

			$this->string = implode('', $chars);
		} elseif (in_array($case, ['title', 'ucfirst', 'ucwords'])) {
			require_once __DIR__ . '/RegularExpressions.php';

			require_once __DIR__ . '/CaseUpper.php';

			require_once __DIR__ . '/CaseTitle.php';

			$prop_classes = utf8_regex_properties();

			$upper = $simple ? utf8_strtoupper_simple_maps() : utf8_strtoupper_maps();

			// Turkish & Azeri conditional casing, part 1.
			if (in_array($this->language, ['tr', 'az'])) {
				$upper['i'] = 'İ';
			}

			$title = array_merge($upper, $simple ? utf8_titlecase_simple_maps() : utf8_titlecase_maps());

			switch ($case) {
				case 'title':
					$this->convertCase($this->string, 'lower', $simple);
					$regex = '/(?:^|[^\w' . $prop_classes['Case_Ignorable'] . '])\K(\p{L})/u';
					break;

				case 'ucwords':
					$regex = '/(?:^|[^\w' . $prop_classes['Case_Ignorable'] . '])\K(\p{L})(?=[' . $prop_classes['Case_Ignorable'] . ']*(?:(?<upper>\p{Lu})|\w?))/u';
					break;

				case 'ucfirst':
					$regex = '/^[^\w' . $prop_classes['Case_Ignorable'] . ']*\K(\p{L})(?=[' . $prop_classes['Case_Ignorable'] . ']*(?:(?<upper>\p{Lu})|\w?))/u';
					break;
			}

			$this->string = preg_replace_callback(
				$regex,
				function ($matches) use ($upper, $title) {
					// If second letter is uppercase, use uppercase for first letter.
					// Otherwise, use titlecase for first letter.
					$case = !empty($matches['upper']) ? 'upper' : 'title';

					$matches[1] = $$case[$matches[1]] ?? $matches[1];

					return $matches[1];
				},
				$this->string,
			);
		}

		// If casefolding, we're done.
		if ($case === 'fold') {
			return $this;
		}

		// Handle conditional casing situations...
		$substitutions = [];
		$replacements = [];

		// Greek conditional casing, part 1: Fix lowercase sigma.
		// Note that this rule doesn't depend on $txt['lang_locale'].
		if ($case !== 'upper' && strpos($this->string, 'ς') !== false || strpos($this->string, 'σ') !== false) {
			require_once $sourcedir . '/Unicode/RegularExpressions.php';

			$prop_classes = utf8_regex_properties();

			// First, convert all lowercase sigmas to regular form.
			$substitutions['ς'] = 'σ';

			// Then convert any at the end of words to final form.
			$replacements['/\Bσ([' . $prop_classes['Case_Ignorable'] . ']*)(?!\p{L})/u'] = 'ς$1';
		}

		// Greek conditional casing, part 2: No accents on uppercase strings.
		if ($this->language === 'el' && $case === 'upper') {
			// Composed forms.
			$substitutions += [
				'Ά' => 'Α', 'Ἀ' => 'Α', 'Ἁ' => 'Α', 'Ὰ' => 'Α', 'Ᾰ' => 'Α',
				'Ᾱ' => 'Α', 'Α' => 'Α', 'Α' => 'Α', 'Ἂ' => 'Α', 'Ἃ' => 'Α',
				'Ἄ' => 'Α', 'Ἅ' => 'Α', 'Ἆ' => 'Α', 'Ἇ' => 'Α', 'Ὰ' => 'Α',
				'Ά' => 'Α', 'Α' => 'Α', 'Ἀ' => 'Α', 'Ἁ' => 'Α', 'Ἂ' => 'Α',
				'Ἃ' => 'Α', 'Ἄ' => 'Α', 'Ἅ' => 'Α', 'Ἆ' => 'Α', 'Ἇ' => 'Α',
				'Έ' => 'Ε', 'Ἐ' => 'Ε', 'Ἑ' => 'Ε', 'Ὲ' => 'Ε', 'Ἒ' => 'Ε',
				'Ἓ' => 'Ε', 'Ἔ' => 'Ε', 'Ἕ' => 'Ε', 'Ή' => 'Η', 'Ἠ' => 'Η',
				'Ἡ' => 'Η', 'Ὴ' => 'Η', 'Η' => 'Η', 'Η' => 'Η', 'Ἢ' => 'Η',
				'Ἣ' => 'Η', 'Ἤ' => 'Η', 'Ἥ' => 'Η', 'Ἦ' => 'Η', 'Ἧ' => 'Η',
				'Ἠ' => 'Η', 'Ἡ' => 'Η', 'Ὴ' => 'Η', 'Ή' => 'Η', 'Η' => 'Η',
				'Ἢ' => 'Η', 'Ἣ' => 'Η', 'Ἤ' => 'Η', 'Ἥ' => 'Η', 'Ἦ' => 'Η',
				'Ἧ' => 'Η', 'Ί' => 'Ι', 'Ἰ' => 'Ι', 'Ἱ' => 'Ι', 'Ὶ' => 'Ι',
				'Ῐ' => 'Ι', 'Ῑ' => 'Ι', 'Ι' => 'Ι', 'Ϊ' => 'Ι', 'Ι' => 'Ι',
				'Ἲ' => 'Ι', 'Ἳ' => 'Ι', 'Ἴ' => 'Ι', 'Ἵ' => 'Ι', 'Ἶ' => 'Ι',
				'Ἷ' => 'Ι', 'Ι' => 'Ι', 'Ι' => 'Ι', 'Ό' => 'Ο', 'Ὀ' => 'Ο',
				'Ὁ' => 'Ο', 'Ὸ' => 'Ο', 'Ὂ' => 'Ο', 'Ὃ' => 'Ο', 'Ὄ' => 'Ο',
				'Ὅ' => 'Ο', 'Ῥ' => 'Ρ', 'Ύ' => 'Υ', 'Υ' => 'Υ', 'Ὑ' => 'Υ',
				'Ὺ' => 'Υ', 'Ῠ' => 'Υ', 'Ῡ' => 'Υ', 'Υ' => 'Υ', 'Ϋ' => 'Υ',
				'Υ' => 'Υ', 'Υ' => 'Υ', 'Ὓ' => 'Υ', 'Υ' => 'Υ', 'Ὕ' => 'Υ',
				'Υ' => 'Υ', 'Ὗ' => 'Υ', 'Υ' => 'Υ', 'Υ' => 'Υ', 'Υ' => 'Υ',
				'Ώ' => 'Ω', 'Ὠ' => 'Ω', 'Ὡ' => 'Ω', 'Ὼ' => 'Ω', 'Ω' => 'Ω',
				'Ω' => 'Ω', 'Ὢ' => 'Ω', 'Ὣ' => 'Ω', 'Ὤ' => 'Ω', 'Ὥ' => 'Ω',
				'Ὦ' => 'Ω', 'Ὧ' => 'Ω', 'Ὠ' => 'Ω', 'Ὡ' => 'Ω', 'Ώ' => 'Ω',
				'Ω' => 'Ω', 'Ὢ' => 'Ω', 'Ὣ' => 'Ω', 'Ὤ' => 'Ω', 'Ὥ' => 'Ω',
				'Ὦ' => 'Ω', 'Ὧ' => 'Ω',
			];

			// Individual Greek diacritics.
			$substitutions += [
				"\xCC\x80" => '', "\xCC\x81" => '', "\xCC\x84" => '',
				"\xCC\x86" => '', "\xCC\x88" => '', "\xCC\x93" => '',
				"\xCC\x94" => '', "\xCD\x82" => '', "\xCD\x83" => '',
				"\xCD\x84" => '', "\xCD\x85" => '', "\xCD\xBA" => '',
				"\xCE\x84" => '', "\xCE\x85" => '',
				"\xE1\xBE\xBD" => '', "\xE1\xBE\xBF" => '', "\xE1\xBF\x80" => '',
				"\xE1\xBF\x81" => '', "\xE1\xBF\x8D" => '', "\xE1\xBF\x8E" => '',
				"\xE1\xBF\x8F" => '', "\xE1\xBF\x9D" => '', "\xE1\xBF\x9E" => '',
				"\xE1\xBF\x9F" => '', "\xE1\xBF\xAD" => '', "\xE1\xBF\xAE" => '',
				"\xE1\xBF\xAF" => '', "\xE1\xBF\xBD" => '', "\xE1\xBF\xBE" => '',
			];
		}

		// Turkish & Azeri conditional casing, part 2.
		if ($case !== 'upper' && in_array($this->language, ['tr', 'az'])) {
			// Remove unnecessary "COMBINING DOT ABOVE" after i
			$substitutions['i' . "\xCC\x87"] = 'i';
		}

		// Lithuanian conditional casing.
		if ($this->language === 'lt') {
			// Force a dot above lowercase i and j with accents by inserting
			// the "COMBINING DOT ABOVE" character.
			// Note: some fonts handle this incorrectly and show two dots,
			// but that's a bug in those fonts and cannot be fixed here.
			if ($case !== 'upper') {
				$replacements['/(i\x{328}?|\x{12F}|j)([\x{300}\x{301}\x{303}])/u'] = '$1' . "\xCC\x87" . '$2';
			}

			// Remove "COMBINING DOT ABOVE" after uppercase I and J.
			if ($case !== 'lower') {
				$replacements['/(I\x{328}?|\x{12E}|J)\x{307}/u'] = '$1';
			}
		}

		// Dutch has a special titlecase rule.
		if ($this->language === 'nl' && $case === 'title') {
			$replacements['/\bIj/u'] = 'IJ';
		}

		// Now perform whatever conditional casing fixes we need.
		if (!empty($substitutions)) {
			$this->string = strtr($this->string, $substitutions);
		}

		if (!empty($replacements)) {
			$this->string = preg_replace(array_keys($replacements), $replacements, $this->string);
		}

		return $this;
	}

	/**
	 * Performs Unicode normalization on this string.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @param string $form A Unicode normalization form: 'c', 'd', 'kc', 'kd'
	 *    or 'kc_casefold'.
	 * @return object A reference to this object for method chaining.
	 */
	public function normalize(string $form = 'c'): object
	{
		switch ($form) {
			case 'd':
				return $this->normalizeD();

			case 'kd':
				return $this->normalizeKD();

			case 'c':
				return $this->normalizeC();

			case 'kc':
				return $this->normalizeKC();

			case 'kc_casefold':
				return $this->normalizeKCFold();

			default:
				unset($this->string);

				return $this;
		}
	}

	/**
	 * Checks whether a string is already normalized to a given form.
	 *
	 * @param string $form One of 'd', 'c', 'kd', 'kc', or 'kc_casefold'
	 * @return bool Whether the string is already normalized to the given form.
	 */
	public function isNormalized(string $form): bool
	{
		// Can we use the intl extension?
		if (self::$use_intl_normalizer) {
			switch ($form) {
				case 'd':
					$form = \Normalizer::FORM_D;
					break;

				case 'kd':
					$form = \Normalizer::FORM_KD;
					break;

				case 'c':
					$form = \Normalizer::FORM_C;
					break;

				case 'kc':
					$form = \Normalizer::FORM_KC;
					break;

				case 'kc_casefold':
					$form = \Normalizer::FORM_KC_CF;
					break;

				default:
					return false;
			}

			return \Normalizer::isNormalized($this->string, $form);
		}

		// Check whether string contains characters that are disallowed in this form.
		switch ($form) {
			case 'd':
				$prop = 'NFD_QC';
				break;

			case 'kd':
				$prop = 'NFKD_QC';
				break;

			case 'c':
				$prop = 'NFC_QC';
				break;

			case 'kc':
				$prop = 'NFKC_QC';
				break;

			case 'kc_casefold':
				$prop = 'Changes_When_NFKC_Casefolded';
				break;

			default:
				return false;
		}

		require_once __DIR__ . '/QuickCheck.php';
		$qc = utf8_regex_quick_check();

		if (preg_match('/[' . $qc[$prop] . ']/u', $this->string)) {
			return false;
		}

		// Check whether all combining marks are in canonical order.
		// Note: Because PCRE's Unicode data might be outdated compared to ours,
		// this regex checks for marks and anything PCRE thinks is not a character.
		// That means the more thorough checks will occasionally be performed on
		// strings that don't need them, but building and running a perfect regex
		// would be more expensive in the vast majority of cases, so meh.
		if (preg_match_all('/([\p{M}\p{Cn}])/u', $this->string, $matches, PREG_OFFSET_CAPTURE)) {
			require_once __DIR__ . '/CombiningClasses.php';

			$combining_classes = utf8_combining_classes();

			$last_pos = 0;
			$last_len = 0;
			$last_ccc = 0;

			foreach ($matches[1] as $match) {
				$char = $match[0];
				$pos = $match[1];
				$ccc = $combining_classes[$char] ?? 0;

				// Not in canonical order, so return false.
				if ($pos === $last_pos + $last_len && $ccc > 0 && $last_ccc > $ccc) {
					return false;
				}

				$last_pos = $pos;
				$last_len = strlen($char);
				$last_ccc = $ccc;
			}
		}

		// If we get here, the string is normalized correctly.
		return true;
	}

	/**
	 * Helper function for Utils::sanitizeChars() that deals with invisible characters.
	 *
	 * This function deals with control characters, private use characters,
	 * non-characters, and characters that are invisible by definition in the
	 * Unicode standard. It does not deal with characters that are supposed to be
	 * visible according to the Unicode standard, and makes no attempt to compensate
	 * for possibly incomplete Unicode support in text rendering engines on client
	 * devices.
	 *
	 * @param int $level Controls how invisible formatting characters are handled.
	 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
	 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
	 *      2: Disallow all formatting characters. Use for internal comparisons
	 *         only, such as in the word censor, search contexts, etc.
	 * @param string $substitute Replacement string for the invalid characters.
	 * @return object A reference to this object for method chaining.
	 */
	public function sanitizeInvisibles(int $level, string $substitute): object
	{
		$level = min(max((int) $level, 0), 2);
		$substitute = (string) $substitute;

		require_once __DIR__ . '/RegularExpressions.php';
		$prop_classes = utf8_regex_properties();

		// We never want non-whitespace control characters
		$disallowed[] = '[^\P{Cc}\t\r\n]';

		// We never want private use characters or non-characters.
		// Use our own version of \p{Cn} in order to avoid possible inconsistencies
		// between our data and whichever version of PCRE happens to be installed
		// on this server. Unlike \p{Cc} and \p{Co}, which never change, the value
		// of \p{Cn} changes with every new version of Unicode.
		$disallowed[] = '[\p{Co}' . $prop_classes['Cn'] . ']';

		// Several more things we never want:
		$disallowed[] = '[' . implode('', [
			// Soft Hyphen.
			'\x{AD}',
			// Khmer Vowel Inherent AQ and Khmer Vowel Inherent AA.
			// Unicode Standard ch. 16 says: "they are insufficient for [their]
			// purpose and should be considered errors in the encoding."
			'\x{17B4}-\x{17B5}',
			// Invisible math characters.
			'\x{2061}-\x{2064}',
			// Deprecated formatting characters.
			'\x{206A}-\x{206F}',
			// Zero Width No-Break Space, a.k.a. Byte Order Mark.
			'\x{FEFF}',
			// Annotation characters and Object Replacement Character.
			'\x{FFF9}-\x{FFFC}',
		]) . ']';

		switch ($level) {
			case 2:
				$disallowed[] = '[' . implode('', [
					// Combining Grapheme Character.
					'\x{34F}',
					// Zero Width Non-Joiner.
					'\x{200C}',
					// Zero Width Joiner.
					'\x{200D}',
					// All variation selectors.
					$prop_classes['Variation_Selector'],
					// Tag characters.
					'\x{E0000}-\x{E007F}',
				]) . ']';

				// no break

			case 1:
				$disallowed[] = '[' . implode('', [
					// Zero Width Space.
					'\x{200B}',
					// Word Joiner.
					'\x{2060}',
					// "Bidi_Control" characters.
					// Disallowing means that all characters will behave according
					// to their default bidirectional text properties.
					$prop_classes['Bidi_Control'],
					// Hangul filler characters.
					// Used as placeholders in incomplete ideographs.
					'\x{115F}\x{1160}\x{3164}\x{FFA0}',
					// Shorthand formatting characters.
					'\x{1BCA0}-\x{1BCA3}',
					// Musical formatting characters.
					'\x{1D173}-\x{1D17A}',
				]) . ']';

				break;

			default:
				// Zero Width Space only allowed in certain scripts.
				$disallowed[] = '(?<![\p{Thai}\p{Myanmar}\p{Khmer}\p{Hiragana}\p{Katakana}])\x{200B}';

				// Word Joiner disallowed inside words. (Yes, \w is Unicode safe.)
				$disallowed[] = '(?<=\w)\x{2060}(?=\w)';

				// Hangul Choseong Filler and Hangul Jungseong Filler must followed
				// by more Hangul Jamo characters.
				$disallowed[] = '[\x{115F}\x{1160}](?![\x{1100}-\x{11FF}\x{A960}-\x{A97F}\x{D7B0}-\x{D7FF}])';

				// Hangul Filler for Hangul compatibility chars.
				$disallowed[] = '\x{3164}(?![\x{3130}-\x{318F}])';

				// Halfwidth Hangul Filler for halfwidth Hangul compatibility chars.
				$disallowed[] = '\x{FFA0}(?![\x{FFA1}-\x{FFDC}])';

				// Shorthand formatting characters only with other shorthand chars.
				$disallowed[] = '[\x{1BCA0}-\x{1BCA3}](?![\x{1BC00}-\x{1BC9F}])';
				$disallowed[] = '(?<![\x{1BC00}-\x{1BC9F}])[\x{1BCA0}-\x{1BCA3}]';

				// Musical formatting characters only with other musical chars.
				$disallowed[] = '[\x{1D173}\x{1D175}\x{1D177}\x{1D179}](?![\x{1D100}-\x{1D1FF}])';
				$disallowed[] = '(?<![\x{1D100}-\x{1D1FF}])[\x{1D174}\x{1D176}\x{1D178}\x{1D17A}]';

				break;
		}

		if ($level < 2) {
			/*
				Combining Grapheme Character has two uses: to override standard
				search and collation behaviours, which we never want to allow, and
				to ensure correct behaviour of combining marks in a few exceptional
				cases, which is legitimate and should be allowed. This means we can
				simply test whether it is followed by a combining mark in order to
				determine whether to allow it.
			*/
			$disallowed[] = '\x{34F}(?!\p{M})';

			// Tag characters not allowed inside words.
			$disallowed[] = '(?<=\w)[\x{E0000}-\x{E007F}](?=\w)';
		}

		$this->string = preg_replace('/' . implode('|', $disallowed) . '/u', $substitute, $this->string);

		// Are we done yet?
		if (!preg_match('/[' . $prop_classes['Join_Control'] . $prop_classes['Regional_Indicator'] . $prop_classes['Emoji'] . $prop_classes['Variation_Selector'] . ']/u', $this->string)) {
			return $this;
		}

		// String must be in Normalization Form C for the following checks to work.
		$this->normalize('c');

		$placeholders = [];

		// Use placeholders to preserve known emoji from further processing.
		$this->preserveEmoji($placeholders);

		// Get rid of any unsanctioned variation selectors.
		if (preg_match('/[' . $prop_classes['Variation_Selector'] . ']/u', $this->string)) {
			// Use placeholders to preserve sanctioned variation selectors, and
			// remove the rest.
			$this->sanitizeVariationSelectors($placeholders, $substitute);
		}

		// Join controls are only allowed inside words in special circumstances.
		// See https://unicode.org/reports/tr31/#Layout_and_Format_Control_Characters
		if (preg_match('/[' . $prop_classes['Join_Control'] . ']/u', $this->string)) {
			// Use placeholders to preserve allowed join controls, and remove
			// the rest.
			$this->sanitizeJoinControls($placeholders, $level, $substitute);
		}

		// Revert placeholders back to original characters.
		$this->string = strtr($this->string, array_flip($placeholders));

		return $this;
	}

	/**
	 * Extracts all the words in this string.
	 *
	 * Emoji characters count as words. Punctuation and other symbols do not.
	 *
	 * @todo Improve the fallback code we use when the IntlBreakIterator class
	 * is unavailable.
	 *
	 * @param int $level See documentation for Utf8String::sanitizeInvisibles().
	 * @return array The words in this string.
	 */
	public function extractWords(int $level): array
	{
		// Save this so we can restore it afterward.
		$original_string = $this->string;

		// Replace any illegal entities with spaces.
		$this->string = \SMF\Utils::sanitizeEntities($this->string, ' ');

		// Decode all the entities.
		$this->string = \SMF\Utils::entityDecode($this->string, true, ENT_QUOTES | ENT_HTML5, true);

		// Replace unwanted invisible characters with spaces.
		$this->sanitizeInvisibles($level, ' ');

		// Normalize the whitespace.
		$this->string = \SMF\Utils::normalizeSpaces($this->string, true, true, ['replace_tabs' => true, 'collapse_hspace' => true]);

		// Preserve emoji characters, variation selectors, and join controls.
		$placeholders = [];
		$this->preserveEmoji($placeholders);
		$this->sanitizeVariationSelectors($placeholders, ' ');
		$this->sanitizeJoinControls($placeholders, $level, ' ');

		// Remove the private use characters that delimit the placeholders
		// so that they don't interfere with the word splitting.
		foreach ($placeholders as $key => $placeholder) {
			$simple_placeholder = sha1($placeholder);
			$this->string = str_replace($placeholder, $simple_placeholder, $this->string);
			$placeholders[$key] = $simple_placeholder;
		}

		// Split into words, with Unicode awareness.
		// Prefer IntlBreakIterator if it is available.
		if (class_exists('IntlBreakIterator')) {
			$break_iterator = \IntlBreakIterator::createWordInstance();
			$break_iterator->setText($this->string);
			$parts_interator = $break_iterator->getPartsIterator();

			$words = [];

			foreach ($parts_interator as $word) {
				$words[] = $word;
			}
		} else {
			/*
			 * This is a sad, weak substitute for the IntlBreakIterator.
			 * It works well enough for European languages, but it fails badly
			 * for many Asian languages. To improve it will require adding more
			 * data to our Unicode data files and then writing code to implement
			 * the Unicode word break algorithm.
			 * See https://www.unicode.org/reports/tr29/#Word_Boundaries
			 */
			$words = preg_split('/(?<![\p{L}\p{M}\p{N}_])(?=[\p{L}\p{M}\p{N}_])|(?<=[\p{L}\p{M}\p{N}_])(?![\p{L}\p{M}\p{N}_])/su', $this->string);
		}

		foreach ($words as $key => $word) {
			$word = trim($word);

			if (preg_replace('/\W/u', '', $word) === '') {
				unset($words[$key]);

				continue;
			}

			if (!empty($placeholders)) {
				$word = strtr($word, array_flip($placeholders));
			}

			$words[$key] = $word;
		}

		// Restore the original version of the string.
		$this->string = $original_string;

		return $words;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $string The string.
	 * @param string $language Two-character locale code for the language of
	 *    this string.
	 * @return object An instance of this class.
	 */
	public static function create(string $string, ?string $language = null): object
	{
		return new self($string, $language);
	}

	/**
	 * Helper method for normalizeD and normalizeKD.
	 *
	 * @param array $chars Array of Unicode characters
	 * @param bool $compatibility If true, perform compatibility decomposition.
	 *    Default: false.
	 * @return array Array of decomposed Unicode characters.
	 */
	public static function decompose(array $chars, bool $compatibility = false): array
	{
		if (!empty($compatibility)) {
			require_once __DIR__ . '/DecompositionCompatibility.php';

			$substitutions = utf8_normalize_kd_maps();

			foreach ($chars as &$char) {
				$char = $substitutions[$char] ?? $char;
			}
		}

		require_once __DIR__ . '/DecompositionCanonical.php';

		require_once __DIR__ . '/CombiningClasses.php';

		$substitutions = utf8_normalize_d_maps();
		$combining_classes = utf8_combining_classes();

		// Replace characters with decomposed forms.
		for ($i = 0; $i < count($chars); $i++) {
			// Hangul characters.
			// See "Hangul Syllable Decomposition" in the Unicode standard, ch. 3.12.
			if ($chars[$i] >= "\xEA\xB0\x80" && $chars[$i] <= "\xED\x9E\xA3") {
				if (!function_exists('mb_ord')) {
					require_once Config::$sourcedir . '/Subs-Compat.php';
				}

				$s = mb_ord($chars[$i]);
				$sindex = $s - 0xAC00;
				$l = (int) (0x1100 + $sindex / (21 * 28));
				$v = (int) (0x1161 + ($sindex % (21 * 28)) / 28);
				$t = $sindex % 28;

				$chars[$i] = implode('', [mb_chr($l), mb_chr($v), $t ? mb_chr(0x11A7 + $t) : '']);
			}
			// Everything else.
			elseif (isset($substitutions[$chars[$i]])) {
				$chars[$i] = $substitutions[$chars[$i]];
			}
		}

		// Must re-split the string before sorting.
		$chars = preg_split('/(.)/su', implode('', $chars), 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// Sort characters into canonical order.
		for ($i = 1; $i < count($chars); $i++) {
			if (empty($combining_classes[$chars[$i]]) || empty($combining_classes[$chars[$i - 1]])) {
				continue;
			}

			if ($combining_classes[$chars[$i - 1]] > $combining_classes[$chars[$i]]) {
				$temp = $chars[$i];
				$chars[$i] = $chars[$i - 1];
				$chars[$i - 1] = $temp;

				// Backtrack and check again.
				if ($i > 1) {
					$i -= 2;
				}
			}
		}

		return $chars;
	}

	/**
	 * Helper method for normalizeC and normalizeKC.
	 *
	 * @param array $chars Array of decomposed Unicode characters
	 * @return array Array of composed Unicode characters.
	 */
	public static function compose(array $chars): array
	{
		require_once __DIR__ . '/Composition.php';

		require_once __DIR__ . '/CombiningClasses.php';

		$substitutions = utf8_compose_maps();
		$combining_classes = utf8_combining_classes();

		for ($c = 0; $c < count($chars); $c++) {
			// Singleton replacements.
			if (isset($substitutions[$chars[$c]])) {
				$chars[$c] = $substitutions[$chars[$c]];
			}

			// Hangul characters.
			// See "Hangul Syllable Composition" in the Unicode standard, ch. 3.12.
			if ($chars[$c] >= "\xE1\x84\x80" && $chars[$c] <= "\xE1\x84\x92" && isset($chars[$c + 1]) && $chars[$c + 1] >= "\xE1\x85\xA1" && $chars[$c + 1] <= "\xE1\x85\xB5") {
				if (!function_exists('mb_ord')) {
					require_once Config::$sourcedir . '/Subs-Compat.php';
				}

				$l_part = $chars[$c];
				$v_part = $chars[$c + 1];
				$t_part = null;

				$l_index = mb_ord($l_part) - 0x1100;
				$v_index = mb_ord($v_part) - 0x1161;

				$lv_index = $l_index * 588 + $v_index * 28;
				$s = 0xAC00 + $lv_index;

				if (isset($chars[$c + 2]) && $chars[$c + 2] >= "\xE1\x86\xA8" && $chars[$c + 2] <= "\xE1\x87\x82") {
					$t_part = $chars[$c + 2];
					$t_index = mb_ord($t_part) - 0x11A7;
					$s += $t_index;
				}

				$chars[$c] = mb_chr($s);
				$chars[++$c] = null;

				if (isset($t_part)) {
					$chars[++$c] = null;
				}

				continue;
			}

			if ($c > 0) {
				$ccc = $combining_classes[$chars[$c]] ?? 0;

				// Find the preceding starter character.
				$l = $c - 1;

				while ($l > 0 && (!isset($chars[$l]) || (!empty($combining_classes[$chars[$l]]) && $combining_classes[$chars[$l]] < $ccc))) {
					$l--;
				}

				// Is there a composed form for this combination?
				if (isset($substitutions[$chars[$l] . $chars[$c]])) {
					// Replace the starter character with the composed character.
					$chars[$l] = $substitutions[$chars[$l] . $chars[$c]];

					// Unset the current combining character.
					$chars[$c] = null;
				}
			}
		}

		return $chars;
	}

	/**
	 * Backward compatibility wrapper for convertCase('lower').
	 *
	 * Equivalent to mb_strtolower($string, 'UTF-8'), except that we can keep the
	 * output consistent across PHP versions and up to date with the latest version
	 * of Unicode.
	 *
	 * @param string $string The string
	 * @return string The lowercase version of $string
	 */
	public static function utf8_strtolower(string $string): string
	{
		return (string) self::create($string)->convertCase('lower');
	}

	/**
	 * Backward compatibility wrapper for convertCase('upper').
	 *
	 * Equivalent to mb_strtoupper($string, 'UTF-8'), except that we can keep the
	 * output consistent across PHP versions and up to date with the latest version
	 * of Unicode.
	 *
	 * @param string $string The string
	 * @return string The uppercase version of $string
	 */
	public static function utf8_strtoupper(string $string): string
	{
		return (string) self::create($string)->convertCase('upper');
	}

	/**
	 * Backward compatibility wrapper for convertCase('fold').
	 *
	 * Equivalent to mb_convert_case($string, MB_CASE_FOLD, 'UTF-8'), except that
	 * we can keep the output consistent across PHP versions and up to date with
	 * the latest version of Unicode.
	 *
	 * @param string $string The string
	 * @return string The uppercase version of $string
	 */
	public static function utf8_casefold($string): string
	{
		return (string) self::create($string)->convertCase('fold');
	}

	/**
	 * Backward compatibility wrapper for the convertCase method.
	 *
	 * @param string $string The string.
	 * @param string $case One of 'upper', 'lower', 'fold', 'title', 'ucwords',
	 *    or 'ucfirst'.
	 * @param bool $simple If true, use simple maps instead of full maps.
	 *    Default: false.
	 * @return string A version of $string converted to the specified case.
	 */
	public static function utf8_convert_case(string $string, string $case, bool $simple = false): string
	{
		return (string) self::create($string)->convertCase($case, $simple);
	}

	/**
	 * Backward compatibility wrapper for normalize('d').
	 *
	 * @param string $string A UTF-8 string
	 * @return string The decomposed version of $string
	 */
	public static function utf8_normalize_d(string $string): string
	{
		return (string) self::create($string)->normalize('d');
	}

	/**
	 * Backward compatibility wrapper for normalize('kd').
	 *
	 * @param string $string A UTF-8 string.
	 * @return string The decomposed version of $string.
	 */
	public static function utf8_normalize_kd(string $string): string
	{
		return (string) self::create($string)->normalize('kd');
	}

	/**
	 * Backward compatibility wrapper for normalize('c').
	 *
	 * @param string $string A UTF-8 string
	 * @return string The composed version of $string
	 */
	public static function utf8_normalize_c(string $string): string
	{
		return (string) self::create($string)->normalize('c');
	}

	/**
	 * Backward compatibility wrapper for normalize('kc').
	 *
	 * @param string $string The string
	 * @return string The composed version of $string
	 */
	public static function utf8_normalize_kc(string $string): string
	{
		return (string) self::create($string)->normalize('kc');
	}

	/**
	 * Backward compatibility wrapper for normalize('kc_casefold').
	 *
	 * @param string $string The string
	 * @return string The casefolded version of $string
	 */
	public static function utf8_normalize_kc_casefold(string $string): string
	{
		return (string) self::create($string)->normalize('kc_casefold');
	}

	/**
	 * Backward compatibility wrapper for the isNormalized method.
	 *
	 * @param string|array $string A string of UTF-8 characters.
	 * @param string $form One of 'd', 'c', 'kd', 'kc', or 'kc_casefold'
	 * @return bool Whether the string is already normalized to the given form.
	 */
	public static function utf8_is_normalized(string $string, string $form): bool
	{
		return (string) self::create($string)->isNormalized($form);
	}

	/**
	 * Backward compatibility wrapper for the sanitizeInvisibles method.
	 *
	 * @param string $string The string to sanitize.
	 * @param int $level Controls how invisible formatting characters are handled.
	 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
	 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
	 *      2: Disallow all formatting characters. Use for internal comparisons
	 *         only, such as in the word censor, search contexts, etc.
	 * @param string $substitute Replacement string for the invalid characters.
	 * @return string The sanitized string.
	 */
	public static function utf8_sanitize_invisibles(string $string, int $level, string $substitute): string
	{
		return (string) self::create($string)->sanitizeInvisibles($level, $substitute);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Normalizes via Canonical Decomposition.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	protected function normalizeD(): object
	{
		if ($this->isNormalized('d')) {
			return $this;
		}

		if (self::$use_intl_normalizer) {
			$this->string = \Normalizer::normalize($this->string, \Normalizer::FORM_D);

			return $this;
		}

		$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			unset($this->string);

			return $this;
		}

		$this->string = implode('', self::decompose($chars, false));

		return $this;
	}

	/**
	 * Normalizes via Compatibility Decomposition.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	protected function normalizeKD(): object
	{
		if ($this->isNormalized('kd')) {
			return $this;
		}

		if (self::$use_intl_normalizer) {
			$this->string = \Normalizer::normalize($this->string, \Normalizer::FORM_KD);

			return $this;
		}

		$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			unset($this->string);

			return $this;
		}

		$this->string = implode('', self::decompose($chars, true));

		return $this;
	}

	/**
	 * Normalizes via Canonical Decomposition then Canonical Composition.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	protected function normalizeC(): object
	{
		if ($this->isNormalized('c')) {
			return $this;
		}

		if (self::$use_intl_normalizer) {
			$this->string = \Normalizer::normalize($this->string, \Normalizer::FORM_C);

			return $this;
		}

		$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			unset($this->string);

			return $this;
		}

		$this->string = implode('', self::compose(self::decompose($chars, false)));

		return $this;
	}

	/**
	 * Normalizes via Compatibility Decomposition then Canonical Composition.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	protected function normalizeKC(): object
	{
		if ($this->isNormalized('kc')) {
			return $this;
		}

		if (self::$use_intl_normalizer) {
			$this->string = \Normalizer::normalize($this->string, \Normalizer::FORM_KC);

			return $this;
		}

		$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			unset($this->string);

			return $this;
		}

		$this->string = implode('', self::compose(self::decompose($chars, true)));

		return $this;
	}

	/**
	 * Casefolds UTF-8 via Compatibility Composition Casefolding.
	 * Used by idn_to_ascii polyfill in Subs-Compat.php.
	 *
	 * On failure, $this->string will be unset.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	protected function normalizeKCFold(): object
	{
		if ($this->isNormalized($this->string, 'kc_casefold')) {
			return $this;
		}

		if (self::$use_intl_normalizer) {
			$this->string = \Normalizer::normalize($this->string, \Normalizer::FORM_KC_CF);

			return $this;
		}

		$chars = preg_split('/(.)/su', $this->string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($chars === false) {
			unset($this->string);

			return $this;
		}

		$chars = self::decompose($chars, true);

		require_once __DIR__ . '/CaseFold.php';

		require_once __DIR__ . '/DefaultIgnorables.php';

		$substitutions = utf8_casefold_maps();
		$ignorables = array_flip(utf8_default_ignorables());

		foreach ($chars as &$char) {
			if (isset($substitutions[$char])) {
				$char = $substitutions[$char];
			} elseif (isset($ignorables[$char])) {
				$char = '';
			}
		}

		$this->string = implode('', self::compose($chars));

		return $this;
	}

	/**
	 * Replaces emoji characters and sequences in $this->string with
	 * placeholders in order to preserve them from further processing.
	 *
	 * The placeholders are added to $placeholders.
	 *
	 * @param array &$placeholders Array of placeholders that can be used to
	 *    restore the original characters.
	 */
	protected function preserveEmoji(array &$placeholders): void
	{
		require_once __DIR__ . '/RegularExpressions.php';
		$prop_classes = utf8_regex_properties();

		// Regex source is https://unicode.org/reports/tr51/#EBNF_and_Regex
		$this->string  = preg_replace_callback(
			'/' .
			// Flag emojis
			'[' . $prop_classes['Regional_Indicator'] . ']{2}' .
			// Or
			'|' .
			// Emoji characters
			'[' . $prop_classes['Emoji'] . ']' .
			// Possibly followed by modifiers of various sorts
			'(' .
				'[' . $prop_classes['Emoji_Modifier'] . ']' .
				'|' .
				'\x{FE0F}\x{20E3}?' .
				'|' .
				'[\x{E0020}-\x{E007E}]+\x{E007F}' .
			')?' .
			// Possibly concatenated with Zero Width Joiner and more emojis
			// (e.g. the "family" emoji sequences)
			'(' .
				'\x{200D}[' . $prop_classes['Emoji'] . ']' .
				'(' .
					'[' . $prop_classes['Emoji_Modifier'] . ']' .
					'|' .
					'\x{FE0F}\x{20E3}?' .
					'|' .
					'[\x{E0020}-\x{E007E}]+\x{E007F}' .
				')?' .
			')*' .
			'/u',
			function ($matches) use (&$placeholders) {
				// Skip lone ASCII characters that are not actually part of an
				// emoji sequence. This can happen because the digits 0-9 and
				// the '*' and '#' characters are the base characters for the
				// "Emoji_Keycap_Sequence" emojis.
				if (strlen($matches[0]) === 1) {
					return $matches[0];
				}

				$placeholders[$matches[0]] = "\xEE\xB3\x9B" . md5($matches[0]) . "\xEE\xB3\x9C";

				return $placeholders[$matches[0]];
			},
			$this->string,
		);
	}

	/**
	 * Replaces sanctioned variation sequences in $this->string with
	 * placeholders in order to preserve them from further processing.
	 *
	 * Unicode gives pre-defined lists of sanctioned variation sequences
	 * and says any use of variation selectors outside those sequences
	 * is unsanctioned.
	 *
	 * The placeholders are added to $placeholders.
	 *
	 * @param array &$placeholders Array of placeholders that can be used to
	 *    restore the original characters.
	 * @param string $substitute Replacement string for the invalid characters.
	 */
	protected function sanitizeVariationSelectors(array &$placeholders, string $substitute): void
	{
		require_once __DIR__ . '/RegularExpressions.php';
		$prop_classes = utf8_regex_properties();

		$patterns = ['/[' . $prop_classes['Ideographic'] . '][\x{E0100}-\x{E01EF}]/u'];

		foreach (utf8_regex_variation_selectors() as $variation_selector => $allowed_base_chars) {
			$patterns[] = '/[' . $allowed_base_chars . '][' . $variation_selector . ']/u';
		}

		// Use placeholders for sanctioned variation selectors.
		$this->string = preg_replace_callback(
			$patterns,
			function ($matches) use (&$placeholders) {
				$placeholders[$matches[0]] = "\xEE\xB3\x9B" . md5($matches[0]) . "\xEE\xB3\x9C";

				return $placeholders[$matches[0]];
			},
			$this->string,
		);

		// Remove any unsanctioned variation selectors.
		$this->string = preg_replace('/[' . $prop_classes['Variation_Selector'] . ']/u', $substitute, $this->string);
	}

	/**
	 * Replaces allowed join controls inside words in $this->string with
	 * placeholders in order to preserve them from further processing.
	 *
	 * Join controls are only allowed inside words in special circumstances.
	 * See https://unicode.org/reports/tr31/#Layout_and_Format_Control_Characters
	 *
	 * The placeholders are added to $placeholders.
	 *
	 * @param array &$placeholders Array of placeholders that can be used to
	 *    restore the original characters.
	 * @param string $substitute Replacement string for the invalid characters.
	 */
	protected function sanitizeJoinControls(array &$placeholders, int $level, string $substitute): void
	{
		require_once __DIR__ . '/RegularExpressions.php';

		// Zero Width Non-Joiner (U+200C)
		$zwnj = "\xE2\x80\x8C";

		// Zero Width Joiner (U+200D)
		$zwj = "\xE2\x80\x8D";

		$placeholders[$zwnj] = "\xEE\x80\x8C";
		$placeholders[$zwj] = "\xEE\x80\x8D";

		// When not in strict mode, allow ZWJ at word boundaries.
		if ($level === 0) {
			$this->string = preg_replace('/\b\x{200D}|\x{200D}\b/u', $placeholders[$zwj], $this->string);
		}

		// Tests for Zero Width Joiner and Zero Width Non-Joiner.
		$joining_type_classes = utf8_regex_joining_type();
		$indic_classes = utf8_regex_indic();

		foreach (array_merge($joining_type_classes, $indic_classes) as $script => $classes) {
			// Cursive scripts like Arabic use ZWNJ in certain contexts.
			// For these scripts, use test A1 for allowing ZWNJ.
			// https://unicode.org/reports/tr31/#A1
			if (isset($joining_type_classes[$script])) {
				$t = !empty($classes['Transparent']) ? '[' . $classes['Transparent'] . ']*' : '';

				$lj = !empty($classes['Left_Joining']) ? $classes['Left_Joining'] : '';
				$rj = !empty($classes['Right_Joining']) ? $classes['Right_Joining'] : '';

				if (!empty($classes['Dual_Joining'])) {
					$lj .= $classes['Dual_Joining'];
					$rj .= $classes['Dual_Joining'];
				}

				$lj = !empty($lj) ? '[' . $lj . ']' : '';
				$rj = !empty($rj) ? '[' . $rj . ']' : '';

				$pattern = $lj . $t . $zwnj . $t . $rj;
			}
			// Indic scripts with viramas use ZWNJ and ZWJ in certain contexts.
			// For these scripts, use tests A2 and B for allowing ZWNJ and ZWJ.
			// https://unicode.org/reports/tr31/#A2
			// https://unicode.org/reports/tr31/#B
			else {
				// A letter that is part of this particular script.
				$letter = '[' . $classes['Letter'] . ']';

				// Zero or more non-spacing marks used in this script.
				$nonspacing_marks = '[' . $classes['Nonspacing_Mark'] . ']*';

				// Zero or more non-spacing combining marks used in this script.
				$nonspacing_combining_marks = '[' . $classes['Nonspacing_Combining_Mark'] . ']*';

				// ZWNJ must be followed by another letter in the same script.
				$zwnj_pattern = '\x{200C}(?=' . $nonspacing_combining_marks . $letter . ')';

				// ZWJ must NOT be followed by a vowel dependent character in
				// this script or by any character from a different script.
				$zwj_pattern = '\x{200D}(?!' . (!empty($classes['Vowel_Dependent']) ? '[' . $classes['Vowel_Dependent'] . ']|' : '') . '[^' . $classes['All'] . '])';

				// Now build the pattern for this script.
				$pattern = $letter . $nonspacing_marks . '[' . $classes['Virama'] . ']' . $nonspacing_combining_marks . '\K' . (!empty($zwj_pattern) ? '(?:' . $zwj_pattern . '|' . $zwnj_pattern . ')' : $zwnj_pattern);
			}

			// Do the thing.
			$this->string = preg_replace_callback(
				'/' . $pattern . '/u',
				function ($matches) use ($placeholders) {
					return strtr($matches[0], $placeholders);
				},
				$this->string,
			);

			// Did we catch 'em all?
			if (strpos($this->string, $zwnj) === false && strpos($this->string, $zwj) === false) {
				break;
			}
		}

		// Apart from the exceptions above, ZWNJ and ZWJ are not allowed.
		$this->string = str_replace([$zwj, $zwnj], $substitute, $this->string);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Utf8String::exportStatic')) {
	Utf8String::exportStatic();
}

?>