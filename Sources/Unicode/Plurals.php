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

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Helper function for SMF\Localization\MessageFormatter::formatMessage.
 *
 * Rules compiled from:
 * https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/plurals.json
 * https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/ordinals.json
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Pluralization rules for different languages
 */
function plurals(): array
{
	return [
		'af' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ak' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'am' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'an' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ar' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [3,4,5,6,7,8,9,10]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ars' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [3,4,5,6,7,8,9,10]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'as' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,5,7,8,9,10]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'asa' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ast' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'az' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i % 10, [1,2,5,7,8]) or in_array($i % 100, [20,50,70,80]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i % 10, [3,4]) or in_array($i % 1000, [100,200,300,400,500,600,700,800,900]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $i % 10 == 6 or in_array($i % 100, [40,60,90]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bal' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'be' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && $n % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [2,3,4]) && !in_array($n % 100, [12,13,14]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 0 or in_array($n % 10, [5,6,7,8,9]) or in_array($n % 100, [11,12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [2,3]) && !in_array($n % 100, [12,13]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bem' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bez' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bg' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bho' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'blo' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [2,3,4,5,6]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bm' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,5,7,8,9,10]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'br' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && !in_array($n % 100, [11,71,91]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 2 && !in_array($n % 100, [12,72,92]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [3,4,9]) && !in_array($n % 100, [10,11,12,13,14,15,16,17,18,19,70,71,72,73,74,75,76,77,78,79,90,91,92,93,94,95,96,97,98,99]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n != 0 && $n % 1000000 == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'brx' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'bs' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11 or $f % 10 == 1 && $f % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]) or in_array($f % 10, [2,3,4]) && !in_array($f % 100, [12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ca' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,3]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ce' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ceb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i, [1,2,3]) or $v == 0 && !in_array($i % 10, [4,6,9]) or $v != 0 && !in_array($f % 10, [4,6,9]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'cgg' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'chr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ckb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'cs' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [2,3,4]) && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'cy' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 3,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,7,8,9]),
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [3,4]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [5,6]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'da' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1 or $t != 0 && in_array($i, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'de' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'doi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'dsb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 1 or $f % 100 == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 2 or $f % 100 == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 100, [3,4]) or in_array($f % 100, [3,4]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'dv' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'dz' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ee' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'el' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'en' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && $n % 100 != 11,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 2 && $n % 100 != 12,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 3 && $n % 100 != 13,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'eo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'es' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'et' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'eu' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fa' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ff' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fil' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i, [1,2,3]) or $v == 0 && !in_array($i % 10, [4,6,9]) or $v != 0 && !in_array($f % 10, [4,6,9]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fur' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'fy' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ga' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [3,4,5,6]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [7,8,9,10]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'gd' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,11]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,12]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [3,4,5,6,7,8,9,10,13,14,15,16,17,18,19]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,11]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,12]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [3,13]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'gl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'gsw' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'gu' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'guw' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'gv' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 100, [0,20,40,60,80]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ha' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'haw' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'he' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0 or $i == 0 && $v != 0,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 2 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hnj' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11 or $f % 10 == 1 && $f % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]) or in_array($f % 10, [2,3,4]) && !in_array($f % 100, [12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hsb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 1 or $f % 100 == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 2 or $f % 100 == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 100, [3,4]) or in_array($f % 100, [3,4]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hu' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'hy' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ia' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'id' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ig' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ii' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'io' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'is' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $t == 0 && $i % 10 == 1 && $i % 100 != 11 or $t % 10 == 1 && $t % 100 != 11,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'it' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [11,8,80,800]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'iu' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ja' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'jbo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'jgo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'jmc' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'jv' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'jw' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ka' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or in_array($i % 100, [2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,40,60,80]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kab' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kaj' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kcg' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kde' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kea' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 6 or $n % 10 == 9 or $n % 10 == 0 && $n != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kkj' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'km' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ko' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ks' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ksb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ksh' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ku' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'kw' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [2,22,42,62,82]) or $n % 1000 == 0 && in_array($n % 100000, [1000,2000,3000,4000,5000,6000,7000,8000,9000,10000,11000,12000,13000,14000,15000,16000,17000,18000,19000,20000,40000,60000,80000]) or $n != 0 && $n % 1000000 == 100000,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [3,23,43,63,83]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n != 1 && in_array($n % 100, [1,21,41,61,81]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,2,3,4]) or in_array($n % 100, [1,2,3,4,21,22,23,24,41,42,43,44,61,62,63,64,81,82,83,84]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 5 or $n % 100 == 5,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ky' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lag' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0,
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]) && $n != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lg' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lij' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [11,8,80,81,82,83,84,85,86,87,88,89,800,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,817,818,819,820,821,822,823,824,825,826,827,828,829,830,831,832,833,834,835,836,837,838,839,840,841,842,843,844,845,846,847,848,849,850,851,852,853,854,855,856,857,858,859,860,861,862,863,864,865,866,867,868,869,870,871,872,873,874,875,876,877,878,879,880,881,882,883,884,885,886,887,888,889,890,891,892,893,894,895,896,897,898,899]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lkt' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ln' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lt' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && !in_array($n % 100, [11,12,13,14,15,16,17,18,19]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [2,3,4,5,6,7,8,9]) && !in_array($n % 100, [11,12,13,14,15,16,17,18,19]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $f != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'lv' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 0 or in_array($n % 100, [11,12,13,14,15,16,17,18,19]) or $v == 2 && in_array($f % 100, [11,12,13,14,15,16,17,18,19]),
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && $n % 100 != 11 or $v == 2 && $f % 10 == 1 && $f % 100 != 11 or $v != 2 && $f % 10 == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mas' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mg' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mgo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11 or $f % 10 == 1 && $f % 100 != 11,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i % 10 == 1 && $i % 100 != 11,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $i % 10 == 2 && $i % 100 != 12,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i % 10, [7,8]) && !in_array($i % 100, [17,18]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ml' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v != 0 or $n == 0 or $n != 1 && in_array($n % 100, [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ms' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'mt' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 0 or in_array($n % 100, [3,4,5,6,7,8,9,10]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 100, [11,12,13,14,15,16,17,18,19]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'my' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nah' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'naq' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nb' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nd' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ne' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,2,3,4]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nnh' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'no' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nqo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nso' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ny' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'nyn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'om' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'or' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [1,5,7,8,9]),
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3]),
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 4,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 6,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'os' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'osa' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pa' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pap' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pcm' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i != 1 && in_array($i % 10, [0,1]) or $v == 0 && in_array($i % 10, [5,6,7,8,9]) or $v == 0 && in_array($i % 100, [12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'prg' => [
			'cardinal' => [
				'zero' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 0 or in_array($n % 100, [11,12,13,14,15,16,17,18,19]) or $v == 2 && in_array($f % 100, [11,12,13,14,15,16,17,18,19]),
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 1 && $n % 100 != 11 or $v == 2 && $f % 10 == 1 && $f % 100 != 11 or $v != 2 && $f % 10 == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ps' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pt' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [0,1]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'pt-PT' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'rm' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ro' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v != 0 or $n == 0 or $n != 1 && in_array($n % 100, [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'rof' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ru' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 0 or $v == 0 && in_array($i % 10, [5,6,7,8,9]) or $v == 0 && in_array($i % 100, [11,12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'rwk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sah' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'saq' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sat' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sc' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [11,8,80,800]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'scn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [11,8,80,800]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sd' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sdh' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'se' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'seh' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ses' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sg' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sh' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11 or $f % 10 == 1 && $f % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]) or in_array($f % 10, [2,3,4]) && !in_array($f % 100, [12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'shi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [2,3,4,5,6,7,8,9,10]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'si' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]) or $i == 0 && $f == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($i, [2,3,4]) && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 100 == 2,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 100, [3,4]) or $v != 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sma' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'smi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'smj' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'smn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sms' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'two' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 2,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'so' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sq' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 4 && $n % 100 != 14,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11 or $f % 10 == 1 && $f % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]) or in_array($f % 10, [2,3,4]) && !in_array($f % 100, [12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ss' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ssy' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'st' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'su' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sv' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [1,2]) && !in_array($n % 100, [11,12]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'sw' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'syr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ta' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'te' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'teo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'th' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ti' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tig' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n % 10, [6,9]) or $n == 10,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tl' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i, [1,2,3]) or $v == 0 && !in_array($i % 10, [4,6,9]) or $v != 0 && !in_array($f % 10, [4,6,9]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tn' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'to' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tpi' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tr' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ts' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'tzm' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]) or in_array($n, [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ug' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'uk' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 1 && $i % 100 != 11,
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && in_array($i % 10, [2,3,4]) && !in_array($i % 100, [12,13,14]),
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $v == 0 && $i % 10 == 0 or $v == 0 && in_array($i % 10, [5,6,7,8,9]) or $v == 0 && in_array($i % 100, [11,12,13,14]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'few' => fn ($n, $i, $v, $w, $f, $t, $c) => $n % 10 == 3 && $n % 100 != 13,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'und' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'ur' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'uz' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		've' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'vec' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => $c == 0 && $i != 0 && $i % 1000000 == 0 && $v == 0 or !in_array($c, [0,1,2,3,4,5]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'many' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [11,8,80,800]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'vi' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'vo' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'vun' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'wa' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => in_array($n, [0,1]),
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'wae' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'wo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'xh' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'xog' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'yi' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 1 && $v == 0,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'yo' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'yue' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'zh' => [
			'cardinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
		'zu' => [
			'cardinal' => [
				'one' => fn ($n, $i, $v, $w, $f, $t, $c) => $i == 0 or $n == 1,
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
			'ordinal' => [
				'other' => fn ($n, $i, $v, $w, $f, $t, $c) => true,
			],
		],
	];
}

?>