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

$message = trim(shell_exec('git show -s --format=%B HEAD'));
$lines = explode("\n", trim(str_replace("\r", "\n", $message)));
$lastLine = $lines[count($lines) - 1];

// This is debugging stuff for now to figure out why Travis can fail to get us the right info about a signed off commit.
$debugMaster = base64_encode(json_encode(array(
	// Raw body.
	'B' => shell_exec('git show -s --format=%B HEAD'),
	// Body.
	'b2' => shell_exec('git show -s --format=%b HEAD'),
	// Commit notes.
	'N' => shell_exec('git show -s --format=%N HEAD'),
	// Ref names.
	'd' => shell_exec('git show -s --format=%d HEAD'),
	// Commit hash.
	'H' => shell_exec('git show -s --format=%H HEAD'),
	// Tree hash.
	'T' => shell_exec('git show -s --format=%T HEAD'),
	// Parent hash.
	'P' => shell_exec('git show -s --format=%P HEAD'),
)));

// Did we find a merge?
if (preg_match('~Merge ([A-Za-z0-9]{40}) into ([A-Za-z0-9]{40})~i', $lastLine, $merges))
{
	echo 'Message contains a merge, trying to find parent [' . $lastLine . ']' . '[' . $message . ']' . "\n";
	$message = trim(shell_exec('git show -s --format=%B ' . $merges[1]));	
	$lines = explode("\n", trim(str_replace("\r", "\n", $message)));
	$lastLine = $lines[count($lines) - 1];

	// This is debugging stuff for now to figure out why Travis can fail to get us the right info about a signed off commit.
	$debugSecondary = base64_encode(json_encode(array(
		// Raw body.
		'B' => shell_exec('git show -s --format=%B HEAD'),
		// Body.
		'b2' => shell_exec('git show -s --format=%b HEAD'),
		// Commit notes.
		'N' => shell_exec('git show -s --format=%N HEAD'),
		// Ref names.
		'd' => shell_exec('git show -s --format=%d HEAD'),
		// Commit hash.
		'H' => shell_exec('git show -s --format=%H HEAD'),
		// Tree hash.
		'T' => shell_exec('git show -s --format=%T HEAD'),
		// Parent hash.
		'P' => shell_exec('git show -s --format=%P HEAD'),
	)));
}
else
	$debugSecondary = base64_encode(json_encode(array(NULL)));

$result = stripos($lastLine, 'Signed-off-by:');
if ($result === false)
{
	// Try 2.
	$result2 = stripos($lastLine, 'Signed by');
	if ($result2 === false)
	{
		echo "--DEBUG MASTER--\n";
		echo $debugMaster . "\n";
		echo "--DEBUG MASTER--\n";

		echo "--DEBUG SECONDARY--\n";
		echo $debugSecondary . "\n";
		echo "--DEBUG SECONDARY--\n";

		die('Error: Signed-off-by not found in commit message [' . $lastLine . ']' . '[' . $message . ']' . '[' . $debugSecondary . '][' . $debugMaster . ']' . "\n");
	}
}