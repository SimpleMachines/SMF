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

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
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
	public const DATA_URL_UCD = 'https://unicode.org/Public/UCD/latest/ucd';
	public const DATA_URL_IDNA = 'https://www.unicode.org/Public/idna/latest';

	/**
	 * @var string The latest official release of the Unicode Character Database.
	 */
	public $ucd_version = '';

	/**
	 * @var string Path to temporary working directory.
	 */
	public $temp_dir = '';

	/**
	 * @var string Convenince alias of Config::$sourcedir . '/Unicode'.
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
			],
			'props' => [
				'Bidi_Control',
				'Case_Ignorable',
				'Cn',
				'Default_Ignorable_Code_Point',
				'Emoji',
				'Emoji_Modifier',
				'Ideographic',
				'Join_Control',
				'Regional_Indicator',
				'Variation_Selector',
			],
			'desc' => [
				'Helper function for utf8_sanitize_invisibles and utf8_convert_case.',
				'',
				'Character class lists compiled from:',
				'https://unicode.org/Public/UNIDATA/DerivedCoreProperties.txt',
				'https://unicode.org/Public/UNIDATA/PropList.txt',
				'https://unicode.org/Public/UNIDATA/emoji/emoji-data.txt',
				'https://unicode.org/Public/UNIDATA/extracted/DerivedGeneralCategory.txt',
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
				'https://unicode.org/Public/UNIDATA/StandardizedVariants.txt',
				'https://unicode.org/Public/UNIDATA/emoji/emoji-variation-sequences.txt',
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
				'https://unicode.org/Public/UNIDATA/extracted/DerivedJoiningType.txt',
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
				'https://unicode.org/Public/UNIDATA/extracted/DerivedCombiningClass.txt',
				'https://unicode.org/Public/UNIDATA/IndicSyllabicCategory.txt',
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
				'https://unicode.org/Public/UNIDATA/extracted/DerivedNormalizationProps.txt',
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
		],
		self::DATA_URL_IDNA => [
			'IdnaMappingTable.txt',
		],
	];

	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		/*****************
		 * Part 1: Setup *
		 *****************/
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

		foreach ($this->funcs as $func_name => &$func_info) {
			$file_paths['final'] = implode(DIRECTORY_SEPARATOR, [$this->unicodedir, $func_info['file']]);

			if (!file_exists($file_paths['final'])) {
				touch($file_paths['final']);
			}

			if (!is_file($file_paths['final']) || !Utils::makeWritable($file_paths['final'])) {
				Lang::load('Errors');
				ErrorHandler::log(sprintf(Lang::$txt['unicode_update_failed'], $this->unicodedir));

				return true;
			}

			$file_paths['temp'] = implode(DIRECTORY_SEPARATOR, [$this->temp_dir, $func_info['file']]);

			if (!file_exists($file_paths['temp'])) {
				touch($file_paths['temp']);
			}

			if (!is_file($file_paths['temp']) || !Utils::makeWritable($file_paths['temp'])) {
				Lang::load('Errors');
				ErrorHandler::log(sprintf(Lang::$txt['unicode_update_failed'], $this->temp_dir));

				return true;
			}

			$file_contents['temp'] = file_get_contents($file_paths['temp']);

			if (empty($file_contents['temp']) || strpos($file_contents['temp'], 'namespace SMF\\Unicode;') === false) {
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

		/*********************************************
		 * Part 2: Normalization, case folding, etc. *
		 *********************************************/
		$success = $this->process_derived_normalization_props() & $success;
		$success = $this->process_main_unicode_data() & $success;
		$success = $this->process_casing_data() & $success;
		$success = $this->finalize_decomposition_forms() & $success;

		$this->full_decomposition_maps = [];

		$this->export_funcs_to_file();

		/***********************************
		 * Part 3: Regular expression data *
		 ***********************************/
		$success = $this->build_quick_check() & $success;

		$this->derived_normalization_props = [];

		$success = $this->build_regex_properties() & $success;
		$success = $this->build_regex_variation_selectors() & $success;
		$success = $this->build_script_stats() & $success;
		$success = $this->build_regex_joining_type() & $success;
		$success = $this->build_regex_indic() & $success;

		unset($this->funcs['utf8_combining_classes']['data']);

		$this->export_funcs_to_file();

		/*********************************
		 * Part 4: IDNA maps and regexes *
		 *********************************/
		$success = $this->build_idna() & $success;

		$this->export_funcs_to_file();

		/*******************
		 * Part 5: Wrapup. *
		 *******************/
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
	private function make_temp_dir()
	{
		if (empty($this->temp_dir)) {
			$this->temp_dir = rtrim(Config::getTempDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Unicode';

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
	 * @return string Path to locally saved copy of the file.
	 */
	private function fetch_unicode_file($filename, $data_url)
	{
		$filename = ltrim($filename, '\\/');
		$file_url_name = strtr($filename, ['\\' => '/']);
		$file_local_name = strtr($filename, ['\\' => DIRECTORY_SEPARATOR, '/' => DIRECTORY_SEPARATOR]);

		switch ($data_url) {
			case self::DATA_URL_IDNA:
				$sub_dir = 'idna';
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
	private function deltree($dir_path)
	{
		// For safety.
		if (strpos($dir_path, $this->temp_dir) !== 0) {
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
	private function smf_file_header()
	{
		static $file_template;

		if (!empty($file_template)) {
			return $file_template;
		}

		$settings_defs = Config::getSettingsDefs();

		$license_block = '';

		$keep_line = true;

		foreach (explode("\n", $settings_defs[0]['text']) as $line) {
			if (strpos($line, 'SMF') !== false || strpos($line, 'Simple Machines') !== false) {
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
	public function export_funcs_to_file()
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
	 * to check whether a copy of the the function is already present
	 * in the file.
	 *
	 * @param string $func_name Key of an element in $this->funcs.
	 *
	 * @return array PHP code and a regular expression.
	 */
	private function get_function_code_and_regex($func_name)
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
	private function build_func_array(&$func_code, $data, $key_type, $val_type)
	{
		static $indent = 2;

		foreach ($data as $key => $value) {
			$func_code .= str_repeat("\t", $indent);

			if ($key_type == 'hexchar') {
				$func_code .= '"';

				$key = mb_decode_numericentity(str_replace(' ', '', $key), [0, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');

				foreach (unpack('C*', $key) as $byte_value) {
					$func_code .= '\\x' . strtoupper(dechex($byte_value));
				}

				$func_code .= '" => ';
			} elseif ($key_type == 'string' && !is_int($key)) {
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
	private function should_update()
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
	private function lookup_ucd_version()
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
	private function process_derived_normalization_props()
	{
		$local_file = $this->fetch_unicode_file('DerivedNormalizationProps.txt', self::DATA_URL_UCD);

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

			if (!isset($this->derived_normalization_props[$fields[1]])) {
				$this->derived_normalization_props[$fields[1]] = [];
			}

			if (strpos($fields[0], '..') === false) {
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
	private function process_main_unicode_data()
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
			if (strpos($fields[5], '<') === false) {
				$this->funcs['utf8_normalize_d_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', $fields[5]) . ';';
			}
		}

		return true;
	}

	/**
	 * Processes SpecialCasing.txt and CaseFolding.txt in order to get
	 * finalized versions of all case conversion data.
	 */
	private function process_casing_data()
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

			if (strpos($line, ';') === false) {
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

			if (strpos($line, ';') === false) {
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
	private function finalize_decomposition_forms()
	{
		// Iterate until we reach the final decomposition forms.
		// First we do the compatibility decomposition forms.
		$changed = true;

		while ($changed) {
			$temp = [];

			foreach ($this->full_decomposition_maps as $composed => $decomposed) {
				$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

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

				$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

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
	private function build_quick_check()
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
					$range_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
					}

					$current_range = ['start' => $ord, 'end' => $ord];

					$this->funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
				}
			}

			if (isset($current_range['start'])) {
				$range_string = '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
				}

				$this->funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
			}
		}

		return true;
	}

	/**
	 * Builds regular expression classes for extended Unicode properties.
	 */
	private function build_regex_properties()
	{
		foreach ($this->funcs['utf8_regex_properties']['propfiles'] as $filename) {
			$local_file = $this->fetch_unicode_file($filename, self::DATA_URL_UCD);

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

				if (in_array($fields[1], $this->funcs['utf8_regex_properties']['props'])) {
					if (!isset($this->funcs['utf8_regex_properties']['data'][$fields[1]])) {
						$this->funcs['utf8_regex_properties']['data'][$fields[1]] = [];
					}

					$this->funcs['utf8_regex_properties']['data'][$fields[1]][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
				}

				// We also track 'Default_Ignorable_Code_Point' property in a separate array.
				if ($fields[1] !== 'Default_Ignorable_Code_Point') {
					continue;
				}

				if (strpos($fields[0], '..') === false) {
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

		ksort($this->funcs['utf8_regex_properties']['data']);

		return true;
	}

	/**
	 * Builds regular expression classes for filtering variation selectors.
	 */
	private function build_regex_variation_selectors()
	{
		$files = ['StandardizedVariants.txt', 'emoji/emoji-variation-sequences.txt'];

		foreach ($files as $filename) {
			$local_file = $this->fetch_unicode_file($filename, self::DATA_URL_UCD);

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

				$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
				}

				$current_range = ['start' => $ord, 'end' => $ord];
			}

			if (isset($current_range['start'])) {
				$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end']) {
					$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
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
	private function build_script_stats()
	{
		$local_file = $this->fetch_unicode_file('PropertyValueAliases.txt', self::DATA_URL_UCD);

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

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			if (in_array($fields[1], ['Common', 'Inherited'])) {
				continue;
			}

			if (strpos($fields[0], '..') === false) {
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

			if (strpos($line, ';') === false) {
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

			if (strpos($fields[0], '..') === false) {
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

			if (strpos($line, ';') === false) {
				continue;
			}

			$fields = explode(';', $line);

			foreach ($fields as $key => $value) {
				$fields[$key] = trim($value);
			}

			$fields[1] = (float) $fields[1];

			if (strpos($fields[0], '..') === false) {
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
	private function build_regex_joining_type()
	{
		$local_file = $this->fetch_unicode_file('extracted/DerivedJoiningType.txt', self::DATA_URL_UCD);

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
	private function build_regex_indic()
	{
		$local_file = $this->fetch_unicode_file('IndicSyllabicCategory.txt', self::DATA_URL_UCD);

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
				} elseif (substr($info['General_Category'], 0, 1) == 'L') {
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

					$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
					}

					$current_range = ['start' => $ord, 'end' => $ord];
				}

				if (isset($current_range['start'])) {
					$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

					if ($current_range['start'] != $current_range['end']) {
						$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
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
	private function build_idna()
	{
		$local_file = $this->fetch_unicode_file('IdnaMappingTable.txt', self::DATA_URL_IDNA);

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
				$fields[$key] = preg_replace('/\b(0(?!\b))+/', '', trim($value));
			}

			if (strpos($fields[0], '..') === false) {
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
}

?>