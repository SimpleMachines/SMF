<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

// Stuff we will ignore.
$ignoreFiles = array(
	// Build tools
	'./other/buildtools/[A-Za-z0-9-_]+.php',

	// Cache and miscellaneous.
	'\./cache/data_[A-Za-z0-9-_]\.php',
	'\./other/db_last_error.php',

	// Installer and ugprade are not a worry.
	'\./other/install.php',
	'\./other/upgrade.php',
	'\./other/upgrade-helper.php',

	// Minify Stuff.
	'\./Sources/minify/[A-Za-z0-9/-]+\.php',

	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/[A-Za-z0-9]+\.php',
	'\./Sources/ReCaptcha/RequestMethod/[A-Za-z0-9]+\.php',

	// We will ignore Settings.php if this is a live dev site.
	'\./Settings.php',
	'\./Settings_bak.php',
	'\./db_last_error.php',
);

// No file? Thats bad.
if (!isset($_SERVER['argv'], $_SERVER['argv'][1]))
	die('Error: No File specified' . "\n");

// The file has to exist.
$currentFile = $_SERVER['argv'][1];
if (!file_exists($currentFile))
	die('Error: File does not exist' . "\n");

// Is this ignored?
foreach ($ignoreFiles as $if)
	if (preg_match('~' . $if . '~i', $currentFile))
		die;

// Less efficent than opening a file with fopen, but we want to be sure to get the right end of the file. file_get_contents
$file = fopen($currentFile, 'r');

// Seek the end minus some bytes.
fseek($file, -100, SEEK_END);
$contents = fread($file, 100);

// There is some white space here.
if (preg_match('~\?>\s+$~', $contents, $matches))
	die('Error: End of File contains extra spaces in ' . $currentFile . "\n");

// Test to see if its there even, StoryBB base package needs it there in our main files to allow package manager to properly handle end operations.  Customizations do not need it.
if (!preg_match('~\?>$~', $contents, $matches))
	die('Error: End of File missing in ' . $currentFile . "\n");

// Test to see if a function/class ending is here but with no return (because we are OCD).
if (preg_match('~}([\r]?\n)?\?>~', $contents, $matches))
	echo('Error: Incorrect return(s) after last function/class but before EOF in ' . $currentFile . "\n");

// Test to see if a string ending is here but with no return (because we are OCD).
if (preg_match('~;([\r]?\n)?\?>~', $contents, $matches))
	echo('Error: Incorrect return(s) after last string but before EOF in ' . $currentFile . "\n");