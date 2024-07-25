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

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Sapi;
use SMF\TaskRunner;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * This class contains code used to update SMF's Unicode data files.
 */
class UpdateUnicode extends BackgroundTask
{
	/**
	 * URLs where we can fetch the Unicode data files.
	 */
	public const DATA_URL_UCD = 'https://www.unicode.org/Public/UCD/latest/ucd';
	public const DATA_URL_IDNA = 'https://www.unicode.org/Public/idna/latest';
	public const DATA_URL_CLDR = 'https://raw.githubusercontent.com/unicode-org/cldr-json/main/cldr-json';
	public const DATA_URL_SECURITY = 'https://www.unicode.org/Public/security/latest';

	/**
	 * @var string The latest official release of the Unicode Character Database.
	 */
	public $ucd_version = '';

	/**
	 * @var string Path to temporary working directory.
	 */
	public $temp_dir = '';

	/**
	 * @var string Convenience alias of Config::$sourcedir . '/Unicode'.
	 */
	public $unicodedir = '';

	/**
	 * @var int Used to ensure we exit long running tasks cleanly.
	 */
	private $time_limit = 30;

	/**
	 * @var array Key-value pairs of character decompositions.
	 */
	private $full_decomposition_maps = [];

	/**
	 * @var array Character properties used during normalization.
	 */
	private $derived_normalization_props = [];

	/**
	 * @var array Assorted info about Unicode characters.
	 */
	private $char_data = [];

	/**
	 * @var array Statistical info about character scripts (e.g. Latin, Greek, Cyrillic, etc.)
	 */
	private $script_stats = [];

	/**
	 * @var array Tracks associations between character scripts' short and long names.
	 */
	private $script_aliases = [];

	/**
	 * @var array Info about functions to build in SMF's Unicode data files.
	 */
	private $funcs = [
		[
			'file' => 'Metadata.php',
			'regex' => '/if \(!defined\(\'SMF_UNICODE_VERSION\'\)\)(?:\s*{)?\n\tdefine\(\'SMF_UNICODE_VERSION\', \'\d+(\.\d+)*\'\);(?:\n})?/',
			'data' => [
				// 0.0.0.0 will be replaced with correct value at runtime.
				"if (!defined('SMF_UNICODE_VERSION')) {\n\tdefine('SMF_UNICODE_VERSION', '0.0.0.0');\n}",
			],
		],
		'utf8_normalize_d_maps' => [
			'file' => 'DecompositionCanonical.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_normalize_d.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Canonical Decomposition maps for Unicode normalization.',
			],
			'data' => [],
		],
		'utf8_normalize_kd_maps' => [
			'file' => 'DecompositionCompatibility.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_normalize_kd.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Compatibility Decomposition maps for Unicode normalization.',
			],
			'data' => [],
		],
		'utf8_compose_maps' => [
			'file' => 'Composition.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_compose.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Composition maps for Unicode normalization.',
			],
			'data' => [],
		],
		'utf8_combining_classes' => [
			'file' => 'CombiningClasses.php',
			'key_type' => 'hexchar',
			'val_type' => 'int',
			'desc' => ['Helper function for utf8_normalize_d.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Combining Class data for Unicode normalization.',
			],
			'data' => [],
		],
		'utf8_strtolower_simple_maps' => [
			'file' => 'CaseLower.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_strtolower.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Uppercase to lowercase maps.',
			],
			'data' => [],
		],
		'utf8_strtolower_maps' => [
			'file' => 'CaseLower.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_strtolower.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Uppercase to lowercase maps.',
			],
			'data' => [],
		],
		'utf8_strtoupper_simple_maps' => [
			'file' => 'CaseUpper.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_strtoupper.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Lowercase to uppercase maps.',
			],
			'data' => [],
		],
		'utf8_strtoupper_maps' => [
			'file' => 'CaseUpper.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_strtoupper.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Lowercase to uppercase maps.',
			],
			'data' => [],
		],
		'utf8_titlecase_simple_maps' => [
			'file' => 'CaseTitle.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_convert_case.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Simple title case maps.',
			],
			'data' => [],
		],
		'utf8_titlecase_maps' => [
			'file' => 'CaseTitle.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_convert_case.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Full title case maps.',
			],
			'data' => [],
		],
		'utf8_casefold_simple_maps' => [
			'file' => 'CaseFold.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_casefold.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Casefolding maps.',
			],
			'data' => [],
		],
		'utf8_casefold_maps' => [
			'file' => 'CaseFold.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_casefold.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Casefolding maps.',
			],
			'data' => [],
		],
		'utf8_default_ignorables' => [
			'file' => 'DefaultIgnorables.php',
			'key_type' => 'int',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for utf8_normalize_kc_casefold.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Characters with the \'Default_Ignorable_Code_Point\' property.',
			],
			'data' => [],
		],
		'utf8_regex_properties' => [
			'file' => 'RegularExpressions.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'propfiles' => [
				'DerivedCoreProperties.txt',
				'PropList.txt',
				'emoji/emoji-data.txt',
				'extracted/DerivedGeneralCategory.txt',
				'auxiliary/WordBreakProperty.txt',
			],
			'props' => [
				'ALetter',
				'Bidi_Control',
				'Case_Ignorable',
				'Cn',
				'Default_Ignorable_Code_Point',
				'Emoji',
				'Emoji_Modifier',
				'Extend',
				'ExtendNumLet',
				'Format',
				'Hebrew_Letter',
				'Ideographic',
				'Join_Control',
				'Katakana',
				'MidLetter',
				'MidNum',
				'MidNumLet',
				'Numeric',
				'Regional_Indicator',
				'Variation_Selector',
				'WSegSpace',
			],
			'desc' => [
				'Helper function for utf8_sanitize_invisibles and utf8_convert_case.',
				'',
				'Character class lists compiled from:',
				self::DATA_URL_UCD . '/DerivedCoreProperties.txt',
				self::DATA_URL_UCD . '/PropList.txt',
				self::DATA_URL_UCD . '/emoji/emoji-data.txt',
				self::DATA_URL_UCD . '/extracted/DerivedGeneralCategory.txt',
				self::DATA_URL_UCD . '/auxiliary/WordBreakProperty.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for various Unicode properties.',
			],
			'data' => [],
		],
		'utf8_regex_variation_selectors' => [
			'file' => 'RegularExpressions.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => [
				'Helper function for utf8_sanitize_invisibles.',
				'',
				'Character class lists compiled from:',
				self::DATA_URL_UCD . '/StandardizedVariants.txt',
				self::DATA_URL_UCD . '/emoji/emoji-variation-sequences.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for filtering variation selectors.',
			],
			'data' => [],
		],
		'utf8_regex_joining_type' => [
			'file' => 'RegularExpressions.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => [
				'Helper function for utf8_sanitize_invisibles.',
				'',
				'Character class lists compiled from:',
				self::DATA_URL_UCD . '/extracted/DerivedJoiningType.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for joining characters in certain scripts.',
			],
			'data' => [],
		],
		'utf8_regex_indic' => [
			'file' => 'RegularExpressions.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => [
				'Helper function for utf8_sanitize_invisibles.',
				'',
				'Character class lists compiled from:',
				self::DATA_URL_UCD . '/extracted/DerivedCombiningClass.txt',
				self::DATA_URL_UCD . '/IndicSyllabicCategory.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for Indic scripts that use viramas.',
			],
			'data' => [],
		],
		'utf8_regex_quick_check' => [
			'file' => 'QuickCheck.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => [
				'Helper function for utf8_is_normalized.',
				'',
				'Character class lists compiled from:',
				self::DATA_URL_UCD . '/extracted/DerivedNormalizationProps.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for disallowed characters in normalization forms.',
			],
			'data' => [],
		],
		'idna_maps' => [
			'file' => 'Idna.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for idn_to_* polyfills.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Character maps for IDNA processing.',
			],
			'data' => [],
		],
		'idna_maps_deviation' => [
			'file' => 'Idna.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for idn_to_* polyfills.'],
			'return' => [
				'type' => 'array',
				'desc' => '"Deviation" character maps for IDNA processing.',
			],
			'data' => [],
		],
		'idna_maps_not_std3' => [
			'file' => 'Idna.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => ['Helper function for idn_to_* polyfills.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Non-STD3 character maps for IDNA processing.',
			],
			'data' => [],
		],
		'idna_regex' => [
			'file' => 'Idna.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => ['Helper function for idn_to_* polyfills.'],
			'return' => [
				'type' => 'array',
				'desc' => 'Regular expressions useful for IDNA processing.',
			],
			'data' => [],
		],
		'plurals' => [
			'file' => 'Plurals.php',
			'key_type' => 'string',
			'val_type' => 'array',
			'desc' => [
				'Helper function for SMF\Localization\MessageFormatter::formatMessage.',
				'',
				'Rules compiled from:',
				'https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/plurals.json',
				'https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/ordinals.json',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Pluralization rules for different languages',
			],
			'data' => [],
		],
		'currencies' => [
			'file' => 'Currencies.php',
			'key_type' => 'string',
			'val_type' => 'array',
			'desc' => [
				'Helper function for SMF\Localization\MessageFormatter::formatMessage.',
				'',
				'Rules compiled from:',
				'https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/currencyData.json',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Information about different currencies',
			],
			'data' => [],
		],
		'country_currencies' => [
			'file' => 'Currencies.php',
			'key_type' => 'string',
			'val_type' => 'array',
			'desc' => [
				'Helper function for SMF\Localization\MessageFormatter::formatMessage.',
				'',
				'Rules compiled from:',
				'https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/currencyData.json',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Information about currencies used in different countries',
			],
			'data' => [],
		],
		'utf8_confusables' => [
			'file' => 'Confusables.php',
			'key_type' => 'hexchar',
			'val_type' => 'hexchar',
			'desc' => [
				'Helper function for SMF\Unicode\SpoofDetector::getSkeletonString.',
				'',
				'Returns an array of "confusables" maps that can be used for confusable string',
				'detection.',
				'',
				'Data compiled from:',
				self::DATA_URL_SECURITY . '/confusables.txt',
			],
			'return' => [
				'type' => 'array',
				'desc' => '"Confusables" maps.',
			],
			'data' => [],
		],
		'utf8_character_scripts' => [
			'file' => 'Confusables.php',
			'key_type' => 'hexchar',
			'val_type' => 'array',
			'desc' => [
				'Helper function for SpoofDetector::resolveScriptSet.',
				'',
				'Each key in the returned array defines the END of a range of characters that',
				'all have the same script set. For example, the first key, "\x40", means the',
				'range of characters from "\x0" to "\x40". Then the second key, "\x5A",',
				'means the range from "\x41" to "\x5A".',
				'',
				'The first entry in each value array indicates the primary script (i.e. the',
				'value of the Script property) for that set of characters. If those characters',
				'can also occur in a limited number of other scripts (i.e. the Script_Extensions',
				'property for those characters is not empty), those additional scripts are',
				'listed after the first.',
				'',
				'See https://www.unicode.org/reports/tr24/ for more info.',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Script data for ranges of Unicode characters.',
			],
			'data' => [],
		],
		'utf8_regex_identifier_status' => [
			'file' => 'Confusables.php',
			'key_type' => 'string',
			'val_type' => 'string',
			'desc' => [
				'Helper function for SpoofDetector::checkHomographNames.',
				'',
				'Returns an array of regexes that can be used to check the "identifier status"',
				'of characters in a string.',
			],
			'return' => [
				'type' => 'array',
				'desc' => 'Character classes for identifier statuses.',
			],
			'data' => [],
		],
	];

	/**
	 * @var array Files to fetch from unicode.org.
	 */
	private $prefetch = [
		self::DATA_URL_UCD => [
			'CaseFolding.txt',
			'DerivedAge.txt',
			'DerivedCoreProperties.txt',
			'DerivedNormalizationProps.txt',
			'IndicSyllabicCategory.txt',
			'PropertyValueAliases.txt',
			'PropList.txt',
			'ScriptExtensions.txt',
			'Scripts.txt',
			'SpecialCasing.txt',
			'StandardizedVariants.txt',
			'UnicodeData.txt',
			'emoji/emoji-data.txt',
			'emoji/emoji-variation-sequences.txt',
			'extracted/DerivedGeneralCategory.txt',
			'extracted/DerivedJoiningType.txt',
			'auxiliary/WordBreakProperty.txt',
		],
		self::DATA_URL_IDNA => [
			'IdnaMappingTable.txt',
		],
		self::DATA_URL_CLDR => [
			'cldr-core/supplemental/plurals.json',
			'cldr-core/supplemental/ordinals.json',
			'cldr-core/supplemental/currencyData.json',
		],
		self::DATA_URL_SECURITY => [
			'confusables.txt',
			'IdentifierStatus.txt',
		],
	];

	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		/*********
		 * Setup *
		 *********/
		$this->unicodedir = Config::$sourcedir . DIRECTORY_SEPARATOR . 'Unicode';

		// We need a temporary directory to hold our files while we work on them.
		$this->make_temp_dir();

		if (empty($this->temp_dir)) {
			return true;
		}

		// Do we even need to update?
		if (!$this->should_update()) {
			$this->deltree($this->temp_dir);

			return true;
		}

		@ini_set('memory_limit', '256M');

		$this->time_limit = (empty(ini_get('max_execution_time')) || @set_time_limit(TaskRunner::MAX_CLAIM_THRESHOLD) !== false) ? TaskRunner::MAX_CLAIM_THRESHOLD : ini_get('max_execution_time');

		foreach ($this->funcs as $func_name => $func_info) {
			$file_paths['final'] = implode(DIRECTORY_SEPARATOR, [$this->unicodedir, $func_info['file']]);

			if (!file_exists($file_paths['final'])) {
				touch($file_paths['final']);
			}

			if (!is_file($file_paths['final']) || !Utils::makeWritable($file_paths['final'])) {
				Lang::load('Errors');
				ErrorHandler::log(Lang::getTxt('unicode_update_failed', [$this->unicodedir]));

				return true;
			}

			$file_paths['temp'] = implode(DIRECTORY_SEPARATOR, [$this->temp_dir, $func_info['file']]);

			if (!file_exists($file_paths['temp'])) {
				touch($file_paths['temp']);
			}

			if (!is_file($file_paths['temp']) || !Utils::makeWritable($file_paths['temp'])) {
				Lang::load('Errors');
				ErrorHandler::log(Lang::getTxt('unicode_update_failed', [$this->temp_dir]));

				return true;
			}

			$file_contents['temp'] = file_get_contents($file_paths['temp']);

			if (empty($file_contents['temp']) || !str_contains($file_contents['temp'], 'namespace SMF\\Unicode;')) {
				file_put_contents($file_paths['temp'], $this->smf_file_header());
			} elseif (substr($file_contents['temp'], -2) === '?' . '>') {
				file_put_contents($file_paths['temp'], substr($file_contents['temp'], 0, -2));
			}
		}

		// Prefetch the files in case the network is slow.
		foreach ($this->prefetch as $data_url => $files) {
			$max_fetch_time = 0;

			foreach ($files as $filename) {
				$fetch_start = microtime(true);

				$local_file = $this->fetch_unicode_file($filename, $data_url);

				$max_fetch_time = max($max_fetch_time, microtime(true) - $fetch_start);

				// If prefetch is taking a really long time, pause and try again later.
				if ($local_file === false || microtime(true) - TIME_START >= $this->time_limit - $max_fetch_time) {
					Db::$db->insert(
						'',
						'{db_prefix}background_tasks',
						[
							'task_class' => 'string',
							'task_data' => 'string',
							'claimed_time' => 'int',
						],
						[
							'SMF\\Tasks\\Update_Unicode',
							'',
							time() - MAX_CLAIM_THRESHOLD,
						],
						['id_task'],
					);

					return true;
				}
			}
		}

		// Track whether anything goes wrong along the way.
		$success = true;

		/*************************************
		 * Normalization, case folding, etc. *
		 *************************************/
		$success = $this->process_derived_normalization_props() & $success;
		$success = $this->process_main_unicode_data() & $success;
		$success = $this->process_casing_data() & $success;
		$success = $this->finalize_decomposition_forms() & $success;

		$this->full_decomposition_maps = [];

		$this->export_funcs_to_file();

		/***************************
		 * Regular expression data *
		 ***************************/
		$success = $this->build_quick_check() & $success;

		$this->derived_normalization_props = [];

		$success = $this->build_regex_properties() & $success;
		$success = $this->build_regex_variation_selectors() & $success;
		$success = $this->build_script_stats() & $success;
		$success = $this->build_regex_joining_type() & $success;
		$success = $this->build_regex_indic() & $success;

		unset($this->funcs['utf8_combining_classes']['data']);

		$this->export_funcs_to_file();

		/*************************
		 * IDNA maps and regexes *
		 *************************/
		$success = $this->build_idna() & $success;

		$this->export_funcs_to_file();

		/*************
		 * CLDR data *
		 *************/
		$success = $this->build_plurals() & $success;
		$success = $this->build_currencies() & $success;

		$this->export_funcs_to_file();

		/********************
		 * Confusables data *
		 ********************/
		$success = $this->build_confusables() & $success;
		$success = $this->build_compressed_character_script_data() & $success;
		$success = $this->build_regex_identifier_status() & $success;

		$this->export_funcs_to_file();

		/**********
		 * Wrapup *
		 **********/
		if ($success) {
			$done_files = [];

			foreach ($this->funcs as $func_name => $func_info) {
				$file_paths['temp'] = $this->temp_dir . DIRECTORY_SEPARATOR . $func_info['file'];
				$file_paths['real'] = $this->unicodedir . DIRECTORY_SEPARATOR . $func_info['file'];

				if (in_array($file_paths['temp'], $done_files)) {
					continue;
				}

				// Add closing PHP tag to the temp file.
				file_put_contents($file_paths['temp'], '?' . '>', FILE_APPEND);

				$done_files[] = $file_paths['temp'];

				// Only move if the file has changed, discounting the license block.
				foreach (['temp', 'real'] as $f) {
					if (file_exists($file_paths[$f])) {
						$file_contents[$f] = preg_replace('~/\*\*.*?@package\h+SMF\b.*?\*/~s', '', file_get_contents($file_paths[$f]));
					} else {
						$file_contents[$f] = '';
					}
				}

				if ($file_contents['temp'] !== $file_contents['real']) {
					rename($file_paths['temp'], $file_paths['real']);
				}
			}

			// Updating Unicode data means we need to update the spoof detector names.
			if (empty($this->_details['files_only'])) {
				Db::$db->insert(
					'insert',
					'{db_prefix}background_tasks',
					[
						'task_class' => 'string',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					[
						'SMF\\Tasks\\UpdateSpoofDetectorNames',
						json_encode(['last_member_id' => 0]),
						0,
					],
					['id_task'],
				);
			}
		}

		// Clean up after ourselves.
		$this->deltree($this->temp_dir);

		// All done.
		return true;
	}

	/**
	 * Makes a temporary directory to hold our working files, and sets
	 * $this->temp_dir to the path of the created directory.
	 */
	private function make_temp_dir(): void
	{
		if (empty($this->temp_dir)) {
			$this->temp_dir = rtrim(Sapi::getTempDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Unicode';

			if (!is_dir($this->temp_dir)) {
				@mkdir($this->temp_dir);
			}

			// Needs to be a writable directory.
			if (!is_dir($this->temp_dir) || !Utils::makeWritable($this->temp_dir)) {
				$this->temp_dir = null;
			}
		}
	}

	/**
	 * Fetches the contents of a Unicode data file.
	 *
	 * Caches a local copy for subsequent lookups.
	 *
	 * @param string $filename Name of a Unicode datafile, relative to $data_url.
	 * @param string $data_url One of this class's DATA_URL_* constants.
	 *
	 * @return string|bool Path to locally saved copy of the file.
	 */
	private function fetch_unicode_file(string $filename, string $data_url): string|bool
	{
		$filename = ltrim($filename, '\\/');
		$file_url_name = strtr($filename, ['\\' => '/']);
		$file_local_name = strtr($filename, ['\\' => DIRECTORY_SEPARATOR, '/' => DIRECTORY_SEPARATOR]);

		switch ($data_url) {
			case self::DATA_URL_IDNA:
				$sub_dir = 'idna';
				break;

			case self::DATA_URL_CLDR:
				$sub_dir = 'cldr';
				break;

			default:
				$sub_dir = 'ucd';
				break;
		}

		$local_file = implode(DIRECTORY_SEPARATOR, [$this->temp_dir, $sub_dir, $file_local_name]);

		if (file_exists($local_file)) {
			return $local_file;
		}

		if (!file_exists(dirname($local_file))) {
			@mkdir(dirname($local_file), 0777, true);

			if (!is_dir(dirname($local_file))) {
				return false;
			}
		}

		$file_contents = WebFetchApi::fetch($data_url . '/' . $file_url_name);

		if (empty($file_contents)) {
			return false;
		}

		file_put_contents($local_file, $file_contents);

		return $local_file;
	}

	/**
	 * Deletes a directory and its contents.
	 *
	 * @param string Path to directory
	 */
	private function deltree(string $dir_path): void
	{
		// For safety.
		if (!str_starts_with($dir_path, $this->temp_dir)) {
			return;
		}

		$dir = new \DirectoryIterator($dir_path);

		$to_delete = [];

		foreach ($dir as $fileinfo) {
			if ($fileinfo->isDot()) {
				continue;
			}

			if ($fileinfo->isDir()) {
				$this->deltree($fileinfo->getPathname());
			} else {
				$to_delete[] = $fileinfo->getPathname();
			}
		}

		foreach ($to_delete as $pathname) {
			unlink($pathname);
		}

		rmdir($dir_path);
	}

	/**
	 * Gets basic boilerplate for the PHP files that will be created.
	 *
	 * @return string Standard SMF file header.
	 */
	private function smf_file_header(): string
	{
		static $file_template;

		if (!empty($file_template)) {
			return $file_template;
		}

		$settings_defs = Config::getSettingsDefs();

		$license_block = '';

		$keep_line = true;

		foreach (explode("\n", $settings_defs[0]['text']) as $line) {
			if (str_contains($line, 'SMF') || str_contains($line, 'Simple Machines')) {
				$keep_line = true;
			}

			if ($keep_line) {
				$license_block .= $line . "\n";
			}

			if ($line === '/**') {
				$keep_line = false;
			}
		}

		$file_template = implode("\n\n", [
			'<' . '?php',
			trim($license_block),
			'declare(strict_types=1);',
			'namespace SMF\\Unicode;',
			"if (!defined('SMF')) {\n\tdie('No direct access...');\n}",
			'',
		]);

		return $file_template;
	}

	/**
	 * Updates Unicode data functions in their designated files.
	 */
	public function export_funcs_to_file(): void
	{
		foreach ($this->funcs as $func_name => $func_info) {
			if (empty($func_info['data'])) {
				continue;
			}

			$temp_file_path = $this->temp_dir . '/' . $func_info['file'];

			list($func_code, $func_regex) = $this->get_function_code_and_regex($func_name);

			$file_contents = file_get_contents($temp_file_path);

			if (preg_match($func_regex, $file_contents)) {
				file_put_contents($temp_file_path, preg_replace($func_regex, $func_code, $file_contents));
			} else {
				file_put_contents($temp_file_path, $func_code . "\n\n", FILE_APPEND);
			}

			// Free up some memory.
			if ($func_name != 'utf8_combining_classes') {
				unset($this->funcs[$func_name]['data']);
			}
		}
	}

	/**
	 * Builds complete code for the specified element in $this->funcs
	 * to be inserted into the relevant PHP file. Also builds a regex
	 * to check whether a copy of the function is already present
	 * in the file.
	 *
	 * @param string|int $func_name Key of an element in $this->funcs.  If an int is provided, it is considered raw code such as a header, and does not replace a function in the file.
	 *
	 * @return array PHP code and a regular expression.
	 */
	private function get_function_code_and_regex(string|int $func_name): array
	{
		// No function name means data is raw code.
		if (!is_string($func_name)) {
			$func_code = implode("\n\n", $this->funcs[$func_name]['data']);
			$func_regex = $this->funcs[$func_name]['regex'] ?? '/' . preg_quote($func_code, '/') . '/';
		} else {
			// The regex to look for this function in the existing file content.
			$func_regex = '~(/\*([^*]|\*(?!/))*\*/\n)?function ' . $func_name . '\(\): array\n{.+?\n}~s';

			// The PHPDoc comment for this function.
			$func_code = '/**' . implode("\n * ", array_merge(
				[''],
				$this->funcs[$func_name]['desc'],
				[
					'',
					'Developers: Do not update the data in this function manually. Instead,',
					'run "php -f other/update_unicode_data.php" on the command line.',
				],
				empty($this->funcs[$func_name]['return']) ? [] : [
					'',
					'@return ' . implode(' ', $this->funcs[$func_name]['return']),
				],
			)) . "\n */\n";

			// The code for this function.
			$func_code .= implode("\n", [
				'function ' . $func_name . '(): array',
				'{',
				"\t" . 'return [',
				'',
			]);

			$this->build_func_array(
				$func_code,
				$this->funcs[$func_name]['data'],
				$this->funcs[$func_name]['key_type'],
				$this->funcs[$func_name]['val_type'],
			);

			$func_code .= implode("\n", [
				"\t" . '];',
				'}',
			]);
		}

		// Some final tidying.
		$func_code = str_replace('""', "''", $func_code);
		$func_code = preg_replace('/\h+$/m', '', $func_code);

		return [$func_code, $func_regex];
	}

	/**
	 * Helper for get_function_code_and_regex(). Builds the function's data array.
	 *
	 * @param string &$func_code The raw string that contains function code.
	 * @param array $data Data to format as an array.
	 * @param string $key_type How to format the array keys.
	 * @param string $val_type How to format the array values.
	 */
	private function build_func_array(string &$func_code, array $data, string $key_type, string $val_type): void
	{
		static $indent = 2;

		foreach ($data as $key => $value) {
			$func_code .= str_repeat("\t", $indent);

			if (is_int($key)) {
				// do nothing.
			} elseif ($key_type == 'hexchar') {
				$func_code .= '"';

				$key = mb_decode_numericentity(str_replace(' ', '', $key), [0, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');

				foreach (unpack('C*', $key) as $byte_value) {
					$func_code .= '\\x' . strtoupper(dechex($byte_value));
				}

				$func_code .= '" => ';
			} elseif ($key_type == 'string') {
				$func_code .= var_export($key, true) . ' => ';
			}

			if (is_array($value)) {
				if ($val_type == 'string' && count($value) === count($value, COUNT_RECURSIVE)) {
					$nextline = "\n" . str_repeat("\t", $indent + 1);

					$func_code = rtrim($func_code);

					$func_code .= $nextline . implode(' .' . $nextline, array_map(
						function ($v) {
							return var_export($v, true);
						},
						$value,
					));
				} else {
					$func_code .= '[' . "\n";

					$indent++;
					$this->build_func_array($func_code, $value, $key_type, $val_type);
					$indent--;

					$func_code .= str_repeat("\t", $indent) . ']';
				}
			} elseif ($val_type == 'hexchar') {
				$func_code .= '"';

				$value = mb_decode_numericentity(str_replace(' ', '', $value), [0, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');

				foreach (unpack('C*', $value) as $byte_value) {
					$func_code .= '\\x' . strtoupper(dechex($byte_value));
				}

				$func_code .= '"';
			} elseif ($val_type == 'string') {
				$func_code .= var_export($value, true);
			} else {
				$func_code .= $value;
			}

			$func_code .= ',' . "\n";
		}
	}

	/**
	 * Compares version of SMF's local Unicode data with the latest release.
	 *
	 * @return bool Whether SMF should update its local Unicode data or not.
	 */
	private function should_update(): bool
	{
		$this->lookup_ucd_version();

		// We can't do anything if lookup failed.
		if (empty($this->ucd_version)) {
			return false;
		}

		// If this file is missing, force an update.
		if (!@include_once($this->unicodedir . DIRECTORY_SEPARATOR . 'Metadata.php')) {
			return true;
		}

		return version_compare($this->ucd_version, SMF_UNICODE_VERSION, '>=');
	}

	/**
	 * Sets $this->ucd_version to latest version number of the UCD.
	 */
	private function lookup_ucd_version(): bool
	{
		if (!empty($this->ucd_version)) {
			return true;
		}

		$local_file = $this->fetch_unicode_file('ReadMe.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		preg_match('/Version\s+(\d+(?:\.\d+)*)/', file_get_contents($local_file), $matches);

		if (empty($matches[1])) {
			return false;
		}

		$this->ucd_version = implode('.', array_pad(explode('.', $matches[1]), 4, '0'));

		// Update this while we are at it.
		foreach ($this->funcs as $func_name => &$func_info) {
			if ($func_info['file'] === 'Metadata.php') {
				$func_info['data'][0] = str_replace('0.0.0.0', $this->ucd_version, $func_info['data'][0]);
				break;
			}
		}

		return true;
	}

	/**
	 * Processes DerivedNormalizationProps.txt in order to populate
	 * $this->derived_normalization_props.
	 */
	private function process_derived_normalization_props(): bool
	{
		$local_file = $this->fetch_unicode_file('DerivedNormalizationProps.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if (!isset($this->derived_normalization_props[$fields[1]])) {
				$this->derived_normalization_props[$fields[1]] = [];
			}

			if (!str_contains($fields[0], '..')) {
				$entities = ['&#x' . $fields[0] . ';'];
			} else {
				$entities = [];

				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					$entities[] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
				}
			}

			$value = '';

			if (!isset($fields[2])) {
				$value = 'SAME';
			} elseif (in_array($fields[1], ['FC_NFKC', 'NFKC_CF'])) {
				$value = trim($fields[2]) !== '' ? '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';' : '';
			} else {
				$value = $fields[2];
			}

			foreach ($entities as $entity) {
				$this->derived_normalization_props[$fields[1]][$entity] = $value === 'SAME' ? $entity : $value;
			}
		}

		return true;
	}

	/**
	 * Processes UnicodeData.txt in order to populate $this->char_data,
	 * $this->full_decomposition_maps, and the 'data' element of most elements
	 * of $this->funcs.
	 */
	private function process_main_unicode_data(): bool
	{
		$local_file = $this->fetch_unicode_file('UnicodeData.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if (!empty($fields[3])) {
				$this->funcs['utf8_combining_classes']['data']['&#x' . $fields[0] . ';'] = $fields[3];
			}

			// Uppercase maps.
			if ($fields[12] !== '') {
				$this->funcs['utf8_strtoupper_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[12] . ';';
			}

			// Lowercase maps.
			if ($fields[13] !== '') {
				$this->funcs['utf8_strtolower_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[13] . ';';
			}

			// Titlecase maps, where different from uppercase maps.
			if ($fields[14] !== '' && $fields[14] !== $fields[12]) {
				$this->funcs['utf8_titlecase_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[14] . ';';
			}

			// Remember this character's general category for later.
			$this->char_data['&#x' . $fields[0] . ';']['General_Category'] = $fields[2];

			if ($fields[5] === '') {
				continue;
			}

			// All canonical decompositions AND all compatibility decompositions.
			$this->full_decomposition_maps['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim(strip_tags($fields[5]))) . ';';

			// Just the canonical decompositions.
			if (!str_contains($fields[5], '<')) {
				$this->funcs['utf8_normalize_d_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', $fields[5]) . ';';
			}
		}

		return true;
	}

	/**
	 * Processes SpecialCasing.txt and CaseFolding.txt in order to get
	 * finalized versions of all case conversion data.
	 */
	private function process_casing_data(): bool
	{
		// Full case conversion maps are the same as the simple ones, unless they're not.
		$this->funcs['utf8_strtoupper_maps']['data'] = $this->funcs['utf8_strtoupper_simple_maps']['data'];
		$this->funcs['utf8_strtolower_maps']['data'] = $this->funcs['utf8_strtolower_simple_maps']['data'];
		$this->funcs['utf8_titlecase_maps']['data'] = $this->funcs['utf8_titlecase_simple_maps']['data'];

		// Deal with the special casing data.
		$local_file = $this->fetch_unicode_file('SpecialCasing.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			// Unconditional mappings.
			// Note: conditional mappings need to be handled by more complex code.
			if (empty($fields[4])) {
				$this->funcs['utf8_strtolower_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[1])) . ';';

				$this->funcs['utf8_strtoupper_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[3])) . ';';

				// Titlecase only where different from uppercase.
				if ($fields[3] !== $fields[2]) {
					$this->funcs['utf8_titlecase_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
				}
			}
		}

		ksort($this->funcs['utf8_strtolower_maps']['data']);
		ksort($this->funcs['utf8_strtoupper_maps']['data']);
		ksort($this->funcs['utf8_titlecase_maps']['data']);

		// Deal with the case folding data.
		$local_file = $this->fetch_unicode_file('CaseFolding.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			// Full casefolding.
			if (in_array($fields[1], ['C', 'F'])) {
				$this->funcs['utf8_casefold_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
			}

			// Simple casefolding.
			if (in_array($fields[1], ['C', 'S'])) {
				$this->funcs['utf8_casefold_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
			}
		}

		return true;
	}

	/**
	 * Finalizes all the decomposition forms.
	 *
	 * This is necessary because some characters decompose to other characters
	 * that themselves decompose further.
	 */
	private function finalize_decomposition_forms(): bool
	{
		// Iterate until we reach the final decomposition forms.
		// First we do the compatibility decomposition forms.
		$changed = true;

		while ($changed) {
			$temp = [];

			foreach ($this->full_decomposition_maps as $composed => $decomposed) {
				$parts = str_contains($decomposed, ' ') ? explode(' ', $decomposed) : (array) $decomposed;

				foreach ($parts as $partnum => $hex) {
					if (isset($this->full_decomposition_maps[$hex])) {
						$parts[$partnum] = $this->full_decomposition_maps[$hex];
					}
				}

				$decomposed = implode(' ', $parts);
				unset($parts);

				$temp[$composed] = $decomposed;
			}

			$changed = $this->full_decomposition_maps !== $temp;

			$this->full_decomposition_maps = $temp;
		}

		// Same as above, but using only canonical decompositions.
		$changed = true;
		$iteration = 0;

		while ($changed) {
			$temp = [];

			foreach ($this->funcs['utf8_normalize_d_maps']['data'] as $composed => $decomposed) {
				if ($iteration === 0 && !in_array($composed, $this->derived_normalization_props['Full_Composition_Exclusion'])) {
					$this->funcs['utf8_compose_maps']['data'][$decomposed] = $composed;
				}

				$parts = str_contains($decomposed, ' ') ? explode(' ', $decomposed) : (array) $decomposed;

				foreach ($parts as $partnum => $hex) {
					if (isset($this->funcs['utf8_normalize_d_maps']['data'][$hex])) {
						$parts[$partnum] = $this->funcs['utf8_normalize_d_maps']['data'][$hex];
					}
				}

				$decomposed = implode(' ', $parts);
				unset($parts);

				$temp[$composed] = $decomposed;
			}

			$changed = $this->funcs['utf8_normalize_d_maps']['data'] !== $temp;

			$this->funcs['utf8_normalize_d_maps']['data'] = $temp;
			$iteration++;
		}

		// Avoid bloat.
		$this->funcs['utf8_normalize_kd_maps']['data'] = array_diff_assoc($this->full_decomposition_maps, $this->funcs['utf8_normalize_d_maps']['data']);

		return true;
	}

	/**
	 * Builds regular expressions for normalization quick check.
	 */
	private function build_quick_check(): bool
	{
		foreach (['NFC_QC', 'NFKC_QC', 'NFD_QC', 'NFKD_QC', 'Changes_When_NFKC_Casefolded'] as $prop) {
			$current_range = ['start' => null, 'end' => null];

			foreach ($this->derived_normalization_props[$prop] as $entity => $nm) {
				$range_string = '';

				$ord = hexdec(trim($entity, '&#x;'));

				if (is_null($current_range['start'])) {
					$current_range['start'] = $ord;
				}

				if (!isset($current_range['end']) || $ord == $current_range['end'] + 1) {
					$current_range['end'] = $ord;
				} else {
					$range_string .= '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
					}

					$current_range = ['start' => $ord, 'end' => $ord];

					$this->funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
				}
			}

			if (isset($current_range['start'])) {
				$range_string = '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
				}

				$this->funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
			}
		}

		return true;
	}

	/**
	 * Builds regular expression classes for extended Unicode properties.
	 */
	private function build_regex_properties(): bool
	{
		foreach ($this->funcs['utf8_regex_properties']['propfiles'] as $filename) {
			$local_file = $this->fetch_unicode_file($filename, self::DATA_URL_UCD);

			if (empty($local_file)) {
				return false;
			}

			foreach (file($local_file) as $line) {
				$line = substr($line, 0, strcspn($line, '#'));

				if (!str_contains($line, ';')) {
					continue;
				}

				$fields = explode(';', $line);

				foreach ($fields as $key => $value) {
					$fields[$key] = trim($value);
				}

				if (in_array($fields[1], $this->funcs['utf8_regex_properties']['props'])) {
					if (!isset($this->funcs['utf8_regex_properties']['data'][$fields[1]])) {
						$this->funcs['utf8_regex_properties']['data'][$fields[1]] = [];
					}

					$this->funcs['utf8_regex_properties']['data'][$fields[1]][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';

					$this->funcs['utf8_regex_properties']['data'][$fields[1]] = array_unique($this->funcs['utf8_regex_properties']['data'][$fields[1]]);
				}

				// We also track 'Default_Ignorable_Code_Point' property in a separate array.
				if ($fields[1] === 'Default_Ignorable_Code_Point') {
					if (!str_contains($fields[0], '..')) {
						$this->funcs['utf8_default_ignorables']['data'][] = '&#x' . $fields[0] . ';';
					} else {
						list($start, $end) = explode('..', $fields[0]);

						$ord_s = hexdec($start);
						$ord_e = hexdec($end);

						$ord = $ord_s;

						while ($ord <= $ord_e) {
							$this->funcs['utf8_default_ignorables']['data'][] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
						}
					}
				}
			}
		}

		ksort($this->funcs['utf8_regex_properties']['data']);

		return true;
	}

	/**
	 * Builds regular expression classes for filtering variation selectors.
	 */
	private function build_regex_variation_selectors(): bool
	{
		$files = ['StandardizedVariants.txt', 'emoji/emoji-variation-sequences.txt'];

		foreach ($files as $filename) {
			$local_file = $this->fetch_unicode_file($filename, self::DATA_URL_UCD);

			if (empty($local_file)) {
				return false;
			}

			foreach (file($local_file) as $line) {
				$line = substr($line, 0, strcspn($line, '#'));

				if (!str_contains($line, ';')) {
					continue;
				}

				$fields = explode(';', $line);

				foreach ($fields as $key => $value) {
					$fields[$key] = trim($value);
				}

				list($base_char, $variation_selector) = explode(' ', $fields[0]);

				$this->funcs['utf8_regex_variation_selectors']['data']['\\x{' . $variation_selector . '}'][] = hexdec($base_char);
			}
		}

		foreach ($this->funcs['utf8_regex_variation_selectors']['data'] as $variation_selector => $ords) {
			$class_string = '';

			$current_range = ['start' => null, 'end' => null];

			foreach ($ords as $ord) {
				if (is_null($current_range['start'])) {
					$current_range['start'] = $ord;
				}

				if (!isset($current_range['end']) || $ord == $current_range['end'] + 1) {
					$current_range['end'] = $ord;

					continue;
				}

				$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
				}

				$current_range = ['start' => $ord, 'end' => $ord];
			}

			if (isset($current_range['start'])) {
				$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
				}
			}

			// As of Unicode 14.0, \x{FE0E} and \x{FE0F} work with identical ranges of base characters.
			if (($identical = array_search($class_string, $this->funcs['utf8_regex_variation_selectors']['data'])) !== false) {
				unset(
					$this->funcs['utf8_regex_variation_selectors']['data'][$identical],
					$this->funcs['utf8_regex_variation_selectors']['data'][$variation_selector]
				);

				$compound_selector = [$identical, $variation_selector];
				sort($compound_selector);

				$variation_selector = implode('', $compound_selector);
			}

			$this->funcs['utf8_regex_variation_selectors']['data'][$variation_selector] = $class_string;
		}

		foreach ($this->funcs['utf8_regex_variation_selectors']['data'] as $variation_selector => $class_string) {
			$this->funcs['utf8_regex_variation_selectors']['data'][$variation_selector] = array_unique(preg_split('/(?<=})(?=\\\x{)/', $class_string));
		}

		krsort($this->funcs['utf8_regex_variation_selectors']['data']);

		return true;
	}

	/**
	 * Helper function for build_regex_joining_type and build_regex_indic.
	 */
	private function build_script_stats(): bool
	{
		$local_file = $this->fetch_unicode_file('PropertyValueAliases.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if ($fields[0] !== 'sc') {
				continue;
			}

			$this->script_aliases[$fields[1]] = $fields[2];
		}

		$local_file = $this->fetch_unicode_file('Scripts.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if (in_array($fields[1], ['Common', 'Inherited'])) {
				continue;
			}

			if (!str_contains($fields[0], '..')) {
				$this->char_data['&#x' . $fields[0] . ';']['scripts'][] = $fields[1];
			} else {
				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					$this->char_data['&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';']['scripts'][] = $fields[1];
				}
			}
		}

		$local_file = $this->fetch_unicode_file('ScriptExtensions.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$char_scripts = [];

			foreach (explode(' ', $fields[1]) as $alias) {
				if (!in_array($this->script_aliases[$alias], ['Common', 'Inherited'])) {
					$char_scripts[] = $this->script_aliases[$alias];
				}
			}

			if (!str_contains($fields[0], '..')) {
				foreach ($char_scripts as $char_script) {
					$this->char_data['&#x' . $fields[0] . ';']['scripts'][] = $char_script;
				}
			} else {
				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					foreach ($char_scripts as $char_script) {
						$this->char_data['&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';']['scripts'][] = $char_script;
					}
				}
			}
		}

		$local_file = $this->fetch_unicode_file('DerivedAge.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$fields[1] = (float) $fields[1];

			if (!str_contains($fields[0], '..')) {
				$entity = '&#x' . $fields[0] . ';';

				if (empty($this->char_data[$entity]['scripts'])) {
					continue;
				}

				foreach ($this->char_data[$entity]['scripts'] as $char_script) {
					if (!isset($this->script_stats[$char_script])) {
						$this->script_stats[$char_script]['age'] = (float) $fields[1];
						$this->script_stats[$char_script]['count'] = 1;
					} else {
						$this->script_stats[$char_script]['age'] = min((float) $fields[1], $this->script_stats[$char_script]['age']);
						$this->script_stats[$char_script]['count']++;
					}
				}
			} else {
				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					$entity = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';

					if (empty($this->char_data[$entity]['scripts'])) {
						continue;
					}

					foreach ($this->char_data[$entity]['scripts'] as $char_script) {
						if (!isset($this->script_stats[$char_script])) {
							$this->script_stats[$char_script]['age'] = $fields[1];
							$this->script_stats[$char_script]['count'] = 1;
						} else {
							$this->script_stats[$char_script]['age'] = min($fields[1], $this->script_stats[$char_script]['age']);
							$this->script_stats[$char_script]['count']++;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Builds regex classes for join control tests in utf8_sanitize_invisibles.
	 * Specifically, for cursive scripts like Arabic.
	 */
	private function build_regex_joining_type(): bool
	{
		$local_file = $this->fetch_unicode_file('extracted/DerivedJoiningType.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			switch ($fields[1]) {
				case 'C':
					$joining_type = 'Join_Causing';
					break;

				case 'D':
					$joining_type = 'Dual_Joining';
					break;

				case 'R':
					$joining_type = 'Right_Joining';
					break;

				case 'L':
					$joining_type = 'Left_Joining';
					break;

				case 'T':
					$joining_type = 'Transparent';
					break;

				default:
					$joining_type = null;
					break;
			}

			if (!isset($joining_type)) {
				continue;
			}

			$entity = '&#x' . substr($fields[0], 0, strcspn($fields[0], '.')) . ';';

			if (empty($this->char_data[$entity]['scripts'])) {
				continue;
			}

			foreach ($this->char_data[$entity]['scripts'] as $char_script) {
				if (!isset($this->funcs['utf8_regex_joining_type']['data'][$char_script]['stats'])) {
					$this->funcs['utf8_regex_joining_type']['data'][$char_script]['stats'] = $this->script_stats[$char_script];
				}

				if (!isset($this->funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type])) {
					$this->funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type] = [];
				}

				$this->funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			}
		}
		// This sort works decently well to ensure widely used scripts are ranked before rare scripts.
		uasort($this->funcs['utf8_regex_joining_type']['data'], function ($a, $b) {
			if ($a['stats']['age'] == $b['stats']['age']) {
				return $b['stats']['count'] - $a['stats']['count'];
			}

			return $a['stats']['age'] - $b['stats']['age'];
		});

		foreach ($this->funcs['utf8_regex_joining_type']['data'] as $char_script => &$joining_types) {
			unset($this->funcs['utf8_regex_joining_type']['data'][$char_script]['stats'], $joining_types['stats']);

			// If the only joining type in this script is transparent, we don't care about it.
			if (array_keys($joining_types) === ['Transparent']) {
				unset($this->funcs['utf8_regex_joining_type']['data'][$char_script]);

				continue;
			}

			foreach ($joining_types as $joining_type => &$value) {
				sort($value);
			}
		}

		return true;
	}

	/**
	 * Builds regex classes for join control tests in utf8_sanitize_invisibles.
	 * Specifically, for Indic scripts like Devanagari.
	 */
	private function build_regex_indic(): bool
	{
		$local_file = $this->fetch_unicode_file('IndicSyllabicCategory.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$insc = $fields[1];

			if (!in_array($insc, ['Virama', 'Vowel_Dependent'])) {
				continue;
			}

			$char_scripts = $this->char_data['&#x' . substr($fields[0], 0, strcspn($fields[0], '.')) . ';']['scripts'];

			if (empty($char_scripts)) {
				continue;
			}

			foreach ($char_scripts as $char_script) {
				if (!isset($this->funcs['utf8_regex_indic']['data'][$char_script]['stats'])) {
					$this->funcs['utf8_regex_indic']['data'][$char_script]['stats'] = $this->script_stats[$char_script];
				}

				if (!isset($this->funcs['utf8_regex_indic']['data'][$char_script][$insc])) {
					$this->funcs['utf8_regex_indic']['data'][$char_script][$insc] = [];
				}

				$this->funcs['utf8_regex_indic']['data'][$char_script][$insc][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			}
		}
		// Again, sort commonly used scripts before rare scripts.
		uasort($this->funcs['utf8_regex_indic']['data'], function ($a, $b) {
			if ($a['stats']['age'] == $b['stats']['age']) {
				return $b['stats']['count'] - $a['stats']['count'];
			}

			return $a['stats']['age'] - $b['stats']['age'];
		});

		// We only want scripts with viramas.
		foreach ($this->funcs['utf8_regex_indic']['data'] as $char_script => $inscs) {
			unset($this->funcs['utf8_regex_indic']['data'][$char_script]['stats'], $inscs['stats']);

			if (!isset($inscs['Virama'])) {
				unset($this->funcs['utf8_regex_indic']['data'][$char_script]);

				continue;
			}
		}

		// Now add some more classes that we need for each script.
		foreach ($this->char_data as $entity => $info) {
			if (empty($info['scripts'])) {
				continue;
			}

			$ord = hexdec(trim($entity, '&#x;'));

			foreach ($info['scripts'] as $char_script) {
				if (!isset($this->funcs['utf8_regex_indic']['data'][$char_script])) {
					continue;
				}

				$this->funcs['utf8_regex_indic']['data'][$char_script]['All'][] = $ord;

				if (empty($info['General_Category'])) {
					continue;
				}

				if ($info['General_Category'] == 'Mn') {
					$this->funcs['utf8_regex_indic']['data'][$char_script]['Nonspacing_Mark'][] = $ord;

					if (!empty($this->funcs['utf8_combining_classes']['data'][$entity])) {
						$this->funcs['utf8_regex_indic']['data'][$char_script]['Nonspacing_Combining_Mark'][] = $ord;
					}
				} elseif (str_starts_with($info['General_Category'], 'L')) {
					$this->funcs['utf8_regex_indic']['data'][$char_script]['Letter'][] = $ord;
				}
			}
		}

		foreach ($this->funcs['utf8_regex_indic']['data'] as $char_script => $inscs) {
			foreach ($inscs as $insc => $value) {
				sort($value);

				if (!in_array($insc, ['All', 'Letter', 'Nonspacing_Mark', 'Nonspacing_Combining_Mark'])) {
					continue;
				}

				$class_string = '';

				$current_range = ['start' => null, 'end' => null];

				foreach ($value as $ord) {
					if (is_null($current_range['start'])) {
						$current_range['start'] = $ord;
					}

					if (!isset($current_range['end']) || $ord == $current_range['end'] + 1) {
						$current_range['end'] = $ord;

						continue;
					}

					$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
					}

					$current_range = ['start' => $ord, 'end' => $ord];
				}

				if (isset($current_range['start'])) {
					$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex((int) $current_range['end']))) . '}';
					}
				}

				$this->funcs['utf8_regex_indic']['data'][$char_script][$insc] = array_unique(preg_split('/(?<=})(?=\\\x{)/', $class_string));
			}

			ksort($this->funcs['utf8_regex_indic']['data'][$char_script]);
		}

		return true;
	}

	/**
	 * Builds maps and regex classes for IDNA purposes.
	 */
	private function build_idna(): bool
	{
		$local_file = $this->fetch_unicode_file('IdnaMappingTable.txt', self::DATA_URL_IDNA);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (!str_contains($line, ';')) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = preg_replace('/\b(0(?!\b))+/', '', trim($value));
			}

			if (!str_contains($fields[0], '..')) {
				$entities = ['&#x' . $fields[0] . ';'];
			} else {
				$entities = [];

				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					$entities[] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
				}
			}

			if ($fields[1] === 'mapped') {
				foreach ($entities as $entity) {
					$this->funcs['idna_maps']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';
				}
			} elseif ($fields[1] === 'deviation') {
				foreach ($entities as $entity) {
					$this->funcs['idna_maps_deviation']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';
				}

				$this->funcs['idna_regex']['data']['deviation'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			} elseif ($fields[1] === 'ignored') {
				$this->funcs['idna_regex']['data']['ignored'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			} elseif ($fields[1] === 'disallowed') {
				if (in_array('&#xD800;', $entities)) {
					continue;
				}

				$this->funcs['idna_regex']['data']['disallowed'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			} elseif ($fields[1] === 'disallowed_STD3_mapped') {
				foreach ($entities as $entity) {
					$this->funcs['idna_maps_not_std3']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';
				}

				$this->funcs['idna_regex']['data']['disallowed_std3'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			} elseif ($fields[1] === 'disallowed_STD3_valid') {
				$this->funcs['idna_regex']['data']['disallowed_std3'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
			}
		}

		return true;
	}

	/**
	 * Builds pluralization rules for different languages.
	 */
	private function build_plurals(): bool
	{
		$sourcefiles = [
			'cardinal' => 'cldr-core/supplemental/plurals.json',
			'ordinal' => 'cldr-core/supplemental/ordinals.json',
		];

		foreach ($sourcefiles as $type => $sourcefile) {
			$local_file = $this->fetch_unicode_file($sourcefile, self::DATA_URL_CLDR);

			if (empty($local_file)) {
				return false;
			}

			$plurals = json_decode(file_get_contents($local_file), true);

			foreach ($plurals['supplemental']['plurals-type-' . $type] as $lang => $rules) {
				$this->funcs['plurals']['data'][$lang][$type] = [];

				foreach ($rules as $key => $rule) {
					$key = str_replace('pluralRule-count-', '', $key);

					$rule = trim(substr($rule, 0, strpos($rule, '@')));

					$rule = preg_replace(
						[
							'/(?<![!=])=(?=\s)/',
							'/([!=]=)=/',
							'/(?<![$])\b[nivwftce]\b/',
							'/[$]e\b/',
							'/\band\b/',
							'/\s+/',
						],
						[
							'==',
							'$1',
							'\\$$0',
							'\\$c',
							'&&',
							' ',
						],
						$rule,
					);

					if (preg_match('/\$([nivwftc]) % (\d+) == 0 && \$\1 % (\d+) ==/', $rule, $matches)) {
						$step = min((int) $matches[2], (int) $matches[3]);
					} else {
						$step = 1;
					}

					$rule = preg_replace_callback('/(\d+)\.\.(\d+)/', fn ($matches) => implode(',', range($matches[1], $matches[2], $step)), $rule);

					$rule = str_replace('=in_array', 'in_array', preg_replace('/(\$[nivwftc](?: % \d+)?) ([!=])= ((?:\d+,\s*)+\d+)/', '$2in_array($1, [$3])', $rule));

					if ($key === 'other' && $rule === '') {
						$rule = 'true';
					}

					$this->funcs['plurals']['data'][$lang][$type][$key] = 'fn ($n, $i, $v, $w, $f, $t, $c) => ' . $rule;
				}
			}
		}

		return true;
	}

	/**
	 * Builds information about different currencies.
	 */
	private function build_currencies(): bool
	{
		$sourcefile = 'cldr-core/supplemental/currencyData.json';

		$local_file = $this->fetch_unicode_file($sourcefile, self::DATA_URL_CLDR);

		if (empty($local_file)) {
			return false;
		}

		$data = json_decode(file_get_contents($local_file), true);

		foreach ($data['supplemental']['currencyData']['region'] as $cc => $region_currency_info) {
			foreach ($region_currency_info as $region_currencies) {
				foreach ($region_currencies as $currency_code => $currency_info) {
					if (!empty($currency_info['_to'])) {
						continue;
					}

					if (isset($currency_info['_tender']) && $currency_info['_tender'] === 'false') {
						continue;
					}

					$this->funcs['country_currencies']['data'][$cc][] = var_export($currency_code, true);

					// Set these to the default for now.
					$this->funcs['currencies']['data'][$currency_code]['digits'] = 2;
					$this->funcs['currencies']['data'][$currency_code]['rounding'] = 0;
				}
			}
		}

		foreach ($data['supplemental']['currencyData']['fractions'] as $currency_code => $fractions) {
			if (!isset($this->funcs['currencies']['data'][$currency_code]) && $currency_code !== 'DEFAULT') {
				continue;
			}

			foreach (['_digits', '_rounding', '_cashDigits', '_cashRounding'] as $key) {
				if (!isset($fractions[$key])) {
					continue;
				}

				$this->funcs['currencies']['data'][$currency_code][substr($key, 1)] = $fractions[$key];
			}
		}

		ksort($this->funcs['currencies']['data']);
		ksort($this->funcs['country_currencies']['data']);

		return true;
	}

	/**
	 * Builds confusables data for the spoof detector.
	 */
	private function build_confusables(): bool
	{
		$local_file = $this->fetch_unicode_file('confusables.txt', self::DATA_URL_SECURITY);

		if (empty($local_file)) {
			return false;
		}

		// Compile the character => skeleton maps.
		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$fields[1] = explode(' ', $fields[1]);

			foreach ($fields[1] as &$hexcode) {
				$hexcode = str_pad($hexcode, 6, '0', STR_PAD_LEFT);
			}

			$fields[1] = implode(' ', $fields[1]);

			$this->funcs['utf8_confusables']['data']['&#x' . str_pad($fields[0], 6, '0', STR_PAD_LEFT) . ';'] = '&#x' . str_replace(' ', '; &#x', $fields[1]) . ';';
		}

		ksort($this->funcs['utf8_confusables']['data']);

		return true;
	}

	/**
	 * Builds confusables data for the spoof detector.
	 */
	private function build_compressed_character_script_data(): bool
	{
		// Once we have identified a pair of potentially confusable strings, we need
		// analyze their script sets in order to figure out what to do with them.
		// For that, we need more data...
		$local_file = $this->fetch_unicode_file('PropertyValueAliases.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		$script_aliases = [];
		$scripts_data = [];

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if ($fields[0] !== 'sc') {
				continue;
			}

			$script_aliases[$fields[1]] = $fields[2];
		}

		$local_file = $this->fetch_unicode_file('Scripts.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if (strpos($fields[0], '..') === false) {
				$scripts_data['&#x' . str_pad($fields[0], 6, '0', STR_PAD_LEFT) . ';'][] = $fields[1];
			} else {
				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					$scripts_data['&#x' . strtoupper(sprintf('%06s', dechex($ord++))) . ';'][] = $fields[1];
				}
			}
		}

		$local_file = $this->fetch_unicode_file('ScriptExtensions.txt', self::DATA_URL_UCD);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$char_scripts = [];

			foreach (explode(' ', $fields[1]) as $alias) {
				$char_scripts[] = $script_aliases[$alias];
			}

			if (strpos($fields[0], '..') === false) {
				foreach ($char_scripts as $char_script) {
					$scripts_data['&#x' . str_pad($fields[0], 6, '0', STR_PAD_LEFT) . ';'][] = $char_script;
				}
			} else {
				list($start, $end) = explode('..', $fields[0]);

				$ord_s = hexdec($start);
				$ord_e = hexdec($end);

				$ord = $ord_s;

				while ($ord <= $ord_e) {
					foreach ($char_scripts as $char_script) {
						$scripts_data['&#x' . strtoupper(sprintf('%06s', dechex($ord++))) . ';'][] = $char_script;
					}
				}
			}
		}

		foreach ($scripts_data as &$char_scripts) {
			$char_scripts = array_unique($char_scripts);
		}

		ksort($scripts_data);

		$prev = [];

		foreach ($scripts_data as $char => $scripts) {
			if (isset($prev['scripts']) && $prev['scripts'] !== $scripts) {
				$this->funcs['utf8_character_scripts']['data'][$prev['char']] = preg_replace(
					[
						'/\d+ => /',
						'/\n/',
					],
					[
						'',
						"\n\t\t",
					],
					Config::varExport($prev['scripts']),
				);
			}

			$prev = ['char' => $char, 'scripts' => $scripts];
		}

		return true;
	}

	/**
	 * Builds regex to distinguish characters' Identifier_Status value.
	 */
	private function build_regex_identifier_status(): bool
	{
		// We also need to distinguish characters' Identifier_Status value.
		$local_file = $this->fetch_unicode_file('IdentifierStatus.txt', self::DATA_URL_SECURITY);

		if (empty($local_file)) {
			return false;
		}

		foreach (file($local_file) as $line) {
			$line = substr($line, 0, strcspn($line, '#'));

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$this->funcs['utf8_regex_identifier_status']['data'][$fields[1]][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
		}

		return true;
	}
}

?>