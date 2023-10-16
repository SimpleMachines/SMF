<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BrowserDetector;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Forum;
use SMF\Group;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Url;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Unicode\Utf8String;
use SMF\WebFetch\WebFetchApi;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Attachment');
class_exists('SMF\\BBCodeParser');
class_exists('SMF\\IntegrationHook');
class_exists('SMF\\Image');
class_exists('SMF\\Logging');
class_exists('SMF\\PageIndex');
class_exists('SMF\\QueryString');
class_exists('SMF\\Theme');
class_exists('SMF\\Time');
class_exists('SMF\\TimeZone');
class_exists('SMF\\Topic');
class_exists('SMF\\Url');
class_exists('SMF\\User');
class_exists('SMF\\Utils');
class_exists('SMF\\WebFetch\\WebFetchApi');

/**
 * Calculates all the possible permutations (orders) of array.
 * should not be called on huge arrays (bigger than like 10 elements.)
 * returns an array containing each permutation.
 *
 * @deprecated since 2.1
 * @param array $array An array
 * @return array An array containing each permutation
 */
function permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

?>