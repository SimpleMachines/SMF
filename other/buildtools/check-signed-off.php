<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

$message = shell_exec('git show -s --format=%B HEAD | tail -n 1');
$result = stripos($message, 'Signed-off by:');
if ($result === false)
	die('Error: Signed-off by not found in commit message');