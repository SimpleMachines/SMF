<?php

/**
 * Removes the empty line the converter leaves after a line ending in a closing
 * tag, when the next line is indented. PHP eats the newline after the tag, so
 * the empty line is what kept that newline in the output; dropping it leaves
 * "\t\t<div>" where "\n\t\t<div>" was, and HTML collapses both to one space.
 *
 * Text inside <pre>, <textarea> and <script> is left alone, since a newline
 * there is content or a statement boundary. `compare.php --rendered` checks
 * that the pages are the same both ways.
 *
 *   php .dev/plates/tidy.php Themes/default/templates/Who/*.php
 */

declare(strict_types=1);

foreach (array_slice($argv, 1) as $file) {
	$lines = explode("\n", file_get_contents($file));
	$out = [];
	$removed = 0;

	for ($i = 0, $n = count($lines); $i < $n; $i++) {
		// Inside these, a newline is content, or a statement boundary in JavaScript.
		$before = implode("\n", array_slice($lines, 0, $i));
		$protected = false;

		foreach (['script', 'pre', 'textarea'] as $tag) {
			$open = max(strripos($before, "<$tag>"), strripos($before, "<$tag "));
			$close = strripos($before, "</$tag>");

			if ($open !== false && ($close === false || $open > $close)) {
				$protected = true;
			}
		}

		if (
			!$protected
			&& $lines[$i] === ''
			&& $i > 0
			&& str_ends_with($lines[$i - 1], '?>')
			&& isset($lines[$i + 1])
			&& preg_match('/^[ \t]/', $lines[$i + 1])
		) {
			$removed++;
			continue;
		}

		$out[] = $lines[$i];
	}

	if ($removed > 0) {
		file_put_contents($file, implode("\n", $out));
		fwrite(STDERR, "$removed\t$file\n");
	}
}
