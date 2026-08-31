<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SMF\Config;

/**
 * Guards the case folding convention across the source tree.
 *
 * Whether a string comparison folds case is decided by the database engine, so
 * a LIKE against text a person typed has to say which behaviour it wants. The
 * {ci:} and {ci_string:} query types say it; LOWER() in the query text says it;
 * a bare column says nothing and gets whatever the engine does, which is a
 * match on MySQL and no match on PostgreSQL.
 *
 * A comparison written the second way returns fewer rows rather than failing,
 * so nothing is written to the error log and nothing in CI notices. This test
 * is what notices. It is not a unit test of any class: the substitution layer
 * needs a live connection to escape with, so the behaviour of {ci:} itself
 * cannot be reached from here.
 *
 * Equality comparisons are not covered. `member_name = {string:name}` and
 * `$member_name = $string` are the same line to a scanner, and the SET clause
 * of an UPDATE looks like both, so the reliable signal is LIKE.
 */
#[CoversNothing]
class QueryCaseFoldingTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Columns holding text a person typed, where two spellings that differ
	 * only in case are the same value to the person who typed them.
	 */
	public const COLUMNS = [
		'member_name',
		'real_name',
		'poster_name',
		'from_name',
		'email_address',
		'group_name',
		'hostname',
	];

	/**
	 * Comparisons on those columns that do not fold case in the query text,
	 * counted per file.
	 *
	 * The two in Security.php are folded by the 'ban_like' identifier instead,
	 * which rewrites LIKE to ILIKE for PostgreSQL when the query runs.
	 *
	 * Everything else here is unresolved: see #9592 for Bans.php and #9593 for
	 * SearchApi.php. Lowering a count is the point of the list, so a change
	 * that fixes one of them belongs in the same commit as its new number.
	 */
	public const BASELINE = [
		'Sources/Actions/Admin/Bans.php' => 3,
		'Sources/Actions/Admin/Subscriptions.php' => 1,
		'Sources/Actions/Profile/Summary.php' => 2,
		'Sources/Search/SearchApi.php' => 3,
		'Sources/Security.php' => 2,
	];

	/****************
	 * Public methods
	 ****************/

	public function testNoNewComparisonSkipsTheCaseFoldingTypes(): void
	{
		$found = $this->scan();

		$this->assertSame(
			self::BASELINE,
			$found,
			"The set of case sensitive comparisons on user text has changed.\n\n"
			. "If a count went up, a new comparison is relying on the engine's\n"
			. "collation to fold case, which MySQL does and PostgreSQL does not.\n"
			. "Write it as {ci:column} LIKE {string:value}, or as\n"
			. "{ci:column} LIKE {ci_string:value} when the value is not already\n"
			. "folded in PHP.\n\n"
			. "If a count went down, the comparison was fixed. Update BASELINE\n"
			. 'in this test to match, in the same commit.',
		);
	}

	public function testTheScanFindsTheFormsItIsLookingFor(): void
	{
		// Without this, deleting the body of scan() would leave the test above
		// passing against an empty baseline.
		$this->assertNotSame([], $this->scan());
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Counts, per file, the comparisons on self::COLUMNS that do not fold case.
	 *
	 * @return array<string, int> Paths relative to the repository root.
	 */
	protected function scan(): array
	{
		$found = [];

		$columns = '~\b(?:' . implode('|', self::COLUMNS) . ')\b~';
		$folded = '~\{ci:|\{ci_string:|LOWER\s*\(~i';

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(Config::$sourcedir, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($files as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
			$contents = file_get_contents($path);

			// Most files never mention it, and reading them line by line to
			// find that out is the whole cost of this scan.
			if (!str_contains($contents, 'LIKE')) {
				continue;
			}

			$relative = 'Sources' . substr($path, \strlen(str_replace(DIRECTORY_SEPARATOR, '/', Config::$sourcedir)));

			foreach (explode("\n", $contents) as $line) {
				$trimmed = trim($line);

				// Comments describe comparisons, they do not make them.
				if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//')) {
					continue;
				}

				if (
					preg_match('~\bLIKE\b~', $line)
					&& preg_match($columns, $line)
					&& !preg_match($folded, $line)
				) {
					$found[$relative] = ($found[$relative] ?? 0) + 1;
				}
			}
		}

		ksort($found);

		return $found;
	}
}
