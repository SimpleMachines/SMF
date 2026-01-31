<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file exists to make it easy for developers to update the data
 * in ./Sources/Localization/data. Just run this file from the command
 * line in order to perform the update.
 *
 * Exceptions will be thrown if the \Transliterator does not exist or
 * if the installed version ICU library is older than the one that the
 * data files were previously generated from.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

use SMF\Config;
use SMF\Lang;
use SMF\Localization\AsciiTransliterator;

// Set a couple of variables that we'll need.
$boarddir = realpath(dirname(__DIR__));
$sourcedir = $boarddir . '/Sources';

define('SMF', 'BACKGROUND');

// Borrow a bit of stuff from index.php.
$index_php_start = file_get_contents($boarddir . '/index.php', false, null, 0, 4096);

foreach (['SMF_VERSION', 'SMF_SOFTWARE_YEAR'] as $const) {
	preg_match("/define\('{$const}', '([^)]+)'\);/", $index_php_start, $matches);

	if (empty($matches[1])) {
		die("Could not find value for {$const} in index.php");
	}

	define($const, $matches[1]);
}

// Get some more stuff we need.
require_once $sourcedir . '/Autoloader.php';
Lang::$default = 'en_US';

// Check some stuff.
if (!class_exists('\Transliterator')) {
	throw new \Exception('\Transliterator class does not exist');
}

if (version_compare(INTL_ICU_DATA_VERSION, AsciiTransliterator::BUILT_FROM_ICU_VERSION, '<')) {
	throw new \Exception('Your ICU data version is outdated.');
}

// Build the new data files.
$output_dir = $sourcedir . '/Localization/data';

for ($block = 0; $block <= 0x10FF00; $block += 0x100) {
	$block_num = $block / 0x100;
	$output_file = $output_dir . '/AsciiTransliteration_' . sprintf('%04d', $block_num) . '.php';

	if (!is_dir(dirname($output_file))) {
		mkdir(dirname($output_file));
	}

	if (file_exists($output_file)) {
		@unlink($output_file);
	}

	$content = implode("\n", [
		'<?php',
		'',
		'/**',
		' * Simple Machines Forum (SMF)',
		' *',
		' * @package SMF',
		' * @author Simple Machines https://www.simplemachines.org',
		' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors',
		' * @license https://www.simplemachines.org/about/smf/license.php BSD',
		' *',
		' * @version ' . SMF_VERSION,
		' */',
		'',
		'// Transliteration maps to replace non-ASCII characters with ASCII approximations.',
		'$ascii_transliteration[' . $block_num . '] = ',
	]);

	$arr = [];

	for ($i = 0; $i <= 0xFF; $i++) {
		// Skip the surrogate pair code points. They are not valid UTF-8 characters.
		if ($block + $i >= 0xD800 && $block + $i <= 0xDFFF) {
			continue;
		}

		$char = mb_chr($block + $i, 'UTF-8');

		$translit = AsciiTransliterator::intl($char);

		// Only record characters that actually changed.
		if ($char !== $translit) {
			$arr[$i] = $translit;
		}
	}

	// If this whole array is empty, skip it.
	if (array_filter($arr, 'strlen') === []) {
		continue;
	}

	$content .= Config::varExport($arr);
	$content .= ';' . "\n";

	file_put_contents($output_file, $content);
}

// Update AsciiTransliterator::BUILT_FROM_ICU_VERSION.
$content = file_get_contents($sourcedir . '/Localization/AsciiTransliterator.php');
$content = preg_replace('/public const BUILT_FROM_ICU_VERSION = \'[^\']+\';/', 'public const BUILT_FROM_ICU_VERSION = \'' . INTL_ICU_DATA_VERSION . '\';', $content);
file_put_contents($sourcedir . '/Localization/AsciiTransliterator.php', $content);
