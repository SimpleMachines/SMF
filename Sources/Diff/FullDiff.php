<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Diff;

use SMF\Time;
use SMF\Utils;

/**
 * Represents a full, two-way diff between two strings.
 */
class FullDiff extends Diff
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * When $this->label1 and $this->label2 point to different file paths, this
	 * indicates whether the file located at $this->label1 should be renamed to
	 * $this->label2 as part of applying the diff (and vice versa when reverting
	 * the diff). When this is false, the old file should be copied to the new
	 * location and then any changes should be applied to the new copy.
	 *
	 * Has no meaning when $this->label1 is the same as $this->label2.
	 *
	 * Note that because this class does not perform file operations itself, the
	 * value of this property has no effect on its own behaviour. Instead, it
	 * exists in order to provide guidance to other code that does perform file
	 * operations based on the data contained in instances of this class.
	 */
	public bool $rename = false;

	/**
	 * @var bool
	 *
	 * Indicates that this diff makes optional changes, meaning that if the diff
	 * cannot be applied successfully, it should be ignored.
	 *
	 * Note that because this class does not perform file operations itself, the
	 * value of this property has no effect on its own behaviour. Instead, it
	 * exists in order to provide guidance to other code that does perform file
	 * operations based on the data contained in instances of this class.
	 */
	public bool $optional = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Given the modified string, reconstructs the original string.
	 *
	 * @param string $str2 The modified string.
	 * @param bool $dynamic_context Whether to allow the matching algorithm to
	 *    dynamically adjust the number of context lines it considers when
	 *    attempting to find a match for each change. Default: false.
	 * @throws \ValueError if given a string it cannot work with.
	 * @return string The original string.
	 */
	public function revert(string $str2, bool $dynamic_context = false): string
	{
		$real_changes = $this->changes;

		// Reverting is just applying in reverse.
		foreach ($this->changes as $c => $change) {
			$this->changes[$c]['l1'] = $change['l2'];
			$this->changes[$c]['l2'] = $change['l1'];
			$this->changes[$c]['old'] = $change['new'];
			$this->changes[$c]['new'] = $change['old'];
		}

		try {
			$str1 = $this->apply($str2, $dynamic_context);
		} catch (\ValueError $e) {
			$this->changes = $real_changes;

			$broken_changes = Utils::jsonDecode($e->getMessage(), true);

			foreach ($broken_changes as $c => $change) {
				$broken_changes[$c]['l1'] = $change['l2'];
				$broken_changes[$c]['l2'] = $change['l1'];
				$broken_changes[$c]['old'] = $change['new'];
				$broken_changes[$c]['new'] = $change['old'];
			}

			throw new \ValueError(json_encode($broken_changes));
		}

		$this->changes = $real_changes;

		return $str1;
	}

	/**
	 * Outputs a raw diff in the old-fashioned "normal" format.
	 *
	 * @return string The diff data in normal format.
	 */
	public function formatNormal(): string
	{
		if ($this->is_binary === 2) {
			return 'Binary files ' . $this->label1 . ' and ' . $this->label2 . ' differ' . "\n";
		}

		if ($this->is_binary === 1) {
			return '';
		}

		// Build the list of hunks.
		$hunks = $this->buildHunks(false);

		$last_h = array_key_last($hunks);

		// Format the hunks for output.
		foreach ($hunks as $h => $hunk) {
			$del_empty = true;
			$ins_empty = true;

			foreach ($hunk['compiled'] as $b => $blob) {
				if (
					\in_array($blob['type'], ['del', 'replace_del'])
					&& !empty($blob['lines'])
				) {
					$del_empty = false;
				}

				if (
					\in_array($blob['type'], ['ins', 'replace_ins'])
					&& !empty($blob['lines'])
				) {
					$ins_empty = false;
				}

				if (!$del_empty && !$ins_empty) {
					break;
				}
			}

			$operation = (!$del_empty && !$ins_empty) ? 'c' : (!$del_empty ? 'd' : 'a');

			$header = '';
			$header .= ($hunk['context1_start'] + ($operation === 'a' ? 0 : 1));
			$header .= ($hunk['context1_count'] > 1 ? ',' . ($hunk['context1_start'] + $hunk['context1_count']) : '');
			$header .= $operation;
			$header .= ($hunk['context2_start'] + ($operation === 'd' ? 0 : 1));
			$header .= ($hunk['context2_count'] > 1 ? ',' . ($hunk['context2_start'] + $hunk['context2_count']) : '');
			$header .= "\n";

			$output[] = $header;

			foreach ($hunk['compiled'] as $b => $blob) {
				$last_l = array_key_last($blob['lines']);

				switch ($blob['type']) {
					case 'context':
						break;

					case 'del':
						if ($operation === 'd') {
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '< ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
						}
						break;

					case 'replace_del':
						if ($operation === 'c') {
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '< ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}

							$output[] = '---' . "\n";
						}
						break;

					case 'ins':
						if ($operation === 'a') {
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '> ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
						}
						break;

					case 'replace_ins':
						if ($operation === 'c') {
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '> ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
						}
						break;
				}
			}
		}

		return implode('', $output);
	}

	/**
	 * Outputs a raw diff in unified format.
	 *
	 * @param bool $escape_paths Whether to escape "unusual" characters in file
	 *    paths, where "unusual" means non-ASCII characters, control characters,
	 *    double-quotes, and backslashes. If any "unusual" characters are found,
	 *    the escaped path will also be wrapped in double-quotes. This behaviour
	 *    replicates how `git diff` handles "unusual" characters in file paths.
	 *    Default: true.
	 * @return string The diff data in unified format.
	 */
	public function formatUnified(bool $escape_paths = true): string
	{
		if ($this->is_binary === 2) {
			return 'Binary files ' . $this->label1 . ' and ' . $this->label2 . ' differ' . "\n";
		}

		if ($this->is_binary === 1) {
			return '';
		}

		$output = [];

		$path1 = $this->label1;
		$path2 = $this->label2;

		// Escape "unusual" characters in file paths?
		if ($escape_paths) {
			foreach (['1', '2'] as $num) {
				${'path' . $num} = self::escapePath(${'path' . $num});
			}
		}

		// Extended headers.
		if ($path1 !== $path2 && $path1 !== '/dev/null' && $path2 !== '/dev/null') {
			if ($this->rename) {
				$output[] = 'rename from ' . $path1 . "\n";
				$output[] = 'rename to ' . $path2 . "\n";
			} elseif (!empty($this->changes)) {
				$output[] = 'copy from ' . $path1 . "\n";
				$output[] = 'copy to ' . $path2 . "\n";
			}
		}

		if (!empty($output) || !empty($this->changes)) {
			if ($this->optional) {
				array_unshift($output, 'optional' . "\n");
			}

			array_unshift($output, 'diff --smf ' . $path1 . ' ' . $path2 . "\n");
		}

		if (empty($this->changes)) {
			return implode('', $output);
		}

		// Standard headers.
		$output[] = '--- ' . $path1 . ($this->time1 !== '' ? "\t" . $this->time1 : '') . "\n";
		$output[] = '+++ ' . $path2 . ($this->time1 !== '' ? "\t" . $this->time2 : '') . "\n";

		// Build the list of hunks.
		$hunks = $this->buildHunks(true);

		$last_h = array_key_last($hunks);

		// Format the hunks for output.
		foreach ($hunks as $h => $hunk) {
			switch ($hunk['context1_count']) {
				case 0:
					// Note that we don't add 1 to the line number in this case.
					$hunk_metadata = '-' . $hunk['context1_start'] . ',0';
					break;

				case 1:
					$hunk_metadata = '-' . $hunk['context1_start'] + 1;
					break;

				default:
					$hunk_metadata = '-' . ($hunk['context1_start'] + 1) . ',' . $hunk['context1_count'];
					break;
			}

			switch ($hunk['context2_count']) {
				case 0:
					// Note that we don't add 1 to the line number in this case.
					$hunk_metadata .= ' +' . $hunk['context2_start'] . ',0';
					break;

				case 1:
					$hunk_metadata .= ' +' . $hunk['context2_start'] + 1;
					break;

				default:
					$hunk_metadata .= ' +' . ($hunk['context2_start'] + 1) . ',' . $hunk['context2_count'];
					break;
			}

			$output[] = '@@ ' . $hunk_metadata . ' @@' . "\n";

			foreach ($hunk['compiled'] as $b => $blob) {
				$last_l = array_key_last($blob['lines']);

				switch ($blob['type']) {
					case 'del':
					case 'replace_del':
						foreach ($blob['lines'] as $l => $line) {
							$output[] = '-' . $line;
							$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
						}
						break;

					case 'ins':
					case 'replace_ins':
						foreach ($blob['lines'] as $l => $line) {
							$output[] = '+' . $line;
							$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
						}
						break;

					default:
						foreach ($blob['lines'] as $l => $line) {
							$output[] = ' ' . $line;
							$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
						}
						break;
				}
			}
		}

		return implode('', $output);
	}

	/**
	 * Outputs a raw diff in context format.
	 *
	 * @return string The diff data in context format.
	 */
	public function formatContext(): string
	{
		if ($this->is_binary === 2) {
			return 'Binary files ' . $this->label1 . ' and ' . $this->label2 . ' differ' . "\n";
		}

		if ($this->is_binary === 1) {
			return '';
		}

		$output = [];

		// Extended headers.
		if ($this->label1 !== $this->label2 && $this->label1 !== '/dev/null' && $this->label2 !== '/dev/null') {
			if ($this->rename) {
				$output[] = 'rename from ' . $this->label1 . "\n";
				$output[] = 'rename to ' . $this->label2 . "\n";
			} elseif (!empty($this->changes)) {
				$output[] = 'copy from ' . $this->label1 . "\n";
				$output[] = 'copy to ' . $this->label2 . "\n";
			}
		}

		if (!empty($output) || !empty($this->changes)) {
			if ($this->optional) {
				array_unshift($output, 'optional' . "\n");
			}

			array_unshift($output, 'diff --smf ' . $this->label1 . ' ' . $this->label2 . "\n");
		}

		if (empty($this->changes)) {
			return implode('', $output);
		}

		// Standard headers.
		$output[] = '*** ' . $this->label1 . ($this->time1 !== '' ? "\t" . $this->time1 : '') . "\n";
		$output[] = '--- ' . $this->label2 . ($this->time1 !== '' ? "\t" . $this->time2 : '') . "\n";

		// Build the list of hunks.
		$hunks = $this->buildHunks(true);

		$last_h = array_key_last($hunks);

		// Format the hunks for output.
		foreach ($hunks as $h => $hunk) {
			$output[] = '***************' . "\n";

			$del_empty = true;
			$ins_empty = true;

			foreach ($hunk['compiled'] as $blob) {
				if (
					\in_array($blob['type'], ['del', 'replace_del'])
					&& !empty($blob['lines'])
				) {
					$del_empty = false;
				}

				if (
					\in_array($blob['type'], ['ins', 'replace_ins'])
					&& !empty($blob['lines'])
				) {
					$ins_empty = false;
				}

				if (!$del_empty && !$ins_empty) {
					break;
				}
			}

			switch ($hunk['context1_count']) {
				case 0:
					// Note that we don't add 1 to the line number in this case.
					$output[] = '*** ' . $hunk['context1_start'] . ' ****' . "\n";
					break;

				case 1:
					$output[] = '*** ' . ($hunk['context1_start'] + 1) . ' ****' . "\n";
					break;

				default:
					$output[] = '*** ' . ($hunk['context1_start'] + 1) . ',' . ($hunk['context1_start'] + $hunk['context1_count']) . ' ****' . "\n";
					break;
			}

			if (!$del_empty) {
				foreach ($hunk['compiled'] as $b => $blob) {
					$last_l = array_key_last($blob['lines']);

					switch ($blob['type']) {
						case 'context':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '  ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;

						case 'del':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '- ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;

						case 'replace_del':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '! ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;
					}
				}
			}

			switch ($hunk['context2_count']) {
				case 0:
					// Note that we don't add 1 to the line number in this case.
					$output[] = '--- ' . $hunk['context2_start'] . ' ----' . "\n";
					break;

				case 1:
					$output[] = '--- ' . ($hunk['context2_start'] + 1) . ' ----' . "\n";
					break;

				default:
					$output[] = '--- ' . ($hunk['context2_start'] + 1) . ',' . ($hunk['context2_start'] + $hunk['context2_count']) . ' ----' . "\n";
					break;
			}

			if (!$ins_empty) {
				foreach ($hunk['compiled'] as $b => $blob) {
					$last_l = array_key_last($blob['lines']);

					switch ($blob['type']) {
						case 'context':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '  ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;

						case 'ins':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '+ ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;

						case 'replace_ins':
							foreach ($blob['lines'] as $l => $line) {
								$output[] = '! ' . $line;
								$this->formatIncompleteLines($output, $line, $h, $l, $last_h, $last_l);
							}
							break;
					}
				}
			}
		}

		return implode('', $output);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Parses raw diff data to make one or more instances of this class.
	 *
	 * Supports raw diffs in unified format, context format, and normal format.
	 * Does not support ed script format.
	 *
	 * If the raw diff contains info about multiple files, the returned array
	 * will contain multiple instances of this class.
	 *
	 * In addition to the standard diff headers for the unified and context
	 * formats, the following extended headers are also recognized:
	 *
	 *    optional
	 *    rename from <old path>
	 *    rename to <new path>
	 *    copy from <old path>
	 *    copy to <new path>
	 *
	 * The "optional" header can be manually inserted into the raw diff in order
	 * to indicate that the changes to a particular file can be ignored if they
	 * cannot be applied. This header should be inserted before the standard
	 * diff headers and, where applicable, before the "rename" and "copy"
	 * extended headers for the file that it refers to. If a mod wants to make
	 * both mandatory and optional changes in the same file, two diffs for the
	 * file can be included: first a diff containing the mandatory changes to
	 * the file, and then a second diff with the "optional" header containing
	 * the optional changes.
	 *
	 * The "rename" and "copy" headers are generated by `git diff` when a file
	 * is copied or renamed. They can also be manually inserted into the raw
	 * diff by mod authors. They must always occur in pairs with the "from"
	 * header line immediately followed by the corresponding "to" header line.
	 * This information is used to indicate the correct file operations to
	 * perform when applying the diff to files.
	 *
	 * @see \SMF\PackageManager\PackageUtils::parseDiff() for more information
	 *    on how to generate raw diffs for use in SMF's package manager.
	 *
	 * @param string $raw_diff The raw diff.
	 * @throws \ValueError if $raw_diff does not contain valid raw diff data.
	 * @return array Instances of this class.
	 */
	public static function createFromRaw(string $raw_diff): array
	{
		$diffs = [];

		$optional = false;
		$path_changes = [
			'copy' => [],
			'rename' => [],
		];

		$lines = preg_split('/\R\K/u', $raw_diff);

		while (!empty($lines)) {
			// Unified diff.
			if (
				str_starts_with($lines[0], '--- ')
				&& str_starts_with($lines[1], '+++ ')
				&& str_starts_with($lines[2], '@@ ')
			) {
				$diffs = array_merge($diffs, self::createFromUnified($lines, $path_changes, $optional));
				continue;
			}

			// Context diff.
			if (
				str_starts_with($lines[0], '*** ')
				&& str_starts_with($lines[1], '--- ')
				&& str_starts_with($lines[2], '***************')
				&& str_starts_with($lines[3], '*** ')
			) {
				$diffs = array_merge($diffs, self::createFromContext($lines, $path_changes, $optional));
				continue;
			}

			// Normal diff.
			if (preg_match('/^\d+(,\d+)?[acd]\d+(,\d+)?/', $lines[0])) {
				$diffs = array_merge($diffs, self::createFromNormal($lines, $path_changes, $optional));
				continue;
			}

			// Special case for extended headers about copied and renamed files.
			if (
				preg_match('/^(rename|copy) from /', $lines[0], $matches)
				&& preg_match('/^' . $matches[1] . ' to /', $lines[1])
			) {
				$old_path = self::unescapePath(rtrim(substr($lines[0], \strlen($matches[1]) + 6)));
				$new_path = self::unescapePath(rtrim(substr($lines[1], \strlen($matches[1]) + 4)));

				// Trim off the initial 'a/' and 'b/' that Git prepends to paths.
				$old_path = str_starts_with($old_path, 'a/') ? substr($old_path, 2) : $old_path;
				$new_path = str_starts_with($new_path, 'b/') ? substr($new_path, 2) : $new_path;

				$path_changes[$matches[1]][$old_path] = [
					'new_path' => $new_path,
					'optional' => $optional,
				];

				$optional = false;

				array_shift($lines);
				continue;
			}

			// Special case for the extended header indicating optional changes.
			if (str_starts_with($lines[0], 'optional')) {
				$optional = true;

				array_shift($lines);
				continue;
			}

			// Initial garbage.
			array_shift($lines);
		}

		// If there were some files that were copied without changes, do them now.
		while (!empty($path_changes['copy'])) {
			$diff = new self();

			$temp = reset($path_changes['copy']);

			$diff->label1 = key($path_changes['copy']);
			$diff->label2 = $temp['new_path'];
			$diff->rename = false;
			$diff->optional = $temp['optional'];

			$diffs[] = $diff;

			array_shift($path_changes['copy']);
		}

		// If there were some files that were renamed without changes, do them now.
		while (!empty($path_changes['rename'])) {
			$diff = new self();

			$temp = reset($path_changes['rename']);

			$diff->label1 = key($path_changes['rename']);
			$diff->label2 = $temp['new_path'];
			$diff->rename = true;
			$diff->optional = $temp['optional'];

			$diffs[] = $diff;

			array_shift($path_changes['rename']);
		}

		// Throw a ValueError if we weren't given valid diff data.
		if (empty($diffs)) {
			throw new \ValueError();
		}

		return $diffs;
	}

	/**
	 * Escapes file paths with "unusual" characters.
	 *
	 * The term "unusual" characters means control characters, double-quotes,
	 * backslashes, and non-ASCII characters. If any "unusual" characters are
	 * found, they will be escaped using addcslashes() and the string will
	 * be wrapped in double-quotes. This replicates the behaviour of `git diff`.
	 *
	 * @param string $path The file path.
	 * @return string Escaped version of $path.
	 */
	public static function escapePath(string $path): string
	{
		$escaped = addcslashes($path, mb_chr(0x0) . '..' . mb_chr(0x1F) . '"\\' . mb_chr(0x80) . '..' . mb_chr(0x10FFFF));

		if ($escaped !== $path) {
			$escaped = '"' . $escaped . '"';
		}

		return $escaped;
	}

	/**
	 * Unescapes file paths with "unusual" characters.
	 *
	 * Only operates on file paths that are wrapped in double-quotes.
	 *
	 * @param string $path The escaped file path.
	 * @return string Unescaped version of $path.
	 */
	public static function unescapePath(string $path): string
	{
		if (str_starts_with($path, '"') && str_ends_with($path, '"')) {
			$path = stripcslashes(substr($path, 1, -1));
		}

		return $path;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Process our internal storage format into data for creating the "hunks" of
	 * raw diff output.
	 *
	 * @param bool $with_context Whether to include context lines in the hunks.
	 * @return array Data hunks about changes that are nearby to each other.
	 */
	protected function buildHunks(bool $with_context): array
	{
		$hunks = [];

		// Build the list of hunks.
		foreach (array_reverse($this->changes, true) as $c => $change) {
			$hunk = [];

			if (!$with_context) {
				$change['before'] = '';
				$change['after'] = '';
			}

			// Replacement is really just deleting and inserting, but we need to
			// keep track of the distinction for the sake of normal and context
			// format diffs.
			$prefix = $change['old'] !== '' && $change['new'] !== '' ? 'replace_' : '';

			$hunk['context1_start'] = $change['l1'] - substr_count($change['before'], "\n");
			$hunk['context2_start'] = $change['l2'] - substr_count($change['before'], "\n");
			$hunk['context1_count'] = substr_count($change['before'] . $change['old'] . $change['after'], "\n");
			$hunk['context2_count'] = substr_count($change['before'] . $change['new'] . $change['after'], "\n");

			$hunk['compiled'] = [
				[
					'type' => 'context',
					'lines' => preg_split('/\R\K/u', $change['before'], -1, PREG_SPLIT_NO_EMPTY),
				],
				[
					'type' => $prefix . 'del',
					'lines' => preg_split('/\R\K/u', $change['old'], -1, PREG_SPLIT_NO_EMPTY),
				],
				[
					'type' => $prefix . 'ins',
					'lines' => preg_split('/\R\K/u', $change['new'], -1, PREG_SPLIT_NO_EMPTY),
				],
				[
					'type' => 'context',
					'lines' => preg_split('/\R\K/u', $change['after'], -1, PREG_SPLIT_NO_EMPTY),
				],
			];

			if (
				isset($hunks[$c + 1])
				&& $hunks[$c + 1]['context1_start'] <= $hunk['context1_start'] + $hunk['context1_count']
			) {
				$hunk['context1_count'] += $hunks[$c + 1]['context1_count'];
				$hunk['context2_count'] += $hunks[$c + 1]['context2_count'];

				if ($hunk['compiled'][1]['type'] === 'replace_del') {
					$hunks[$c + 1]['compiled'][1]['type'] = 'replace_del';
					$hunks[$c + 1]['compiled'][2]['type'] = 'replace_ins';
				}

				$hunk['compiled'] = array_merge(
					$hunk['compiled'],
					$hunks[$c + 1]['compiled'],
				);

				unset($hunks[$c + 1]);
			}

			$hunks[$c] = $hunk;
		}

		ksort($hunks);

		// Fix the count values of the last hunk when either string ended without a newline.
		$last_h = array_key_last($hunks);
		$last_b = array_key_last($hunks[$last_h]['compiled']);

		// If there are context lines at the end, check those.
		if (!empty($hunks[$last_h]['compiled'][$last_b]['lines'])) {
			if (!str_ends_with(end($hunks[$last_h]['compiled'][$last_b]['lines']), "\n")) {
				$hunks[$last_h]['context1_count']++;
				$hunks[$last_h]['context2_count']++;
			}
		} else {
			// Check the inserted lines.
			if (
				!empty($hunks[$last_h]['compiled'][$last_b - 1]['lines'])
				&& !str_ends_with(end($hunks[$last_h]['compiled'][$last_b - 1]['lines']), "\n")
			) {
				$hunks[$last_h]['context2_count']++;
			}

			// Check the deleted lines.
			if (
				!empty($hunks[$last_h]['compiled'][$last_b - 2]['lines'])
				&& !str_ends_with(end($hunks[$last_h]['compiled'][$last_b - 2]['lines']), "\n")
			) {
				$hunks[$last_h]['context1_count']++;
			}
		}

		return $hunks;
	}

	/**
	 * Adds '\ No newline at end of file' to the output where appropriate.
	 *
	 * @param array &$output Content for the raw diff we are building.
	 * @param string $line Content of the current line.
	 * @param int $h Number of the current hunk.
	 * @param int $l Number of the current line.
	 * @param int $last_h Number of the last hunk.
	 * @param int $last_l Number of the lase line.
	 */
	protected function formatIncompleteLines(array &$output, string $line, int $h, int $l, int $last_h, int $last_l): void
	{
		if ($h === $last_h && $l === $last_l && !str_ends_with($line, "\n")) {
			$output[] = "\n\\ No newline at end of file\n";
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Parses an old-fashioned "normal" diff to make instances of this class.
	 *
	 * Lines will be removed from $lines as they are processed. If $lines isn't
	 * empty after this method returns, it can be called again to make more
	 * instances.
	 *
	 * @param array &$lines The lines of the raw diff.
	 * @param array &$path_changes Data from any copy/rename headers.
	 * @param bool &$optional Whether these changes are optional.
	 * @return array Instances of this class.
	 */
	protected static function createFromNormal(array &$lines, array &$path_changes, bool &$optional): array
	{
		if (empty($lines)) {
			return [];
		}

		// Just in case...
		while (!preg_match('/^(\d+)(?:,(\d+))?([acd])(\d+)(?:,(\d+))?/', $lines[0])) {
			array_shift($lines);

			if (empty($lines)) {
				return [];
			}
		}

		$diff = new self();

		// Should this diff be marked as optional?
		$diff->optional = $optional;
		$optional = false;

		// Now analyze the lines of diff data.
		$temp = [
			'old' => [],
			'new' => [],
		];

		foreach ($lines as $l => $line) {
			if (preg_match('/^(\d+)(?:,(\d+))?([acd])(\d+)(?:,(\d+))?/', $line, $matches)) {
				if (!empty($temp['old']) || !empty($temp['new'])) {
					if ($op === 'a') {
						$l1 = max(0, $l1);
						$l2 = max(0, $l2 - 1);
					} elseif ($op === 'd') {
						$l1 = max(0, $l1 - 1);
						$l2 = max(0, $l2);
					} elseif ($op === 'c') {
						$l1 = max(0, $l1 - 1);
						$l2 = max(0, $l2 - 1);
					}

					$diff->changes[] = [
						'l1' => $l1,
						'l2' => $l2,
						'offset' => 0,
						'old' => $temp['old'],
						'new' => $temp['new'],
					];

					$temp = [
						'old' => [],
						'new' => [],
					];
				}

				$l1 = (int) $matches[1];
				$n1 = (int) ($matches[2] ?? $l1) - $l1 + 1;
				$op = $matches[3];
				$l2 = (int) $matches[4];
				$n2 = (int) ($matches[5] ?? $l2) - $l2 + 1;

				continue;
			}

			// Break if we have gone past the end of the last hunk.
			if (
				!str_starts_with($line, '< ')
				&& !str_starts_with($line, '> ')
				&& $line !== '---' . "\n"
			) {
				break;
			}

			if ($op === 'a') {
				$temp['new'][] = substr($line, 2);
			} elseif ($op === 'd') {
				$temp['old'][] = substr($line, 2);
			} elseif ($op === 'c') {
				if (str_starts_with($line, '< ')) {
					$temp['old'][] = substr($line, 2);
				} elseif (str_starts_with($line, '> ')) {
					$temp['new'][] = substr($line, 2);
				}
			}
		}

		$lines = \array_slice($lines, $l);

		if (!empty($temp['old']) || !empty($temp['new'])) {
			if ($op === 'a') {
				$l1 = max(0, $l1);
				$l2 = max(0, $l2 - 1);
			} elseif ($op === 'd') {
				$l1 = max(0, $l1 - 1);
				$l2 = max(0, $l2);
			} elseif ($op === 'c') {
				$l1 = max(0, $l1 - 1);
				$l2 = max(0, $l2 - 1);
			}

			$diff->changes[] = [
				'l1' => $l1,
				'l2' => $l2,
				'offset' => 0,
				'old' => $temp['old'],
				'new' => $temp['new'],
			];

			$temp = [
				'old' => [],
				'new' => [],
			];
		}

		// Do all the cleanup the constructor normally would.
		$diff->changes = $diff->consolidate($diff->changes);
		$diff->changes = $diff->wordsToStrings($diff->changes);
		$diff->changes = array_filter($diff->changes, fn($c) => !empty($c['old']) || !empty($c['new']));
		$diff->changes = array_values($diff->changes);

		return [$diff];
	}

	/**
	 * Parses a unified diff to make instances of this class.
	 *
	 * Lines will be removed from $lines as they are processed. If $lines isn't
	 * empty after this method returns, it can be called again to make more
	 * instances.
	 *
	 * @param array &$lines The lines of the raw diff.
	 * @param array &$path_changes Data from any copy/rename headers.
	 * @param bool &$optional Whether these changes are optional.
	 * @return array Instances of this class.
	 */
	protected static function createFromUnified(array &$lines, array &$path_changes, bool &$optional): array
	{
		if (empty($lines)) {
			return [];
		}

		// Just in case...
		while (
			!str_starts_with($lines[0], '--- ')
			|| !str_starts_with($lines[1], '+++ ')
			|| !str_starts_with($lines[2], '@@ ')
		) {
			array_shift($lines);

			if (empty($lines)) {
				return [];
			}
		}

		$diff = new self();

		// Set the diff labels and times.
		[$diff->label1, $diff->time1] = array_pad(explode("\t", rtrim(substr(array_shift($lines), 4), "\n")), 2, '');
		[$diff->label2, $diff->time2] = array_pad(explode("\t", rtrim(substr(array_shift($lines), 4), "\n")), 2, '');

		foreach (['1', '2'] as $n) {
			$diff->{'label' . $n} = self::unescapePath($diff->{'label' . $n});

			// Trim off the initial 'a/' and 'b/' that Git prepends to paths.
			if (str_starts_with($diff->{'label' . $n}, $n === '1' ? 'a/' : 'b/')) {
				$diff->{'label' . $n} = substr($diff->{'label' . $n}, 2);
			}

			if ($diff->{'time' . $n} !== '') {
				$t = new Time((is_numeric($diff->{'time' . $n}) ? '@' : '') . $diff->{'time' . $n}, 'UTC');

				if ($t !== false) {
					$diff->{'time' . $n} = $t->format('Y-m-d H:i:s.u O');
				}
			}
		}

		// Should this diff be marked as optional?
		$diff->optional = $optional || !empty($path_changes['rename'][$diff->label1]['optional']) || !empty($path_changes['copy'][$diff->label1]['optional']);
		$optional = false;

		// Is this a rename?
		$diff->rename = isset($path_changes['rename'][$diff->label1]);

		// Either way, we don't need to remember this path change any more.
		unset(
			$path_changes['rename'][$diff->label1],
			$path_changes['copy'][$diff->label1],
		);

		// Now analyze the lines of diff data.
		$l1 = 0;
		$n1 = 1;
		$l2 = 0;
		$n2 = 1;
		$old = [];
		$new = [];
		$before = [];
		$after = [];
		$no_newline_at_end = [];
		$contains_context = false;

		foreach ($lines as $l => $line) {
			if (preg_match('/^@@ -(\d+)(?:,\s*(\d+))? \+(\d+)(?:,\s*(\d+))? @@/', $line, $matches)) {
				if (!empty($old) || !empty($new)) {
					// If there are no context lines, some of the normal logic won't have been executed.
					$diff->changes[] = [
						'l1' => $l1 - ($contains_context || !empty($old) || $l1 === 1 ? 1 : 0),
						'l2' => $l2 - ($contains_context || !empty($new) || $l2 === 1 ? 1 : 0),
						'offset' => 0,
						'old' => $old,
						'new' => $new,
						'before' => $before,
						'after' => $after,
					];
				}

				$l1 = (int) $matches[1];
				$n1 = (int) ($matches[2] ?? 1);
				$l2 = (int) $matches[3];
				$n2 = (int) ($matches[4] ?? 1);
				$old = [];
				$new = [];
				$before = [];
				$after = [];

				// Reset this at the start of each hunk, because some diff utils
				// output the '\ No newline at end of file' in every hunk rather
				// than just in the last hunk.
				$no_newline_at_end = [];
				continue;
			}

			switch (substr($line, 0, 1)) {
				// Add deleted lines to $old.
				case '-':
					$old[] = substr($line, 1);
					$n1--;
					break;

				// Add inserted lines to $new.
				case '+':
					$new[] = substr($line, 1);
					$n2--;
					break;

				// Context line.
				case ' ':
					$contains_context = true;

					// Is this before or after the changed lines?
					if (empty($old) && empty($new)) {
						$before[] = substr($line, 1);

						$l1++;
						$l2++;
					} else {
						$after[] = substr($line, 1);

						// If this line isn't followed by another context line,
						// record the changes.
						if (!str_starts_with($lines[$l + 1] ?? '', ' ')) {
							$diff->changes[] = [
								'l1' => $l1 - ($contains_context || !empty($old) || $l1 === 1 ? 1 : 0),
								'l2' => $l2 - ($contains_context || !empty($new) || $l2 === 1 ? 1 : 0),
								'offset' => 0,
								'old' => $old,
								'new' => $new,
								'before' => $before,
								'after' => $after,
							];

							$l1 += \count($old) + \count($after);
							$l2 += \count($new) + \count($after);
							$old = [];
							$new = [];
							$before = [];
							$after = [];
						}
					}

					$n1--;
					$n2--;
					break;

				// Special handling for '\ No newline at end of file'.
				case '\\':
					if (isset($lines[$l - 1])) {
						switch (substr($lines[$l - 1], 0, 1)) {
							case '-':
								$no_newline_at_end['old'] = true;
								break;

							case '+':
								$no_newline_at_end['new'] = true;
								break;

							default:
								$no_newline_at_end['after'] = true;
								break;
						}

						if (
							\in_array(substr($lines[$l + 1] ?? '', 0, 1), ['-', '+', ' '])
							|| substr($lines[$l + 1] ?? '', 0, 3) === '@@ '
						) {
							break;
						}
					}

					// no break

				// Stop if we have gone past the end of the last hunk.
				default:
					break 2;
			}
		}

		$lines = \array_slice($lines, $l);

		if (!empty($old) || !empty($new)) {
			$diff->changes[] = [
				'l1' => $l1 - ($contains_context || !empty($old) || $l1 === 1 ? 1 : 0),
				'l2' => $l2 - ($contains_context || !empty($new) || $l2 === 1 ? 1 : 0),
				'offset' => 0,
				'old' => $old,
				'new' => $new,
				'before' => $before,
				'after' => $after,
			];
		}

		// Redistribute the context lines where necessary.
		$diff->changes = self::redistributeContextLines($diff->changes);

		// Deal with '\ No newline at end of file'
		$diff->changes = self::fixFinalLineEndings($no_newline_at_end, $diff->changes);

		// Do all the cleanup the constructor normally would.
		$diff->changes = $diff->consolidate($diff->changes);
		$diff->changes = $diff->wordsToStrings($diff->changes);
		$diff->changes = array_filter($diff->changes, fn($c) => !empty($c['old']) || !empty($c['new']));
		$diff->changes = array_values($diff->changes);

		return [$diff];
	}

	/**
	 * Parses a context diff to make instances of this class.
	 *
	 * Lines will be removed from $lines as they are processed. If $lines isn't
	 * empty after this method returns, it can be called again to make more
	 * instances.
	 *
	 * @param array &$lines The lines of the raw diff.
	 * @param array &$path_changes Data from any copy/rename headers.
	 * @param bool &$optional Whether these changes are optional.
	 * @return array Instances of this class.
	 */
	protected static function createFromContext(array &$lines, array &$path_changes, bool &$optional): array
	{
		if (empty($lines)) {
			return [];
		}

		// Just in case...
		while (
			!str_starts_with($lines[0], '*** ')
			|| !str_starts_with($lines[1], '--- ')
			|| !str_starts_with($lines[2], '***************')
			|| !str_starts_with($lines[3], '*** ')

		) {
			array_shift($lines);

			if (empty($lines)) {
				return [];
			}
		}

		$diff = new self();

		// Set the diff labels and times.
		[$diff->label1, $diff->time1] = array_pad(explode("\t", rtrim(substr(array_shift($lines), 4), "\n")), 2, '');
		[$diff->label2, $diff->time2] = array_pad(explode("\t", rtrim(substr(array_shift($lines), 4), "\n")), 2, '');

		$diff->time1 = $diff->time1 !== '' && ($d = date_create((is_numeric($diff->time1) ? '@' : '') . $diff->time1)) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : $diff->time1;

		$diff->time2 = $diff->time2 !== '' && ($d = date_create((is_numeric($diff->time2) ? '@' : '') . $diff->time2)) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : $diff->time2;

		// Should this diff be marked as optional?
		$diff->optional = $optional || !empty($path_changes['rename'][$diff->label1]['optional']) || !empty($path_changes['copy'][$diff->label1]['optional']);
		$optional = false;

		// Is this a rename?
		$diff->rename = isset($path_changes['rename'][$diff->label1]);

		// Either way, we don't need to remember this path change any more.
		unset(
			$path_changes['rename'][$diff->label1],
			$path_changes['copy'][$diff->label1],
		);

		// Now analyze the lines of diff data.
		$hunk = [
			'l1' => 0,
			'l2' => 0,
			'old' => [],
			'new' => [],
		];

		$contains_context = false;

		foreach ($lines as $l => $line) {
			if ($line === "***************\n") {
				if (!empty($hunk['old']) || !empty($hunk['new'])) {
					$diff->changes = self::addContextHunkToChanges($hunk, $diff->changes, $contains_context);
				}

				$hunk = [
					'l1' => 0,
					'l2' => 0,
					'old' => [],
					'new' => [],
				];

				$section = '';

				// Reset this at the start of each hunk, because some diff utils
				// output the '\ No newline at end of file' in every hunk rather
				// than just in the last hunk.
				$no_newline_at_end = [];
				continue;
			}

			if (preg_match('/^\*{3} (\d+)(?:,(\d+))? \*{4}/', $line, $matches)) {
				$hunk['l1'] = (int) $matches[1];
				$section = 'old';
				continue;
			}

			if (preg_match('/^-{3} (\d+)(?:,(\d+))? -{4}/', $line, $matches)) {
				$hunk['l2'] = (int) $matches[1];
				$section = 'new';
				continue;
			}

			if (\in_array(substr($line, 0, 2), ['  ', '+ ', '- ', '! '])) {
				$hunk[$section][] = $line;

				if (str_starts_with($line, '  ')) {
					$contains_context = true;
				}

				continue;
			}

			if (str_starts_with($line, '\\')) {
				$no_newline_at_end[$section] = true;
			}

			// Stop if we are at the end of the last hunk.
			if (
				isset($lines[$l + 1])
				&& $lines[$l + 1] !== "***************\n"
				&& !preg_match('/^\*{3} (\d+)(?:,(\d+))? \*{4}/', $lines[$l + 1])
				&& !preg_match('/^-{3} (\d+)(?:,(\d+))? -{4}/', $lines[$l + 1])
				&& !\in_array(substr($lines[$l + 1], 0, 2), ['  ', '+ ', '- ', '! '])
			) {
				break;
			}
		}

		$lines = \array_slice($lines, $l);

		if (!empty($hunk['old']) || !empty($hunk['new'])) {
			$diff->changes = self::addContextHunkToChanges($hunk, $diff->changes, $contains_context);
		}

		// Redistribute the context lines where necessary.
		$diff->changes = self::redistributeContextLines($diff->changes);

		// Deal with '\ No newline at end of file'
		$diff->changes = self::fixFinalLineEndings($no_newline_at_end, $diff->changes);

		// Do all the cleanup the constructor normally would.
		$diff->changes = $diff->consolidate($diff->changes);
		$diff->changes = $diff->wordsToStrings($diff->changes);
		$diff->changes = array_filter(array: $diff->changes, callback: fn($c) => !empty($c['old']) || !empty($c['new']));
		$diff->changes = array_values($diff->changes);

		return [$diff];
	}

	/**
	 * Helper for self::createFromContext() that processes a hunk of change
	 * data from a context diff and adds it to the $changes array.
	 *
	 * @param array $hunk The current hunk of changes.
	 * @param array $changes The array of change data to add $hunk's data to.
	 * @param bool $contains_context Whether the diff has any context lines.
	 * @return array Updated $changes.
	 */
	protected static function addContextHunkToChanges(array $hunk, array $changes, bool $contains_context): array
	{
		$change = [
			'l1' => 0,
			'l2' => 0,
			'offset' => 0,
			'old' => [],
			'new' => [],
			'before' => [],
			'after' => [],
		];

		$l1_modifier = ($contains_context || !empty($hunk['old']) || $hunk['l1'] === 1 ? 1 : 0);
		$l2_modifier = ($contains_context || !empty($hunk['new']) || $hunk['l2'] === 1 ? 1 : 0);

		// Add the preceding context lines to $change['before'].
		if (!empty($hunk['old']) && !empty($hunk['new'])) {
			while (
				!empty($hunk['old'])
				&& !empty($hunk['new'])
				&& $hunk['old'][0] === $hunk['new'][0]
				&& str_starts_with($hunk['old'][0], '  ')
			) {
				$change['before'][] = substr($hunk['old'][0], 2);
				$hunk['l1']++;
				$hunk['l2']++;
				array_shift($hunk['new']);
				array_shift($hunk['old']);
			}
		} elseif (!empty($hunk['old'])) {
			while (
				!empty($hunk['old'])
				&& str_starts_with($hunk['old'][0], '  ')
			) {
				$change['before'][] = substr($hunk['old'][0], 2);
				$hunk['l1']++;
				$hunk['l2']++;
				array_shift($hunk['old']);
			}
		} else {
			while (
				!empty($hunk['new'])
				&& str_starts_with($hunk['new'][0], '  ')
			) {
				$change['before'][] = substr($hunk['new'][0], 2);
				$hunk['l1']++;
				$hunk['l2']++;
				array_shift($hunk['new']);
			}
		}

		// We now know the correct values for l1 and l2.
		$change['l1'] = $hunk['l1'] - $l1_modifier;
		$change['l2'] = $hunk['l2'] - $l2_modifier;

		// Add the changed or deleted lines to $change['old'].
		while (
			!empty($hunk['old'])
			&& (
				str_starts_with($hunk['old'][0], '- ')
				|| str_starts_with($hunk['old'][0], '! ')
			)
		) {
			$change['old'][] = substr(array_shift($hunk['old']), 2);
			$hunk['l1']++;
		}

		// Add the changed or inserted lines to $change['new'].
		while (
			!empty($hunk['new'])
			&& (
				str_starts_with($hunk['new'][0], '+ ')
				|| str_starts_with($hunk['new'][0], '! ')
			)
		) {
			$change['new'][] = substr(array_shift($hunk['new']), 2);
			$hunk['l2']++;
		}

		// Add the following context lines to $change['after'].
		if (!empty($hunk['old']) && !empty($hunk['new'])) {
			while (
				!empty($hunk['old'])
				&& !empty($hunk['new'])
				&& $hunk['old'][0] === $hunk['new'][0]
				&& str_starts_with($hunk['old'][0], '  ')
			) {
				$change['after'][] = substr($hunk['old'][0], 2);
				$hunk['l1']++;
				$hunk['l2']++;
				array_shift($hunk['new']);
				array_shift($hunk['old']);
			}
		} elseif (!empty($hunk['old'])) {
			while (
				!empty($hunk['old'])
				&& str_starts_with($hunk['old'][0], '  ')
			) {
				$change['after'][] = substr($hunk['old'][0], 2);
				$hunk['l1']++;
				array_shift($hunk['old']);
			}
		} else {
			while (
				!empty($hunk['new'])
				&& str_starts_with($hunk['new'][0], '  ')
			) {
				$change['after'][] = substr($hunk['new'][0], 2);
				$hunk['l2']++;
				array_shift($hunk['new']);
			}
		}

		// We're done building this change.
		$changes[] = $change;

		// If this hunk contains more changes, build them.
		if (!empty($hunk['old']) || !empty($hunk['new'])) {
			$changes = self::addContextHunkToChanges($hunk, $changes, $contains_context);
		}

		return $changes;
	}

	/**
	 * Helper for self::createFromUnified() and self::createFromContext() that
	 * moves excess lines from one change's 'after' to the next one's 'before'.
	 *
	 * @param array $changes The diff's complete set of changes.
	 * @return array Updated $changes.
	 */
	protected static function redistributeContextLines(array $changes): array
	{
		foreach ($changes as $c => $change) {
			if (!isset($changes[$c - 1])) {
				continue;
			}

			$context_start = $changes[$c]['l1'] - \count($changes[$c]['before']);
			$prev_context_end = $changes[$c - 1]['l1'] + \count($changes[$c - 1]['old']) + \count($changes[$c - 1]['after']);

			if ($context_start > $prev_context_end) {
				continue;
			}

			while (\count($changes[$c - 1]['after']) > \count($changes[$c]['before']) + 1) {
				array_unshift($changes[$c]['before'], array_pop($changes[$c - 1]['after']));
			}
		}

		return $changes;
	}

	/**
	 * Helper for self::createFromUnified() and self::createFromContext() that
	 * deals with '\ No newline at end of file'.
	 *
	 * @param array $where Info about which change parts have no final newline.
	 * @param array $changes The diff's complete set of changes.
	 * @return array Updated $changes.
	 */
	protected static function fixFinalLineEndings(array $where, array $changes): array
	{
		foreach ($where as $part => $should_trim) {
			if ($should_trim) {
				$last_c = array_key_last($changes);

				$temp = array_pop($changes[$last_c][$part]);

				if (str_ends_with($temp, "\n")) {
					$temp = substr($temp, 0, -1);
				}

				array_push($changes[$last_c][$part], $temp);
			}
		}

		return $changes;
	}
}
