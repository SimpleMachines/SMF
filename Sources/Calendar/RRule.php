<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Calendar;

use SMF\Config;
use SMF\Lang;
use SMF\Time;
use SMF\Utils;

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
					if (str_contains($value, 'TZID')) {
						foreach (explode(':', $value) as $value_part) {
							if (str_contains($value_part, 'TZID')) {
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
					} elseif (str_ends_with($value, 'Z')) {
						$value = new \DateTimeImmutable(substr($value, 0, -1), new \DateTimeZone('UTC'));

						$this->until_type = RecurrenceIterator::TYPE_ABSOLUTE;
					} else {
						$this->until_type = str_contains($value, 'T') ? RecurrenceIterator::TYPE_FLOATING : RecurrenceIterator::TYPE_ALLDAY;

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

	/**
	 * Builds a human-readable description of this RRule.
	 *
	 * @param EventOccurrence $occurrence The event occurrence that is currently
	 *    being viewed.
	 * @param ?bool $show_start Whether to show the start date in the description.
	 *    If not set, will be determined automatically.
	 */
	public function getDescription(EventOccurrence $occurrence, ?bool $show_start = null): string
	{
		if (($this->count ?? 0) === 1) {
			return '';
		}

		// Just in case...
		Lang::load('General+Calendar');

		if (!empty($this->bysetpos)) {
			$description = $this->getDescriptionBySetPos($occurrence->getParentEvent()->start);
		} else {
			$description = $this->getDescriptionNormal($occurrence->getParentEvent()->start);
		}

		// When the repetition ends.
		if (isset($this->until)) {
			if (
				!in_array($this->freq, ['HOURLY', 'MINUTELY', 'SECONDLY'])
				&& empty($this->byhour)
				&& empty($this->byminute)
				&& empty($this->bysecond)
			) {
				$until = Time::createFromInterface($this->until)->format(Time::getDateFormat());
			} else {
				$until = Time::createFromInterface($this->until)->format();
			}

			$description .= ' ' . Lang::getTxt('calendar_rrule_desc_until', ['date' => $until]);
		} elseif (!empty($this->count)) {
			$description .= ' ' . Lang::getTxt('calendar_rrule_desc_count', ['count' => $this->count]);
		}

		$description = Lang::getTxt(
			'calendar_rrule_desc',
			[
				'rrule_description' => $description,
				'start_date' => ($show_start ?? !$occurrence->is_first) ? '<a href="' . Config::$scripturl . '?action=calendar;event=' . $occurrence->id_event . '" class="bbc_link">' . Time::createFromInterface($occurrence->getParentEvent()->start)->format($occurrence->allday ? Time::getDateFormat() : null, false) . '</a>' : 'false',
			],
		);

		return Utils::normalizeSpaces(
			$description,
			true,
			true,
			[
				'no_breaks' => true,
				'collapse_hspace' => true,
			],
		);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the description without any BYSETPOS considerations.
	 *
	 * @param \DateTimeInterface $start
	 */
	protected function getDescriptionNormal(\DateTimeInterface $start): string
	{
		$description = [];

		// Basic frequency interval (e.g. "Every year", "Every 3 weeks", etc.)
		$description['frequency_interval'] = Lang::getTxt('calendar_rrule_desc_frequency_interval', ['freq' => $this->freq, 'interval' => $this->interval]);

		$this->getDescriptionByDay($start, $description);
		$this->getDescriptionByMonth($start, $description);
		$this->getDescriptionByWeekNo($start, $description);
		$this->getDescriptionByYearDay($start, $description);
		$this->getDescriptionByMonthDay($start, $description);
		$this->getDescriptionByTime($start, $description);

		return implode(' ', $description);
	}

	/**
	 * Gets the description with BYSETPOS considerations.
	 *
	 * @param \DateTimeInterface $start
	 */
	protected function getDescriptionBySetPos(\DateTimeInterface $start): string
	{
		$description = [];

		// BYSETPOS can theoretically be used with any RRule, but it only really
		// makes much sense to use it with BYDAY and BYMONTH (and that is the
		// only way it will ever be used in events created by SMF). For that
		// reason, it is really awkward to build a custom BYSETPOS description
		// for RRules that contain any BY* values besides BYDAY and BYMONTH.
		// So in the rare case that we are given such an RRule, we just use
		// 'the nth instance of "<normal description>"' and call it good enough.
		if (
			empty($byweekno)
			&& empty($byyearday)
			&& empty($bymonthday)
			&& empty($byhour)
			&& empty($byminute)
			&& empty($bysecond)
		) {
			if (!empty($this->byday)) {
				$days_long_or_short = count($this->byday) > 3 ? 'days_short' : 'days';

				$day_names = [];

				foreach ($this->byday as $day) {
					if (in_array($day, self::WEEKDAYS)) {
						$day_names[] = Lang::$txt[$days_long_or_short][(array_search($day, self::WEEKDAYS) + 1) % 7];
					} else {
						$desc_byday = 'calendar_rrule_desc_byday';

						list($num, $name) = preg_split('/(?=MO|TU|WE|TH|FR|SA|SU)/', $day);
						$num = empty($num) ? 1 : (int) $num;

						$nth = Lang::getTxt($num < 0 ? 'ordinal_spellout_last' : 'ordinal_spellout', [abs($num)]);
						$day_name = Lang::$txt[$days_long_or_short][(array_search($name, self::WEEKDAYS) + 1) % 7];

						$day_names[] = Lang::getTxt('calendar_rrule_desc_ordinal_day_name', ['ordinal' => $nth, 'day_name' => $day_name]);
					}
				}

				$description['byday'] = Lang::sentenceList($day_names, 'xor');
			}

			if (!empty($this->bymonth)) {
				if (($this->freq === 'MONTHLY' || $this->freq === 'YEARLY') && ($this->interval ?? 1) === 1) {
					unset($description['frequency_interval']);
				}

				$months_titles = [];

				foreach ($this->bymonth as $month_num) {
					$months_titles[] = Lang::$txt['month_titles'][$month_num];
				}

				$description['bymonth'] = Lang::getTxt('calendar_rrule_desc_bymonth', ['months_titles' => Lang::sentenceList($months_titles)]);
			}

			// Basic frequency interval (e.g. "Every year", "Every 3 weeks", etc.)
			$frequency_interval = Lang::getTxt('calendar_rrule_desc_frequency_interval', ['freq' => $this->freq, 'interval' => $this->interval]);
		} else {
			$description[] = '<q>' . $this->getDescriptionNormal($start) . '</q>';
		}

		$ordinals = array_map(fn ($n) => Lang::getTxt($n < 0 ? 'ordinal_spellout_last' : 'ordinal_spellout', [abs($n)]), $this->bysetpos);

		return (isset($frequency_interval) ? $frequency_interval . ' ' : '') . Lang::getTxt(
			'calendar_rrule_desc_bysetpos',
			[
				'ordinal_list' => Lang::sentenceList($ordinals),
				'count' => count($this->bysetpos),
				'rrule_description' => implode(' ', $description),
			],
		);
	}

	/**
	 * Day of week (e.g. "on Monday", "on Monday and Tuesday", "on the 3rd Monday")
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByDay(\DateTimeInterface $start, array &$description): void
	{
		if (empty($this->byday)) {
			return;
		}

		if (($this->freq === 'DAILY' || $this->freq === 'WEEKLY') && ($this->interval ?? 1) === 1) {
			unset($description['frequency_interval']);
		}

		$desc_byday = ($this->freq === 'YEARLY' && empty($this->byweekno)) || $this->freq === 'MONTHLY' ? 'calendar_rrule_desc_byday_every' : 'calendar_rrule_desc_byday';

		$days_long_or_short = count($this->byday) > 3 ? 'days_short' : 'days';

		$day_names = [];

		foreach ($this->byday as $day) {
			if (in_array($day, self::WEEKDAYS)) {
				$day_names[] = Lang::$txt[$days_long_or_short][(array_search($day, self::WEEKDAYS) + 1) % 7];
			} else {
				$desc_byday = 'calendar_rrule_desc_byday';

				list($num, $name) = preg_split('/(?=MO|TU|WE|TH|FR|SA|SU)/', $day);
				$num = empty($num) ? 1 : (int) $num;

				$nth = Lang::getTxt($num < 0 ? 'ordinal_spellout_last' : 'ordinal_spellout', [abs($num)]);
				$day_name = Lang::$txt[$days_long_or_short][(array_search($name, self::WEEKDAYS) + 1) % 7];

				$day_names[] = Lang::getTxt('calendar_rrule_desc_ordinal_day_name', ['ordinal' => $nth, 'day_name' => $day_name]);
			}
		}

		$description['byday'] = Lang::getTxt($desc_byday, ['day_names' => Lang::sentenceList($day_names)]);

		if (
			$desc_byday === 'calendar_rrule_desc_byday_every'
			&& ($this->interval ?? 1) > 1
			&& (
				$this->freq === 'MONTHLY'
				|| (
					$this->freq === 'YEARLY'
					&& empty($this->bymonth)
					&& empty($this->byweekno)
					&& empty($this->byyearday)
					&& empty($this->bymonthday)
				)
			)
		) {
			$description['bymonth'] = Lang::getTxt(
				'calendar_rrule_desc_bygeneric',
				[
					'list' => Lang::getTxt(
						'calendar_rrule_desc_frequency_interval_ordinal',
						[
							'freq' => $this->freq,
							'interval' => $this->interval,
						],
					),
				],
			);

			unset($description['frequency_interval']);
		}
	}

	/**
	 * Months (e.g. "in January", "in March and April")
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByMonth(\DateTimeInterface $start, array &$description): void
	{
		if (empty($this->bymonth)) {
			return;
		}

		if (($this->freq === 'MONTHLY' || $this->freq === 'YEARLY') && ($this->interval ?? 1) === 1) {
			unset($description['frequency_interval']);
		}

		$months_titles = [];

		foreach ($this->bymonth as $month_num) {
			$months_titles[] = Lang::$txt['months_titles'][$month_num];
		}

		$description['bymonth'] = Lang::getTxt('calendar_rrule_desc_bymonth', ['months_titles' => Lang::sentenceList($months_titles)]);
	}

	/**
	 * Week number (e.g. "in the 3rd week of the year")
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByWeekNo(\DateTimeInterface $start, array &$description): void
	{
		if (empty($this->byweekno)) {
			return;
		}

		if ($this->freq === 'YEARLY' && ($this->interval ?? 1) === 1) {
			unset($description['frequency_interval']);
		}

		$ordinals = array_map(fn ($n) => Lang::getTxt('ordinal_spellout', [$n]), $this->byweekno);
		$description['byweekno'] = Lang::getTxt('calendar_rrule_desc_byweekno', ['ordinal_list' => Lang::sentenceList($ordinals), 'count' => count($ordinals)]);
	}

	/**
	 * Day of year (e.g. "on the 3rd day of the year")
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByYearDay(\DateTimeInterface $start, array &$description): void
	{
		if (empty($this->byyearday)) {
			return;
		}

		if ($this->freq === 'YEARLY' && ($this->interval ?? 1) === 1) {
			unset($description['frequency_interval']);
		}

		$ordinals = array_map(fn ($n) => Lang::getTxt('ordinal_spellout', [$n]), $this->byyearday);
		$description['byeyarday'] = Lang::getTxt('calendar_rrule_desc_byyearday', ['ordinal_list' => Lang::sentenceList($ordinals), 'count' => count($ordinals)]);
	}

	/**
	 * Day of month (e.g. "on the 3rd day of the month")
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByMonthDay(\DateTimeInterface $start, array &$description): void
	{
		if (empty($this->bymonthday)) {
			return;
		}

		if ($this->freq === 'MONTHLY' && ($this->interval ?? 1) === 1) {
			unset($description['frequency_interval']);
		}

		// Special cases for when we have both day names and month days.
		if (
			count($this->byday ?? []) >= 1
			&& array_intersect($this->byday, self::WEEKDAYS) === $this->byday
		) {
			$days_long_or_short = count($this->byday) > 3 ? 'days_short' : 'days';

			// "Friday the 13th"
			if (count($this->bymonthday) === 1 && count($this->byday) === 1) {
				$named_monthday = Lang::getTxt(
					'calendar_rrule_desc_named_monthday',
					[
						'day_name' => Lang::$txt[$days_long_or_short][(array_search($this->byday[0], self::WEEKDAYS) + 1) % 7],
						'ordinal_month_day' => Lang::getTxt('ordinal', $this->bymonthday),
						'cardinal_month_day' => $this->bymonthday[0],
					],
				);

				if ($this->freq === 'MONTHLY') {
					$description['frequency_interval'] = Lang::getTxt(
						'calendar_rrule_desc_frequency_interval',
						[
							'freq' => $named_monthday,
						],
					);

					unset($description['byday']);
				} else {
					$description['byday'] = Lang::getTxt(
						'calendar_rrule_desc_byday',
						[
							'day_names' => $named_monthday,
						],
					);
				}
			}
			// "the first Tuesday or Thursday that is the second, third, or fourth day of the month"
			else {
				foreach ($this->byday as $day_abbrev) {
					$day_names[] = Lang::$txt[$days_long_or_short][(array_search($day_abbrev, self::WEEKDAYS) + 1) % 7];
				}

				$ordinal_form = max(array_map('abs', $this->bymonthday)) < 10 && count($this->bymonthday) <= 3 ? 'ordinal_spellout' : 'ordinal';

				$ordinals = array_map(fn ($n) => Lang::getTxt($n < 0 ? $ordinal_form . '_last' : $ordinal_form, [abs($n)]), $this->bymonthday);

				$description['byday'] = Lang::getTxt(
					'calendar_rrule_desc_byday',
					[
						'day_names' => Lang::getTxt(
							'calendar_rrule_desc_named_monthdays',
							[
								'ordinal_list' => Lang::sentenceList($ordinals, 'or'),
								'day_name' => Lang::sentenceList($day_names, 'or'),
							],
						),
					],
				);
			}
		}
		// Normal case.
		else {
			$ordinals = array_map(fn ($n) => Lang::getTxt($n < 0 ? 'ordinal_spellout_last' : 'ordinal_spellout', [abs($n)]), $this->bymonthday);
			$description['bymonthday'] = Lang::getTxt('calendar_rrule_desc_bymonthday', ['ordinal_list' => Lang::sentenceList($ordinals), 'count' => count($ordinals)]);
		}
	}

	/**
	 * Hour, minute, and second.
	 *
	 * @param \DateTimeInterface $start
	 * @param array &$description
	 */
	protected function getDescriptionByTime(\DateTimeInterface $start, array &$description): void
	{
		// Hour, minute, and second.
		$time_format = Time::getTimeFormat();

		// Do we need to show seconds?
		if (!empty($this->bysecond) || $this->freq === 'SECONDLY') {
			if (Time::isStrftimeFormat($time_format)) {
				if (!str_contains($time_format, '%S')) {
					if (str_contains($time_format, '%M')) {
						$time_format = str_replace('%M', '%M:%S', $time_format);
					} else {
						if (str_contains($time_format, '%I')) {
							$time_format = str_replace('%I', '%I:%M:%S', $time_format);
						} elseif (str_contains($time_format, '%l')) {
							$time_format = str_replace('%l', '%l:%M:%S', $time_format);
						} elseif (str_contains($time_format, '%H')) {
							$time_format = str_replace('%H', '%H:%M:%S', $time_format);
						} elseif (str_contains($time_format, '%k')) {
							$time_format = str_replace('%k', '%k:%M:%S', $time_format);
						} else {
							$time_format = '%H:%M:%S';
						}
					}
				}
			} else {
				if (!str_contains($time_format, 's')) {
					if (str_contains($time_format, 'i')) {
						$time_format = str_replace('i', 'i:s', $time_format);
					} else {
						if (str_contains($time_format, 'h')) {
							$time_format = str_replace('h', 'h:i:s', $time_format);
						} elseif (str_contains($time_format, 'g')) {
							$time_format = str_replace('g', 'g:i:s', $time_format);
						} elseif (str_contains($time_format, 'H')) {
							$time_format = str_replace('H', 'H:i:s', $time_format);
						} elseif (str_contains($time_format, 'G')) {
							$time_format = str_replace('G', 'G:i:s', $time_format);
						} else {
							$time_format = 'H:i:s';
						}
					}
				}
			}
		}

		$min = Time::createFromInterface($start);

		$max = Time::createFromInterface($start)->setTime(
			(int) (isset($this->byhour) ? max($this->byhour) : $start->format('H')),
			(int) (isset($this->byminute) ? max($this->byminute) : $start->format('i')),
			(int) (isset($this->bysecond) ? max($this->bysecond) : $start->format('s')),
		);

		// Seconds.
		if (!empty($this->bysecond)) {
			if ($this->freq === 'SECONDLY' && ($this->interval ?? 1) === 1) {
				unset($description['frequency_interval']);
			}

			if (range(min($this->bysecond), max($this->bysecond)) === $this->bysecond) {
				$list = Lang::getTxt(
					'calendar_rrule_desc_between',
					[
						'min' => $min->format('s'),
						'max' => Lang::getTxt('number_of_seconds', [$max->format('s')]),
					],
				);
			} else {
				$list = $this->bysecond;

				sort($list);
				$list[array_key_last($list)] = Lang::getTxt('number_of_seconds', [$list[array_key_last($list)]]);
				$list = Lang::sentenceList($list);
			}

			$description['bytime'] = Lang::getTxt('calendar_rrule_desc_bytime', ['times_list' => $list]);
		}

		// Minutes.
		if (!empty($this->byminute)) {
			if ($this->freq === 'MINUTELY' && ($this->interval ?? 1) === 1) {
				unset($description['frequency_interval']);
			}

			if (range(min($this->byminute), max($this->byminute)) === $this->byminute) {
				$list = Lang::getTxt(
					'calendar_rrule_desc_between',
					[
						'min' => $min->format('i'),
						'max' => Lang::getTxt('number_of_minutes', [$max->format('i')]),
					],
				);

				if (!isset($description['bytime'])) {
					$description['bytime'] = Lang::getTxt('calendar_rrule_desc_byminute', ['minute_list' => $list]);
				} else {
					$description['bytime'] .= ' ' . Lang::getTxt('calendar_rrule_desc_byminute', ['minute_list' => $list]);
				}
			} else {
				$list = $this->byminute;

				sort($list);
				$list[array_key_last($list)] = Lang::getTxt('number_of_minutes', [$list[array_key_last($list)]]);
				$list = Lang::sentenceList($list);

				if (!isset($description['bytime'])) {
					$description['bytime'] = Lang::getTxt('calendar_rrule_desc_bytime', ['times_list' => Lang::getTxt('calendar_rrule_desc_byminute', ['minute_list' => $list])]);
				} else {
					$description['bytime'] .= ' ' . Lang::getTxt('calendar_rrule_desc_bygeneric', ['list' => Lang::getTxt('calendar_rrule_desc_byminute', ['minute_list' => $list])]);
				}
			}
		} elseif (!empty($this->bysecond)) {
			$description['bytime'] .= ' ' . Lang::getTxt('calendar_rrule_desc_bygeneric', ['list' => Lang::getTxt('calendar_rrule_desc_frequency_interval', ['freq' => 'MINUTELY', 'interval' => 1])]);
		}

		// Hours.
		if (!empty($this->byhour)) {
			if ($this->freq === 'HOURLY' && ($this->interval ?? 1) === 1) {
				unset($description['frequency_interval']);
			}

			if (range(min($this->byhour), max($this->byhour)) === $this->byhour) {
				$list = Lang::getTxt(
					'calendar_rrule_desc_between',
					[
						'min' => $min->format($time_format),
						'max' => $max->format($time_format),
					],
				);

				if (!isset($description['bytime'])) {
					$description['bytime'] = $list;
				} else {
					$description['bytime'] .= ' ' . $list;
				}
			} else {
				$list = $this->byhour;

				sort($list);
				$list[array_key_last($list)] = Lang::getTxt('number_of_hours', [$list[array_key_last($list)]]);
				$list = Lang::sentenceList($list);

				if (!isset($description['bytime'])) {
					$description['bytime'] = Lang::getTxt('calendar_rrule_desc_bytime', ['times_list' => $list]);
				} else {
					$description['bytime'] .= ' ' . Lang::getTxt('calendar_rrule_desc_bygeneric', ['list' => $list]);
				}
			}
		} elseif (!empty($this->byminute)) {
			$description['bytime'] .= ' ' . Lang::getTxt('calendar_rrule_desc_bygeneric', ['list' => Lang::getTxt('calendar_rrule_desc_frequency_interval', ['freq' => 'HOURLY', 'interval' => 1])]);
		}
	}
}

?>