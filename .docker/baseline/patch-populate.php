<?php

/**
 * Prepares the upstream Populate.php for use as a library.
 *
 *   php .docker/baseline/patch-populate.php <source> <destination>
 *   php .docker/baseline/patch-populate.php --check <source>
 *
 * Populate.php (https://github.com/SimpleMachines/tools) is written to be
 * opened in a browser: it loads SSI.php, constructs itself, does one block of
 * work and refreshes the page. Four small edits make it usable from a script.
 * Every one of them asserts it matched exactly once, so an upstream change
 * breaks the generator loudly instead of quietly producing a different baseline.
 *
 *   1 + 2. Neutralise the two top-level statements, so requiring the file
 *          defines the classes and nothing else. run-populate.php then loads
 *          SSI.php itself and constructs Populate with the targets for the
 *          chosen size profile -- the constructor already accepts an options
 *          array and copies it over its own defaults, so the hardcoded counters
 *          never need rewriting.
 *
 *   3 + 4. Two mt_rand() calls are fatal on PHP 8, which rejects a min greater
 *          than its max where PHP 7 quietly swapped them:
 *
 *            makeBoards()   mt_rand(1, $current - 1)  with $current == 1
 *            makeMessages() mt_rand(1, $current)      with $current == 0
 *
 *          Both happen on the very first item, so unpatched the tool cannot run
 *          at all on a modern PHP. Guarding them is the smallest fix that keeps
 *          the original behaviour everywhere else.
 *
 * The patched copy lands in .docker/baseline/cache/, which is gitignored: the
 * upstream file is third-party licensed (MPL 1.1, plus a BSD lorem ipsum
 * generator) and is fetched at a pinned commit rather than vendored here.
 *
 * Deliberately written to PHP 7.1 syntax: SMF 2.1's CI lints every PHP file in
 * the checkout against 7.1 through 8.4.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (PHP_SAPI !== 'cli')
	die('This script may only be run from the command line.');

$check_only = false;
$paths = array();

foreach (array_slice($argv, 1) as $arg)
{
	if ($arg === '--check')
		$check_only = true;
	else
		$paths[] = $arg;
}

if (empty($paths[0]) || (!$check_only && empty($paths[1])))
	die("usage: patch-populate.php <source> <destination>\n       patch-populate.php --check <source>\n");

$source_file = $paths[0];

if (!is_file($source_file))
	fail('no such file: ' . $source_file);

$source = file_get_contents($source_file);

/*
 * Each edit is a search pattern, a replacement, and why it exists. The patterns
 * are anchored on code rather than line numbers so that unrelated upstream
 * edits do not break them.
 */
$edits = array(
	array(
		'name' => 'ssi-require',
		'pattern' => "~^require_once\('SSI\.php'\);~m",
		'replacement' => "// [baseline] SSI.php is loaded by run-populate.php before this file.",
		'why' => 'run-populate.php controls when and how SMF is loaded',
	),
	array(
		'name' => 'auto-run',
		'pattern' => '~^\$populate = new Populate\(\);~m',
		'replacement' => '// [baseline] run-populate.php constructs Populate with the profile targets.',
		'why' => 'the targets have to be passed in, which means constructing it ourselves',
	),
	array(
		'name' => 'boards-parent-mt-rand',
		'pattern' => '~if \(mt_rand\(\) < \(mt_getrandmax\(\) / 2\)\)~',
		'replacement' => "if (\$this->counters['boards']['current'] > 1 && mt_rand() < (mt_getrandmax() / 2))",
		'why' => 'the first board would call mt_rand(1, 0), which is fatal on PHP 8',
	),
	array(
		'name' => 'topic-id-mt-rand',
		'pattern' => "~'id' => \\\$makenew \\? 0 : mt_rand\\(1, \\\$this->counters\\['topics'\\]\\['current'\\]\\),~",
		'replacement' => "'id' => \$makenew || \$this->counters['topics']['current'] < 1 ? 0 : mt_rand(1, \$this->counters['topics']['current']),",
		'why' => 'the first message would call mt_rand(1, 0), which is fatal on PHP 8',
	),
);

$applied = 0;

foreach ($edits as $edit)
{
	$source = preg_replace($edit['pattern'], $edit['replacement'], $source, 1, $count);

	if ($count !== 1)
		fail(sprintf(
			"could not apply the '%s' edit (matched %d times, expected 1).\n" .
			"           It is there to: %s\n" .
			'           Upstream Populate.php has probably changed. Re-read it, fix the pattern, then bump POPULATE_COMMIT and POPULATE_SHA256 in lib.sh.',
			$edit['name'],
			$count,
			$edit['why']
		));

	$applied++;
	echo '[baseline] patch: ', $edit['name'], "\n";
}

echo '[baseline] ', $applied, ' of ', count($edits), " edits applied\n";

if ($check_only)
	exit(0);

$destination = $paths[1];
$dir = dirname($destination);

if (!is_dir($dir) && !mkdir($dir, 0777, true))
	fail('cannot create ' . $dir);

if (file_put_contents($destination, $source) === false)
	fail('cannot write ' . $destination);

exit(0);

/**
 * @param string $message
 */
function fail($message)
{
	fwrite(STDERR, '[baseline] patch-populate.php: ' . $message . "\n");
	exit(1);
}
