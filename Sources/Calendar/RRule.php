<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Calendar;

/**
 * Represents a recurrence rule from RFC 5545.
 */
class RRule implements \Stringable
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var array
	 *
	 * Recurrence frequencies defined in RFC 5545.
	 */
	public const FREQUENCIES = [
		'YEARLY',
		'MONTHLY',
		'WEEKLY',
		'DAILY',
		'HOURLY',
		'MINUTELY',
		'SECONDLY',
	];

	/**
	 * @var array
	 *
	 * Weekday abbreviations defined in RFC 5545.
	 *
	 * The order of the elements matters. Do not change it.
	 */
	public const WEEKDAYS = [
		'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU',
	];

	/**
	 * @var array
	 *
	 * PHP time zone names that are synonymous with UTC.
	 */
	public const UTC_SYNONYMS = [
		'UTC',
		'GMT',
		'GMT+0',
		'GMT-0',
		'GMT0',
		'Greenwich',
		'UCT',
		'Universal',
		'Zulu',
		'Z',
		'Etc/GMT',
		'Etc/GMT+0',
		'Etc/GMT-0',
		'Etc/GMT0',
		'Etc/Greenwich',
		'Etc/UCT',
		'Etc/Universal',
		'Etc/UTC',
		'Etc/Zulu',
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The base frequency of this recurrence rule.
	 *
	 * Must be one of the values of self::FREQUENCIES.
	 */
	public string $freq;

	/**
	 * @var int
	 *
	 * The value of the recurrence rule's INTERVAL part.
	 *
	 * Defaults to 1 if the INTERVAL part is not present.
	 */
	public int $interval = 1;

	/**
	 * @var \DateTimeInterface
	 *
	 * When the recurrence ends.
	 */
	public \DateTimeInterface $until;

	/**
	 * @var int
	 *
	 * Whether UNTIL is a normal, floating, or all day event type.
	 *
	 * Normal means it has a date, a time, and a time zone.
	 * Floating means it has a date and a time, but no time zone.
	 * All day means it has a date, but neither a time nor a time zone.
	 *
	 * Value must be one of the RecurrenceIterator::TYPE_* constants
	 */
	public int $until_type;

	/**
	 * @var int
	 *
	 * The value of the recurrence rule's COUNT part, if present.
	 */
	public int $count;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYMONTH part, if present.
	 */
	public array $bymonth;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYWEEKNO part, if present.
	 */
	public array $byweekno;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYYEARDAY part, if present.
	 */
	public array $byyearday;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYMONTHDAY part, if present.
	 */
	public array $bymonthday;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYDAY part, if present.
	 */
	public array $byday;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYHOUR part, if present.
	 */
	public array $byhour;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYMINUTE part, if present.
	 */
	public array $byminute;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYSECOND part, if present.
	 */
	public array $bysecond;

	/**
	 * @var array
	 *
	 * Values extracted from the recurrence rule's BYSETPOS part, if present.
	 */
	public array $bysetpos;

	/**
	 * @var string
	 *
	 * The value of the recurrence rule's WKST part, if present.
	 */
	public string $wkst = 'MO';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $rrule An RRule string.
	 */
	public function __construct(string $rrule)
	{
		// Drop the leading 'RRULE:', if present.
		$rrule = preg_replace('/^RRULE:/i', '', $rrule);

		// The FREQ part is required.
		if (!str_contains($rrule, 'FREQ=')) {
			throw new \ValueError();
		}

		// Parse the RRule into an associative array.
		parse_str(strtr($rrule, ';', substr(ini_get('arg_separator.input'), 0, 1)), $rrule);

		// Set initial values of properties.
		foreach ($rrule as $prop => $value) {
			$prop = strtolower($prop);

			switch ($prop) {
				case 'freq':
					$value = strtoupper($value);

					if (!in_array($value, self::FREQUENCIES)) {
						continue 2;
					}

					break;

				case 'wkst':
					$value = strtoupper($value);

					if (!in_array($value, self::WEEKDAYS)) {
						continue 2;
					}

					break;

				case 'until':
					if (strpos($value, 'TZID') !== false) {
						foreach (explode(':', $value) as $value_part) {
							if (strpos($value_part, 'TZID') !== false) {
								$tzid = str_replace('TZID=', '', $value_part);

								if (in_array($tzid, self::UTC_SYNONYMS)) {
									$tzid = 'UTC';
								}

								try {
									$tz = new \DateTimeZone($tzid);
								} catch (\Throwable $e) {
									continue 3;
								}
							} else {
								$value = $value_part;
							}
						}

						$value = (new \DateTimeImmutable(substr($value, 0, -1), $tz))->setTimezone(new \DateTimeZone('UTC'));

						$this->until_type = RecurrenceIterator::TYPE_ABSOLUTE;
					} elseif (substr($value, -1) === 'Z') {
						$value = new \DateTimeImmutable(substr($value, 0, -1), new \DateTimeZone('UTC'));

						$this->until_type = RecurrenceIterator::TYPE_ABSOLUTE;
					} else {
						$this->until_type = strpos($value, 'T') !== false ? RecurrenceIterator::TYPE_FLOATING : RecurrenceIterator::TYPE_ALLDAY;

						$value = new \DateTime($value);
					}

					break;

				case 'count':
				case 'interval':
					$value = max(1, (int) $value);
					break;

				case 'bysecond':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						// 60 is allowed because of leap seconds.
						fn ($v) => $v >= 0 && $v <= 60,
					);
					sort($value);
					break;

				case 'byminute':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						fn ($v) => $v >= 0 && $v < 60,
					);
					sort($value);
					break;

				case 'byhour':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						fn ($v) => $v >= 0 && $v < 24,
					);
					sort($value);
					break;

				case 'byday':
					$value = array_filter(
						explode(',', $value),
						function ($v) {
							// Simple case.
							if (in_array($v, self::WEEKDAYS)) {
								return true;
							}

							// E.g: '-1TH' for 'last Thursday of the month'.
							return (bool) (preg_match('/^[+-]?\d+(SU|MO|TU|WE|TH|FR|SA)/', $v));
						},
					);
					break;

				case 'bymonthday':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						fn ($v) => $v >= -31 && $v <= 31 && $v !== 0,
					);
					usort(
						$value,
						function ($a, $b) {
							$a += $a < 0 ? 62 : 0;
							$b += $b < 0 ? 62 : 0;

							return $a <=> $b;
						},
					);
					break;

				case 'byyearday':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						// 366 allowed because of leap years.
						fn ($v) => $v >= -366 && $v <= 366 && $v !== 0,
					);
					break;

				case 'byweekno':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						fn ($v) => $v >= -53 && $v <= 53 && $v !== 0,
					);
					break;

				case 'bymonth':
					$value = array_filter(
						array_map('intval', explode(',', $value)),
						fn ($v) => $v >= 1 && $v <= 12,
					);
					break;

				case 'bysetpos':
					$value = array_map('intval', explode(',', $value));
					break;

				default:
					break;
			}

			if (property_exists($this, $prop)) {
				$this->{$prop} = $value;
			}
		}

		// Some rule parts are subject to interdependent conditions.

		// BYWEEKNO only applies when the frequency is YEARLY.
		if ($this->freq !== 'YEARLY') {
			unset($this->byweekno);
		}

		// BYYEARDAY never applies with these frequencies.
		if (in_array($this->freq, ['DAILY', 'WEEKLY', 'MONTHLY'])) {
			unset($this->byyearday);
		}

		// BYMONTHDAY never applies when the frequency is WEEKLY.
		if ($this->freq === 'WEEKLY') {
			unset($this->bymonthday);
		}

		// BYDAY can only have integer modifiers in certain cases.
		if (
			!empty($this->byday)
			&& (
				// Can't have integer modifiers with frequencies besides these.
				!in_array($this->freq, ['MONTHLY', 'YEARLY'])
				//  Can't have integer modifiers in combination with BYWEEKNO.
				|| isset($this->byweekno)
			)
		) {
			foreach ($this->byday as $value) {
				if (!in_array($value, self::WEEKDAYS)) {
					unset($this->byday);
					break;
				}
			}
		}

		// BYSETPOS can only be used in conjunction with another BY*** rule part.
		if (
			!isset($this->bysecond)
			&& !isset($this->byminute)
			&& !isset($this->byhour)
			&& !isset($this->byday)
			&& !isset($this->bymonthday)
			&& !isset($this->byyearday)
			&& !isset($this->byweekno)
			&& !isset($this->bymonth)
		) {
			unset($this->bysetpos);
		}
	}

	/**
	 * Allows this object to be handled like a string.
	 */
	public function __toString(): string
	{
		$rrule = [];

		$parts = [
			'FREQ',
			'INTERVAL',
			'UNTIL',
			'COUNT',
			'BYMONTH',
			'BYWEEKNO',
			'BYYEARDAY',
			'BYMONTHDAY',
			'BYDAY',
			'BYHOUR',
			'BYMINUTE',
			'BYSECOND',
			'BYSETPOS',
			'WKST',
		];

		foreach ($parts as $part) {
			unset($value);
			$prop = strtolower($part);

			if (!isset($this->{$prop})) {
				continue;
			}

			switch ($prop) {
				case 'freq':
					$value = strtoupper($this->{$prop});
					break;

				// Skip if default.
				case 'interval':
					$value = $this->{$prop} > 1 ? $this->{$prop} : null;
					break;

				// Skip if default or irrelevant.
				case 'wkst':
					$value = strtoupper($this->wkst);

					if (
						// Skip if default.
						$value === 'MO'
						// Skip if irrelevant.
						|| !in_array($this->freq, ['WEEKLY', 'YEARLY'])
						|| ($this->freq === 'WEEKLY' && empty($this->byday))
						|| ($this->freq === 'YEARLY' && empty($this->byweekno))
					) {
						$value = null;
					}
					break;

				case 'bysecond':
				case 'byminute':
				case 'byhour':
				case 'byday':
				case 'bymonthday':
				case 'byyearday':
				case 'byweekno':
				case 'bymonth':
				case 'bysetpos':
					$value = implode(',', $this->{$prop});
					break;

				// Force the time zone to UTC, just in case someone changed it.
				case 'until':
					if ($this->until_type === RecurrenceIterator::TYPE_ALLDAY) {
						$value = $this->until->setTimezone(new \DateTimeZone('UTC'))->format('Ymd');
					} elseif ($this->until_type === RecurrenceIterator::TYPE_FLOATING) {
						$value = $this->until->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis');
					} else {
						$value = $this->until->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
					}
					break;

				default:
					$value = (string) $this->{$prop};
					break;
			}

			if (isset($value) && strlen($value) > 0) {
				$rrule[] = $part . '=' . $value;
			}
		}

		return implode(';', $rrule);
	}
}

?>