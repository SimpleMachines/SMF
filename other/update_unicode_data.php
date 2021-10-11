<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file exists to make it easy for developers to update the
 * Unicode data in Subs-Charset.php whenever a new version of the
 * Unicode Character Database is released. Just run this file from the
 * command line in order to perform the update.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2021 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC4
 */

$unicode_data_url = 'https://unicode.org/Public/UCD/latest/ucd';

$sourcedir = realpath(dirname(__DIR__) . "/Sources");

if (!is_file($sourcedir . '/Subs-Charset.php') || !is_writable($sourcedir . '/Subs-Charset.php'))
	die($sourcedir . '/Subs-Charset.php not found or not writable.');

$full_decomposition_maps = array();
$utf8_arrays['utf8_normalize_d_maps'] = array();
$utf8_arrays['utf8_normalize_kd_maps'] = array();
$utf8_arrays['utf8_compose_maps'] = array();
$utf8_arrays['utf8_combining_classes'] = array();
$utf8_arrays['utf8_strtolower_maps'] = array();
$utf8_arrays['utf8_strtoupper_maps'] = array();
$utf8_arrays['utf8_casefold_maps'] = array();
$utf8_arrays['utf8_default_ignorables'] = array();

// We need some of these for further analysis below.
$derived_normalization_props = array();
foreach (file($unicode_data_url . '/DerivedNormalizationProps.txt') as $line) {
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
		continue;

	$fields = explode(';', $line);

	foreach ($fields as $key => $value) {
		$fields[$key] = trim($value);
	}

	if (!isset($derived_normalization_props[$fields[1]])) {
		$derived_normalization_props[$fields[1]] = array();
	}

	if (strpos($fields[0], '..') === false) {
		$entities = array('&#x' . $fields[0] . ';');
	} else {
		$entities = array();

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
	} elseif (in_array($fields[1], array('FC_NFKC', 'NFKC_CF'))) {
		$value = trim($fields[2]) !== '' ? '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';' : '';
	} else {
		$value = $fields[2];
	}

	foreach ($entities as $entity) {
		$derived_normalization_props[$fields[1]][$entity] = $value === 'SAME' ? $entity : $value;
	}
}

// Go through all the characters in the Unicode database.
foreach (file($unicode_data_url . '/UnicodeData.txt') as $line) {
	$fields = explode(';', $line);

	if (!empty($fields[3]))
		$utf8_arrays['utf8_combining_classes']['&#x' . $fields[0] . ';'] = trim($fields[3]);

	// Uppercase maps.
	if ($fields[12] !== '')
		$utf8_arrays['utf8_strtoupper_maps']['&#x' . $fields[0] . ';'] = '&#x' . $fields[12] . ';';

	// Lowercase maps.
	if ($fields[13] !== '')
		$utf8_arrays['utf8_strtolower_maps']['&#x' . $fields[0] . ';'] = '&#x' . $fields[13] . ';';

	if ($fields[5] === '')
		continue;

	// All canonical decompositions AND all compatibility decompositions.
	$full_decomposition_maps['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim(strip_tags($fields[5]))) . ';';

	// Just the canonical decompositions.
	if (strpos($fields[5], '<') === false) {
		$utf8_arrays['utf8_normalize_d_maps']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[5])) . ';';
	}
}

foreach (file($unicode_data_url . '/CaseFolding.txt') as $line) {
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
		continue;

	$fields = explode(';', $line);

	foreach ($fields as $key => $value) {
		$fields[$key] = trim($value);
	}

	// Full casefolding.
	if (in_array($fields[1], array('C', 'F'))) {
		$utf8_arrays['utf8_casefold_maps']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
	}

	// Simple casefolding. Currently unused.
	// if (in_array($fields[1], array('C', 'S'))) {
	// 	$utf8_arrays['utf8_casefold_simple_maps']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
	// }
}

// Recursively iterate until we reach the final decomposition forms.
// This is necessary because some characters decompose to other characters that
// themselves decompose further.
$changed = true;
while ($changed) {
	$temp = array();
	foreach ($full_decomposition_maps as $composed => $decomposed) {
		$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

		foreach ($parts as $partnum => $hex) {
			if (isset($full_decomposition_maps[$hex])) {
				$parts[$partnum] = $full_decomposition_maps[$hex];
			}
		}

		$decomposed = implode(' ', $parts);
		unset($parts);

		$temp[$composed] = $decomposed;
	}

	$changed = $full_decomposition_maps !== $temp;

	$full_decomposition_maps = $temp;
}

// Same as above, but using only canonical decompositions.
$changed = true;
$iteration = 0;
while ($changed) {
	$temp = array();
	foreach ($utf8_arrays['utf8_normalize_d_maps'] as $composed => $decomposed) {
		if ($iteration === 0 && !in_array($composed, $derived_normalization_props['Full_Composition_Exclusion'])) {
			$utf8_arrays['utf8_compose_maps'][$decomposed] = $composed;
		}

		$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

		foreach ($parts as $partnum => $hex) {
			if (isset($utf8_arrays['utf8_normalize_d_maps'][$hex])) {
				$parts[$partnum] = $utf8_arrays['utf8_normalize_d_maps'][$hex];
			}
		}

		$decomposed = implode(' ', $parts);
		unset($parts);

		$temp[$composed] = $decomposed;
	}

	$changed = $utf8_arrays['utf8_normalize_d_maps'] !== $temp;

	$utf8_arrays['utf8_normalize_d_maps'] = $temp;
	$iteration++;
}

$utf8_arrays['utf8_normalize_kd_maps'] = array_diff_assoc($full_decomposition_maps, $utf8_arrays['utf8_normalize_d_maps']);

// Some characters have the 'Default_Ignorable_Code_Point' property.
foreach (file($unicode_data_url . '/DerivedCoreProperties.txt') as $line) {
	if (strpos($line, 'Default_Ignorable_Code_Point') === false)
		continue;

	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
		continue;

	$fields = explode(';', $line);

	foreach ($fields as $key => $value) {
		$fields[$key] = trim($value);
	}

	if (strpos($fields[0], '..') === false) {
		$utf8_arrays['utf8_default_ignorables'][] = '&#x' . $fields[0] . ';';
	} else {
		$entities = array();

		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e) {
			$utf8_arrays['utf8_default_ignorables'][] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
		}
	}
}

// Now update the file.
$subs_charset_contents = file_get_contents($sourcedir . '/Subs-Charset.php');

foreach ($utf8_arrays as $func_name => $arr) {
	$func_text = 'function ' . $func_name . '()' . "\n" . '{';

	$func_regex = '/' . preg_quote($func_text, '/') . '[^}]*}/';

	$func_text .= "\n\t" . 'return array(' . "\n";

	foreach ($arr as $key => $value) {
		$func_text .= "\t\t";

		if ($func_name !== 'utf8_default_ignorables') {
			$func_text .= '"';

			$key = mb_decode_numericentity(str_replace(' ', '', $key), array(0,0x10FFFF,0,0xFFFFFF), 'UTF-8');

			foreach (unpack('C*', $key) as $byte_value) {
				$func_text .= '\\x' . strtoupper(dechex($byte_value));
			}

			$func_text .= '" => ';
		}

		if ($func_name == 'utf8_combining_classes') {
			$func_text .= $value;
		} else {
			$func_text .= '"';

			$value = mb_decode_numericentity(str_replace(' ', '', $value), array(0,0x10FFFF,0,0xFFFFFF), 'UTF-8');
			foreach (unpack('C*', $value) as $byte_value) {
				$func_text .= '\\x' . strtoupper(dechex($byte_value));
			}

			$func_text .= '"';
		}

		$func_text .= ',' . "\n";
	}

	$func_text .= "\t" . ');' . "\n" . '}';

	$subs_charset_contents = preg_replace($func_regex, $func_text, $subs_charset_contents);
}

file_put_contents($sourcedir . '/Subs-Charset.php', $subs_charset_contents);

?>