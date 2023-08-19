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
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.4
 */

$unicode_data_url = 'https://unicode.org/Public/UCD/latest/ucd';
$idna_data_url = 'https://www.unicode.org/Public/idna/latest';

$sourcedir = realpath(dirname(__DIR__) . '/Sources');
$unicodedir = $sourcedir . '/Unicode';

$full_decomposition_maps = array();
$funcs = array(
	'utf8_normalize_d_maps' => array(
		'file' => 'DecompositionCanonical.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_normalize_kd_maps' => array(
		'file' => 'DecompositionCompatibility.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_compose_maps' => array(
		'file' => 'Composition.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_combining_classes' => array(
		'file' => 'CombiningClasses.php',
		'key_type' => 'hexchar',
		'val_type' => 'int',
		'data' => array(),
	),
	'utf8_strtolower_simple_maps' => array(
		'file' => 'CaseLower.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_strtolower_maps' => array(
		'file' => 'CaseLower.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_strtoupper_simple_maps' => array(
		'file' => 'CaseUpper.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_strtoupper_maps' => array(
		'file' => 'CaseUpper.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_titlecase_simple_maps' => array(
		'file' => 'CaseTitle.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_titlecase_maps' => array(
		'file' => 'CaseTitle.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_casefold_simple_maps' => array(
		'file' => 'CaseFold.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_casefold_maps' => array(
		'file' => 'CaseFold.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_default_ignorables' => array(
		'file' => 'DefaultIgnorables.php',
		'key_type' => 'int',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'utf8_regex_properties' => array(
		'file' => 'RegularExpressions.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'propfiles' => array(
			'DerivedCoreProperties.txt',
			'PropList.txt',
			'emoji/emoji-data.txt',
			'extracted/DerivedGeneralCategory.txt',
		),
		'props' => array(
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
		),
		'data' => array(),
	),
	'utf8_regex_variation_selectors' => array(
		'file' => 'RegularExpressions.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'data' => array(),
	),
	'utf8_regex_joining_type' => array(
		'file' => 'RegularExpressions.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'data' => array(),
	),
	'utf8_regex_indic' => array(
		'file' => 'RegularExpressions.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'data' => array(),
	),
	'utf8_regex_quick_check' => array(
		'file' => 'QuickCheck.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'data' => array(),
	),
	'idna_maps' => array(
		'file' => 'Idna.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'idna_maps_deviation' => array(
		'file' => 'Idna.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'idna_maps_not_std3' => array(
		'file' => 'Idna.php',
		'key_type' => 'hexchar',
		'val_type' => 'hexchar',
		'data' => array(),
	),
	'idna_regex' => array(
		'file' => 'Idna.php',
		'key_type' => 'string',
		'val_type' => 'string',
		'data' => array(),
	),
);

foreach ($funcs as $func_name => $func_info)
{
	if (!is_file($unicodedir . '/' . $func_info['file']) || !is_writable($unicodedir . '/' . $func_info['file']))
	{
		die($unicodedir . '/' . $func_info['file'] . ' not found or not writable.');
	}
}

@ini_set('memory_limit', '256M');

/*********************************************
 * Part 1: Normalization, case folding, etc. *
 *********************************************/

// We need some of these for further analysis below.
$derived_normalization_props = array();
$unicode_version = '';
foreach (file($unicode_data_url . '/DerivedNormalizationProps.txt') as $line)
{
	if ($unicode_version === '' && preg_match('/(\d+\.\d+\.\d+(?:\.\d+)?)\.txt$/', $line, $matches))
	{
		$unicode_version = implode('.', array_pad(explode('.', $matches[1]), 4, '0'));

		$file_contents = file_get_contents($unicodedir . '/Metadata.php');
		$file_contents = preg_replace(
			"~\bdefine\('SMF_UNICODE_VERSION', '[^']+'\)~",
			"define('SMF_UNICODE_VERSION', '" . $unicode_version . "')",
			$file_contents
		);
		file_put_contents($unicodedir . '/Metadata.php', $file_contents);
	}

	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	if (!isset($derived_normalization_props[$fields[1]]))
	{
		$derived_normalization_props[$fields[1]] = array();
	}

	if (strpos($fields[0], '..') === false)
	{
		$entities = array('&#x' . $fields[0] . ';');
	}
	else
	{
		$entities = array();

		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e)
		{
			$entities[] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
		}
	}

	$value = '';
	if (!isset($fields[2]))
	{
		$value = 'SAME';
	}
	elseif (in_array($fields[1], array('FC_NFKC', 'NFKC_CF')))
	{
		$value = trim($fields[2]) !== '' ? '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';' : '';
	}
	else
	{
		$value = $fields[2];
	}

	foreach ($entities as $entity)
	{
		$derived_normalization_props[$fields[1]][$entity] = $value === 'SAME' ? $entity : $value;
	}
}

// Go through all the characters in the Unicode database.
$char_data = array();
foreach (file($unicode_data_url . '/UnicodeData.txt') as $line)
{
	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	if (!empty($fields[3]))
	{
		$funcs['utf8_combining_classes']['data']['&#x' . $fields[0] . ';'] = $fields[3];
	}

	// Uppercase maps.
	if ($fields[12] !== '')
	{
		$funcs['utf8_strtoupper_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[12] . ';';
	}

	// Lowercase maps.
	if ($fields[13] !== '')
	{
		$funcs['utf8_strtolower_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[13] . ';';
	}

	// Titlecase maps, where different from uppercase maps.
	if ($fields[14] !== '' && $fields[14] !== $fields[12])
	{
		$funcs['utf8_titlecase_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . $fields[14] . ';';
	}

	// Remember this character's general category for later.
	$char_data['&#x' . $fields[0] . ';']['General_Category'] = $fields[2];

	if ($fields[5] === '')
	{
		continue;
	}

	// All canonical decompositions AND all compatibility decompositions.
	$full_decomposition_maps['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim(strip_tags($fields[5]))) . ';';

	// Just the canonical decompositions.
	if (strpos($fields[5], '<') === false)
	{
		$funcs['utf8_normalize_d_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', $fields[5]) . ';';
	}
}

// Full case conversion maps
$funcs['utf8_strtoupper_maps']['data'] = $funcs['utf8_strtoupper_simple_maps']['data'];
$funcs['utf8_strtolower_maps']['data'] = $funcs['utf8_strtolower_simple_maps']['data'];
$funcs['utf8_titlecase_maps']['data'] = $funcs['utf8_titlecase_simple_maps']['data'];
foreach (file($unicode_data_url . '/SpecialCasing.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	// Unconditional mappings.
	// Note: conditional mappings need to be handled by more complex code.
	if (empty($fields[4]))
	{
		$funcs['utf8_strtolower_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[1])) . ';';

		$funcs['utf8_strtoupper_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[3])) . ';';

		// Titlecase only where different from uppercase.
		if ($fields[3] !== $fields[2])
		{
			$funcs['utf8_titlecase_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
		}
	}
}
ksort($funcs['utf8_strtolower_maps']['data']);
ksort($funcs['utf8_strtoupper_maps']['data']);
ksort($funcs['utf8_titlecase_maps']['data']);

foreach (file($unicode_data_url . '/CaseFolding.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	// Full casefolding.
	if (in_array($fields[1], array('C', 'F')))
	{
		$funcs['utf8_casefold_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
	}

	// Simple casefolding.
	if (in_array($fields[1], array('C', 'S')))
		$funcs['utf8_casefold_simple_maps']['data']['&#x' . $fields[0] . ';'] = '&#x' . str_replace(' ', '; &#x', trim($fields[2])) . ';';
}

// Recursively iterate until we reach the final decomposition forms.
// This is necessary because some characters decompose to other characters that
// themselves decompose further.
$changed = true;
while ($changed)
{
	$temp = array();
	foreach ($full_decomposition_maps as $composed => $decomposed)
	{
		$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

		foreach ($parts as $partnum => $hex)
		{
			if (isset($full_decomposition_maps[$hex]))
			{
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
while ($changed)
{
	$temp = array();
	foreach ($funcs['utf8_normalize_d_maps']['data'] as $composed => $decomposed)
	{
		if ($iteration === 0 && !in_array($composed, $derived_normalization_props['Full_Composition_Exclusion']))
		{
			$funcs['utf8_compose_maps']['data'][$decomposed] = $composed;
		}

		$parts = strpos($decomposed, ' ') !== false ? explode(' ', $decomposed) : (array) $decomposed;

		foreach ($parts as $partnum => $hex)
		{
			if (isset($funcs['utf8_normalize_d_maps']['data'][$hex]))
			{
				$parts[$partnum] = $funcs['utf8_normalize_d_maps']['data'][$hex];
			}
		}

		$decomposed = implode(' ', $parts);
		unset($parts);

		$temp[$composed] = $decomposed;
	}

	$changed = $funcs['utf8_normalize_d_maps']['data'] !== $temp;

	$funcs['utf8_normalize_d_maps']['data'] = $temp;
	$iteration++;
}

$funcs['utf8_normalize_kd_maps']['data'] = array_diff_assoc($full_decomposition_maps, $funcs['utf8_normalize_d_maps']['data']);
unset($full_decomposition_maps);

// Now update the files with the data we've got so far.
foreach ($funcs as $func_name => $func_info)
{
	if (empty($func_info['data']))
	{
		continue;
	}

	export_func_to_file($func_name, $func_info);

	// Free up some memory.
	if ($func_name != 'utf8_combining_classes')
	{
		unset($funcs[$func_name]);
	}
}

/***********************************
 * Part 2: Regular expression data *
 ***********************************/

foreach (array('NFC_QC', 'NFKC_QC', 'NFD_QC', 'NFKD_QC', 'Changes_When_NFKC_Casefolded') as $prop)
{
	$current_range = array('start' => null, 'end' => null);
	foreach ($derived_normalization_props[$prop] as $entity => $nm)
	{
		$range_string = '';

		$ord = hexdec(trim($entity, '&#x;'));

		if (!isset($current_range['start']))
		{
			$current_range['start'] = $ord;
		}

		if (!isset($current_range['end']) || $ord == $current_range['end'] + 1)
		{
			$current_range['end'] = $ord;
		}
		else
		{
			$range_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

			if ($current_range['start'] != $current_range['end'])
			{
				$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
			}

			$current_range = array('start' => $ord, 'end' => $ord);

			$funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
		}
	}

	if (isset($current_range['start']))
	{
		$range_string = '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

		if ($current_range['start'] != $current_range['end'])
		{
			$range_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
		}

		$funcs['utf8_regex_quick_check']['data'][$prop][] = $range_string;
	}
}
unset($derived_normalization_props);

// Build regular expression classes for extended Unicode properties.
foreach ($funcs['utf8_regex_properties']['propfiles'] as $filename)
{
	foreach (file($unicode_data_url . '/' . $filename) as $line)
	{
		$line = substr($line, 0, strcspn($line, '#'));

		if (strpos($line, ';') === false)
		{
			continue;
		}

		$fields = explode(';', $line);

		foreach ($fields as $key => $value)
		{
			$fields[$key] = trim($value);
		}

		if (in_array($fields[1], $funcs['utf8_regex_properties']['props']))
		{
			if (!isset($funcs['utf8_regex_properties']['data'][$fields[1]]))
			{
				$funcs['utf8_regex_properties']['data'][$fields[1]] = array();
			}

			$funcs['utf8_regex_properties']['data'][$fields[1]][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
		}

		// We also track 'Default_Ignorable_Code_Point' property in a separate array.
		if ($fields[1] !== 'Default_Ignorable_Code_Point')
		{
			continue;
		}

		if (strpos($fields[0], '..') === false)
		{
			$funcs['utf8_default_ignorables']['data'][] = '&#x' . $fields[0] . ';';
		}
		else
		{
			list($start, $end) = explode('..', $fields[0]);

			$ord_s = hexdec($start);
			$ord_e = hexdec($end);

			$ord = $ord_s;
			while ($ord <= $ord_e)
			{
				$funcs['utf8_default_ignorables']['data'][] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
			}
		}
	}
}
ksort($funcs['utf8_regex_properties']['data']);

// Build regular expression classes for filtering variation selectors.
$files = array('StandardizedVariants.txt', 'emoji/emoji-variation-sequences.txt');
foreach ($files as $filename)
{
	foreach (file($unicode_data_url . '/' . $filename) as $line)
	{
		$line = substr($line, 0, strcspn($line, '#'));

		if (strpos($line, ';') === false)
		{
			continue;
		}

		$fields = explode(';', $line);

		foreach ($fields as $key => $value)
		{
			$fields[$key] = trim($value);
		}

		list($base_char, $variation_selector) = explode(' ', $fields[0]);

		$funcs['utf8_regex_variation_selectors']['data']['\\x{' . $variation_selector . '}'][] = hexdec($base_char);
	}
}
foreach ($funcs['utf8_regex_variation_selectors']['data'] as $variation_selector => $ords)
{
	$class_string = '';

	$current_range = array('start' => null, 'end' => null);
	foreach ($ords as $ord)
	{
		if (!isset($current_range['start']))
		{
			$current_range['start'] = $ord;
		}

		if (!isset($current_range['end']) || $ord == $current_range['end'] + 1)
		{
			$current_range['end'] = $ord;
			continue;
		}
		else
		{
			$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

			if ($current_range['start'] != $current_range['end'])
			{
				$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
			}

			$current_range = array('start' => $ord, 'end' => $ord);
		}
	}

	if (isset($current_range['start']))
	{
		$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

		if ($current_range['start'] != $current_range['end'])
		{
			$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
		}
	}

	// As of Unicode 14.0, \x{FE0E} and \x{FE0F} work with identical ranges of base characters.
	if (($identical = array_search($class_string, $funcs['utf8_regex_variation_selectors']['data'])) !== false)
	{
		unset(
			$funcs['utf8_regex_variation_selectors']['data'][$identical],
			$funcs['utf8_regex_variation_selectors']['data'][$variation_selector]
		);

		$compound_selector = array($identical, $variation_selector);
		sort($compound_selector);

		$variation_selector = implode('', $compound_selector);
	}

	$funcs['utf8_regex_variation_selectors']['data'][$variation_selector] = $class_string;
}
foreach ($funcs['utf8_regex_variation_selectors']['data'] as $variation_selector => $class_string)
{
	$funcs['utf8_regex_variation_selectors']['data'][$variation_selector] = preg_split('/(?<=})(?=\\\x{)/', $class_string);
}
krsort($funcs['utf8_regex_variation_selectors']['data']);

// The regex classes for join control tests require info about language scripts.
$script_stats = array();
$script_aliases = array();
foreach (file($unicode_data_url . '/PropertyValueAliases.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	if ($fields[0] !== 'sc')
	{
		continue;
	}

	$script_aliases[$fields[1]] = $fields[2];
}
foreach (file($unicode_data_url . '/Scripts.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	if (in_array($fields[1], array('Common', 'Inherited')))
	{
		continue;
	}

	if (strpos($fields[0], '..') === false)
	{
		$char_data['&#x' . $fields[0] . ';']['scripts'][] = $fields[1];
	}
	else
	{
		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e)
		{
			$char_data['&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';']['scripts'][] = $fields[1];
		}
	}
}
foreach (file($unicode_data_url . '/ScriptExtensions.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	$char_scripts = array();
	foreach (explode(' ', $fields[1]) as $alias)
	{
		if (!in_array($script_aliases[$alias], array('Common', 'Inherited')))
		{
			$char_scripts[] = $script_aliases[$alias];
		}
	}

	if (strpos($fields[0], '..') === false)
	{
		foreach ($char_scripts as $char_script)
		{
			$char_data['&#x' . $fields[0] . ';']['scripts'][] = $char_script;
		}
	}
	else
	{
		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e)
		{
			foreach ($char_scripts as $char_script)
			{
				$char_data['&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';']['scripts'][] = $char_script;
			}
		}
	}
}
foreach (file($unicode_data_url . '/DerivedAge.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	$fields[1] = (float) $fields[1];

	if (strpos($fields[0], '..') === false)
	{
		$entity = '&#x' . $fields[0] . ';';

		if (empty($char_data[$entity]['scripts']))
		{
			continue;
		}

		foreach ($char_data[$entity]['scripts'] as $char_script)
		{
			if (!isset($script_stats[$char_script]))
			{
				$script_stats[$char_script]['age'] = (float) $fields[1];
				$script_stats[$char_script]['count'] = 1;
			}
			else
			{
				$script_stats[$char_script]['age'] = min((float) $fields[1], $script_stats[$char_script]['age']);
				$script_stats[$char_script]['count']++;
			}
		}
	}
	else
	{
		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e)
		{
			$entity = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';

			if (empty($char_data[$entity]['scripts']))
			{
				continue;
			}

			foreach ($char_data[$entity]['scripts'] as $char_script)
			{
				if (!isset($script_stats[$char_script]))
				{
					$script_stats[$char_script]['age'] = $fields[1];
					$script_stats[$char_script]['count'] = 1;
				}
				else
				{
					$script_stats[$char_script]['age'] = min($fields[1], $script_stats[$char_script]['age']);
					$script_stats[$char_script]['count']++;
				}
			}
		}
	}
}

// Build regex classes for join control tests in utf8_sanitize_invisibles:
// 1. Cursive scripts like Arabic.
foreach (file($unicode_data_url . '/extracted/DerivedJoiningType.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	switch ($fields[1])
	{
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

	if (!isset($joining_type))
	{
		continue;
	}

	$entity = '&#x' . substr($fields[0], 0, strcspn($fields[0], '.')) . ';';

	if (empty($char_data[$entity]['scripts']))
	{
		continue;
	}

	foreach ($char_data[$entity]['scripts'] as $char_script)
	{
		if (!isset($funcs['utf8_regex_joining_type']['data'][$char_script]['stats']))
		{
			$funcs['utf8_regex_joining_type']['data'][$char_script]['stats'] = $script_stats[$char_script];
		}

		if (!isset($funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type]))
		{
			$funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type] = array();
		}

		$funcs['utf8_regex_joining_type']['data'][$char_script][$joining_type][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
}
// This sort works decently well to ensure widely used scripts are ranked before rare scripts.
uasort($funcs['utf8_regex_joining_type']['data'], function ($a, $b)
{
	if ($a['stats']['age'] == $b['stats']['age'])
	{
		return $b['stats']['count'] - $a['stats']['count'];
	}
	else
	{
		return $a['stats']['age'] - $b['stats']['age'];
	}
});
foreach ($funcs['utf8_regex_joining_type']['data'] as $char_script => $joining_types)
{
	unset($funcs['utf8_regex_joining_type']['data'][$char_script]['stats'], $joining_types['stats']);

	// If the only joining type in this script is transparent, we don't care about it.
	if (array_keys($joining_types) === array('Transparent'))
	{
		unset($funcs['utf8_regex_joining_type']['data'][$char_script]);
		continue;
	}

	foreach ($joining_types as $joining_type => $value)
	{
		sort($value);
	}
}

// 2. Indic scripts like Devanagari.
foreach (file($unicode_data_url . '/IndicSyllabicCategory.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = trim($value);
	}

	$insc = $fields[1];

	if (!in_array($insc, array('Virama', 'Vowel_Dependent')))
	{
		continue;
	}

	$char_scripts = $char_data['&#x' . substr($fields[0], 0, strcspn($fields[0], '.')) . ';']['scripts'];

	if (empty($char_scripts))
	{
		continue;
	}

	foreach ($char_scripts as $char_script)
	{
		if (!isset($funcs['utf8_regex_indic']['data'][$char_script]['stats']))
		{
			$funcs['utf8_regex_indic']['data'][$char_script]['stats'] = $script_stats[$char_script];
		}

		if (!isset($funcs['utf8_regex_indic']['data'][$char_script][$insc]))
		{
			$funcs['utf8_regex_indic']['data'][$char_script][$insc] = array();
		}

		$funcs['utf8_regex_indic']['data'][$char_script][$insc][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
}
// Again, sort commonly used scripts before rare scripts.
uasort($funcs['utf8_regex_indic']['data'], function ($a, $b)
{
	if ($a['stats']['age'] == $b['stats']['age'])
	{
		return $b['stats']['count'] - $a['stats']['count'];
	}
	else
	{
		return $a['stats']['age'] - $b['stats']['age'];
	}
});
// We only want scripts with viramas.
foreach ($funcs['utf8_regex_indic']['data'] as $char_script => $inscs)
{
	unset($funcs['utf8_regex_indic']['data'][$char_script]['stats'], $inscs['stats']);

	if (!isset($inscs['Virama']))
	{
		unset($funcs['utf8_regex_indic']['data'][$char_script]);
		continue;
	}
}
// Now add some more classes that we need for each script.
foreach ($char_data as $entity => $info)
{
	if (empty($info['scripts']))
	{
		continue;
	}

	$ord = hexdec(trim($entity, '&#x;'));

	foreach ($info['scripts'] as $char_script)
	{
		if (!isset($funcs['utf8_regex_indic']['data'][$char_script]))
		{
			continue;
		}

		$funcs['utf8_regex_indic']['data'][$char_script]['All'][] = $ord;

		if (empty($info['General_Category']))
		{
			continue;
		}
		elseif ($info['General_Category'] == 'Mn')
		{
			$funcs['utf8_regex_indic']['data'][$char_script]['Nonspacing_Mark'][] = $ord;

			if (!empty($funcs['utf8_combining_classes']['data'][$entity]))
			{
				$funcs['utf8_regex_indic']['data'][$char_script]['Nonspacing_Combining_Mark'][] = $ord;
			}
		}
		elseif (substr($info['General_Category'], 0, 1) == 'L')
		{
			$funcs['utf8_regex_indic']['data'][$char_script]['Letter'][] = $ord;
		}
	}
}
foreach ($funcs['utf8_regex_indic']['data'] as $char_script => $inscs)
{
	foreach ($inscs as $insc => $value)
	{
		sort($value);

		if (!in_array($insc, array('All', 'Letter', 'Nonspacing_Mark', 'Nonspacing_Combining_Mark')))
		{
			continue;
		}

		$class_string = '';

		$current_range = array('start' => null, 'end' => null);
		foreach ($value as $ord)
		{
			if (!isset($current_range['start']))
			{
				$current_range['start'] = $ord;
			}

			if (!isset($current_range['end']) || $ord == $current_range['end'] + 1)
			{
				$current_range['end'] = $ord;
				continue;
			}
			else
			{
				$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

				if ($current_range['start'] != $current_range['end'])
				{
					$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
				}

				$current_range = array('start' => $ord, 'end' => $ord);
			}
		}

		if (isset($current_range['start']))
		{
			$class_string .= '\\x{' . strtoupper(sprintf('%04s', dechex($current_range['start']))) . '}';

			if ($current_range['start'] != $current_range['end'])
			{
				$class_string .= '-\\x{' . strtoupper(sprintf('%04s', dechex($current_range['end']))) . '}';
			}
		}

		$funcs['utf8_regex_indic']['data'][$char_script][$insc] = preg_split('/(?<=})(?=\\\x{)/', $class_string);
	}

	ksort($funcs['utf8_regex_indic']['data'][$char_script]);
}
unset($funcs['utf8_combining_classes']);

foreach ($funcs as $func_name => $func_info)
{
	if (empty($func_info['data']))
	{
		continue;
	}

	export_func_to_file($func_name, $func_info);
}

/*********************************
 * Part 3: IDNA maps and regexes *
 *********************************/

foreach (file($idna_data_url . '/IdnaMappingTable.txt') as $line)
{
	$line = substr($line, 0, strcspn($line, '#'));

	if (strpos($line, ';') === false)
	{
		continue;
	}

	$fields = explode(';', $line);

	foreach ($fields as $key => $value)
	{
		$fields[$key] = preg_replace('/\b(0(?!\b))+/', '', trim($value));
	}

	if (strpos($fields[0], '..') === false)
	{
		$entities = array('&#x' . $fields[0] . ';');
	}
	else
	{
		$entities = array();

		list($start, $end) = explode('..', $fields[0]);

		$ord_s = hexdec($start);
		$ord_e = hexdec($end);

		$ord = $ord_s;
		while ($ord <= $ord_e)
		{
			$entities[] = '&#x' . strtoupper(sprintf('%04s', dechex($ord++))) . ';';
		}
	}

	if ($fields[1] === 'mapped')
	{
		foreach ($entities as $entity)
			$funcs['idna_maps']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';
	}
	elseif ($fields[1] === 'deviation')
	{
		foreach ($entities as $entity)
			$funcs['idna_maps_deviation']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';

		$funcs['idna_regex']['data']['deviation'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
	elseif ($fields[1] === 'ignored')
	{
		$funcs['idna_regex']['data']['ignored'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
	elseif ($fields[1] === 'disallowed')
	{
		if (in_array('&#xD800;', $entities))
			continue;

		$funcs['idna_regex']['data']['disallowed'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
	elseif ($fields[1] === 'disallowed_STD3_mapped')
	{
		foreach ($entities as $entity)
			$funcs['idna_maps_not_std3']['data'][$entity] = $fields[2] === '' ? '' : '&#x' . str_replace(' ', '; &#x', $fields[2]) . ';';

		$funcs['idna_regex']['data']['disallowed_std3'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
	elseif ($fields[1] === 'disallowed_STD3_valid')
	{
		$funcs['idna_regex']['data']['disallowed_std3'][] = '\\x{' . str_replace('..', '}-\\x{', $fields[0]) . '}';
	}
}

foreach ($funcs as $func_name => $func_info)
{
	if (empty($func_info['data']))
	{
		continue;
	}

	export_func_to_file($func_name, $func_info);
}

/**
 * Updates a Unicode data function in its designated file.
 *
 * @param string $func_name The name of the function.
 * @param array $func_info Info about the function, including its data.
 */
function export_func_to_file($func_name, $func_info)
{
	global $unicodedir;

	$file_contents = file_get_contents($unicodedir . '/' . $func_info['file']);

	$func_text = 'function ' . $func_name . '()' . "\n" . '{';

	$func_regex = '/' . preg_quote($func_text, '/') . '.+?\n}/s';

	$func_text .= "\n\t" . 'return array(' . "\n";

	build_func_array($func_text, $func_info['data'], $func_info['key_type'], $func_info['val_type']);

	$func_text .= "\t" . ');' . "\n" . '}';

	$file_contents = preg_replace($func_regex, $func_text, $file_contents);

	file_put_contents($unicodedir . '/' . $func_info['file'], $file_contents);
}

/**
 * Helper for export_func_to_file(). Builds the function's data array.
 *
 * @param string &$func_text The raw string that contains function code.
 * @param array $data Data to format as an array.
 * @param string $key_type How to format the array keys.
 * @param string $val_type How to format the array values.
 */
function build_func_array(&$func_text, $data, $key_type, $val_type)
{
	static $indent = 2;

	foreach ($data as $key => $value)
	{
		$func_text .= str_repeat("\t", $indent);

		if ($key_type == 'hexchar')
		{
			$func_text .= '"';

			$key = mb_decode_numericentity(str_replace(' ', '', $key), array(0, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');

			foreach (unpack('C*', $key) as $byte_value)
			{
				$func_text .= '\\x' . strtoupper(dechex($byte_value));
			}

			$func_text .= '" => ';
		}
		elseif ($key_type == 'string' && !is_int($key))
		{
			$func_text .= var_export($key, true) . ' => ';
		}

		if (is_array($value))
		{
			if ($val_type == 'string' && ($string_count = count($value)) === count($value, COUNT_RECURSIVE))
			{
				$nextline = "\n" . str_repeat("\t", $indent + 1);

				$func_text = rtrim($func_text);

				$func_text .= $nextline . implode(' .' . $nextline, array_map(function ($v) { return var_export($v, true); }, $value));
			}
			else
			{
				$func_text .= 'array(' . "\n";

				$indent++;
				build_func_array($func_text, $value, $key_type, $val_type);
				$indent--;

				$func_text .= str_repeat("\t", $indent) . ')';
			}
		}
		elseif ($val_type == 'hexchar')
		{
			$func_text .= '"';

			$value = mb_decode_numericentity(str_replace(' ', '', $value), array(0, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');
			foreach (unpack('C*', $value) as $byte_value)
			{
				$func_text .= '\\x' . strtoupper(dechex($byte_value));
			}

			$func_text .= '"';
		}
		elseif ($val_type == 'string')
		{
			$func_text .= var_export($value, true);
		}
		else
		{
			$func_text .= $value;
		}

		$func_text .= ',' . "\n";
	}
}

?>