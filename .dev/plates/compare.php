<?php

/**
 * Compares two sets of captured pages, as written by .dev/capture-pages.sh,
 * and lists the pages that differ.
 *
 *   php .dev/plates/compare.php logs/pages-before logs/pages-after [--rendered] [-v]
 *
 * Both sides are masked first for what changes between any two runs: the
 * verification honeypot's random field name, every run of hex that holds a
 * digit (sessions, tokens, ids, counts, times), and the plural that follows
 * the number of guests online.
 *
 * By default the rest must match byte for byte. With --rendered, runs of
 * whitespace count as one space, as a browser shows them, except inside
 * <pre>, <textarea> and <script>, where a newline is content or a statement
 * boundary and must still match exactly.
 *
 * COMPARE_SKIP is a regular expression of pages to leave out. By default it
 * skips the login history and the error log, which grow with every run.
 */

declare(strict_types=1);

$args = array_slice($argv, 1);
$verbose = in_array('-v', $args, true);
$rendered = in_array('--rendered', $args, true);
$dirs = array_values(array_filter($args, fn ($a) => !str_starts_with($a, '-')));

if (count($dirs) !== 2 || !is_dir($dirs[0]) || !is_dir($dirs[1])) {
	fwrite(STDERR, "usage: php .dev/plates/compare.php BEFORE_DIR AFTER_DIR [--rendered] [-v]\n");

	exit(2);
}

[$before, $after] = array_map(fn ($d) => rtrim($d, '/'), $dirs);
$skip = getenv('COMPARE_SKIP') ?: 'profile_track_logins|admin.track_ip';

$mask = function (string $s): string {
	$s = preg_replace('/name="[a-z]+-[a-z]+-[0-9a-z]{4}"/', 'name="{honeypot}"', $s);
	$s = preg_replace('/\b(\d+) guests?\b/', '$1 guest', $s);

	return preg_replace('/[0-9a-f]*\d[0-9a-f]*/', 'H', $s);
};

// The blocks whose whitespace matters, and the text around them with its whitespace collapsed.
$split = function (string $s): array {
	$pattern = '~<(pre|textarea|script)\b.*?</\1>~is';
	preg_match_all($pattern, $s, $m);

	return [$m[0], array_map(fn ($t) => preg_replace('/\s+/', ' ', $t), preg_split($pattern, $s))];
};

// Where two strings first differ, with some context on each side.
$near = function (string $a, string $b): string {
	$at = strspn($a ^ $b, "\0");

	return '    ' . json_encode(substr($a, max(0, $at - 80), 160)) . "\n    " . json_encode(substr($b, max(0, $at - 80), 160)) . "\n";
};

$files = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($before, FilesystemIterator::SKIP_DOTS)) as $file) {
	if (str_ends_with($file->getPathname(), '.html')) {
		$files[] = substr($file->getPathname(), strlen($before) + 1);
	}
}

sort($files);
$total = $identical = $changed = 0;

foreach ($files as $rel) {
	if (preg_match("~$skip~", $rel)) {
		continue;
	}

	$total++;

	if (!is_file("$after/$rel")) {
		echo "MISSING $rel\n";
		$changed++;

		continue;
	}

	$a = $mask(file_get_contents("$before/$rel"));
	$b = $mask(file_get_contents("$after/$rel"));

	if ($a === $b) {
		$identical++;

		continue;
	}

	if ($rendered) {
		[$blocks_a, $text_a] = $split($a);
		[$blocks_b, $text_b] = $split($b);

		if ($blocks_a === $blocks_b && $text_a === $text_b) {
			continue;
		}
	}

	$changed++;
	echo "DIFF $rel\n";

	if (!$verbose) {
		continue;
	}

	if (!$rendered) {
		echo $near($a, $b);

		continue;
	}

	foreach ($blocks_a as $i => $block) {
		if ($block !== ($blocks_b[$i] ?? null)) {
			echo "  in <pre>, <textarea> or <script> number $i:\n" . $near($block, $blocks_b[$i] ?? '');

			continue 2;
		}
	}

	foreach ($text_a as $i => $text) {
		if ($text !== ($text_b[$i] ?? null)) {
			echo "  in the text:\n" . $near($text, $text_b[$i] ?? '');

			continue 2;
		}
	}

	echo "  in the number of <pre>, <textarea> or <script> blocks\n";
}

echo "$changed of $total pages differ ($identical identical"
	. ($rendered ? ', the rest the same as rendered' : '') . ")\n";

exit($changed === 0 ? 0 : 1);
