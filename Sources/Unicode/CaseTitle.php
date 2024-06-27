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

namespace SMF\Sources\Unicode;

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Helper function for utf8_convert_case.
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Simple title case maps.
 */
function utf8_titlecase_simple_maps(): array
{
	return [
		"\xC7\x84" => "\xC7\x85",
		"\xC7\x85" => "\xC7\x85",
		"\xC7\x86" => "\xC7\x85",
		"\xC7\x87" => "\xC7\x88",
		"\xC7\x88" => "\xC7\x88",
		"\xC7\x89" => "\xC7\x88",
		"\xC7\x8A" => "\xC7\x8B",
		"\xC7\x8B" => "\xC7\x8B",
		"\xC7\x8C" => "\xC7\x8B",
		"\xC7\xB1" => "\xC7\xB2",
		"\xC7\xB2" => "\xC7\xB2",
		"\xC7\xB3" => "\xC7\xB2",
		"\xE1\x83\x90" => "\xE1\x83\x90",
		"\xE1\x83\x91" => "\xE1\x83\x91",
		"\xE1\x83\x92" => "\xE1\x83\x92",
		"\xE1\x83\x93" => "\xE1\x83\x93",
		"\xE1\x83\x94" => "\xE1\x83\x94",
		"\xE1\x83\x95" => "\xE1\x83\x95",
		"\xE1\x83\x96" => "\xE1\x83\x96",
		"\xE1\x83\x97" => "\xE1\x83\x97",
		"\xE1\x83\x98" => "\xE1\x83\x98",
		"\xE1\x83\x99" => "\xE1\x83\x99",
		"\xE1\x83\x9A" => "\xE1\x83\x9A",
		"\xE1\x83\x9B" => "\xE1\x83\x9B",
		"\xE1\x83\x9C" => "\xE1\x83\x9C",
		"\xE1\x83\x9D" => "\xE1\x83\x9D",
		"\xE1\x83\x9E" => "\xE1\x83\x9E",
		"\xE1\x83\x9F" => "\xE1\x83\x9F",
		"\xE1\x83\xA0" => "\xE1\x83\xA0",
		"\xE1\x83\xA1" => "\xE1\x83\xA1",
		"\xE1\x83\xA2" => "\xE1\x83\xA2",
		"\xE1\x83\xA3" => "\xE1\x83\xA3",
		"\xE1\x83\xA4" => "\xE1\x83\xA4",
		"\xE1\x83\xA5" => "\xE1\x83\xA5",
		"\xE1\x83\xA6" => "\xE1\x83\xA6",
		"\xE1\x83\xA7" => "\xE1\x83\xA7",
		"\xE1\x83\xA8" => "\xE1\x83\xA8",
		"\xE1\x83\xA9" => "\xE1\x83\xA9",
		"\xE1\x83\xAA" => "\xE1\x83\xAA",
		"\xE1\x83\xAB" => "\xE1\x83\xAB",
		"\xE1\x83\xAC" => "\xE1\x83\xAC",
		"\xE1\x83\xAD" => "\xE1\x83\xAD",
		"\xE1\x83\xAE" => "\xE1\x83\xAE",
		"\xE1\x83\xAF" => "\xE1\x83\xAF",
		"\xE1\x83\xB0" => "\xE1\x83\xB0",
		"\xE1\x83\xB1" => "\xE1\x83\xB1",
		"\xE1\x83\xB2" => "\xE1\x83\xB2",
		"\xE1\x83\xB3" => "\xE1\x83\xB3",
		"\xE1\x83\xB4" => "\xE1\x83\xB4",
		"\xE1\x83\xB5" => "\xE1\x83\xB5",
		"\xE1\x83\xB6" => "\xE1\x83\xB6",
		"\xE1\x83\xB7" => "\xE1\x83\xB7",
		"\xE1\x83\xB8" => "\xE1\x83\xB8",
		"\xE1\x83\xB9" => "\xE1\x83\xB9",
		"\xE1\x83\xBA" => "\xE1\x83\xBA",
		"\xE1\x83\xBD" => "\xE1\x83\xBD",
		"\xE1\x83\xBE" => "\xE1\x83\xBE",
		"\xE1\x83\xBF" => "\xE1\x83\xBF",
	];
}

/**
 * Helper function for utf8_convert_case.
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Full title case maps.
 */
function utf8_titlecase_maps(): array
{
	return [
		"\xC3\x9F" => "\x53\x73",
		"\xC7\x84" => "\xC7\x85",
		"\xC7\x85" => "\xC7\x85",
		"\xC7\x86" => "\xC7\x85",
		"\xC7\x87" => "\xC7\x88",
		"\xC7\x88" => "\xC7\x88",
		"\xC7\x89" => "\xC7\x88",
		"\xC7\x8A" => "\xC7\x8B",
		"\xC7\x8B" => "\xC7\x8B",
		"\xC7\x8C" => "\xC7\x8B",
		"\xC7\xB1" => "\xC7\xB2",
		"\xC7\xB2" => "\xC7\xB2",
		"\xC7\xB3" => "\xC7\xB2",
		"\xD6\x87" => "\xD4\xB5\xD6\x82",
		"\xE1\x83\x90" => "\xE1\x83\x90",
		"\xE1\x83\x91" => "\xE1\x83\x91",
		"\xE1\x83\x92" => "\xE1\x83\x92",
		"\xE1\x83\x93" => "\xE1\x83\x93",
		"\xE1\x83\x94" => "\xE1\x83\x94",
		"\xE1\x83\x95" => "\xE1\x83\x95",
		"\xE1\x83\x96" => "\xE1\x83\x96",
		"\xE1\x83\x97" => "\xE1\x83\x97",
		"\xE1\x83\x98" => "\xE1\x83\x98",
		"\xE1\x83\x99" => "\xE1\x83\x99",
		"\xE1\x83\x9A" => "\xE1\x83\x9A",
		"\xE1\x83\x9B" => "\xE1\x83\x9B",
		"\xE1\x83\x9C" => "\xE1\x83\x9C",
		"\xE1\x83\x9D" => "\xE1\x83\x9D",
		"\xE1\x83\x9E" => "\xE1\x83\x9E",
		"\xE1\x83\x9F" => "\xE1\x83\x9F",
		"\xE1\x83\xA0" => "\xE1\x83\xA0",
		"\xE1\x83\xA1" => "\xE1\x83\xA1",
		"\xE1\x83\xA2" => "\xE1\x83\xA2",
		"\xE1\x83\xA3" => "\xE1\x83\xA3",
		"\xE1\x83\xA4" => "\xE1\x83\xA4",
		"\xE1\x83\xA5" => "\xE1\x83\xA5",
		"\xE1\x83\xA6" => "\xE1\x83\xA6",
		"\xE1\x83\xA7" => "\xE1\x83\xA7",
		"\xE1\x83\xA8" => "\xE1\x83\xA8",
		"\xE1\x83\xA9" => "\xE1\x83\xA9",
		"\xE1\x83\xAA" => "\xE1\x83\xAA",
		"\xE1\x83\xAB" => "\xE1\x83\xAB",
		"\xE1\x83\xAC" => "\xE1\x83\xAC",
		"\xE1\x83\xAD" => "\xE1\x83\xAD",
		"\xE1\x83\xAE" => "\xE1\x83\xAE",
		"\xE1\x83\xAF" => "\xE1\x83\xAF",
		"\xE1\x83\xB0" => "\xE1\x83\xB0",
		"\xE1\x83\xB1" => "\xE1\x83\xB1",
		"\xE1\x83\xB2" => "\xE1\x83\xB2",
		"\xE1\x83\xB3" => "\xE1\x83\xB3",
		"\xE1\x83\xB4" => "\xE1\x83\xB4",
		"\xE1\x83\xB5" => "\xE1\x83\xB5",
		"\xE1\x83\xB6" => "\xE1\x83\xB6",
		"\xE1\x83\xB7" => "\xE1\x83\xB7",
		"\xE1\x83\xB8" => "\xE1\x83\xB8",
		"\xE1\x83\xB9" => "\xE1\x83\xB9",
		"\xE1\x83\xBA" => "\xE1\x83\xBA",
		"\xE1\x83\xBD" => "\xE1\x83\xBD",
		"\xE1\x83\xBE" => "\xE1\x83\xBE",
		"\xE1\x83\xBF" => "\xE1\x83\xBF",
		"\xE1\xBE\x80" => "\xE1\xBE\x88",
		"\xE1\xBE\x81" => "\xE1\xBE\x89",
		"\xE1\xBE\x82" => "\xE1\xBE\x8A",
		"\xE1\xBE\x83" => "\xE1\xBE\x8B",
		"\xE1\xBE\x84" => "\xE1\xBE\x8C",
		"\xE1\xBE\x85" => "\xE1\xBE\x8D",
		"\xE1\xBE\x86" => "\xE1\xBE\x8E",
		"\xE1\xBE\x87" => "\xE1\xBE\x8F",
		"\xE1\xBE\x88" => "\xE1\xBE\x88",
		"\xE1\xBE\x89" => "\xE1\xBE\x89",
		"\xE1\xBE\x8A" => "\xE1\xBE\x8A",
		"\xE1\xBE\x8B" => "\xE1\xBE\x8B",
		"\xE1\xBE\x8C" => "\xE1\xBE\x8C",
		"\xE1\xBE\x8D" => "\xE1\xBE\x8D",
		"\xE1\xBE\x8E" => "\xE1\xBE\x8E",
		"\xE1\xBE\x8F" => "\xE1\xBE\x8F",
		"\xE1\xBE\x90" => "\xE1\xBE\x98",
		"\xE1\xBE\x91" => "\xE1\xBE\x99",
		"\xE1\xBE\x92" => "\xE1\xBE\x9A",
		"\xE1\xBE\x93" => "\xE1\xBE\x9B",
		"\xE1\xBE\x94" => "\xE1\xBE\x9C",
		"\xE1\xBE\x95" => "\xE1\xBE\x9D",
		"\xE1\xBE\x96" => "\xE1\xBE\x9E",
		"\xE1\xBE\x97" => "\xE1\xBE\x9F",
		"\xE1\xBE\x98" => "\xE1\xBE\x98",
		"\xE1\xBE\x99" => "\xE1\xBE\x99",
		"\xE1\xBE\x9A" => "\xE1\xBE\x9A",
		"\xE1\xBE\x9B" => "\xE1\xBE\x9B",
		"\xE1\xBE\x9C" => "\xE1\xBE\x9C",
		"\xE1\xBE\x9D" => "\xE1\xBE\x9D",
		"\xE1\xBE\x9E" => "\xE1\xBE\x9E",
		"\xE1\xBE\x9F" => "\xE1\xBE\x9F",
		"\xE1\xBE\xA0" => "\xE1\xBE\xA8",
		"\xE1\xBE\xA1" => "\xE1\xBE\xA9",
		"\xE1\xBE\xA2" => "\xE1\xBE\xAA",
		"\xE1\xBE\xA3" => "\xE1\xBE\xAB",
		"\xE1\xBE\xA4" => "\xE1\xBE\xAC",
		"\xE1\xBE\xA5" => "\xE1\xBE\xAD",
		"\xE1\xBE\xA6" => "\xE1\xBE\xAE",
		"\xE1\xBE\xA7" => "\xE1\xBE\xAF",
		"\xE1\xBE\xA8" => "\xE1\xBE\xA8",
		"\xE1\xBE\xA9" => "\xE1\xBE\xA9",
		"\xE1\xBE\xAA" => "\xE1\xBE\xAA",
		"\xE1\xBE\xAB" => "\xE1\xBE\xAB",
		"\xE1\xBE\xAC" => "\xE1\xBE\xAC",
		"\xE1\xBE\xAD" => "\xE1\xBE\xAD",
		"\xE1\xBE\xAE" => "\xE1\xBE\xAE",
		"\xE1\xBE\xAF" => "\xE1\xBE\xAF",
		"\xE1\xBE\xB2" => "\xE1\xBE\xBA\xCD\x85",
		"\xE1\xBE\xB3" => "\xE1\xBE\xBC",
		"\xE1\xBE\xB4" => "\xCE\x86\xCD\x85",
		"\xE1\xBE\xB7" => "\xCE\x91\xCD\x82\xCD\x85",
		"\xE1\xBE\xBC" => "\xE1\xBE\xBC",
		"\xE1\xBF\x82" => "\xE1\xBF\x8A\xCD\x85",
		"\xE1\xBF\x83" => "\xE1\xBF\x8C",
		"\xE1\xBF\x84" => "\xCE\x89\xCD\x85",
		"\xE1\xBF\x87" => "\xCE\x97\xCD\x82\xCD\x85",
		"\xE1\xBF\x8C" => "\xE1\xBF\x8C",
		"\xE1\xBF\xB2" => "\xE1\xBF\xBA\xCD\x85",
		"\xE1\xBF\xB3" => "\xE1\xBF\xBC",
		"\xE1\xBF\xB4" => "\xCE\x8F\xCD\x85",
		"\xE1\xBF\xB7" => "\xCE\xA9\xCD\x82\xCD\x85",
		"\xE1\xBF\xBC" => "\xE1\xBF\xBC",
		"\xEF\xAC\x80" => "\x46\x66",
		"\xEF\xAC\x81" => "\x46\x69",
		"\xEF\xAC\x82" => "\x46\x6C",
		"\xEF\xAC\x83" => "\x46\x66\x69",
		"\xEF\xAC\x84" => "\x46\x66\x6C",
		"\xEF\xAC\x85" => "\x53\x74",
		"\xEF\xAC\x86" => "\x53\x74",
		"\xEF\xAC\x93" => "\xD5\x84\xD5\xB6",
		"\xEF\xAC\x94" => "\xD5\x84\xD5\xA5",
		"\xEF\xAC\x95" => "\xD5\x84\xD5\xAB",
		"\xEF\xAC\x96" => "\xD5\x8E\xD5\xB6",
		"\xEF\xAC\x97" => "\xD5\x84\xD5\xAD",
	];
}

?>