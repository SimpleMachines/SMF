<?php

/**
 * This is an internal development file. It should NOT be included in any SMF
 * distribution packages.
 *
 * This file exists to make it easier for devs to update Sources/TimeZone.php,
 * Languages/en_US/Timezone.php, and the files in Sources/Calendar/VTimeZones
 * when a new version of the IANA's time zone database is released.
 *
 * After running this updater, you must review any changes manually before
 * committing.
 *
 * In particular, review the following:
 *
 * 1. If new $txt or $tztxt strings were added to the language file, check that
 *    they are spelled correctly and make sense.
 *
 * 2. If the TZDB added an entirely new time zone, a new chunk of fallback code
 *    will be added to TimeZone::$fallbacks, with an "ADD INFO HERE" comment
 *    above it.
 *
 *     - Replace "ADD INFO HERE" with something meaningful before committing,
 *       such as a comment about when the new time zone was added to the TZDB
 *       and which existing time zone it diverged from. This info can be found
 *       at https://data.iana.org/time-zones/tzdb/NEWS.
 *
 * 3. When this script suggests a fallback tzid in the fallback code, it will
 *    insert an "OPTIONS" comment above that suggestion listing other tzids that
 *    could be used instead.
 *
 *     - If you like the automatically suggested tzid, just delete the comment.
 *
 *     - If you prefer one of the other options, change the suggested tzid to
 *       that other option, and then delete the comment.
 *
 *     - All "OPTIONS" comments should be removed before committing.
 *
 * 4. Newly created time zones are also appended to their country's list in the
 *    TimeZone::$sorted_tzids array.
 *
 *     - Adjust the position of the new tzid in that list by comparing the
 *       city's population with the populations of the other listed cities.
 *       A quick Google or Wikipedia search is your friend here.
 *
 * 5. If a new metazone is required, new entries for it will be added to
 *    TimeZone::$metazones and to the $tztxt array in the language file.
 *
 *     - The new entry in TimeZone::$metazones will have an "OPTIONS" comment
 *       listing all the tzids in this new metazone. Feel free to use any of
 *       them as the representative tzid for the metazone. All "OPTIONS"
 *       comments should be removed before committing.
 *
 *     - Also feel free to edit the $tztxt key for the new metazone. Just make
 *       sure to use the same key in both files.
 *
 *     - The value of the $tztxt string in the language file will probably need
 *       to be changed, because only a human can know what it should really be.
 *
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\other\Updaters;

use SMF\Calendar\RecurrenceIterator;
use SMF\Calendar\RRule;
use SMF\Config;
use SMF\TimeZone;
use SMF\WebFetch\WebFetchApi;

/**
 * Class TimezoneDataUpdater
 */
class TimezoneDataUpdater extends UpdaterBase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string
	 *
	 * Git tag of the earliest version of the TZDB to check against.
	 *
	 * This can be set to the TZDB version that was included in the earliest
	 * version of PHP that SMF supports, e.g. 2015g (a.k.a. 2015.7) for PHP 7.0,
	 * or 2020d (a.k.a. 2020.4) for PHP 8.0.
	 *
	 * Leave blank to use the earliest release available. (Not recommended.)
	 */
	public const TZDB_PREV_TAG = '2020d';

	/**
	 * @var string
	 *
	 * Git tag of the most recent version of the TZDB to check against.
	 *
	 * Leave blank to use the latest release of the TZDB. (Recommended.)
	 */
	public const TZDB_CURR_TAG = '';

	/**
	 * @var string
	 *
	 * URL where we can get a list of tagged releases of the TZDB.
	 */
	public const TZDB_TAGS_URL = 'https://api.github.com/repos/eggert/tz/tags?per_page=1000';

	/**
	 * @var string
	 * URL template to fetch raw data files for the TZDB.
	 */
	public const TZDB_FILE_URL = 'https://raw.githubusercontent.com/eggert/tz/{COMMIT}/{FILE}';

	/**
	 * @var string
	 *
	 * URL where we can get a list of tagged releases of the CLDR in JSON format.
	 */
	public const CLDR_TAGS_URL = 'https://api.github.com/repos/unicode-org/cldr-json/tags?per_page=1000';

	/**
	 * @var string
	 *
	 * URL template to fetch raw data files for the CLDR in JSON format.
	 */
	public const CLDR_FILE_URL = 'https://raw.githubusercontent.com/unicode-org/cldr-json/{COMMIT}/{FILE}';

	/**
	 * @var string
	 *
	 * Used in places where an earliest date is required.
	 *
	 * To support 32-bit PHP builds, use '1901-12-13T20:45:52+0000'
	 */
	public const DATE_MIN = '1582-10-15T00:00:00+0000';

	/**
	 * @var string
	 *
	 * The date equivalent to Unix timestamp 0.
	 *
	 * The TZDB tracks info about dates prior to this in its backzone file
	 * rather than its main files, and the CLDR doesn't maintain metazone info
	 * for dates prior to this.
	 */
	public const DATE_EPOCH = '1970-01-01T00:00:00+0000';

	/**
	 * @var string
	 *
	 * Used in places where a date in the next year or so is required.
	 */
	public const DATE_SOON = 'January 1 + 2 years UTC';

	/**
	 * @var string
	 *
	 * Used in places where a latest date is required.
	 */
	public const DATE_MAX = 'January 1 + 100 years UTC';

	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $commit_msg = 'Updates time zone data';

	/**
	 * @var string
	 *
	 * Git commit hash associated with TZDB_PREV_TAG.
	 */
	public string $prev_commit;

	/**
	 * @var string
	 *
	 * Git commit hash associated with TZDB_CURR_TAG.
	 */
	public string $curr_commit;

	/**
	 * @var bool
	 *
	 * This keeps track of whether any files actually changed.
	 */
	public bool $files_updated = false;

	/**
	 * @var array
	 *
	 * Tags from the TZDB's GitHub repository.
	 */
	public array $tzdb_tags = [];

	/**
	 * @var array
	 *
	 * Tags from the CLDR's GitHub repository.
	 */
	public array $cldr_tags = [];

	/**
	 * @var array
	 *
	 * A multidimensional array of time zone identifiers,
	 * grouped into different information blocks.
	 */
	public array $tz_data = [];

	/**
	 * @var array
	 *
	 * Data retrieved from the CLDR.
	 */
	public array $cldr_data = [];

	/**
	 * @var array
	 *
	 * Compiled information about all time zones in the TZDB.
	 */
	public array $zones = [];

	/**
	 * @var array
	 *
	 * Compiled information about all time zone transitions.
	 *
	 * This is similar to return value of PHP's timezone_transitions_get(),
	 * except that the array is built from the TZDB source as it existed at
	 * whatever version is defined as 'current' via self::TZDB_CURR_TAG.
	 */
	public array $transitions;

	/**
	 * @var array
	 *
	 * Info about metazones.
	 */
	public array $metazones = [];

	/**
	 * @var array
	 *
	 * Info about each country's preferred exemplar time zone for each metazone.
	 */
	public array $preferred_zones = [];

	/**
	 * @var array
	 *
	 * Lists of each country's time zones.
	 */
	public array $sorted_tzids = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute()
	{
		if (php_sapi_name() === 'cli') {
			echo 'Updating time zone data...', PHP_EOL;
		}

		$this->checkoutNewBranch();

		// Assume true until proven otherwise.
		$this->ready_to_commit = true;

		$this->fetchTzdbUpdates();
		$this->updateTimezoneClass();
		$this->updateTimezonesLangfile();

		$this->buildVTimeZoneClasses();

		// Changed in unexpected ways?
		if (!empty($this->tz_data['changed']['wtf'])) {
			$wtf_message = 'The following time zones changed in unexpected ways. Please review them manually to figure out what to do.' . PHP_EOL . "\t" . implode(PHP_EOL . "\t", $this->tz_data['changed']['wtf']) . PHP_EOL . PHP_EOL;

			throw new \Exception($wtf_message);
		}

		// Add any new files to Git.
		if ($this->files_updated && $this->ready_to_commit) {
			shell_exec('git add --all');
		}

		// Say something when finished.
		if (php_sapi_name() === 'cli') {
			echo 'Done.', !$this->files_updated ? ' No changes were made.' : (!$this->ready_to_commit ? ' Please review all changes manually.' : ''), PHP_EOL;
		}

		$this->removeUselessBranch();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Builds an array of information about the time zone identifiers in
	 * two different versions of the TZDB, including information about
	 * what changed between the two.
	 *
	 * The data is saved in $this->tz_data.
	 */
	private function fetchTzdbUpdates(): void
	{
		$fetched = [];

		$this->fetchTzdbTags();

		foreach (['prev', 'curr'] as $build) {
			if ($build == 'prev') {
				$tag = isset($this->tzdb_tags[self::TZDB_PREV_TAG]) ? self::TZDB_PREV_TAG : array_key_first($this->tzdb_tags);
				$this->prev_commit = $this->tzdb_tags[$tag];
			} else {
				$tag = isset($this->tzdb_tags[self::TZDB_CURR_TAG]) ? self::TZDB_CURR_TAG : array_key_last($this->tzdb_tags);
				$this->curr_commit = $this->tzdb_tags[$tag];
			}

			$backzone_exists = $tag >= '2014g';

			list($fetched['zones'], $fetched['links']) = $this->getPrimaryZones($this->tzdb_tags[$tag]);

			$fetched['backward_links'] = $this->getBacklinks($this->tzdb_tags[$tag]);

			list($fetched['backzones'], $fetched['backzone_links']) = $backzone_exists ? $this->getBackzones($this->tzdb_tags[$tag]) : [[], []];

			$this->tz_data[$build]['all'] = array_unique(array_merge(
				$fetched['zones'],
				array_keys($fetched['links']),
				array_values($fetched['links']),
				array_keys($fetched['backward_links']),
				array_values($fetched['backward_links']),
				array_keys($fetched['backzone_links']),
				array_values($fetched['backzone_links']),
			));

			$this->tz_data[$build]['links'] = array_merge(
				$fetched['backzone_links'],
				$fetched['backward_links'],
				$fetched['links'],
			);

			$this->tz_data[$build]['canonical'] = array_diff(
				$this->tz_data[$build]['all'],
				array_keys($this->tz_data[$build]['links']),
			);

			$this->tz_data[$build]['backward_links'] = $fetched['backward_links'];
			$this->tz_data[$build]['backzones'] = $fetched['backzones'];
			$this->tz_data[$build]['backzone_links'] = $fetched['backzone_links'];
		}

		$this->tz_data['changed'] = [
			'new' => array_diff($this->tz_data['curr']['all'], $this->tz_data['prev']['all']),
			'renames' => [],
			'additions' => [],
			'wtf' => [],
		];

		// Figure out which new tzids are renames of old tzids.
		foreach ($this->tz_data['changed']['new'] as $tzid) {
			// Get any tzids that link to this one.
			$linked_tzids = array_keys($this->tz_data['curr']['links'], $tzid);

			// If this tzid is itself a link, get its target.
			if (isset($this->tz_data['curr']['links'][$tzid])) {
				$linked_tzids[] = $this->tz_data['curr']['links'][$tzid];
			}

			// No links, so skip.
			if (empty($linked_tzids)) {
				continue;
			}

			$linked_tzids = array_unique($linked_tzids);

			// Try filtering out backzones in order to find to one unambiguous link.
			if (\count($linked_tzids) > 1) {
				$not_backzones = array_diff($linked_tzids, $this->tz_data['curr']['backzones']);

				if (\count($not_backzones) !== 1) {
					$this->tz_data['changed']['wtf'][] = $tzid;
					continue;
				}

				$linked_tzids = $not_backzones;
			}

			$this->tz_data['changed']['renames'][reset($linked_tzids)] = $tzid;
		}

		$this->tz_data['changed']['additions'] = array_diff(
			$this->tz_data['changed']['new'],
			$this->tz_data['changed']['renames'],
			$this->tz_data['changed']['wtf'],
		);
	}

	/**
	 * Updates the contents of ./Sources/TimeZone.php with any changes
	 * required to reflect changes in the TZDB.
	 *
	 * - Handles renames of tzids (e.g. Europe/Kiev -> Europe/Kyiv)
	 *   fully automatically.
	 *
	 * - If a new tzid has been created, adds fallback code for it in
	 *   TimeZone::$fallbacks, and appends it to the list of tzids for
	 *   its country in TimeZone::$sorted_tzids.
	 *
	 * - Checks the rules defined in existing fallback code to make sure
	 *   they are still accurate, and updates any that are not. This is
	 *   necessary because new versions of the TZDB sometimes contain
	 *   corrections to previous data.
	 */
	private function updateTimezoneClass(): void
	{
		$file_contents = file_get_contents(Config::$sourcedir . '/TimeZone.php');

		$old_hash = md5($file_contents);

		// Update the list of canonical links.
		$this->buildZones();

		$canonical_links = [];

		foreach ($this->zones as $tzid => $zone) {
			if (isset($zone['canonical'])) {
				$canonical_links[$tzid] = $zone['canonical'];
			}
		}

		ksort($canonical_links);

		$file_contents = preg_replace(
			[
				'/public const CANONICAL_LINKS = \[[^\]]*\];/',
				'/^\h+$/m',
			],
			[
				'public const CANONICAL_LINKS = ' . preg_replace('/^(?!\[)/m', "\t", Config::varExport($canonical_links)) . ';',
				'',
			],
			$file_contents,
		);

		// Handle any renames.
		foreach ($this->tz_data['changed']['renames'] as $old_tzid => $new_tzid) {
			// Rename it in TimeZone::$preferred_zones
			if (!preg_match('~\n\h+\K\'' . $new_tzid . '\'(?=\s+=>\s+\'\w+\',)~', $file_contents)) {
				$file_contents = preg_replace('~\n\h+\K\'' . $old_tzid . '\'(?=\s+=>\s+\'\w+\',)~', "'{$new_tzid}'", $file_contents);

				if (preg_match('~\n\h+\K\'' . $new_tzid . '\'(?=\s+=>\s+\'\w+\',)~', $file_contents)) {
					echo "Renamed {$old_tzid} to {$new_tzid} in TimeZone::\$preferred_zones.\n\n";
				}
			}

			// Rename it in TimeZone::$sorted_tzids
			if (!preg_match('~\n\h+\K\'' . $new_tzid . '\'(?=,\n)~', $file_contents)) {
				$file_contents = preg_replace('~\n\h+\K\'' . $old_tzid . '\'(?=,\n)~', "'{$new_tzid}'", $file_contents);

				if (preg_match('~\n\h+\K\'' . $new_tzid . '\'(?=,\n)~', $file_contents)) {
					echo "Renamed {$old_tzid} to {$new_tzid} in TimeZone::\$sorted_tzids.\n\n";
				}
			}

			// Ensure the fallback code is added.
			$insert_before = '(?=\n\h+/\*\s+\* 2. Newly created time zones.)';
			$code = $this->generateRenameFallbackCode([$old_tzid => $new_tzid]);

			$search_for = preg_quote(substr(trim($code), 0, strpos(trim($code), "\n")), '~');
			$search_for = preg_replace('~\s+~', '\s+', $search_for);

			if (!preg_match('~' . $search_for . '~', $file_contents)) {
				$file_contents = preg_replace('~' . $insert_before . '~', $code, $file_contents);

				if (preg_match('~' . $search_for . '~', $file_contents)) {
					echo "Added fallback code for {$new_tzid} in TimeZone::\$fallbacks.\n\n";
				}
			}
		}

		// Insert fallback code for any additions.
		if (!empty($this->tz_data['changed']['additions'])) {
			$fallbacks = $this->buildFallbacks();

			foreach ($this->tz_data['changed']['additions'] as $tzid) {
				// Ensure it is present in TimeZone::$sorted_tzids
				if (!preg_match('~\n\h+\K\'' . $tzid . '\'(?=,\n)~', $file_contents)) {
					$cc = $this->getCcForTzid($tzid, $this->curr_commit);

					$file_contents = preg_replace("~('{$cc}'\s*=>\s*\[(?:\s*'[^']+',)*\n)(\h*)(\],)~", '$1$2' . "\t'{$tzid}',\n" . '$2$3', $file_contents);

					if (preg_match('~\n\h+\K\'' . $tzid . '\'(?=,\n)~', $file_contents)) {
						echo "Added {$tzid} to {$cc} in TimeZone::\$sorted_tzids.\n\n";
					}
				}

				// Ensure the fallback code is added.
				$insert_before = '(?=\s+\];\s+/\**\s+\* Internal static properties)';
				$code = $this->generateFullFallbackCode([$tzid => $fallbacks[$tzid]]);

				$search_for = preg_quote(substr(trim($code), 0, strpos(trim($code), "\n")), '~');
				$search_for = preg_replace('~\s+~', '\s+', $search_for);

				// Not present at all.
				if (!preg_match('~' . $search_for . '~', $file_contents)) {
					$file_contents = preg_replace('~' . $insert_before . '~', "\n\n\t\t// ADD INFO HERE\n" . rtrim($code), $file_contents, 1);

					if (preg_match('~' . $search_for . '~', $file_contents)) {
						echo "Added fallback code for {$tzid} in TimeZone::\$fallbacks.\nACTION NEEDED: Review the fallback code for {$tzid}.\n\n";

						$this->ready_to_commit = false;
					}
				}
				// Check whether our fallback rules are out of date.
				else {
					// First, parse the existing code into usable chunks.
					$search_for = str_replace('\[', '(\[(?' . '>[^\[\]]|(?1))*\]),', $search_for);

					preg_match('~' . $search_for . '~', $file_contents, $matches);

					if (empty($matches[1])) {
						continue;
					}

					$existing_code = $matches[0];
					$existing_inner = $matches[1];

					preg_match_all('~(?:\h*//[^\n]*\n)*\h*(\[(?' . '>[^\[\]]|(?1))*\]),~', $existing_inner, $matches);
					$existing_entries = $matches[0];

					// Now do the same with the generated code.
					preg_match('~' . $search_for . '~', $code, $matches);
					$new_inner = $matches[1];

					preg_match_all('~(?:\h*//[^\n]*\n)*\h*(\[(?' . '>[^\[\]]|(?1))*\]),~', $new_inner, $matches);
					$new_entries = $matches[0];

					// This is what we will ultimately save.
					$final_entries = [];

					foreach ($new_entries as $new_entry_num => $new_entry) {
						if (str_contains($new_entry, 'PHP_INT_MIN')) {
							$final_entries[] = $new_entry;
							continue;
						}

						preg_match('~\'ts\' => \'([^\']*)\'~', $new_entry, $m);
						$new_ts = $m[1];

						preg_match('~\'tzid\' => \'([^\']*)\'~', $new_entry, $m);
						$new_alt_tzid = $m[1];

						preg_match('~(//[^\n]*\n\h*)*(?=\'tzid\')~', $new_entry, $m);
						$new_alt_tzid_comment = $m[0];

						foreach ($existing_entries as $existing_entry_num => $existing_entry) {
							if (str_contains($existing_entry, 'PHP_INT_MIN')) {
								continue;
							}

							preg_match('~\'ts\' => \'([^\']*)\'~', $existing_entry, $m);
							$existing_ts = $m[1];

							preg_match('~\'tzid\' => \'([^\']*)\'~', $existing_entry, $m);
							$existing_alt_tzid = $m[1];

							preg_match('~(//[^\n]*\n\h*)*(?=\'tzid\')~', $existing_entry, $m);
							$existing_alt_tzid_comment = $m[0];

							// Found an entry with the same timestamp.
							if ($existing_ts === $new_ts) {
								// Modify the existing entry rather than creating a new one.
								$final_entry = $existing_entry;

								// Do we need to change the tzid?
								if (!str_contains($new_alt_tzid_comment, $existing_alt_tzid)) {
									$final_entry = str_replace("'tzid' => '{$existing_alt_tzid}',", "'tzid' => '{$new_alt_tzid}',", $final_entry);
								}

								// Add or update the options comment.
								if (!str_contains($existing_alt_tzid_comment, '// OPTIONS: ')) {
									// Only insert options comment if we changed the tzid.
									if (!str_contains($new_alt_tzid_comment, $existing_alt_tzid)) {
										$final_entry = preg_replace("/'tzid' => '([^']*)',/", $new_alt_tzid_comment . "'tzid' => '$1',", $final_entry);
									}
								} else {
									$final_entry = preg_replace('~//\s*OPTIONS[^\n]+\n\h*~', $new_alt_tzid_comment, $final_entry);
								}

								if (str_contains($final_entry, 'OPTIONS')) {
									$this->ready_to_commit = false;
								}

								$final_entries[] = $final_entry;

								continue 2;
							}

							// No existing entry has the same time stamp, so insert
							// a new entry at the correct position in the code.
							if (strtotime($existing_ts) > strtotime($new_ts)) {
								$final_entries[] = $new_entry;

								continue 2;
							}
						}
					}

					$final_inner = "[\n" . implode("\n", $final_entries) . "\n\t\t]";

					if ($existing_inner !== $final_inner) {
						$final_code = str_replace($existing_inner, $final_inner, $existing_code);
						$file_contents = str_replace($existing_code, $final_code, $file_contents);

						$this->ready_to_commit = false;

						echo "Fallback code for {$tzid} has been updated in TimeZone::\$fallbacks.\nACTION NEEDED: Review the fallback code for {$tzid}.\n\n";
					}
				}
			}
		}

		// Save the changes we've made so far.
		file_put_contents(Config::$sourcedir . '/TimeZone.php', $file_contents);

		// Ensure the TimeZone::$preferred_zones array in up to date.
		$file_contents = $this->updatePreferredZones($file_contents);

		// Extract the $sorted_tzids array and evaluate it.
		preg_match('/protected static array \K\$sorted_tzids = \[[^;]+;/', $file_contents, $matches);

		eval($matches[0]);
		$this->sorted_tzids = $sorted_tzids;

		// Have any time zones changed their country codes?
		foreach ($this->zones as $tzid => $zone) {
			$cc = $this->getCcForTzid($tzid, $this->curr_commit);

			if ($cc !== '??' && !\in_array($tzid, $sorted_tzids[$cc] ?? [])) {
				// Remove the existing occurrence of the tzid in TimeZone::$sorted_tzids.
				$file_contents = preg_replace('~\n\h+\'' . $tzid . '\',?(?=\n)~', '', $file_contents);

				// A brand new country code?
				if (empty($sorted_tzids[$cc])) {
					foreach ($sorted_tzids as $existing_cc => $tzids) {
						if ($existing_cc > $cc) {
							break;
						}
					}

					$file_contents = preg_replace("~(\n\h+)('{$existing_cc}' => \[)~", "$1'{$cc}' => [$1],$0", $file_contents);
				}

				// Add the tzid to the correct country code's list.
				$file_contents = preg_replace("~('{$cc}'\s*=>\s*\[(?:\s*'[^']+',)*\n)(\h*)(\],)~", '$1$2' . "\t'{$tzid}',\n" . '$2$3', $file_contents);

				if (preg_match('~\n\h+\K\'' . $tzid . '\'(?=,\n)~', $file_contents)) {
					echo "Moved {$tzid} to '{$cc}' in TimeZone::\$sorted_tzids.\n\n";
				}

				// Also update our live version for use elsewhere.
				foreach ($this->sorted_tzids as $existing_cc => $tzids) {
					if ($existing_cc == $cc && !\in_array($tzid, $this->sorted_tzids[$cc])) {
						$this->sorted_tzids[$cc][] = $tzid;
					} else {
						$this->sorted_tzids[$existing_cc] = array_diff($tzids, [$tzid]);
					}
				}
			}
		}

		$new_hash = md5($file_contents);

		if ($old_hash !== $new_hash) {
			$this->files_updated = true;
		}

		// Save the changes again.
		file_put_contents(Config::$sourcedir . '/TimeZone.php', $file_contents);
	}

	/**
	 * Updates the $preferred_zones array in TimeZone.php.
	 *
	 * @param string $file_contents String content of TimeZone.php.
	 * @return string Modified copy of $file_contents.
	 */
	private function updatePreferredZones(string $file_contents): string
	{
		$this->buildMetaZones();

		$this->preferred_zones = [];

		$metazone_names = [];

		foreach ($this->metazones['mapped'] as $mapzones) {
			foreach ($mapzones as $mapzone) {
				$region = $mapzone['_territory'];
				$metazone = $mapzone['_other'];
				$tzid = $this->getBestMetaZoneTzid($mapzone['_type']);

				$metazone_names[] = $metazone;
				$this->preferred_zones[$region][$metazone] = $tzid;
			}
		}

		// Sort for developer sanity.
		ksort($this->preferred_zones);

		foreach ($this->preferred_zones as $region => $value) {
			ksort($this->preferred_zones[$region]);
		}

		return preg_replace(
			'/(\h*protected static array \$preferred_zones)\h*=\h*\[[^;]*;/',
			'$1 = ' . preg_replace('/^(?!\[)/m', "\t", Config::varExport($this->preferred_zones)) . ';',
			$file_contents,
		);
	}

	/**
	 * Updates the contents of the Timezones.php language file with any
	 * changes required to reflect changes in the TZDB.
	 *
	 * - Handles renames of tzids (e.g. Europe/Kiev -> Europe/Kyiv)
	 *   fully automatically. For this situation, no further developer
	 *   work should be needed.
	 *
	 * - If a new tzid has been created, adds a new $txt string for it.
	 *   We try to fetch a label from the CLDR project, or generate a
	 *   preliminary label if the CLDR has not yet been updated to
	 *   include the new tzid.
	 *
	 * - Makes sure that $txt['iso3166'] is up to date, just in case a
	 *   new country has come into existence since the last update.
	 */
	private function updateTimezonesLangfile(): void
	{
		$file_contents = file_get_contents(Config::$languagesdir . '/en_US/Timezones.php');

		$old_hash = md5($file_contents);

		// Perform any renames.
		foreach ($this->tz_data['changed']['renames'] as $old_tzid => $new_tzid) {
			if (!str_contains($file_contents, "\$txt['{$new_tzid}']")) {
				$file_contents = str_replace("\$txt['{$old_tzid}']", "\$txt['{$new_tzid}']", $file_contents);

				if (str_contains($file_contents, "\$txt['{$new_tzid}']")) {
					echo "Renamed \$txt['{$old_tzid}'] to \$txt['{$new_tzid}'] in Languages/en_US/Timezones.php.\n\n";
				}
			}
		}

		// Get $txt and $tztxt as real variables so that we can work with them.
		eval(substr(rtrim($file_contents, '?>'), 5));

		// $tztxt keys that should go first in the list.
		$tztxt_first = [
			'region_format',
			'region_format_type_daylight',
			'region_format_type_standard',
			'fallback_format',
			'Etc/UTC',
			'Europe/Dublin',
			'Europe/London',
		];

		// Keys for $tztxt items that take plain strings, not arrays.
		$tztxt_strings = [
			'region_format',
			'region_format_type_daylight',
			'region_format_type_standard',
			'fallback_format',
		];

		// Check over the existing $tztxt data.
		foreach ($tztxt as $metazone => $value) {
			if (\in_array($metazone, $tztxt_strings)) {
				$tztxt[$metazone] = str_replace(['%1$s', '%2$s'], ['{0}', '{1}'], $value);
				continue;
			}

			// Remove any unknown metazones from $tztxt.
			if (
				!\in_array($metazone, $tztxt_first)
				&& !isset($this->preferred_zones['001'][$metazone])
			) {
				unset($tztxt[$metazone]);
				continue;
			}

			// Replace any strings with arrays.
			if (\is_string($value)) {
				$tztxt[$metazone] = [
					'generic' => [
						// Make sure the string is using MessageFormat tokens, not sprintf tokens.
						'long' => str_replace(['%1$s', '%2$s'], ['{0}', '{1}'], $value),
					],
				];
			}
		}

		// Update the labels in $tztxt.
		foreach ($this->metazones['labels'] as $metazone => $labels) {
			$tztxt[$metazone] = $labels;
		}

		// Sort the strings into our preferred order.
		uksort(
			$tztxt,
			function ($a, $b) use ($tztxt_first) {
				if (\in_array($a, $tztxt_first) && !\in_array($b, $tztxt_first)) {
					return -1;
				}

				if (!\in_array($a, $tztxt_first) && \in_array($b, $tztxt_first)) {
					return 1;
				}

				if (\in_array($a, $tztxt_first) && \in_array($b, $tztxt_first)) {
					return array_search($a, $tztxt_first) <=> array_search($b, $tztxt_first);
				}

				return $a <=> $b;
			},
		);

		// Update the time zone location names.
		foreach ($this->zones as $tzid => $zone) {
			if (isset($zone['canonical']) || $this->getCcForTzid($tzid, $this->curr_commit) === '??') {
				$no_label_needed = 1;

				foreach ($this->preferred_zones as $region => $tzids) {
					$no_label_needed &= !\in_array($tzid, $tzids);
				}

				foreach ($this->sorted_tzids as $region => $tzids) {
					$no_label_needed &= !\in_array($tzid, $tzids);
				}

				$no_label_needed |= str_starts_with($tzid, 'Etc/') || !str_contains($tzid, '/');

				if ($no_label_needed) {
					unset($txt[$tzid]);
					continue;
				}
			}

			if (!isset($txt[$tzid])) {
				$added_txt_msg = "Added \$txt['{$tzid}'] to Languages/en_US/Timezones.php.\n";

				// Get a label from the CLDR.
				list($label, $msg) = $this->getTzidLabel($tzid);

				$txt[$tzid] = $label;

				$added_txt_msg .= $msg;

				echo $added_txt_msg . "\n";
			} else {
				list($label, $msg) = $this->getTzidLabel($tzid);

				if ($label !== $txt[$tzid]) {
					$txt[$tzid] = $label;
					echo "Updated \$txt['{$tzid}'] in Languages/en_US/Timezones.php.\n\n";
				}
			}
		}

		ksort($txt);

		// Ensure $txt['iso3166'] is up to date.
		foreach ($this->getIso3166() as $cc => $label) {
			if (!isset($txt['iso3166'][$cc])) {
				echo "Added \$txt['iso3166']['{$cc}'] to Languages/en_US/Timezones.php.\n\n";
			} elseif ($txt['iso3166'][$cc] !== $label) {
				echo "Updated \$txt['iso3166']['{$cc}'] in Languages/en_US/Timezones.php.\n\n";
			}

			$txt['iso3166'][$cc] = $label;
		}

		ksort($txt['iso3166']);

		// Rebuild the file content.
		$lines = [
			'<' . '?php',
			'',
			current(preg_grep('~^// Version:~', explode("\n", $file_contents))),
			'',
		];

		foreach ($tztxt as $metazone => $value) {
			switch ($metazone) {
				case 'region_format':
					$lines[] = '// Generic metazone format. Argument {0} is the name of a country or city.';
					break;

				case 'region_format_type_daylight':
					$lines[] = '// Daylight Time metazone format. Argument {0} is the name of a country or city.';
					break;

				case 'region_format_type_standard':
					$lines[] = '// Standard Time metazone format. Argument {0} is the name of a country or city.';
					break;

				case 'fallback_format':
					$lines[] = '// Metazone with location. Argument {1} is the metazone and argument {0} is the name of a country or city.';
					break;

				case 'Etc/UTC':
					$lines[] = '';
					$lines[] = '// Special overrides for certain time zones.';
					break;
			}

			if (\in_array($metazone, $tztxt_strings)) {
				$lines[] = "\$tztxt['{$metazone}'] = " . Config::varExport($value) . ';';
			} else {
				foreach ($value as $dst_type => $variants) {
					foreach ($variants as $length => $label) {
						$lines[] = "\$tztxt['{$metazone}']['{$dst_type}']['{$length}'] = " . Config::varExport($label) . ';';
					}
				}
			}

			if ($metazone === $tztxt_first[array_key_last($tztxt_first)]) {
				$lines[] = '';
				$lines[] = '// Labels for metazones.';
			}
		}

		$lines[] = '';
		$lines[] = '// Location names.';

		foreach ($txt as $key => $value) {
			if ($key === 'iso3166') {
				continue;
			}

			$value = addcslashes($value, "'");

			$lines[] = "\$txt['{$key}'] = '{$value}';";
		}

		$lines[] = '';
		$lines[] = '// Countries.';

		foreach ($txt['iso3166'] as $key => $value) {
			$value = addcslashes($value, "'");

			$lines[] = "\$txt['iso3166']['{$key}'] = '{$value}';";
		}

		$lines[] = '';

		$file_contents = implode("\n", $lines);

		$new_hash = md5($file_contents);

		if ($old_hash !== $new_hash) {
			$this->files_updated = true;
		}

		// Save the changes.
		file_put_contents(Config::$languagesdir . '/en_US/Timezones.php', $file_contents);
	}

	/**
	 * Returns a list of Git tags and the associated commit hashes for
	 * each release of the TZDB available on GitHub.
	 */
	private function fetchTzdbTags(): void
	{
		foreach (json_decode(WebFetchApi::fetch(self::TZDB_TAGS_URL), true) as $tag) {
			$this->tzdb_tags[$tag['name']] = $tag['commit']['sha'];
		}

		ksort($this->tzdb_tags);
	}

	/**
	 * Returns a list of Git tags and the associated commit hashes for
	 * each release of the CLDR available on GitHub.
	 */
	private function fetchCldrTags(): void
	{
		foreach (json_decode(WebFetchApi::fetch(self::CLDR_TAGS_URL), true) as $tag) {
			if (!preg_match('/^\d+\.\d+\.\d+$/', $tag['name'])) {
				continue;
			}

			$this->cldr_tags[$tag['name']] = $tag['commit']['sha'];
		}

		krsort($this->cldr_tags);
	}

	/**
	 * Builds an array of canonical and linked time zone identifiers.
	 *
	 * Canonical tzids are a simple list, while linked tzids are given
	 * as 'link' => 'target' key-value pairs, where 'target' is a
	 * canonical tzid and 'link' is a compatibility tzid that uses the
	 * same time zone rules as its canonical target.
	 *
	 * @param string $commit Git commit hash of a specific TZDB version.
	 * @return array Canonical and linked time zone identifiers.
	 */
	private function getPrimaryZones(string $commit = 'main'): array
	{
		$canonical = [];
		$links = [];

		$filenames = [
			'africa',
			'antarctica',
			'asia',
			'australasia',
			'etcetera',
			'europe',
			// 'factory',
			'northamerica',
			'southamerica',
		];

		foreach ($filenames as $filename) {
			$file_contents = $this->fetchTzdbFile($filename, $commit);

			foreach (explode("\n", $file_contents) as $line) {
				$line = trim(substr($line, 0, strcspn($line, '#')));

				if (!str_starts_with($line, 'Zone') && !str_starts_with($line, 'Link')) {
					continue;
				}

				$parts = array_values(array_filter(preg_split("~\h+~", $line)));

				if ($parts[0] === 'Zone') {
					$canonical[] = $parts[1];
				} elseif ($parts[0] === 'Link') {
					$links[$parts[2]] = $parts[1];
				}
			}
		}

		return [$canonical, $links];
	}

	/**
	 * Builds an array of backward compatibility time zone identifiers.
	 *
	 * These supplement the linked tzids supplied by getPrimaryZones()
	 * and are formatted the same way (i.e. 'link' => 'target')
	 *
	 * @param string $commit Git commit hash of a specific TZDB version.
	 * @return array Linked time zone identifiers.
	 */
	private function getBacklinks(string $commit): array
	{
		$backlinks = [];

		$file_contents = $this->fetchTzdbFile('backward', $commit);

		foreach (explode("\n", $file_contents) as $line) {
			$line = trim(substr($line, 0, strcspn($line, '#')));

			if (!str_starts_with($line, 'Link')) {
				continue;
			}

			$parts = array_values(array_filter(preg_split("~\h+~", $line)));

			if (!isset($backlinks[$parts[2]])) {
				$backlinks[$parts[2]] = [];
			}

			$backlinks[$parts[2]] = $parts[1];
		}

		return $backlinks;
	}

	/**
	 * Similar to getPrimaryZones() in all respects, except that it
	 * returns the pre-1970 data contained in the TZDB's backzone file
	 * rather than the main data files.
	 *
	 * @param string $commit Git commit hash of a specific TZDB version.
	 * @return array Canonical and linked time zone identifiers.
	 */
	private function getBackzones(string $commit): array
	{
		$backzones = [];
		$backzone_links = [];

		$file_contents = $this->fetchTzdbFile('backzone', $commit);

		foreach (explode("\n", $file_contents) as $line) {
			$line = str_replace('#PACKRATLIST zone.tab ', '', $line);

			$line = trim(substr($line, 0, strcspn($line, '#')));

			if (str_starts_with($line, 'Zone')) {
				$parts = array_values(array_filter(preg_split("~\h+~", $line)));
				$backzones[] = $parts[1];
			} elseif (str_starts_with($line, 'Link')) {
				$parts = array_values(array_filter(preg_split("~\h+~", $line)));
				$backzone_links[$parts[2]] = $parts[1];
			}
		}

		$backzones = array_unique($backzones);
		$backzone_links = array_unique($backzone_links);

		return [$backzones, $backzone_links];
	}

	/**
	 * Simply fetches the full contents of a file for the specified
	 * version of the TZDB.
	 *
	 * @param string $filename File name.
	 * @param string $commit Git commit hash of a specific TZDB version.
	 * @return string The content of the file.
	 */
	private function fetchTzdbFile(string $filename, string $commit): string
	{
		 static $files;

		 if (empty($files[$commit])) {
			$files[$commit] = [];
		 }

		 if (empty($files[$commit][$filename])) {
			$files[$commit][$filename] = WebFetchApi::fetch(strtr(self::TZDB_FILE_URL, ['{COMMIT}' => $commit, '{FILE}' => $filename]));
		 }

		 return $files[$commit][$filename];
	}

	/**
	 * Fetches the contents of a CLDR JSON file and decodes it to an array.
	 *
	 * @param string $filename File name.
	 * @return array The file's data array.
	 */
	private function fetchCldrData(string $filename): array
	{
		 static $commit;

		 if (empty($commit)) {
			$this->fetchCldrTags();

			$commit = reset($this->cldr_tags);
		 }

		 if (empty($this->cldr_data)) {
			$this->cldr_data = [];
		 }

		 if (empty($this->cldr_data[$filename])) {
			$content = WebFetchApi::fetch(strtr(self::CLDR_FILE_URL, ['{COMMIT}' => $commit, '{FILE}' => $filename]));

			$this->cldr_data[$filename] = (array) json_decode($content, true);
		 }

		 return $this->cldr_data[$filename];
	}

	/**
	 * Gets the ISO-3166 country code for a time zone identifier as
	 * defined in the specified version of the TZDB.
	 *
	 * @param string $tzid A time zone identifier string.
	 * @param string $commit Git commit hash of a specific TZDB version.
	 * @return string A two-character country code, or '??' if not found.
	 */
	private function getCcForTzid(string $tzid, string $commit): string
	{
		preg_match('~^(\w\w)\h+[+\-\d]+\h+' . $tzid . '~m', $this->fetchTzdbFile('zone.tab', $commit), $matches);

		return $matches[1] ?? '??';
	}

	/**
	 * Returns a nice English label for the given time zone identifier.
	 *
	 * @param string $tzid A time zone identifier.
	 * @return array The label text, and possibly an "ACTION NEEDED" message.
	 */
	private function getTzidLabel(string $tzid): array
	{
		$sub_array = $this->fetchCldrData('cldr-json/cldr-dates-full/main/en/timeZoneNames.json')['main']['en']['dates']['timeZoneNames']['zone'];

		$tzid_parts = explode('/', $tzid);

		foreach ($tzid_parts as $part) {
			if (!isset($sub_array[$part])) {
				$sub_array = ['exemplarCity' => false];
				break;
			}

			$sub_array = $sub_array[$part];
		}

		$label = $sub_array['exemplarCity'] ?? false;
		$msg = '';

		// If tzid is not yet in the CLDR, is there an existing label to use?
		if ($label === false) {
			include Config::$languagesdir . '/en_US/Timezones.php';

			if (isset($txt[$tzid])) {
				$label = $txt[$tzid];
			}
		}

		// Unknown tzid. Probably new, so make a preliminary label.
		if ($label === false) {
			$label = str_replace(['St_', '_'], ['St. ', ' '], substr($tzid, strrpos($tzid, '/') + 1));

			$msg = "ACTION NEEDED: Check that the label is spelled correctly, etc.\n";

			$this->ready_to_commit = false;
		}

		$label = strtr($label, ['&' => 'and', 'St ' => 'St. ']);

		return [$label, $msg];
	}

	/**
	 * Gets the label strings for all known ISO 3166 country codes.
	 *
	 * @return array Strings that can be saved as $txt['iso3166'].
	 */
	private function getIso3166(): array
	{
		static $iso3166 = [];

		if (empty($iso3166)) {
			// Compile the list of ISO 3166 codes from the TZDB
			$iso3166_tab = $this->fetchTzdbFile('iso3166.tab', $this->curr_commit);

			foreach (explode("\n", $iso3166_tab) as $line) {
				$line = trim(substr($line, 0, strcspn($line, '#')));

				if (empty($line)) {
					continue;
				}

				list($cc, $label) = explode("\t", $line);

				$iso3166[$cc] = strtr($label, ['&' => 'and', 'St ' => 'St. ']);
			}

			// However, use the English labels from the CLDR, not the TZDB.
			$territories = $this->fetchCldrData('cldr-json/cldr-localenames-full/main/en/territories.json')['main']['en']['localeDisplayNames']['territories'];

			$use_alt_forms = ['CD', 'CG', 'HK', 'MM', 'MO', 'PS'];

			foreach ($territories as $code => $label) {
				if (is_numeric($code)) {
					continue;
				}

				$cc = substr($code, 0, 2);

				if (!isset($iso3166[$cc])) {
					continue;
				}

				// Don't use alternative labels except in a few special cases.
				if (
					str_starts_with($code, $cc . '-alt')
					&& !\in_array($cc, $use_alt_forms)
				) {
					$label = $territories[$cc];
				}

				$iso3166[$cc] = strtr($label, ['&' => 'and', 'St ' => 'St. ']);
			}
		}

		ksort($iso3166);

		return $iso3166;
	}

	/**
	 * Builds fallback information for new time zones.
	 *
	 * @return array Fallback info for the new time zones.
	 */
	private function buildFallbacks(): array
	{
		$date_min = new \DateTime(self::DATE_MIN);

		$this->buildZones();

		// See if we can find suitable fallbacks for each newly added zone.
		$fallbacks = [];

		foreach ($this->tz_data['changed']['additions'] as $tzid) {
			// Build a list of possible fallback zones for this zone.
			$possible_fallback_zones = $this->buildPossibleFallbackZones($tzid);

			// Build a preliminary list of fallbacks.
			$fallbacks[$tzid] = [];

			$prev_fallback_tzid = '';

			foreach ($this->zones[$tzid]['entries'] as $entry_num => $entry) {
				if ($entry['format'] == '-00') {
					$fallbacks[$tzid][] = [
						'ts' => 'PHP_INT_MIN',
						'tzid' => '',
					];

					$prev_fallback_tzid = '';

					continue;
				}

				foreach ($this->findFallbacks($possible_fallback_zones, $entry, $tzid, $prev_fallback_tzid, $this->tz_data['changed']['new']) as $fallback) {
					$prev_fallback_tzid = $fallback['tzid'];
					$fallbacks[$tzid][] = $fallback;
				}
			}

			// Walk through the preliminary list and amalgamate any we can.
			// Go in reverse order, because things tend to work out better that way.
			$remove_earlier = false;

			for ($i = \count($fallbacks[$tzid]) - 1; $i > 0; $i--) {
				if ($fallbacks[$tzid][$i]['tzid'] === '') {
					$remove_earlier = true;
				}

				if ($fallbacks[$tzid][$i]['ts'] === 'PHP_INT_MIN') {
					if (empty($fallbacks[$tzid][$i - 1]['tzid'])) {
						$fallbacks[$tzid][$i - 1]['tzid'] = $fallbacks[$tzid][$i]['tzid'];
					}

					$remove_earlier = true;
				}

				if ($remove_earlier) {
					unset($fallbacks[$tzid][$i]);
					continue;
				}

				// If there are no options available, we can do nothing more.
				if (empty($fallbacks[$tzid][$i]['options']) || empty($fallbacks[$tzid][$i - 1]['options'])) {
					continue;
				}

				// Which options work for both the current and previous entry?
				$shared_options = array_intersect(
					$fallbacks[$tzid][$i]['options'],
					$fallbacks[$tzid][$i - 1]['options'],
				);

				// No shared options means we can't amalgamate these entries.
				if (empty($shared_options)) {
					continue;
				}

				// We don't want canonical tzids unless absolutely necessary.
				$temp = $shared_options;

				foreach ($temp as $option) {
					if (isset($this->zones[$option]['canonical'])) {
						// Filter out the canonical tzid.
						$shared_options = array_filter(
							$shared_options,
							function ($tzid) use ($option) {
								return $tzid !== $this->zones[$option]['canonical'];
							},
						);

						// If top choice is the canonical tzid, replace it with the link.
						// This check is probably redundant, but it doesn't hurt.
						if ($fallbacks[$tzid][$i]['tzid'] === $this->zones[$option]['canonical']) {
							$fallbacks[$tzid][$i]['tzid'] = $option;
						}

						if ($fallbacks[$tzid][$i - 1]['tzid'] === $this->zones[$option]['canonical']) {
							$fallbacks[$tzid][$i - 1]['tzid'] = $option;
						}
					}
				}

				// If the previous entry's top choice isn't in the list of shared options,
				// change it to one that is.
				if (!empty($shared_options) && !\in_array($fallbacks[$tzid][$i - 1]['tzid'], $shared_options)) {
					$fallbacks[$tzid][$i - 1]['tzid'] = reset($shared_options);
				}

				// Reduce the options for the previous entry down to only those that are
				// in the current list of shared options.
				$fallbacks[$tzid][$i - 1]['options'] = $shared_options;

				// We no longer need this one.
				unset($fallbacks[$tzid][$i]);
			}
		}

		return $fallbacks;
	}

	/**
	 * Finds a viable fallback for an entry in a time zone's list of
	 * transition rule changes. In some cases, the returned value will
	 * consist of a series of fallbacks for different times during the
	 * overall period of the entry.
	 *
	 * @param array $pfzs Array returned from buildPossibleFallbackZones()
	 * @param array $entry An element from $this->zones[$tzid]['entries']
	 * @param string $tzid A time zone identifier
	 * @param string $prev_fallback_tzid A time zone identifier
	 * @param array $skip_tzids Tzids that should not be used as fallbacks.
	 * @return array Fallback data for the entry.
	 */
	private function findFallbacks(array $pfzs, array $entry, string $tzid, string $prev_fallback_tzid, array $skip_tzids): array
	{
		static $depth = 0;

		$fallbacks = [];

		unset($entry['from'], $entry['from_suffix'], $entry['until'], $entry['until_suffix']);

		$entry_id = md5(json_encode($entry));

		$date_min = new \DateTime(self::DATE_MIN);
		$ts_min = $date_min->format('Y-m-d\TH:i:sO');

		$date_from = new \DateTime($entry['from_utc']);
		$date_until = new \DateTime($entry['until_utc']);

		// Our first test should be the zone we used for the last one.
		// This helps reduce unnecessary switching between zones.
		$ordered_pfzs = $pfzs;

		if (!empty($prev_fallback_tzid) && isset($pfzs[$prev_fallback_tzid])) {
			$prev_fallback_zone = $ordered_pfzs[$prev_fallback_tzid];

			unset($ordered_pfzs[$prev_fallback_tzid]);

			$ordered_pfzs = array_merge([$prev_fallback_zone], $ordered_pfzs);
		}

		$fallback_found = false;
		$earliest_fallback_timestamp = strtotime('now');

		$i = 0;

		while (!$fallback_found && $i < 50) {
			foreach ($ordered_pfzs as $pfz) {
				if (\in_array($pfz['tzid'], $skip_tzids)) {
					continue;
				}

				if (isset($fallbacks[$entry_id]['options']) && \in_array($pfz['tzid'], $fallbacks[$entry_id]['options'])) {
					continue;
				}

				foreach ($pfz['entries'] as $pfz_entry_num => $pfz_entry) {
					$pfz_date_from = new \DateTime($pfz_entry['from_utc']);
					$pfz_date_until = new \DateTime($pfz_entry['until_utc']);

					// Offset and rules must match.
					if ($entry['stdoff'] !== $pfz_entry['stdoff']) {
						continue;
					}

					if ($entry['rules'] !== $pfz_entry['rules']) {
						continue;
					}

					// Before the start of our range, so move on to the next entry.
					if ($date_from->getTimestamp() >= $pfz_date_until->getTimestamp()) {
						continue;
					}

					// After the end of our range, so move on to the next possible fallback zone.
					if ($date_from->getTimestamp() < $pfz_date_from->getTimestamp()) {
						// Remember this in case we need to try again for transitions away from LMT.
						$earliest_fallback_timestamp = min($earliest_fallback_timestamp, $pfz_date_from->getTimestamp());

						continue 2;
					}

					// If this possible fallback ends before our existing options, skip it.
					if (!empty($fallbacks[$entry_id]) && $pfz_date_until->getTimestamp() < $fallbacks[$entry_id]['end']) {
						continue;
					}

					// At this point, we know we've found one.
					$fallback_found = true;

					// If there is no fallback for this entry yet, create one.
					if (empty($fallbacks[$entry_id])) {
						$fallbacks[$entry_id] = [
							'ts' => $date_from->format('Y-m-d\TH:i:sO'),
							'end' => min($date_until->getTimestamp(), $pfz_date_until->getTimestamp()),
							'tzid' => $pfz['tzid'],
							'options' => [],
						];
					}

					// Append to the list of options.
					$fallbacks[$entry_id]['options'][] = $pfz['tzid'];

					if (isset($pfz['canonical'])) {
						$fallbacks[$entry_id]['options'][] = $pfz['canonical'];
					}

					if (isset($pfz['links'])) {
						$fallbacks[$entry_id]['options'] = array_merge($fallbacks[$entry_id]['options'], $pfz['links']);
					}

					// Only a partial overlap.
					if ($date_until->getTimestamp() > $pfz_date_until->getTimestamp() && $depth < 10) {
						$depth++;

						$partial_entry = $entry;
						$partial_entry['from_utc'] = $pfz_date_until->format('c');

						$fallbacks = array_merge($fallbacks, $this->findFallbacks($pfzs, $partial_entry, $tzid, $pfz['tzid'], $skip_tzids));

						$depth--;
					}

					break;
				}
			}

			if (!$fallback_found) {
				// If possible, move the timestamp forward and try again.
				if ($date_from->format('Y-m-d\TH:i:sO') !== $ts_min && $date_from->getTimestamp() < $earliest_fallback_timestamp) {
					$fallbacks[] = [
						'ts' => $date_from->format('Y-m-d\TH:i:sO'),
						'tzid' => '',
						'options' => [],
					];

					$prev_fallback_tzid = '';

					$date_from->setTimestamp($earliest_fallback_timestamp);
				}
				// We've run out of options.
				else {
					$fallbacks[$entry_id] = [
						'ts' => $date_from->format('Y-m-d\TH:i:sO'),
						'tzid' => '',
						'options' => [],
					];

					$fallback_found = true;
				}
			}
		}

		foreach ($fallbacks as &$fallback) {
			$fallback['options'] = array_unique($fallback['options']);

			if ($fallback['ts'] <= $ts_min) {
				$fallback['ts'] = 'PHP_INT_MIN';
			}
		}

		return $fallbacks;
	}

	/**
	 * Compiles information about all time zones in the TZDB, including
	 * transitions, location data, what other zones it links to or that
	 * link to it, and whether it is new (where "new" means not present
	 * in the earliest version of the TZDB that we are considering).
	 */
	private function buildZones(): void
	{
		if (!empty($this->zones)) {
			return;
		}

		$date_min = new \DateTime(self::DATE_MIN);
		$date_max = new \DateTime(self::DATE_MAX);

		$links = [];

		$filenames = [
			'africa',
			'antarctica',
			'asia',
			'australasia',
			'etcetera',
			'europe',
			// 'factory',
			'northamerica',
			'southamerica',
			'backward',
			'backzone',
		];

		// Populate $this->zones with TZDB data.
		foreach ($filenames as $filename) {
			$tzid = '';

			foreach (explode("\n", $this->fetchTzdbFile($filename, $this->curr_commit)) as $line_num => $line) {
				$line = rtrim(substr($line, 0, strcspn($line, '#')));

				if ($line === '') {
					continue;
				}

				// Line starts a new zone record.
				if (preg_match('/^Zone\h+(\w+(\/[\w+\-]+)*)/', $line, $matches)) {
					$tzid = $matches[1];
				}
				// Line provides a link.
				elseif (str_starts_with($line, 'Link')) {
					// No longer in a zone record.
					$tzid = '';

					$parts = array_values(array_filter(preg_split("~\h+~", $line)));
					$links[$parts[2]] = $parts[1];
				}
				// Line provides a rule.
				elseif (str_starts_with($line, 'Rule')) {
					// No longer in a zone record.
					$tzid = '';
				}
				// Line is not a continuation of the current zone record.
				elseif (!empty($tzid) && !preg_match('/^\h+([+\-]?\d{1,2}:\d{2}|0\h+)/', $line)) {
					$tzid = '';
				}

				// If in a zone record, do stuff.
				if (!empty($tzid)) {
					$data = trim(preg_replace('/^Zone\h+\w+(\/[\w+\-]+)*\h+/', '', $line));

					$parts = array_combine(
						['stdoff', 'rules', 'format', 'until'],
						array_pad(preg_split("~\h+~", $data, 4), 4, ''),
					);

					if (!str_contains($parts['stdoff'], ':')) {
						$parts['stdoff'] .= ':00';
					}

					$this->zones[$tzid]['entries'][] = $parts;

					$this->zones[$tzid]['file'] = $filename;
				}
			}
		}

		// Add a 'from' date to every entry of every zone.
		foreach ($this->zones as $tzid => &$record) {
			$record['tzid'] = $tzid;

			foreach ($record['entries'] as $entry_num => &$entry) {
				// Until is when the current entry ends.
				if (empty($entry['until'])) {
					$entry['until'] = $date_max->format('Y-m-d\TH:i:s');
					$entry['until_suffix'] = 'u';
				} else {
					// Rewrite date into PHP-parseable format.
					$entry['until'] = $this->rewriteDateString($entry['until']);

					// Find the suffix. Determines which zone the until timestamp is in.
					preg_match('/\d+:\d+(|[wsugz])$/', $entry['until'], $matches);

					// Now set the until values.
					if (!empty($matches[1])) {
						$entry['until_suffix'] = $matches[1];

						$entry['until'] = substr($entry['until'], 0, strrpos($entry['until'], $entry['until_suffix']));
					} else {
						$entry['until_suffix'] = '';
					}

					$entry['until'] = date_format(new \DateTime($entry['until']), 'Y-m-d\TH:i:s');
				}

				// From is just a copy of the previous entry's until.
				if ($entry_num === 0) {
					$entry['from'] = $date_min->format('Y-m-d\TH:i:s');
					$entry['from_suffix'] = 'u';
				} else {
					$entry['from'] = $record['entries'][$entry_num - 1]['until'];
					$entry['from_suffix'] = $record['entries'][$entry_num - 1]['until_suffix'];
				}
			}
		}

		// Set coordinates and country codes for each zone.
		foreach (explode("\n", $this->fetchTzdbFile('zone.tab', $this->curr_commit)) as $line_num => $line) {
			$line = rtrim(substr($line, 0, strcspn($line, '#')));

			if ($line === '') {
				continue;
			}

			$parts = array_combine(
				['country_code', 'coordinates', 'tzid', 'comments'],
				array_pad(preg_split("~\h~", $line, 4), 4, ''),
			);

			if (!isset($this->zones[$parts['tzid']])) {
				continue;
			}

			$this->zones[$parts['tzid']]['country_code'] = $parts['country_code'];

			list($latitude, $longitude) = preg_split('/\b(?=[+\-])/', $parts['coordinates']);

			foreach (['latitude', 'longitude'] as $varname) {
				$deg_len = $varname === 'latitude' ? 3 : 4;

				$deg = substr(${$varname}, 0, $deg_len);
				$min = substr(${$varname}, $deg_len, 2);
				$sec = substr(${$varname}, $deg_len + 2);
				$frac = (int) $min / 60 + (int) $sec / 3600;

				$this->zones[$parts['tzid']][$varname] = (float) $deg + $frac;
			}
		}

		// Ensure all zones have coordinates.
		foreach ($this->zones as $tzid => &$record) {
			// The vast majority of zones.
			if (isset($record['longitude'])) {
				continue;
			}

			// Etc/* can be given fake coordinates.
			if (\count($record['entries']) === 1) {
				$this->zones[$tzid]['latitude'] = 0;
				$this->zones[$tzid]['longitude'] = (int) ($record['entries'][0]['stdoff']) * 15;
			}

			// Still nothing? Must be a backzone that isn't in zone.tab.
			// As of version 2022d, only case is Asia/Hanoi.
			if (!isset($record['longitude'])) {
				unset($this->zones[$tzid]);
			}
		}

		// From this point forward, handle links like canonical zones.
		foreach ($links as $link_name => $target) {
			// Links can point to other links. We want the true canonical.
			while (isset($links[$target])) {
				$target = $links[$target];
			}

			if (!isset($this->zones[$link_name])) {
				$this->zones[$link_name] = $this->zones[$target];
				$this->zones[$link_name]['tzid'] = $link_name;
				unset($this->zones[$link_name]['links']);
			}

			$this->zones[$link_name]['canonical'] = $target;
			$this->zones[$target]['links'][] = $link_name;

			$this->zones[$target]['links'] = array_unique($this->zones[$target]['links']);
		}

		// Mark new zones as such.
		foreach ($this->tz_data['changed']['new'] as $tzid) {
			$this->zones[$tzid]['new'] = true;
		}

		// Set UTC versions of every entry's 'from' and 'until' dates.
		$this->buildTransitions(true);
	}

	/**
	 * Processes metazone data from the CLDR and populates $this->metazones.
	 */
	private function buildMetaZones(): void
	{
		if (!empty($this->metazones)) {
			return;
		}

		$metazones_data = $this->fetchCldrData('cldr-json/cldr-core/supplemental/metaZones.json');

		// Compile info about when different metazones were used by various time zones.
		foreach ($this->zones as $tzid => $zone) {
			$this->metazones['usage'][$tzid] = [];

			$sub_array = $metazones_data['supplemental']['metaZones']['metazoneInfo']['timezone'];

			$tzid_parts = explode('/', $tzid);

			foreach ($tzid_parts as $part_num => $part) {
				if (isset($sub_array[$part])) {
					$sub_array = $sub_array[$part];
				} elseif (isset($tzid_parts[$part_num + 1], $sub_array[$tzid_parts[$part_num + 1]])) {
					continue;
				} else {
					continue 2;
				}
			}

			foreach ($sub_array as $entries) {
				foreach ($entries as $entry) {
					if (!isset($entry['_mzone'])) {
						continue;
					}

					$this->metazones['usage'][$tzid][] = [
						'ts' => empty($entry['_from']) ? self::DATE_EPOCH : (new \DateTime($entry['_from'] . ' UTC'))->format('Y-m-d\TH:i:sO'),
						'metazone' => $entry['_mzone'],
					];
				}
			}
		}

		// There are some cases where the metazone info is in a linked time zone
		// while the canonical time zone has no metazone info. In those cases,
		// move the metazone info to the canonical one.
		foreach ($this->metazones['usage'] as $tzid => $entries) {
			$canonical = $this->getBestMetaZoneTzid($tzid);

			if (empty($this->metazones['usage'][$canonical])) {
				$this->metazones['usage'][$canonical] = $entries;
				unset($this->metazones['usage'][$tzid]);
			}
		}

		// Compile info about which time zones are the preferred ones for different metazones.
		$this->metazones['mapped'] = $metazones_data['supplemental']['metaZones']['metazones'];

		// Compile long metazone labels for use in $tztxt.
		foreach ($this->fetchCldrData('cldr-json/cldr-dates-full/main/en/timeZoneNames.json')['main']['en']['dates']['timeZoneNames']['metazone'] as $metazone => $names) {
			if (!empty($names['long'])) {
				foreach ($names['long'] as $dst_type => $label) {
					$this->metazones['labels'][$metazone][$dst_type]['long'] = strtr($label, ['&' => 'and', 'St ' => 'St. ']);
				}
			}
		}

		// Compile short metazone labels (i.e. abbreviations) for use in $tztxt.
		// The abbreviations come from the TZDB rather than the CLDR because the
		// CLDR scatters them across many files.
		foreach ($this->metazones['mapped'] as $mapzones) {
			foreach ($mapzones as $mapzone) {
				if ($mapzone['_territory'] !== '001') {
					continue;
				}

				$metazone = $mapzone['_other'];
				$tzid = $this->getBestMetaZoneTzid($mapzone['_type']);

				if (
					isset($this->metazones['labels'][$metazone]['standard']['long'])
					|| isset($this->metazones['labels'][$metazone]['daylight']['long'])
				) {
					foreach (array_reverse($this->transitions[$tzid]) as $transition) {
						if ($transition['offset'] % 900 !== 0) {
							break;
						}

						$dst_type = $transition['isdst'] ? 'daylight' : 'standard';

						if (isset($this->metazones['labels'][$metazone][$dst_type]['long'])) {
							$this->metazones['labels'][$metazone][$dst_type]['short'] ??= $transition['abbr'];
						}

						if (
							isset($this->metazones['labels'][$metazone]['standard']['long']) === isset($this->metazones['labels'][$metazone]['standard']['short'])
							&& isset($this->metazones['labels'][$metazone]['daylight']['long']) === isset($this->metazones['labels'][$metazone]['daylight']['short'])
						) {
							break;
						}
					}
				}

				if (isset($this->metazones['labels'][$metazone]['generic']['long'])) {
					$entry = array_last($this->zones[$tzid]['entries']);

					if ($entry['format'] !== '%z') {
						$this->metazones['labels'][$metazone]['generic']['short'] ??= \sprintf($entry['format'], '');
					} elseif (
						isset($this->metazones['labels'][$metazone]['standard']['short'])
						&& (
							!isset($this->metazones['labels'][$metazone]['daylight']['short'])
							|| $this->metazones['labels'][$metazone]['standard']['short'] === $this->metazones['labels'][$metazone]['daylight']['short']
						)
					) {
						$this->metazones['labels'][$metazone]['generic']['short'] ??= $this->metazones['labels'][$metazone]['standard']['short'];
					}
				}
			}
		}

		// Some special snowflakes do things differently...
		foreach ($this->zones as $tzid => $zone) {
			$sub_array = $this->fetchCldrData('cldr-json/cldr-dates-full/main/en/timeZoneNames.json')['main']['en']['dates']['timeZoneNames']['zone'];

			$tzid_parts = explode('/', $tzid);

			foreach ($tzid_parts as $part) {
				if (!isset($sub_array[$part])) {
					continue 2;
				}

				$sub_array = $sub_array[$part];
			}

			if (!empty($sub_array['long'])) {
				foreach ($sub_array['long'] as $dst_type => $label) {
					$this->metazones['labels'][$tzid][$dst_type]['long'] = strtr($label, ['&' => 'and', 'St ' => 'St. ']);
				}
			}

			if (!empty($sub_array['short'])) {
				foreach ($sub_array['short'] as $dst_type => $label) {
					$this->metazones['labels'][$tzid][$dst_type]['short'] = strtr($label, ['&' => 'and', 'St ' => 'St. ']);
				}
			}
		}

		$string_order = [
			'generic',
			'standard',
			'daylight',
		];

		foreach ($this->metazones['labels'] as $metazone => $dummy) {
			uksort(
				$this->metazones['labels'][$metazone],
				fn($a, $b) => array_search($a, $string_order) <=> array_search($b, $string_order),
			);
		}
	}

	/**
	 * Gets the best time zone identifier to use for a metazone's exemplar.
	 *
	 * @param string $tzid A time zone identifier.
	 * @return string The time zone identifier to use for a metazone's exemplar.
	 */
	private function getBestMetaZoneTzid(string $tzid): string
	{
		// If $tzid is not canonical, it might be better to use a different one.
		if (!empty($this->zones[$tzid]['canonical'])) {
			$cc = $this->getCcForTzid($tzid, $this->curr_commit);

			if (
				// '??' usually means international, but for some backlinks it
				// just means undefined. Those should be avoided.
				$cc === '??'
				// If the canonical equivalent is in the same country, use it.
				|| $cc === $this->getCcForTzid(
					$this->zones[$tzid]['canonical'],
					$this->curr_commit,
				)
			) {
				$tzid = $this->zones[$tzid]['canonical'];
			}
		}

		return $tzid;
	}

	/**
	 * Populates $this->transitions with time zone transition information
	 * similar to PHP's timezone_transitions_get(), except that the array
	 * is built from the TZDB source as it existed at whatever version is
	 * defined as 'current' via self::TZDB_CURR_TAG & $this->curr_commit.
	 *
	 * Also updates the entries for every tzid in $this->zones with
	 * unambiguous UTC timestamps for their start and end values.
	 *
	 * @param bool $rebuild If true, force a rebuild.
	 */
	private function buildTransitions(bool $rebuild = false): void
	{
		static $zones_hash = '';

		if (md5(json_encode($this->zones)) !== $zones_hash) {
			$rebuild = true;
		}

		$zones_hash = md5(json_encode($this->zones));

		if (!empty($this->transitions) && !$rebuild) {
			return;
		}

		$utc = new \DateTimeZone('UTC');
		$date_min = new \DateTime(self::DATE_MIN);
		$date_max = new \DateTime(self::DATE_MAX);

		foreach ($this->zones as $tzid => &$zone) {
			// Shouldn't happen, but just in case...
			if (empty($zone['entries'])) {
				continue;
			}

			$this->transitions[$tzid] = [];

			$zero = 0;
			$prev_offset = 0;
			$prev_std_offset = 0;
			$prev_save = 0;
			$prev_isdst = false;
			$prev_abbr = '';
			$prev_rules = '-';

			foreach ($zone['entries'] as $entry_num => $entry) {
				// Determine the standard time offset for this entry.
				$stdoff_parts = array_map('intval', explode(':', $entry['stdoff']));
				$stdoff_parts = array_pad($stdoff_parts, 3, 0);
				$std_offset = abs($stdoff_parts[0]) * 3600 + $stdoff_parts[1] * 60 + $stdoff_parts[2];

				if (substr($entry['stdoff'], 0, 1) === '-') {
					$std_offset *= -1;
				}

				// Entries never have gaps, so the end of one is the start of the next.
				$entry_start = new \DateTime($entry['from'], $utc);
				$entry_end = new \DateTime($entry['until'], $utc);

				$unadjusted_date_strings = [
					'entry_start' => $entry_start->format('Y-m-d\TH:i:s'),
					'entry_end' => $entry_end->format('Y-m-d\TH:i:s'),
				];

				switch ($entry['from_suffix']) {
					case 'u':
					case 'g':
					case 'z':
						break;

					case 's':
						$entry_start->setTimestamp($entry_start->getTimestamp() - $prev_std_offset);
						break;

					default:
						$entry_start->setTimestamp($entry_start->getTimestamp() - $prev_offset);
						break;
				}

				switch ($entry['until_suffix']) {
					case 'u':
					case 'g':
					case 'z':
						$entry_end_offset_var = 'zero';
						break;

					case 's':
						$entry_end_offset_var = 'prev_std_offset';
						break;

					default:
						$entry_end_offset_var = 'prev_offset';
						break;
				}

				// For convenience elsewhere, provide UTC timestamps for the entry boundaries.
				$zone['entries'][$entry_num]['from_utc'] = $entry_start->format('Y-m-d\TH:i:sO');

				if (isset($zone['entries'][$entry_num - 1])) {
					$zone['entries'][$entry_num - 1]['until_utc'] = $entry_start->format('Y-m-d\TH:i:sO');
				}


				// No DST rules.
				if ($entry['rules'] == '-') {
					$ts = $entry_start->getTimestamp();
					$time = $entry_start->format('Y-m-d\TH:i:sO');
					$offset = $std_offset;
					$isdst = false;
					$abbr = $entry['format'] === '%z' ? (($interval = date_diff(date_create('@0'), date_create('@' . $offset)))->i === 0 ? $interval->format('%R%H') : ($interval->s === 0 ? $interval->format('%R%H%i') : $interval->format('%R%H%i%s'))) : \sprintf($entry['format'], 'S');
					$save = 0;
					$unadjusted_date_string = $unadjusted_date_strings['entry_start'];

					// Some abbr values use '+00/+01' instead of sprintf formats.
					if (str_contains($abbr, '/')) {
						$abbr = substr($abbr, 0, strpos($abbr, '/'));
					}

					// Skip if these values are identical to the previous values.
					// ... with an exception for Europe/Lisbon, which is a special snowflake.
					if ($offset === $prev_offset && $isdst === $prev_isdst && $abbr === $prev_abbr && $abbr !== 'LMT') {
						continue;
					}

					$this->transitions[$tzid][$ts] = compact('ts', 'time', 'offset', 'isdst', 'abbr', 'entry_end');

					$prev_offset = $offset;
					$prev_std_offset = $std_offset;
					$prev_save = $save == 0 ? 0 : $save / 3600 . ':' . \sprintf('%02d', $save % 3600);
					$prev_isdst = $isdst;
					$prev_abbr = $abbr;
					$entry_end_offset = ${$entry_end_offset_var};
				}
				// Simple DST rules.
				elseif (preg_match('/^-?\d+(:\d+)*$/', $entry['rules'])) {
					$rules_parts = array_map('intval', explode(':', $entry['rules']));
					$rules_parts = array_pad($rules_parts, 3, 0);
					$rules_offset = abs($rules_parts[0]) * 3600 + $rules_parts[1] * 60 + $rules_parts[2];

					if (substr($entry['rules'], 0, 1) === '-') {
						$rules_offset *= -1;
					}

					$ts = $entry_start->getTimestamp();
					$time = $entry_start->format('Y-m-d\TH:i:sO');
					$offset = $std_offset + $rules_offset;
					$isdst = true;
					$abbr = $entry['format'] === '%z' ? (($interval = date_diff(date_create('@0'), date_create('@' . $offset)))->i === 0 ? $interval->format('%R%H') : ($interval->s === 0 ? $interval->format('%R%H%i') : $interval->format('%R%H%i%s'))) : \sprintf($entry['format'], 'D');
					$save = $rules_offset;
					$unadjusted_date_string = $unadjusted_date_strings['entry_start'];

					// Some abbr values use '+00/+01' instead of sprintf formats.
					if (str_contains($abbr, '/')) {
						$abbr = substr($abbr, strpos($abbr, '/'));
					}

					// Skip if these values are identical to the previous values.
					if ($offset === $prev_offset && $isdst === $prev_isdst && $abbr === $prev_abbr) {
						continue;
					}

					$this->transitions[$tzid][$ts] = compact('ts', 'time', 'offset', 'isdst', 'abbr', 'entry_end');

					$prev_offset = $offset;
					$prev_std_offset = $std_offset;
					$prev_save = $save == 0 ? 0 : $save / 3600 . ':' . \sprintf('%02d', $save % 3600);
					$prev_isdst = $isdst;
					$prev_abbr = $abbr;
					$entry_end_offset = ${$entry_end_offset_var};
				}
				// Complex DST rules
				else {
					$default_letter = '-';
					$default_save = 0;

					$rule_transitions = $this->getApplicableRuleTransitions($entry['rules'], $unadjusted_date_strings, (int) $std_offset, (string) $prev_save);

					// Figure out the state when the entry starts.
					foreach ($rule_transitions as $date_string => $info) {
						if ($date_string >= $unadjusted_date_strings['entry_start']) {
							break;
						}

						$default_letter = $info['letter'];
						$default_save = $info['save'];

						if ($std_offset === $prev_std_offset && $prev_rules === $entry['rules']) {
							$prev_save = $info['save'];

							if ($prev_save != 0) {
								$prev_save_parts = array_map('intval', explode(':', $prev_save));
								$prev_save_parts = array_pad($prev_save_parts, 3, 0);
								$prev_save_offset = abs($prev_save_parts[0]) * 3600 + $prev_save_parts[1] * 60 + $prev_save_parts[2];

								if (str_starts_with($prev_save, '-')) {
									$prev_save_offset *= -1;
								}
							} else {
								$prev_save_offset = 0;
							}

							$prev_offset = $prev_std_offset + $prev_save_offset;
						}

						unset($rule_transitions[$date_string]);
					}

					// Add a rule transition at entry start, if not already present.
					if (!\in_array($unadjusted_date_strings['entry_start'], array_column($rule_transitions, 'unadjusted_date_string'))) {
						if ($default_letter === '-') {
							foreach ($rule_transitions as $date_string => $info) {
								if ($info['save'] == $default_save) {
									$default_letter = $info['letter'];
									break;
								}
							}
						}

						$rule_transitions[$unadjusted_date_strings['entry_start']] = [
							'letter' => $default_letter,
							'save' => $default_save,
							'at_suffix' => $entry['from_suffix'],
							'unadjusted_date_string' => $unadjusted_date_strings['entry_start'],
							'adjusted_date_string' => $entry_start->format('Y-m-d\TH:i:sO'),
							'rrule' => $rule_transitions[$unadjusted_date_strings['entry_start']]['rrule'] ?? null,
							'dtstart' => $rule_transitions[$unadjusted_date_strings['entry_start']]['dtstart'] ?? null,
							'until_local' => $rule_transitions[$unadjusted_date_strings['entry_start']]['until_local'] ?? null,
						];

						ksort($rule_transitions);
					}
					// Ensure entry start rule transition uses correct UTC time.
					else {
						$rule_transitions[$unadjusted_date_strings['entry_start']]['adjusted_date_string'] = $entry_start->format('Y-m-d\TH:i:sO');
					}

					// Create the transitions
					foreach ($rule_transitions as $date_string => $info) {
						if (!empty($info['adjusted_date_string'])) {
							$transition_date = new \DateTime($info['adjusted_date_string']);
						} else {
							$transition_date = new \DateTime($date_string, $utc);

							if (empty($info['at_suffix']) || $info['at_suffix'] === 'w') {
								$transition_date->setTimestamp($transition_date->getTimestamp() - $prev_offset);
							} elseif ($info['at_suffix'] === 's') {
								$transition_date->setTimestamp($transition_date->getTimestamp() - $prev_std_offset);
							}
						}

						$save_parts = array_map('intval', explode(':', (string) $info['save']));
						$save_parts = array_pad($save_parts, 3, 0);
						$save_offset = abs($save_parts[0]) * 3600 + $save_parts[1] * 60 + $save_parts[2];

						if (str_starts_with((string) $info['save'], '-')) {
							$save_offset *= -1;
						}

						// Populate the transition values.
						$ts = $transition_date->getTimestamp();
						$time = $transition_date->format('Y-m-d\TH:i:sO');
						$offset = $std_offset + $save_offset;
						$isdst = $save_offset != 0;
						$abbr = $entry['format'] === '%z' ? (($interval = date_diff(date_create('@0'), date_create('@' . $offset)))->i === 0 ? $interval->format('%R%H') : ($interval->s === 0 ? $interval->format('%R%H%i') : $interval->format('%R%H%i%s'))) : (\sprintf($entry['format'], $info['letter'] === '-' ? '' : $info['letter']));
						$save = $save_offset;
						$unadjusted_date_string = $info['unadjusted_date_string'];
						$rrule = $info['rrule'] ?? null;

						if (isset($info['dtstart'])) {
							$dtstart = new \DateTime($info['dtstart'], $utc);

							switch ($info['at_suffix']) {
								case 'u':
								case 'g':
								case 'z':
									$dtstart->add($this->offsetToDateInterval((string) $prev_save));
									$dtstart->add($this->offsetToDateInterval((string) $prev_std_offset));
									$dtstart = $dtstart->format('Ymd\THis');
									break;

								case 's':
									$dtstart->add($this->offsetToDateInterval((string) $prev_save));
									$dtstart = $dtstart->format('Ymd\THis');
									break;

								default:
									$dtstart = $info['dtstart'];
									break;
							}
						} else {
							$dtstart = null;
						}

						if (isset($rrule, $info['until_local'])) {
							$until = new \DateTime($info['until_local']);
							$until->modify(-$prev_offset . ' seconds');
							$rrule .= ';UNTIL=' . $until->format('Ymd\THis\Z');
						}

						// Some abbr values use '+00/+01' instead of sprintf formats.
						if (str_contains($abbr, '/')) {
							$abbrs = explode('/', $abbr);
							$abbr = $isdst ? $abbrs[1] : $abbrs[0];
						}

						// Skip if these values are identical to the previous values.
						if ($offset === $prev_offset && $isdst === $prev_isdst && $abbr === $prev_abbr) {
							continue;
						}

						// Don't create a redundant transition for the entry's end.
						if ($ts >= $entry_end->getTimestamp() - ${$entry_end_offset_var}) {
							break;
						}

						// Remember for the next iteration.
						$prev_offset = $offset;
						$prev_std_offset = $std_offset;
						$prev_save = $save == 0 ? 0 : $save / 3600 . ':' . \sprintf('%02d', $save % 3600);
						$prev_isdst = $isdst;
						$prev_abbr = $abbr;
						$entry_end_offset = ${$entry_end_offset_var};

						// This can happen in some rare cases.
						if ($ts < $entry_start->getTimestamp()) {
							// Update the transition for the entry start, if it exists.
							if (isset($this->transitions[$tzid][$entry_start->getTimestamp()])) {
								$this->transitions[$tzid][$entry_start->getTimestamp()] = array_merge(
									$this->transitions[$tzid][$entry_start->getTimestamp()],
									compact('offset', 'isdst', 'abbr'),
								);
							}

							continue;
						}

						// Create the new transition.
						$this->transitions[$tzid][$ts] = compact('ts', 'time', 'offset', 'isdst', 'abbr', 'entry_end');

						if (isset($rrule)) {
							$this->transitions[$tzid][$ts]['rrule'] = $rrule;
							$this->transitions[$tzid][$ts]['dtstart'] = $dtstart;
						}
					}
				}

				if (!empty($entry_end_offset)) {
					$entry_end->setTimestamp($entry_end->getTimestamp() - $entry_end_offset);
				}

				$prev_rules = $entry['rules'];
			}

			// Ensure the transitions are in the correct chronological order
			ksort($this->transitions[$tzid]);

			// Work around a data error in versions 2021b - 2022c of the TZDB.
			if ($tzid === 'Africa/Freetown') {
				$last_transition = end($this->transitions[$tzid]);

				if ($last_transition['time'] === '1941-12-07T01:00:00+0000' && $last_transition['abbr'] === '+01') {
					$this->transitions[$tzid][$last_transition['ts']] = array_merge(
						$last_transition,
						[
							'offset' => 0,
							'isdst' => false,
							'abbr' => 'GMT',
						],
					);
				}
			}

			// Use numeric keys.
			$this->transitions[$tzid] = array_values($this->transitions[$tzid]);

			// Give the final entry an 'until_utc' date.
			$zone['entries'][$entry_num]['until_utc'] = self::DATE_MAX;
		}
	}

	/**
	 * Identifies time zones that might work as fallbacks for a given tzid.
	 *
	 * @param array $new_tzid A time zone identifier
	 * @return array A subset of $this->zones that might work as fallbacks for $new_tzid
	 */
	private function buildPossibleFallbackZones($new_tzid): array
	{
		$new_zone = $this->zones[$new_tzid];

		// Build a list of possible fallback zones to check for this zone.
		$possible_fallback_zones = $this->zones;

		// Filter and sort $possible_fallback_zones.
		// We do this for performance purposes, because we are more likely to find
		// a suitable fallback nearby than far away.
		foreach ($possible_fallback_zones as $tzid => $record) {
			// Obviously the new ones can't be fallbacks. That's the whole point of
			// this exercise, after all.
			if (!empty($record['new'])) {
				unset($possible_fallback_zones[$tzid]);
				continue;
			}

			// Obviously won't work if it's on the other side of the planet.
			$possible_fallback_zones[$tzid]['distance'] = $this->getDistanceFrom($possible_fallback_zones[$tzid], $this->zones[$new_tzid]);

			if ($possible_fallback_zones[$tzid]['distance'] > 6 * 15) {
				unset($possible_fallback_zones[$tzid]);
				continue;
			}
		}

		// Rank the possible fallbacks so that the (probably) best one is first.
		// A human should still check our suggestion, though.
		uasort(
			$possible_fallback_zones,
			function ($a, $b) use ($new_zone) {
				$cc = $new_zone['country_code'];

				if (!isset($a['country_code'])) {
					$a['country_code'] = 'ZZ';
				}

				if (!isset($b['country_code'])) {
					$b['country_code'] = 'ZZ';
				}

				// Prefer zones in the same country.
				if ($a['country_code'] === $cc && $b['country_code'] !== $cc) {
					return -1;
				}

				if ($a['country_code'] !== $cc && $b['country_code'] === $cc) {
					return 1;
				}

				// Legacy zones make good fallbacks, because they are rarely used.
				if ($a['country_code'] === 'ZZ' && $b['country_code'] !== 'ZZ') {
					return -1;
				}

				if ($a['country_code'] !== 'ZZ' && $b['country_code'] === 'ZZ') {
					return 1;
				}

				if (!str_contains($a['tzid'], '/') && str_contains($b['tzid'], '/')) {
					return -1;
				}

				if (str_contains($a['tzid'], '/') && !str_contains($b['tzid'], '/')) {
					return 1;
				}

				// Prefer links over canonical zones.
				if (isset($a['canonical']) && !isset($b['canonical'])) {
					return -1;
				}

				if (!isset($a['canonical']) && isset($b['canonical'])) {
					return 1;
				}

				// Prefer nearby zones over distant zones.
				if ($a['distance'] > $b['distance']) {
					return 1;
				}

				if ($a['distance'] < $b['distance']) {
					return -1;
				}

				// This is unlikely, but as a last resort use alphabetical sorting.
				return $a['tzid'] > $b['tzid'] ? 1 : -1;
			},
		);

		// Obviously, a time zone can't fall back to itself.
		unset($possible_fallback_zones[$new_tzid]);

		return $possible_fallback_zones;
	}

	/**
	 * Gets rule-based transitions for a time zone entry.
	 *
	 * @param string $rule_name The name of a time zone rule.
	 * @param array $unadjusted_date_strings Dates for $entry_start and $entry_end.
	 * @param int $std_offset The standard time offset for this time zone entry.
	 * @param string $prev_save The daylight saving value that applied just before $entry_start.
	 * @return array Transition rules.
	 */
	private function getApplicableRuleTransitions(string $rule_name, array $unadjusted_date_strings, int $std_offset, string $prev_save): array
	{
		static $rule_transitions = [];

		$utc = new \DateTimeZone('UTC');
		$date_max = new \DateTime(self::DATE_MAX);

		if (!isset($rule_transitions[$rule_name])) {
			$rules = $this->getRules();

			foreach ($rules[$rule_name] as $rule_num => $rule) {
				preg_match('/(\d+(?::\d+)*)([wsugz]|)$/', $rule['at'], $matches);
				$rule['at'] = $matches[1];
				$rule['at_suffix'] = $matches[2];

				$year_from = $rule['from'];

				if ($rule['to'] === 'max') {
					$year_to = $date_max->format('Y');
				} elseif ($rule['to'] === 'only') {
					$year_to = $year_from;
				} else {
					$year_to = $rule['to'];
				}

				for ($year = $year_from; $year <= $year_to; $year++) {
					$transition_date_string = $this->rewriteDateString(
						implode(' ', [
							$year,
							$rule['in'],
							$rule['on'],
							$rule['at'] . (!str_contains($rule['at'], ':') ? ':00' : ''),
						]),
					);

					$transition_date = new \DateTime($transition_date_string, $utc);

					$rule_transitions[$rule_name][$transition_date->format('Y-m-d\TH:i:s')] = [
						'letter' => $rule['letter'],
						'save' => $rule['save'],
						'at_suffix' => $rule['at_suffix'],
						'unadjusted_date_string' => $transition_date->format('Y-m-d\TH:i:s'),
						'dtstart' => $year_to > $year_from ? $this->buildRecurrenceRuleStart($rule) : null,
						'rrule' => $year_to > $year_from ? $this->buildRecurrenceRule($rule) : null,
						'until_local' => $year_to > $year_from ? $this->buildRecurrenceRuleUntil($rule) : null,
					];
				}
			}

			$temp = [];

			foreach ($rule_transitions[$rule_name] as $date_string => $info) {
				if (!empty($info['at_suffix']) && $info['at_suffix'] !== 'w') {
					$temp[$date_string] = $info;
					$prev_save = $info['save'];
					continue;
				}

				$transition_date = new \DateTime($date_string, $utc);

				$save_parts = array_map('intval', explode(':', $prev_save));
				$save_parts = array_pad($save_parts, 3, 0);
				$save_offset = abs($save_parts[0]) * 3600 + $save_parts[1] * 60 + $save_parts[2];

				if (str_starts_with($prev_save, '-')) {
					$save_offset *= -1;
				}

				$temp[$transition_date->format('Y-m-d\TH:i:s')] = $info;
				$prev_save = $info['save'];
			}
			$rule_transitions[$rule_name] = $temp;

			ksort($rule_transitions[$rule_name]);
		}

		$applicable_transitions = [];

		foreach ($rule_transitions[$rule_name] as $date_string => $info) {
			// After end of entry, so discard it.
			if ($date_string > $unadjusted_date_strings['entry_end']) {
				continue;
			}

			// Keep exactly one that precedes the start of the entry,
			// so that we can know the state at the start of the entry.
			if ($date_string < $unadjusted_date_strings['entry_start']) {
				array_shift($applicable_transitions);
			}

			$applicable_transitions[$date_string] = $info;
		}

		return $applicable_transitions;
	}

	/**
	 * Compiles all the daylight saving rules in the TZDB.
	 *
	 * @return array Compiled rules, indexed by rule name.
	 */
	private function getRules(): array
	{
		static $rules = [];

		if (!empty($rules)) {
			return $rules;
		}

		$filenames = [
			'africa',
			'antarctica',
			'asia',
			'australasia',
			'etcetera',
			'europe',
			'northamerica',
			'southamerica',
			'backward',
			'backzone',
		];

		// Populate $rules with TZDB data.
		foreach ($filenames as $filename) {
			$tzid = '';

			foreach (explode("\n", $this->fetchTzdbFile($filename, $this->curr_commit)) as $line_num => $line) {
				$line = rtrim(substr($line, 0, strcspn($line, '#')));

				if ($line === '') {
					continue;
				}

				if (str_starts_with($line, 'Rule')) {
					if (str_contains($line, '"')) {
						preg_match_all('/"[^"]*"/', $line, $matches);

						$patterns = [];
						$replacements = [];

						foreach ($matches[0] as $key => $value) {
							$patterns[$key] = '/' . preg_quote($value, '/') . '/';
							$replacements[$key] = md5($value);
						}

						$line = preg_replace($patterns, $replacements, $line);

						$parts = preg_split('/\h+/', $line);

						foreach ($parts as &$part) {
							$r_keys = array_keys($replacements, $part);

							if (!empty($r_keys)) {
								$part = $matches[0][$r_keys[0]];
							}
						}
					} else {
						$parts = preg_split('/\h+/', $line);
					}

					$parts = array_combine(['rule', 'name', 'from', 'to', 'type', 'in', 'on', 'at', 'save', 'letter'], $parts);

					$parts['file'] = $filename;

					// These are useless.
					unset($parts['rule'], $parts['type']);

					$rules[$parts['name']][] = $parts;
				}
			}
		}

		return $rules;
	}

	/**
	 * Calculates the distance between the locations of two time zones.
	 *
	 * This somewhat simplistically treats locations on opposite sides of the
	 * antimeridian as maximally distant from each other. But since the antimeridian
	 * is approximately the track of the International Date Line, and locations on
	 * opposite sides of the IDL can't be fallbacks for each other, it's sufficient.
	 * In the unlikely edge case that we ever need to find a fallback for, say,
	 * a newly created time zone for an island in Kiribati, the worst that could
	 * happen is that we might overlook some better option and therefore end up
	 * suggesting a generic Etc/* time zone as a fallback.
	 *
	 * @param array $this_zone One element from the $this->zones array.
	 * @param array $from_zone Another element from the $this->zones array.
	 * @return float The distance (in degrees) between the two locations.
	 */
	private function getDistanceFrom($this_zone, $from_zone): float
	{
		foreach (['latitude', 'longitude'] as $varname) {
			if (!isset($this_zone[$varname])) {
				echo $this_zone['tzid'], " has no {$varname}.\n";

				return 0;
			}
		}

		$lat_diff = abs($this_zone['latitude'] - $from_zone['latitude']);
		$lng_diff = abs($this_zone['longitude'] - $from_zone['longitude']);

		return sqrt($lat_diff ** 2 + $lng_diff ** 2);
	}

	/**
	 * Rewrites date strings from TZDB format to a PHP-parseable format.
	 *
	 * @param string $date_string A date string in TZDB format.
	 * @return string A date string that can be parsed by strtotime()
	 */
	private function rewriteDateString(string $date_string): string
	{
		$month = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sept?(?:ember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
		$weekday = 'Sun|Mon|Tue|Wed|Thu|Fri|Sat';

		$replacements = [
			'/^\h*(\d{4})\h*$/' => '$1-01-01',

			"/(\d{4})\h+({$month})\h+last({$weekday})/" => 'last $3 of $2 $1,',

			"/(\d{4})\h+({$month})\h+({$weekday})>=(\d+)/" => '$2 $4 $1 this $3,',

			"/(\d{4})\h+({$month})\h*$/" => '$2 $1',

			"/(\d{4})\h+({$month})\h+(\d+)/" => '$2 $3 $1,',
		];

		if (str_contains($date_string, '<=')) {
			$date_string = preg_replace_callback(
				"/(\d{4})\h+({$month})\h+({$weekday})<=(\d+)/",
				function ($matches) {
					$d = new \DateTime($matches[2] . ' ' . $matches[4] . ' ' . $matches[1]);
					$d->add(new \DateInterval('P1D'));

					return $d->format('M j Y') . ' previous ' . $matches[3];
				},
				$date_string,
			);
		} else {
			$date_string = preg_replace(array_keys($replacements), $replacements, $date_string);
		}

		$date_string = rtrim($date_string, ', ');

		// Some rules use '24:00' or even '25:00'
		if (preg_match('/\b(\d+)((?::\d+)+)\b/', $date_string, $matches)) {
			if ($matches[1] > 23) {
				$d = new \DateTime(str_replace($matches[0], ($matches[1] % 24) . $matches[2], $date_string));
				$d->add(new \DateInterval('PT' . ($matches[1] - ($matches[1] % 24)) . 'H'));
				$date_string = $d->format('M j Y, G:i:s');
			}
		}

		return $date_string;
	}

	/**
	 * Generates PHP code to insert into TimeZone::$fallbacks for renamed tzids.
	 *
	 * @param array $renamed_tzids Key-value pairs of renamed tzids.
	 * @return string PHP code to insert into TimeZone::$fallbacks
	 */
	private function generateRenameFallbackCode(array $renamed_tzids): string
	{
		$generated = [];

		foreach ($renamed_tzids as $old_tzid => $new_tzid) {
			$generated[$new_tzid] = [['ts' => 'PHP_INT_MIN', 'tzid' => $old_tzid]];
		}

		return preg_replace(
			[
				'~\b\d+ =>\s+~',
				"~'PHP_INT_MIN'~",
				'~^~m',
				'~^\s+\[\n~',
				'~\s+\]$~',
			],
			[
				'',
				'PHP_INT_MIN',
				"\t",
				'',
				'',
			],
			Config::varExport($generated) . "\n",
		);
	}

	/**
	 * Generates PHP code to insert into TimeZone::$fallbacks for new tzids.
	 * Uses the fallback data created by $this->buildFallbacks() to do so.
	 *
	 * @param array $fallbacks Fallback info for tzids.
	 * @return string PHP code to insert into TimeZone::$fallbacks
	 */
	private function generateFullFallbackCode(array $fallbacks): string
	{
		$generated = '';

		foreach ($fallbacks as $tzid => &$entries) {
			foreach ($entries as &$entry) {
				if (!empty($entry['options'])) {
					$entry = [
						'ts' => $entry['ts'],
						'// OPTIONS: ' . implode(', ', $entry['options']),
						'tzid' => $entry['tzid'],
					];
				}

				unset($entry['options']);
			}

			$generated .= preg_replace(
				[
					'~\b\d+ =>\s+~',
					"~'PHP_INT_MIN'~",
					"~'(// OPTIONS: [^'\\n]*)',~",
					'~^~m',
					'~^\s+\[\n~',
					'~\s+\]$~',
				],
				[
					'',
					'PHP_INT_MIN',
					'$1',
					"\t",
					'',
					'',
				],
				Config::varExport([$tzid => $entries]) . "\n",
			);
		}

		return $generated;
	}

	/**
	 * Builds the SMF\Calendar\VTimeZone class and sub-classes.
	 */
	private function buildVTimeZoneClasses(): void
	{
		if (!file_exists(Config::$sourcedir . '/Calendar/VTimeZones')) {
			mkdir(Config::$sourcedir . '/Calendar/VTimeZones');
		}

		$this->buildZones();
		$this->buildMetaZones();

		// Build the individual VTimeZone classes.
		$max_date = new \DateTimeImmutable(self::DATE_MAX);

		uasort(
			$this->zones,
			fn($a, $b) => isset($a['canonical']) <=> isset($b['canonical']) ?: $a['tzid'] <=> $b['tzid'],
		);

		foreach ($this->zones as $tzid => $zone) {
			unset($use, $extends, $metazones, $components);

			// Avoid unnecessary duplication in linked time zones.
			if (isset($zone['canonical'])) {
				$tzid_parts = explode('/', $tzid);
				$canonical_parts = explode('/', $zone['canonical']);

				if (\count($tzid_parts) === 1) {
					$use = '';
					$extends = implode('\\', $canonical_parts);
				} elseif (
					\array_slice($tzid_parts, 0, -1) === \array_slice($canonical_parts, 0, -1)
				) {
					$use = '';
					$extends = end($canonical_parts);
				} else {
					$use = "\n\n" . 'use SMF\\Calendar\\VTimeZones\\' . reset($canonical_parts) . ';';
					$extends = implode('\\', $canonical_parts);
				}
			} else {
				$use = "\n\n" . 'use SMF\\Calendar\\VTimeZone;';
				$extends = 'VTimeZone';

				// Build the component data.
				$components = [];
				$untils = [];
				$prev_transition_num = -1;

				foreach ($this->transitions[$tzid] as $transition_num => $transition) {
					$prev_transition = $this->transitions[$tzid][$prev_transition_num] ?? $transition;
					$prev_transition_num = $transition_num;

					// Skip entries for Local Mean Time.
					if ($transition['offset'] % 900 !== 0 && empty($components)) {
						continue;
					}

					$type = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';

					// Manually apply the offset in order to get a DateTime that
					// will output a string AS IF it were in the local time zone,
					// but without actually changing the time zone. We do this in
					// order to avoid relying on PHP's internal TZDB, which might
					// be out of date.
					$local_start = (new \DateTime($transition['time']))->modify($prev_transition['offset'] . ' seconds')->format('Ymd\THis');

					$dtstart = $transition['dtstart'] ?? $local_start;

					$tzoffsetfrom = implode('', [
						// Hours.
						\sprintf('%+03d', (int) ($prev_transition['offset'] < 0 ? ceil($prev_transition['offset'] / 3600) : floor($prev_transition['offset'] / 3600))),
						// Minutes.
						\sprintf('%02d', (int) abs($prev_transition['offset'] / 60) % 60),
						// Seconds.
						abs($prev_transition['offset']) % 60 !== 0 ? \sprintf('%02d', (int) abs($prev_transition['offset']) % 60) : '',
					]);

					$tzoffsetto = implode('', [
						// Hours.
						\sprintf('%+03d', (int) ($transition['offset'] < 0 ? ceil($transition['offset'] / 3600) : floor($transition['offset'] / 3600))),
						// Minutes.
						\sprintf('%02d', (int) abs($transition['offset'] / 60) % 60),
						// Seconds.
						abs($transition['offset']) % 60 !== 0 ? \sprintf('%02d', (int) abs($transition['offset']) % 60) : '',
					]);

					if (!is_numeric($transition['abbr'])) {
						$tzname = $transition['abbr'];
					}
					// Offsets from UTC are propertly written like 'UTC-07' or
					// 'UTC+1030'. In contrast, 'GMT' is merely the name of a
					// time zone with a UTC offset of zero. So 'GMT' can be used
					// as the TZNAME for UTC+00, but for everything else the
					// correct notation is the UTC offset. This is all the more
					// true since the signs are flipped in time zone names like
					// 'Etc/GMT-5', whose offset is actually UTC+05.
					elseif ((int) $tzoffsetto === 0 && substr($tzoffsetto, 0, 1) === '+') {
						$tzname = 'GMT';
					} else {
						$tzname = 'UTC' . $tzoffsetto;

						while (
							\strlen($tzname) > 6
							&& str_ends_with($tzname, '00')
						) {
							$tzname = substr($tzname, 0, -2);
						}
					}

					$rrule = $transition['rrule'] ?? null;

					// If the RRULE has no UNTIL value, but the entry_end is not
					// our maximum date, that means the RRULE was built from a TZDB
					// *rule* that had no ending, but the *entry* does have a date
					// when it stopped using that rule. This means that, from the
					// entry's perspective, there *is* an until date even though
					// the rule itself doesn't give one.
					if (
						isset($rrule)
						&& !str_contains($rrule, ';UNTIL=')
						&& $transition['entry_end'] < $max_date
					) {
						if (isset($untils[$rrule][$transition['entry_end']->format('Ymd\THisO')])) {
							$until = $untils[$rrule][$transition['entry_end']->format('Ymd\THisO')];
						} else {
							// The entry_end date is exclusive (i.e., it indicates
							// when the rule no longer applies). But the UNTIL value
							// of an RRULE is inclusive (i.e., it indicates when the
							// last occurrence happens). Thus, we need to find the
							// last occurrence prior to the entry_end date.
							$recurrence_iterator = new RecurrenceIterator(
								rrule: new RRule($rrule),
								dtstart: new \DateTime($local_start),
								view: (new \DateTime($local_start))->diff($transition['entry_end']),
								type: RecurrenceIterator::TYPE_FLOATING,
							);

							$recurrence_iterator->end();

							while (
								$recurrence_iterator->valid()
								&& $recurrence_iterator->current() > $transition['entry_end']
							) {
								$recurrence_iterator->prev();
							}

							if ($recurrence_iterator->valid()) {
								$until = $recurrence_iterator->current();
							} else {
								// This shouldn't happen, but just in case...
								$recurrence_iterator->rewind();
								$until = $recurrence_iterator->current();
							}

							// To UTC.
							$sign = substr($tzoffsetfrom, 0, strspn($tzoffsetfrom, '+-'));
							$offset = $sign . implode(':', str_split(substr($tzoffsetfrom, \strlen($sign)), 2));
							$until->sub($this->offsetToDateInterval($offset));

							$untils[$rrule][$transition['entry_end']->format('Ymd\THisO')] = $until;
						}

						$rrule .= ';UNTIL=' . $until->format('Ymd\THis\Z');
					}

					$component = array_filter(
						[
							'type' => $type,
							'DTSTART' => $dtstart,
							'RRULE' => $rrule,
							'TZNAME' => $tzname,
							'TZOFFSETFROM' => $tzoffsetfrom,
							'TZOFFSETTO' => $tzoffsetto,
						],
						fn($v) => $v !== null,
					);

					$components[md5(Config::varExport($component))] = $component;
				}

				// Filter out some weird ones.
				$std_key = null;
				$dst_key = null;

				foreach (array_reverse($components) as $key => $component) {
					if ($component['type'] === 'DAYLIGHT') {
						$type_key = &$dst_key;
					} else {
						$type_key = &$std_key;
					}

					if (!isset($type_key)) {
						$type_key = $key;
						continue;
					}

					// When a location changed its time zone (e.g. from Central to
					// Eastern), that can leave artifacts in the transitions that we
					// don't want to retain in the VTimeZone data.
					if (
						$component['TZOFFSETFROM'] === $component['TZOFFSETTO']
						&& isset($component['RRULE'], $components[$type_key]['RRULE'])
						&& $component['RRULE'] === $components[$type_key]['RRULE']
						&& $component['TZNAME'] === $components[$type_key]['TZNAME']
						&& $component['DTSTART'] === $components[$type_key]['DTSTART']
					) {
						unset($components[$key]);
						continue;
					}

					$type_key = $key;
				}

				$components = array_values($components);
			}

			$metazones = $this->metazones['usage'][$tzid] ?? [];

			// Write the file.
			if (!file_exists(\dirname(Config::$sourcedir . '/Calendar/VTimeZones/' . $tzid))) {
				mkdir(\dirname(Config::$sourcedir . '/Calendar/VTimeZones/' . $tzid));
			}

			$class_name = strtr($tzid, ['+' => '', '-' => '_']);

			$old_hash = file_exists(Config::$sourcedir . '/Calendar/VTimeZones/' . $class_name . '.php') ? sha1_file(Config::$sourcedir . '/Calendar/VTimeZones/' . $class_name . '.php') : null;

			$properties = [
				'section_comment' => implode("\n\t", [
					'/*******************',
					' * Public properties',
					' *******************/',
				]),
				'tzid' => implode("\n\t", [
					'',
					'/**',
					' * @var string',
					' *',
					' * Time zone identifier.',
					' */',
					'public string $tzid = ' . Config::varExport($tzid) . ';',
				]),
			];

			if (!empty($metazones)) {
				$properties['metazones'] = implode("\n\t", [
					'',
					'/**',
					' * @var array',
					' *',
					' * Data about which metazone label to use for this time zone at any given',
					' * date and time.',
					' *',
					' * Developers: Do not update the data in this array manually. Instead,',
					' * run "php -f other/update_timezones.php" on the command line.',
					' */',
					'public array $metazones = ' . preg_replace('/^(?!\[)/m', "\t", Config::varExport($metazones)) . ';',
				]);
			}

			if (!empty($components)) {
				$properties['components'] = implode("\n\t", [
					'',
					'/**',
					' * @var array',
					' *',
					' * Data for the VTIMEZONE components.',
					' *',
					' * Developers: Do not update the data in this array manually. Instead,',
					' * run "php -f other/update_timezones.php" on the command line.',
					' */',
					'public array $components = ' . preg_replace('/^(?!\[)/m', "\t", Config::varExport($components)) . ';',
				]);
			}

			file_put_contents(
				Config::$sourcedir . '/Calendar/VTimeZones/' . $class_name . '.php',
				preg_replace('/^\h+$/m', '', implode("\n", [
					'<' . '?php',
					'',
					'/**',
					' * Simple Machines Forum (SMF)',
					' *',
					' * @package SMF',
					' * @author Simple Machines https://www.simplemachines.org',
					' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors',
					' * @license https://www.simplemachines.org/about/smf/license.php BSD',
					' *',
					' * @version ' . SMF_VERSION,
					' */',
					'',
					'declare(strict_types=1);',
					'',
					'namespace ' . str_replace('/', '\\', rtrim('SMF\\Calendar\\VTimeZones\\' . \dirname($tzid), '.\\')) . ';' . $use,
					'',
					'/**',
					' * ' . $tzid,
					' */',
					'class ' . basename($class_name) . ' extends ' . $extends,
					'{',
					"\t" . implode("\n\t", $properties),
					'}',
					'',
				])),
			);

			$new_hash = sha1_file(Config::$sourcedir . '/Calendar/VTimeZones/' . $class_name . '.php');

			if ($old_hash !== $new_hash) {
				$this->files_updated = true;
			}
		}
	}

	/**
	 * Builds an iCalendar recurrence rule based on TZDB rule data.
	 *
	 * @param array $rule One line from a TZDB rule.
	 * @return string An iCalendar recurrence rule.
	 */
	private function buildRecurrenceRule(array $rule): string
	{
		// 2001 was not a leap year, so it will give us typical values.
		$in_month = new \DateTime('2001-' . $rule['in'] . '-01');

		$rrule = [
			'FREQ' => 'YEARLY',
			'BYMONTH' => [(int) $in_month->format('m')],
		];

		// Figure out the values for the RRULE and DTSTART.
		if (str_contains($rule['on'], '>=') || str_contains($rule['on'], '<=')) {
			if (str_contains($rule['on'], '>=')) {
				list($day_name, $month_day) = explode('>=', $rule['on']);
				$byday_max_month_day = 22;
				$byday_remainder = 1;
				$bymonthday_limit = (int) $in_month->format('t') - 6;
				$bymonthday_increment = 1;
			} else {
				list($day_name, $month_day) = explode('<=', $rule['on']);
				$byday_max_month_day = (int) $in_month->format('t');
				$byday_remainder = 0;
				$bymonthday_limit = 7;
				$bymonthday_increment = -1;
			}

			if ($month_day <= $byday_max_month_day && $month_day % 7 === $byday_remainder) {
				$rrule['BYDAY'] = [(($month_day - ($month_day % 7)) / 7 + $byday_remainder) . strtoupper(substr($day_name, 0, 2))];
			} else {
				$rrule['BYDAY'] = [strtoupper(substr($day_name, 0, 2))];

				if (($month_day <=> $bymonthday_limit) !== $bymonthday_increment) {
					for ($i = 0; $i < 7; $i++) {
						$rrule['BYMONTHDAY'][] = $month_day + ($i * $bymonthday_increment);
					}

					sort($rrule['BYMONTHDAY']);
				} else {
					unset($rrule['BYMONTH']);

					$rrule['BYYEARDAY'] = [];

					$d = new \DateTime($rule['from'] . '-' . $rule['in'] . '-' . \sprintf('%02d', $month_day));

					for ($i = 0; $i < 7; $i++) {
						// In normal years, day 60 and day -306 are both Mar 1, but in
						// leap years Feb 29 is day 60 while day -306 is Mar 1. It is
						// safe to list both 60 and -306 in normal years (the duplicate
						// will be silently ignored), but it is important to give both
						// for the sake of leap years.
						$comp_day_60 = ((int) $d->format('z') + 1 <=> 60);

						// Before day 61.
						if ($comp_day_60 < 1) {
							$rrule['BYYEARDAY'][] = (int) $d->format('z') + 1;
						}

						// After day 59.
						if ($comp_day_60 > -1) {
							$rrule['BYYEARDAY'][] = (int) $d->format('z') - 365;
						}

						$d->modify(\sprintf('%+d day', $bymonthday_increment));
					}

					usort(
						$rrule['BYYEARDAY'],
						fn($a, $b) => ($a < 0) === ($b < 0) ? $a <=> $b : $b <=> $a,
					);

					$rrule['BYSETPOS'] = [1];
				}
			}
		} elseif (str_starts_with($rule['on'], 'last')) {
			$rrule['BYDAY'] = '-1' . strtoupper(substr($rule['on'], 4, 2));
		} else {
			$rrule['BYMONTHDAY'][] = (int) $rule['on'];
		}

		// Finalize the RRULE.
		foreach ($rrule as $part => $value) {
			$rrule[$part] = $part . '=' . implode(',', (array) $value);
		}

		return implode(';', $rrule);
	}

	/**
	 * Returns the start date (in local time) for an iCalendar recurrence rule
	 * based on TZDB rule data.
	 *
	 * @param array $rule One line from a TZDB rule.
	 * @return string A date string in iCalendar format ('Ymd\THis').
	 */
	private function buildRecurrenceRuleStart(array $rule): string
	{
		// Figure out the date component.
		if (str_contains($rule['on'], '>=') || str_contains($rule['on'], '<=')) {
			if (str_contains($rule['on'], '>=')) {
				list($day_name, $month_day) = explode('>=', $rule['on']);
				$bymonthday_increment = 1;
			} else {
				list($day_name, $month_day) = explode('<=', $rule['on']);
				$bymonthday_increment = -1;
			}

			$dtstart = new \DateTime($rule['from'] . '-' . $rule['in'] . '-' . \sprintf('%02d', $month_day));

			while (strtoupper(substr($day_name, 0, 2)) !== strtoupper(substr($dtstart->format('D'), 0, 2))) {
				$dtstart->modify(\sprintf('%+d day', $bymonthday_increment));
			}
		} elseif (str_starts_with($rule['on'], 'last')) {
			$dtstart = new \DateTime($rule['from'] . '-' . $rule['in'] . '-01');
			$dtstart->modify('+1 month');

			do {
				$dtstart->modify('-1 day');
			} while (strtoupper(substr($rule['on'], 4, 2)) !== strtoupper(substr($dtstart->format('D'), 0, 2)));
		} else {
			$dtstart = new \DateTime($rule['from'] . '-' . $rule['in'] . '-' . \sprintf('%02d', (int) $rule['on']));
		}

		// Add the time component.
		if ($rule['at'] === '-') {
			$rule['at'] = '0';
		}

		$dtstart->add($this->offsetToDateInterval((string) $rule['at']));

		return $dtstart->format('Ymd\THis');
	}

	/**
	 * Returns the UNTIL date (in local time) for an iCalendar recurrence rule
	 * as calculated based on TZDB rule data.
	 *
	 * Note that the UNTIL date must actually be given in UTC in an RRULE for
	 * a time zone, so this will need to be adjusted before inclusion. However,
	 * we don't have sufficient data in the rule definition itself to make that
	 * adjustment, so it must be done afterward.
	 *
	 * @param array $rule One line from a TZDB rule.
	 * @return ?string A date string in iCalendar format ('Ymd\THis'), or null
	 *    if the rule doesn't have an expiry date.
	 */
	private function buildRecurrenceRuleUntil(array $rule): ?string
	{
		if ($rule['to'] === 'max') {
			return null;
		}

		if ($rule['to'] === 'only') {
			$rule['to'] = $rule['from'];
		}

		// Figure out the date component.
		if (str_contains($rule['on'], '>=') || str_contains($rule['on'], '<=')) {
			if (str_contains($rule['on'], '>=')) {
				list($day_name, $month_day) = explode('>=', $rule['on']);
				$bymonthday_increment = 1;
			} else {
				list($day_name, $month_day) = explode('<=', $rule['on']);
				$bymonthday_increment = -1;
			}

			$until = new \DateTime($rule['to'] . '-' . $rule['in'] . '-' . \sprintf('%02d', $month_day));

			while (strtoupper(substr($day_name, 0, 2)) !== strtoupper(substr($until->format('D'), 0, 2))) {
				$until->modify(\sprintf('%+d day', $bymonthday_increment));
			}
		} elseif (str_starts_with($rule['on'], 'last')) {
			$until = new \DateTime($rule['to'] . '-' . $rule['in'] . '-01');
			$until->modify('+1 month');

			do {
				$until->modify('-1 day');
			} while (strtoupper(substr($rule['on'], 4, 2)) !== strtoupper(substr($until->format('D'), 0, 2)));
		} else {
			$until = new \DateTime($rule['to'] . '-' . $rule['in'] . '-' . \sprintf('%02d', (int) $rule['on']));
		}

		// Add the time component.
		if ($rule['at'] === '-') {
			$rule['at'] = '0';
		}

		$until->add($this->offsetToDateInterval((string) $rule['at']));

		return $until->format('Ymd\THis');
	}

	/**
	 * Given an offset value such as '+3:00' or '-05:23:37', returns a
	 * \DateInterval that corresponds to the indicated amount of time.
	 *
	 * The offset value is always assumed to begin with hours, then minutes,
	 * then seconds, with missing values filled in by zeros. For example,
	 * passing '+3' will be interpreted as '+03:00:00'.
	 *
	 * @param string $offset The offset value as a string.
	 * @return \DateInterval for the indicated amount of time.
	 */
	private function offsetToDateInterval(string $offset): \DateInterval
	{
		$duration = 'PT';

		foreach (
			array_combine(
				['H', 'M', 'S'],
				array_pad(explode(':', $offset), 3, '00'),
			) as $unit => $value
		) {
			$duration .= abs((int) $value) . $unit;
		}

		$interval = new \DateInterval($duration);
		$interval->invert = (int) (substr($offset, 0, 1) === '-');

		return $interval;
	}
}
