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

/**
 * Represents a one-way diff used to record the edit history of forum posts,
 * the registration agreement, the privacy policy, etc.
 *
 * One-way diffs require less storage space than two-way diffs, but they can
 * only be used to transform $str1 into $str2 and not vice versa.
 *
 * @todo Add a column to the messages table to store the JSON. For each revision
 *       of a post, create an EditDiff using the new text for $str1, the old text
 *       for $str2, and the post's new modification time for $time1. Then use
 *       EditDiff::export() to get a diff of the revision and add it to the edit
 *       history list. During retrieval, the timestamp of each revision will be
 *       available in the first value of the exported JSON, and the crc32c hash
 *       of the string that it applies to will be the second value. The reason
 *       to include the crc32c is to detect and protect against cases where the
 *       text was changed by external processes (e.g. the admin using SQL to
 *       alter message content).
 * @todo Use the same approach to record the edit history of the privacy policy.
 */
class EditDiff extends Diff
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The ID of the member who made the edit.
	 */
	public int $id = 0;

	/**
	 * @var string
	 *
	 * The name of the member who made the edit.
	 */
	public string $name = '';

	/**
	 * @var string
	 *
	 * The reason for the edit, if any.
	 */
	public string $reason = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If $str1 and/or $str2 are not supplied, an instance with empty data will
	 * be created. This is useful when you plan to populate the diff data using
	 * $this->import().
	 *
	 * Differences from the parent constructor:
	 *
	 *  - Does not accept a $time2 parameter, because the $time2 property is
	 *    always set to an empty string.
	 *
	 *  - Does not accept a $label1 parameter, because the $label1 property is
	 *    always set to the crc32c hash of $str1.
	 *
	 *  - Does not accept a $label2 parameter, because the $label2 property is
	 *    always set to an empty string.
	 *
	 *  - Does not accept a $context parameter. Instead, an EditDiff always uses
	 *    a $context value of PHP_INT_MIN.
	 *
	 *  - Accepts $id, $name, and $reason params about who made the edit and why.
	 *
	 * @param ?string $str1 The original string.
	 * @param ?string $str2 The modified string.
	 * @param ?string $time1 Timestamp to use for the original string.
	 * @param int $id The ID of the person who made the edit
	 * @param string $name The name of the member who made the edit.
	 * @param string $reason The reason for the edit, if any.
	 */
	public function __construct(
		?string $str1 = null,
		?string $str2 = null,
		?string $time1 = null,
		int $id = 0,
		string $name = '',
		string $reason = '',
	) {
		if (isset($str1, $str2)) {
			// For EditDiffs, we don't want or need subsecond precision.
			if (is_numeric($time1)) {
				$time1 = strval(intval($time1));
			} else {
				$time1 = (string) $time1 !== '' && ($d = date_create((string) $time1)) !== false ? $d->format('U') : (string) $time1;
			}

			parent::__construct(
				$str1,
				$str2,
				$time1,
				null,
				hash('crc32c', $str1),
				'',
				PHP_INT_MIN,
			);

			// We only need the lengths of the deletions.
			foreach ($this->changes as $i => $change) {
				$this->changes[$i]['old'] = mb_strlen($this->changes[$i]['old']);
			}

			$this->id = $id;
			$this->name = $name;
			$this->reason = $reason;
		}
	}

	/**
	 *
	 */
	public function import(array $data): static
	{
		if (
			!is_array($data)
			|| count($data) < 5
			|| (!is_string($data[0]) && !is_int($data[0]) && !is_float($data[0]))
			|| !is_string($data[1])
			|| (!is_string($data[2]) && !is_int($data[2]) && !is_float($data[2]))
			|| !is_string($data[3])
			|| !is_array($data[4])
			|| !is_int($data[5])
			|| (isset($data[6]) && !is_string($data[6]))
			|| (isset($data[7]) && !is_string($data[7]))
		) {
			throw new \ValueError();
		}

		$this->time1 = (string) $data[0] !== '' && ($d = date_create((is_numeric($data[0]) ? '@' : '') . (string) $data[0])) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s O') : (string) $data[0];

		$this->label1 = $data[1];

		$this->time2 = (string) $data[2] !== '' && ($d = date_create((is_numeric($data[2]) ? '@' : '') . (string) $data[2])) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s O') : (string) $data[2];

		$this->label2 = $data[3];

		$this->id = $data[5] ?? 0;
		$this->name = $data[6] ?? '';
		$this->reason = $data[7] ?? '';

		foreach ($data[4] as $change) {
			if (
				!is_array($change)
				|| count($change) < 5
				|| !is_int($change[0])
				|| !is_int($change[1])
				|| !is_int($change[2])
				|| (!is_int($change[3]) && !is_string($change[3]))
				|| !is_string($change[4])
			) {
				throw new \ValueError();
			}

			// We only need the lengths of the deletions.
			if (is_string($change[3])) {
				$change[3] = mb_strlen($change[3]);
			}

			$this->changes[] = array_combine(
				['l1', 'l2', 'offset', 'old', 'new'],
				array_slice($change, 0, 5),
			);
		}

		return $this;
	}

	/**
	 *
	 */
	public function export(): array
	{
		$changes = $this->changes;

		foreach ($changes as &$change) {
			$change = array_values($change);
		}

		$ts1 = $this->time1 !== '' && ($d = date_create((is_numeric($this->time1) ? '@' : '') . $this->time1)) !== false ? (int) $d->format('U') : $this->time1;

		$ts2 = $this->time2 !== '' && ($d = date_create((is_numeric($this->time2) ? '@' : '') . $this->time2)) !== false ? (int) $d->format('U') : $this->time2;

		return [
			$ts1,
			$this->label1,
			$ts2,
			$this->label2,
			$changes,
			$this->id,
			$this->name,
			$this->reason,
		];
	}

	/**
	 *
	 */
	public function apply(string $str1): string
	{
		$lines = $this->splitLines($str1);

		$str2 = '';

		foreach (array_reverse($this->changes) as $change) {
			if (isset($lines[$change['l1']])) {
				$substring = implode('', array_splice($lines, $change['l1']));

				$str2 = mb_substr($substring, 0, $change['offset']) . $change['new'] . mb_substr($substring, $change['offset'] + $change['old']) . $str2;
			} else {
				$str2 = mb_substr($str2, 0, $change['offset']) . $change['new'] . mb_substr($str2, $change['offset'] + $change['old']);
			}
		}

		return implode('', $lines) . $str2;
	}
}
