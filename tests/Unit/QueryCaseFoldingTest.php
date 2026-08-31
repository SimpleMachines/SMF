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
 * There are two scans, because there are two ways to get this wrong. One looks
 * for a comparison that folds neither side, which matches too much on MySQL.
 * The other looks for one that folds only its column, which matches nothing at
 * all on PostgreSQL, and is the worse of the two for being the one that hides:
 * the query says {ci:} and looks handled.
 *
 * The first scan covers LIKE only, since `member_name = {string:name}` and
 * `$member_name = $string` are the same line to a scanner, and an UPDATE's SET
 * clause looks like both. The second can cover equality as well, because a
 * line carrying {ci:} is SQL and its = is therefore a comparison.
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

	/**
	 * Comparisons where {ci:} folds the column but the value beside it is not
	 * folded in the query, counted per file.
	 *
	 * Folding one side is worse than folding neither. A folded column can
	 * never equal an unfolded value, so instead of matching too much on
	 * PostgreSQL the comparison matches nothing at all, including the row it
	 * was looking for.
	 *
	 * These are correct only if the caller folded the value in PHP first,
	 * which is a claim about code somewhere else and so is listed rather than
	 * counted. Folded by their callers:
	 *
	 *  - Admin/Members.php, through strtolower().
	 *  - AutoSuggest.php, RequestMembers.php, PM.php, through
	 *    Utils::strtolower().
	 *  - User.php, through array_map(), for the engines that need it.
	 *
	 * Not folded by their callers, and so matching nothing on PostgreSQL:
	 *
	 *  - Register2.php and Profile.php, which is #9594.
	 *  - PersonalMessage/Search.php, which is the same fault in the search for
	 *    a personal message by its author.
	 */
	public const UNFOLDED_VALUES = [
		'Sources/Actions/Admin/Members.php' => 1,
		'Sources/Actions/AutoSuggest.php' => 2,
		'Sources/Actions/Register2.php' => 2,
		'Sources/Actions/RequestMembers.php' => 1,
		'Sources/PersonalMessage/PM.php' => 1,
		'Sources/PersonalMessage/Search.php' => 2,
		'Sources/Profile.php' => 1,
		'Sources/User.php' => 1,
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

	public function testNoNewComparisonFoldsOnlyItsColumn(): void
	{
		$found = $this->scanUnfoldedValues();

		$this->assertSame(
			self::UNFOLDED_VALUES,
			$found,
			"The set of comparisons folding a column but not the value has changed.\n\n"
			. "If a count went up, check that the caller folds the value before it\n"
			. "reaches the query. If it does, add the file to UNFOLDED_VALUES and\n"
			. "say so in the note there. If it does not, the comparison matches\n"
			. "nothing on PostgreSQL: fold the value in PHP, or write it as\n"
			. '{ci_string:value} and let the query fold it.',
		);
	}

	public function testTheScansFindTheFormsTheyAreLookingFor(): void
	{
		// Without these, emptying either scan would leave the tests above
		// passing against an empty baseline.
		$this->assertNotSame([], $this->scan());
		$this->assertNotSame([], $this->scanUnfoldedValues());
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

	/**
	 * Counts, per file, the comparisons that fold the column with {ci:} while
	 * comparing it against a value the query does not fold.
	 *
	 * A line carrying {ci:} is SQL, so an = on it is a comparison rather than
	 * a PHP assignment. That is what lets this one look at equality, which the
	 * scan above cannot.
	 *
	 * @return array<string, int> Paths relative to the repository root.
	 */
	protected function scanUnfoldedValues(): array
	{
		$found = [];

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(Config::$sourcedir, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($files as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
			$contents = file_get_contents($path);

			if (!str_contains($contents, '{ci:')) {
				continue;
			}

			$relative = 'Sources' . substr($path, \strlen(str_replace(DIRECTORY_SEPARATOR, '/', Config::$sourcedir)));

			foreach (explode("\n", $contents) as $line) {
				if (
					str_contains($line, '{ci:')
					&& preg_match('~\{(?:array_)?string:~', $line)
				) {
					$found[$relative] = ($found[$relative] ?? 0) + 1;
				}
			}
		}

		ksort($found);

		return $found;
	}
}
