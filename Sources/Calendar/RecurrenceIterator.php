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

use SMF\TimeInterval;

/**
 * Iterator to calculate all occurrences of a recurring event.
 */
class RecurrenceIterator implements \Iterator
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * Indicates that the event has a date, time, and time zone.
	 *
	 * For example, an absolute event on '2023-10-26 14:00:00 Europe/Paris'
	 * will occur on Oct 26, 2023, at 2:00 PM for a user in Paris, France, but
	 * at 8:00 AM for a user in Toronto, Canada, and at 11:00 PM for a user in
	 * Sydney, Australia.
	 */
	public const TYPE_ABSOLUTE = 0;

	/**
	 * @var int
	 *
	 * Indicates that the event has specific date and time, but no time zone.
	 *
	 * For example, a floating event on '2023-10-26 14:00:00' will occur on
	 * Oct 26, 2023, at 2:00 PM in whatever time zone the viewer happens to
	 * currently be in.
	 */
	public const TYPE_FLOATING = 1;

	/**
	 * @var int
	 *
	 * Indicates that the event has a specific date, but no time or time zone.
	 *
	 * For example, an all day event on '2023-10-26' will occur on Oct 26, 2023,
	 * starting at midnight in whatever time zone the viewer happens to be in,
	 * and ending on the following midnight in that time zone.
	 */
	public const TYPE_ALLDAY = 2;

	/**
	 * @var array
	 *
	 * Maps RFC 5545 weekday abbreviations to full weekday names.
	 *
	 * The order of the elements matters. Do not change it.
	 */
	public const WEEKDAY_NAMES = [
		'MO' => 'Monday',
		'TU' => 'Tuesday',
		'WE' => 'Wednesday',
		'TH' => 'Thursday',
		'FR' => 'Friday',
		'SA' => 'Saturday',
		'SU' => 'Sunday',
	];

	/**
	 * @var array
	 *
	 * Used to calculate a default value for $this->view_end.
	 *
	 * These are only used when the RRule repeats forever and no value was
	 * specified for the constructor's $view parameter.
	 *
	 * Keys are possible values of $this->rrule->freq.
	 * Values are the number of times to add $this->frequency_interval to
	 * $this->view_start in order to calculate $this->view_end.
	 *
	 * These defaults are entirely arbitrary choices by the developer. ;)
	 */
	public const DEFAULT_COUNTS = [
		'YEARLY' => 10,
		'MONTHLY' => 12,
		'WEEKLY' => 52,
		'DAILY' => 365,
		'HOURLY' => 720,
		'MINUTELY' => 1440,
		'SECONDLY' => 3600,
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Info about how to process the various BYxxx rule parts (except BYSETPOS).
	 *
	 * As RFC 5545 says:
	 *
	 * "BYxxx rule parts modify the recurrence in some manner. BYxxx rule parts
	 * for a period of time that is the same or greater than the frequency
	 * generally reduce or limit the number of occurrences of the recurrence
	 * generated. [...] BYxxx rule parts for a period of time less than the
	 * frequency generally increase or expand the number of occurrences of the
	 * recurrence."
	 *
	 * For more info on these rule parts and how they modify the recurrence, see
	 * RFC 5545, §3.3.10.
	 *
	 * Note: the order of the elements in this array matters. Do not change it.
	 */
	private array $by = [
		'bysecond' => [
			'fmt' => 's',
			'type' => 'int',
			'is_expansion' => false,
		],
		'byminute' => [
			'fmt' => 'i',
			'type' => 'int',
			'is_expansion' => false,
		],
		'byhour' => [
			'fmt' => 'H',
			'type' => 'int',
			'is_expansion' => false,
		],
		'byday' => [
			'fmt' => 'D',
			'type' => 'string',
			'adjust' => '$current_value = strtoupper(substr($current_value, 0, 2));',
			'is_expansion' => false,
		],
		'bymonthday' => [
			'fmt' => 'j',
			'type' => 'int',
			'is_expansion' => false,
		],
		'byyearday' => [
			'fmt' => 'z',
			'type' => 'int',
			'adjust' => '$current_value++;',
			'is_expansion' => false,
		],
		'byweekno' => [
			'fmt' => 'W',
			'type' => 'int',
			'is_expansion' => true,
		],
		'bymonth' => [
			'fmt' => 'm',
			'type' => 'int',
			'is_expansion' => false,
		],
	];

	/**
	 * @var RRule
	 *
	 * The recurrence rule for this event.
	 */
	private RRule $rrule;

	/**
	 * @var \DateTimeInterface
	 *
	 * Date of the first occurrence of the event.
	 */
	private \DateTimeInterface $dtstart;

	/**
	 * @var int
	 *
	 * The type of event (normal, floating, or all day), as indicated by one of
	 * this class's TYPE_* constants.
	 */
	private int $type;

	/**
	 * @var array
	 *
	 * Timestamps of dates to add to the recurrence set.
	 * These are dates besides the ones generated from the RRule.
	 */
	private array $rdates = [];

	/**
	 * @var array
	 *
	 * Timestamps of dates to exclude from the recurrence set.
	 */
	private array $exdates = [];

	/**
	 * @var \DateInterval
	 *
	 * How far to jump ahead for each main iteration of the recurrence rule.
	 *
	 * Derived from $this->rrule->freq and $this->rrule->interval.
	 */
	private \DateInterval $frequency_interval;

	/**
	 * @var int|float
	 *
	 * Used for sanity checks.
	 *
	 * Value may change based on $this->rrule->freq and/or $this->rrule->count.
	 */
	private int|float $max_occurrences;

	/**
	 * @var \DateTimeImmutable
	 *
	 * Occurrences before this date will be skipped when returning results.
	 */
	private \DateTimeInterface $view_start;

	/**
	 * @var \DateTimeImmutable
	 *
	 * Occurrences after this date will be skipped when returning results.
	 */
	private \DateTimeInterface $view_end;

	/**
	 * @var \DateTimeImmutable
	 *
	 * Occurrences before this date will not be calculated.
	 *
	 * This value is typically one frequency interval before $this->view_start.
	 */
	private \DateTimeInterface $limit_before;

	/**
	 * @var \DateTimeImmutable
	 *
	 * Occurrences after this date will not be calculated.
	 *
	 * This value is typically one frequency interval after $this->view_end.
	 */
	private \DateTimeInterface $limit_after;

	/**
	 * @var array
	 *
	 * Date string records of all valid occurrences.
	 */
	private array $occurrences = [];

	/**
	 * @var array
	 *
	 * Date string records of the initial recurrence set generated from the
	 * RRule before any RDates or ExDates are applied.
	 */
	private array $rrule_occurrences = [];

	/**
	 * @var string
	 *
	 * \DateTime format to use for the records in $this->occurrences.
	 */
	private string $record_format = 'Ymd\THis\Z';

	/**
	 * @var int
	 *
	 * Iterator key. Points to the current element of $this->occurrences when
	 * iterating over an instance of this class.
	 */
	private int $key = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param RRule $rrule The recurrence rule for this event.
	 *
	 * @param \DateTimeInterface $dtstart Date of the event's first occurrence.
	 *
	 * @param ?\DateInterval $view Length of the period for which dates will be
	 *    shown. For example, use \DateInterval('P1M') to show one month's worth
	 *    of occurrences, \DateInterval('P1W') to show one week's worth, etc.
	 *    If null, will be determined automatically.
	 *
	 * @param ?\DateTimeInterface $view_start Lower limit for dates to be shown.
	 *    Iterating over the object will never return values before this date.
	 *    If null, will be set to $this->dtstart.
	 *
	 * @param ?int $type One of this class's TYPE_* constants, or null to set
	 *    it automatically based on the UNTIL value of the RRule. If this is
	 *    null and the RRule's UNTIL value is null, default is TYPE_ABSOLUTE.
	 *
	 * @param ?array $rdates Arbitrary dates to add to the recurrence set.
	 *    Elements must be arrays containing an instance of \DateTimeInterface
	 *    and an optional \DateInterval.
	 *    Used to make exceptions to the general recurrence rule.
	 *
	 * @param ?array $exdates Dates to exclude from the recurrence set.
	 *    Elements must be instances of \DateTimeInterface.
	 *    Used to make exceptions to the general recurrence rule.
	 */
	public function __construct(
		RRule $rrule,
		\DateTimeInterface $dtstart,
		?\DateInterval $view = null,
		?\DateTimeInterface $view_start = null,
		?int $type = null,
		array $rdates = [],
		array $exdates = [],
	) {
		$this->rrule = $rrule;

		$this->type = isset($type) && in_array($type, range(0, 2)) ? $type : ($this->rrule->until_type ?? self::TYPE_ABSOLUTE);

		$this->dtstart = $this->type === self::TYPE_ABSOLUTE ? \DateTimeImmutable::createFromInterface($dtstart) : \DateTime::createFromInterface($dtstart);
		$this->view_start = $this->type === self::TYPE_ABSOLUTE ? \DateTimeImmutable::createFromInterface($view_start ?? $this->dtstart) : \DateTime::createFromInterface($view_start ?? $this->dtstart);

		$this->setFrequencyInterval();

		// We were given a view duration.
		if (isset($view)) {
			$this->view_end = (clone $this->view_start)->add($view);
		}
		// Rule contained an UNTIL value, so use that as our view end.
		elseif (isset($this->rrule->until)) {
			$this->view_end = $this->type === self::TYPE_ABSOLUTE ? \DateTimeImmutable::createFromInterface($this->rrule->until) : \DateTime::createFromInterface($this->rrule->until);
		}
		// Figure out the view end based on the count value.
		else {
			$this->view_end = clone $this->view_start;

			// Add the frequency interval enough times to cover the specified
			// number of occurrences, or the default number if unspecified.
			for ($i = 0; $i < ($this->rrule->count ?? self::DEFAULT_COUNTS[$this->rrule->freq]); $i++) {
				$this->view_end = $this->view_end->add($this->frequency_interval);
			}
		}

		// If given a view duration and an UNTIL value, end at whichever comes first.
		if (isset($this->rrule->until) && $this->rrule->until < $this->view_end) {
			$this->view_end = $this->type === self::TYPE_ABSOLUTE ? \DateTimeImmutable::createFromInterface($this->rrule->until) : \DateTime::createFromInterface($this->rrule->until);
		}

		// Set some limits.
		$this->limit_before = !empty($this->rrule->bysetpos) ? (clone $this->dtstart)->sub($this->frequency_interval) : $this->dtstart;
		$this->limit_after = (clone $this->view_end)->add($this->frequency_interval);
		$this->max_occurrences = $this->rrule->count ?? self::DEFAULT_COUNTS[$this->rrule->freq] * 1000;

		// Figure out the appropriate way to record the occurrences.
		switch ($this->type) {
			case self::TYPE_ALLDAY:
				$this->record_format = 'Ymd';
				break;

			case self::TYPE_FLOATING:
				$this->record_format = 'Ymd\THis';
				break;

			default:
				$this->record_format = 'Ymd\THis\Z';
				break;
		}

		// Finalize values in $this->by.
		switch ($this->rrule->freq) {
			case 'YEARLY':
				$this->by['bymonth']['is_expansion'] = true;
				$this->by['byyearday']['is_expansion'] = true;
				$this->by['bymonthday']['is_expansion'] = true;

				if (empty($this->rrule->bymonthday) && empty($this->rrule->byyearday)) {
					$this->by['byday']['is_expansion'] = true;
				}
				$this->by['byhour']['is_expansion'] = true;
				$this->by['byminute']['is_expansion'] = true;
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'MONTHLY':
				$this->by['bymonthday']['is_expansion'] = true;

				if (empty($this->rrule->bymonthday)) {
					$this->by['byday']['is_expansion'] = true;
				}
				$this->by['byhour']['is_expansion'] = true;
				$this->by['byminute']['is_expansion'] = true;
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'WEEKLY':
				$this->by['byday']['is_expansion'] = true;
				$this->by['byhour']['is_expansion'] = true;
				$this->by['byminute']['is_expansion'] = true;
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'DAILY':
				$this->by['byhour']['is_expansion'] = true;
				$this->by['byminute']['is_expansion'] = true;
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'HOURLY':
				$this->by['byminute']['is_expansion'] = true;
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'MINUTELY':
				$this->by['bysecond']['is_expansion'] = true;
				break;

			case 'SECONDLY':
				break;
		}

		// First, calculate occurrence dates based on the recurrence rule.
		$this->calculate();

		// Remember the initially calculated recurrence set. We may need it later.
		$this->rrule_occurrences = $this->occurrences;

		// Next, add any manually specified dates.
		foreach ($rdates as $rdate) {
			if ($rdate[0] instanceof \DateTimeInterface) {
				$this->add(...$rdate);
			}
		}

		// Finally, remove any excluded dates.
		foreach ($exdates as $exdate) {
			if ($exdate instanceof \DateTimeInterface) {
				$this->remove($exdate);
			}
		}
	}

	/**
	 * Returns a copy of $this->dtstart.
	 */
	public function getDtStart(): \DateTimeInterface
	{
		return clone $this->dtstart;
	}

	/**
	 * Returns a copy of $this->frequency_interval.
	 */
	public function getFrequencyInterval(): \DateInterval
	{
		return clone $this->frequency_interval;
	}

	/**
	 * Returns a copy of the recurrence rule.
	 */
	public function getRRule(): RRule
	{
		return clone $this->rrule;
	}

	/**
	 * Returns a copy of $this->rdates.
	 */
	public function getRDates(): array
	{
		return $this->rdates;
	}

	/**
	 * Returns a copy of $this->exdates.
	 */
	public function getExDates(): array
	{
		return $this->exdates;
	}

	/**
	 * Returns occurrences generated by the RRule only.
	 */
	public function getRRuleOccurrences(): array
	{
		return $this->rrule_occurrences;
	}

	/**
	 * Adds an arbitrary date to the recurrence set.
	 *
	 * Used for making exceptions to the general recurrence rule.
	 * Note that calling this method always rewinds the iterator key.
	 *
	 * @param \DateTimeInterface $date The date to add.
	 * @param ?\DateInterval $duration Optional duration for this occurrence.
	 *    Only necessary if the duration for this occurrence differs from the
	 *    usual duration of the event.
	 */
	public function add(\DateTimeInterface $date, ?\DateInterval $duration = null): void
	{
		$this->rewind();

		if ($date < $this->view_start || $date > $this->view_end) {
			return;
		}

		$string = (clone $date)->setTimezone(new \DateTimeZone('UTC'))->format($this->record_format);

		if (in_array($string, $this->occurrences)) {
			return;
		}

		// Re-adding a date that was previously removed.
		if (in_array($string, $this->exdates)) {
			$this->exdates = array_values(array_diff($this->exdates, [$string]));
		}
		// Adding a new date.
		else {
			if (isset($duration)) {
				$string .= '/' . (string) ($duration instanceof TimeInterval ? $duration : TimeInterval::createFromDateInterval($duration));
			}

			$this->rdates[] = $string;

			// Increment max_occurrences so that we don't drop any occurrences
			// generated from the RRule.
			$this->max_occurrences++;
		}

		$this->record($date);
	}

	/**
	 * Removes a date from the recurrence set.
	 *
	 * Used for making exceptions to the general recurrence rule.
	 * Note that calling this method always rewinds the iterator key.
	 *
	 * @param \DateTimeInterface $date The date to remove.
	 */
	public function remove(\DateTimeInterface $date): void
	{
		$this->rewind();

		$string = $date->format($this->record_format);

		$is_rdate = false;

		foreach ($this->rdates as $key => $rdate) {
			if (str_starts_with($rdate, $string)) {
				$is_rdate = true;
				unset($this->rdates[$key]);
				$this->rdates = array_values($this->rdates);
			}
		}

		if (!$is_rdate && !in_array($string, $this->exdates)) {
			$this->exdates[] = $string;
		}

		$this->occurrences = array_values(array_diff($this->occurrences, [$string]));
	}

	/**
	 * Checks whether the given date/time occurs in the recurrence set.
	 *
	 * @param \DateTimeInterface $date A date.
	 * @return bool Whether the date occurs in the recurrence set.
	 */
	public function dateOccurs(\DateTimeInterface $date): bool
	{
		return in_array((clone $date)->setTimezone(new \DateTimeZone('UTC'))->format($this->record_format), $this->occurrences);
	}

	/**
	 * Moves the iterator key one step forward, and then returns a
	 * DateTimeInterface object for the newly selected element in the event's
	 * recurrence set.
	 *
	 * @return \DateTimeInterface|false The next occurrence, or false if there
	 *    are no more occurrences.
	 */
	public function getNext(): \DateTimeInterface|bool
	{
		$this->next();

		return $this->current();
	}

	/**
	 * Moves the iterator key one step backward, and then returns a
	 * DateTimeInterface object for the newly selected element in the event's
	 * recurrence set.
	 *
	 * @return \DateTimeInterface|false The previous occurrence, or false if
	 *    there is no previous occurrence.
	 */
	public function getPrev(): \DateTimeInterface|bool
	{
		$this->prev();

		return $this->current();
	}

	/**
	 * Returns a \DateTimeInterface object for the currently selected element
	 * in the event's recurrence set.
	 *
	 * If $this->type is self::TYPE_FLOATING or self::TYPE_ALLDAY, the returned
	 * object will be a mutable \DateTime instance so that its time zone can be
	 * changed to the viewing user's current time zone.
	 *
	 * Otherwise, the returned object will a \DateTimeImmutable instance.
	 *
	 * @return \DateTimeInterface|false The date of the occurrence, or false on
	 *    error.
	 */
	public function current(): \DateTimeInterface|bool
	{
		if (!$this->valid()) {
			return false;
		}

		// For TYPE_ABSOLUTE, return a \DateTimeImmutable object.
		if ($this->type === self::TYPE_ABSOLUTE) {
			$occurrence = new \DateTimeImmutable($this->occurrences[$this->key]);
			$occurrence = $occurrence->setTimezone($this->dtstart->getTimezone());
		}
		// For TYPE_FLOATING and TYPE_ALLDAY, return a \DateTime object.
		else {
			$occurrence = new \DateTime(
				$this->occurrences[$this->key],
				$this->dtstart->getTimezone(),
			);
		}

		return $occurrence;
	}

	/**
	 * Checks whether the current value of the iterator key is valid.
	 *
	 * @return bool Whether the current value of the iterator key is valid.
	 */
	public function valid(): bool
	{
		return isset($this->occurrences[$this->key]);
	}

	/**
	 * Get the current value of the iterator key.
	 *
	 * @return int The current value of the iterator key.
	 */
	public function key(): int
	{
		return $this->key;
	}

	/**
	 * Moves the iterator key one step forward.
	 */
	public function next(): void
	{
		$this->key++;
	}

	/**
	 * Moves the iterator key one step backward.
	 */
	public function prev(): void
	{
		$this->key--;

		$this->key = max(0, $this->key);
	}

	/**
	 * Moves the iterator key back to the beginning.
	 */
	public function rewind(): void
	{
		$this->key = 0;
	}

	/**
	 * Moves the iterator key to the end.
	 */
	public function end(): void
	{
		$this->key = !empty($this->occurrences) ? max(array_keys($this->occurrences)) : 0;
	}

	/**
	 * Moves the iterator key to a specific position.
	 *
	 * If the requested key is invalid, will go to the closest valid one.
	 *
	 * @return int The new value of the iterator key.
	 */
	public function setKey(int $key): int
	{
		// Clamp to valid range.
		$this->key = min(max($key, 0), (!empty($this->occurrences) ? max(array_keys($this->occurrences)) : 0));

		// If still not valid, find one that is.
		while (!$this->valid() && $this->key > 0) {
			$this->prev();
		}

		return $this->key;
	}

	/**
	 * Finds the iterator key that corresponds to the requested value.
	 *
	 * @param string $needle The value to search for.
	 * @return int|false The key for the requested value, or false on error.
	 */
	public function search(string $needle): int|bool
	{
		return array_search($needle, $this->occurrences);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Calculates the week number of a date according to RFC 5545.
	 */
	public static function calculateWeekNum(\DateTimeInterface $current, string $wkst): int
	{
		// If $wkst is Monday, we can skip the extra work.
		if ($wkst === 'MO') {
			return (int) $current->format('W');
		}

		$temp = \DateTime::createFromInterface($current);

		$wkst_diff = array_search($wkst, array_keys(self::WEEKDAY_NAMES));
		$wkst_diff = ($wkst_diff >= 5 ? $wkst_diff - 7 : $wkst_diff) * -1;

		$temp->modify(sprintf('%1$+d days', $wkst_diff));

		$weeknum = $temp->format('W');

		return (int) $weeknum;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets the value of $this->frequency_interval.
	 */
	private function setFrequencyInterval(): void
	{
		$interval_string = 'P';

		if (in_array($this->rrule->freq, ['HOURLY', 'MINUTELY', 'SECONDLY'])) {
			$interval_string .= 'T';
		}

		$interval_string .= $this->rrule->interval;

		$interval_string .= substr($this->rrule->freq, 0, 1);

		$this->frequency_interval = new \DateInterval($interval_string);
	}

	/**
	 * Adds an occurrence to $this->occurrences.
	 *
	 * @return bool True if the occurrence is a new addition; false if it is
	 *    a duplicate or out of bounds.
	 */
	private function record(\DateTimeInterface $occurrence): bool
	{
		// If it is too late, don't add it.
		if ($occurrence > $this->limit_after) {
			return false;
		}

		$string = (clone $occurrence)->setTimezone(new \DateTimeZone('UTC'))->format($this->record_format);

		// If it is already in the array, don't add it.
		if (in_array($string, $this->occurrences)) {
			return false;
		}

		// Add and sort.
		$this->occurrences[] = $string;
		sort($this->occurrences);

		// If the RRule contains a BYSETPOS rule part, we can't truncate yet.
		if (!empty($this->rrule->bysetpos)) {
			return true;
		}

		// If we don't need to truncate the array, return true now.
		if ($this->max_occurrences >= count($this->occurrences)) {
			return true;
		}

		// We have too many occurrences, so truncate the array.
		array_splice($this->occurrences, $this->max_occurrences);

		// Is this occurrence still in the array?
		return in_array($string, $this->occurrences);
	}

	/**
	 * Calculates occurrences based on the recurrence rule.
	 */
	private function calculate(): void
	{
		if ($this->view_end < $this->view_start) {
			return;
		}

		$current = \DateTime::createFromInterface($this->dtstart);

		$this->jumpToVisible($current);

		$max = $this->max_occurrences;

		// Calculate all the occurrences and store them in $this->occurrences.
		while (
			$current <= $this->limit_after
			&& (
				!empty($this->rrule->bysetpos)
				|| count($this->occurrences) < $max
			)
		) {
			if ($current < $this->view_start) {
				$this->nextInterval($current);

				// If $this->max_occurrences was derived from an RRule's COUNT,
				// then skipped occurrences still count against the maximum.
				if (!empty($this->rrule->count)) {
					$max--;
				}

				continue;
			}

			// Get the expansions.
			$expansions = [];

			foreach ($this->expand($current) as $expanded) {
				if ($this->limit($expanded)) {
					$this->record($expanded);
				}

				// Failsafe if we get stuck in a loop.
				$formatted = $expanded->format($this->record_format);

				if (($expansions[$formatted] = ($expansions[$formatted] ?? 0) + 1) > 2) {
					break;
				}
			}

			if ($this->limit($current)) {
				$this->record($current);
			}

			// Step forward one frequency interval.
			$this->nextInterval($current);
		}

		// Apply BYSETPOS limitation.
		$this->limitBySetPos();

		// Final cleanup.
		$view_start = $this->view_start->format($this->record_format);
		$view_end = $this->view_end->format($this->record_format);

		$this->occurrences = array_values(array_filter(
			$this->occurrences,
			fn ($occurrence) => $view_start <= $occurrence && $view_end >= $occurrence,
		));

		if ($this->max_occurrences < count($this->occurrences)) {
			array_splice($this->occurrences, $this->max_occurrences);
		}
	}

	/**
	 * If possible, move $current ahead in order to skip values before
	 * $this->view_start.
	 *
	 * @param \DateTime &$current
	 */
	private function jumpToVisible(\DateTime &$current): void
	{
		if (
			// Don't jump ahead if $current is already in the visible range.
			$current >= $this->view_start
			// Can't jump ahead if recurrence rule requires counting the occurrences.
			|| isset($this->rrule->count)
		) {
			return;
		}

		// Special handling for WEEKLY.
		if ($this->rrule->freq === 'WEEKLY') {
			$weekdays = !empty($this->rrule->byday) ? array_map(fn ($weekday) => substr($weekday, -2), $this->rrule->byday) : [strtoupper(substr($current->format('D'), 0, 2))];

			$current->setDate(
				(int) $this->view_start->format('Y'),
				(int) $this->view_start->format('m'),
				(int) $this->view_start->format('d'),
			);

			while (!in_array(strtoupper(substr($current->format('D'), 0, 2)), $weekdays)) {
				$current->modify('-1 day');
			}

			return;
		}

		// Everything else.
		foreach ($this->by as $prop => $info) {
			if (!$info['is_expansion']) {
				continue;
			}

			if (!empty($this->rrule->{$prop}) && count($this->rrule->{$prop}) > 1) {
				return;
			}
		}

		switch ($this->rrule->freq) {
			case 'SECONDLY':
				$s = (int) $this->view_start->format('s');
				// no break

			case 'MINUTELY':
				$i = (int) $this->view_start->format('i');
				// no break

			case 'HOURLY':
				$h = (int) $this->view_start->format('h');
				// no break

			case 'DAILY':
				$d = (int) $this->view_start->format('d');
				// no break

			case 'MONTHLY':
				$m = (int) $this->view_start->format('m');
				// no break

			case 'YEARLY':
				$y = (int) $this->view_start->format('Y');
		}

		$current->setDate($y, $m ?? (int) $current->format('m'), $d ?? (int) $current->format('d'));

		if (isset($h)) {
			$current->setTime($h, $i ?? (int) $current->format('i'), $s ?? (int) $current->format('s'));
		}
	}

	/**
	 * Move $current ahead to the next interval.
	 *
	 * Handles months of varying lengths according to RFC 5545's rules.
	 *
	 * @param \DateTime &$current
	 */
	private function nextInterval(\DateTime &$current): void
	{
		// Monthly is complicated.
		if ($this->rrule->freq == 'MONTHLY') {
			$months_to_add = 0;
			$next_monthday = !empty($this->rrule->bymonthday) ? 1 : (int) $current->format('d');
			$test = \DateTimeImmutable::createFromMutable($current);

			do {
				$months_to_add += $this->rrule->interval;
				$next = clone $current;
				$next->setDate((int) $next->format('Y'), (int) $next->format('m') + $months_to_add, (int) $next_monthday);
			} while ($test->setDate((int) $test->format('Y'), (int) $test->format('m') + $months_to_add, (int) $next_monthday)->format('m') % 12 != (($test->format('m') + $months_to_add) % 12));

			$current = $next;
		}
		// Everything else is straightforward.
		else {
			$current->add($this->frequency_interval);
		}
	}

	/**
	 * Checks whether a calculated occurrence should be included based on BYxxx
	 * rule parts in the RRule.
	 *
	 * Limitations cause occurrences that would normally occur according the
	 * RRule's stated frequency to be skipped. For example, if the frequency is
	 * set to HOURLY, that normally means that the event recurs on minute 0 of
	 * every hour (or whatever minute was set in DTSTART) of every day. But if
	 * the BYDAY rule part is set to 'TU,TH', then occurrences are limited so
	 * that the event recurs only at every hour during Tuesdays and Thursdays.
	 * Occurrences that happen on any other day of the week are skipped.
	 *
	 * @param \DateTime $current A occurrence whose value we want to check.
	 * @return bool Whether the occurrence is allowed.
	 */
	private function limit(\DateTime $current): bool
	{
		if ($current < $this->limit_before || $current > $this->limit_after) {
			return false;
		}

		$valid = true;

		foreach ($this->by as $prop => $info) {
			if (empty($this->rrule->{$prop})) {
				continue;
			}

			// Get the current value.
			$current_value = $current->format($info['fmt']);

			// Coerce the value to the expected type.
			settype($current_value, $info['type']);

			// Make any necessary adjustment to the value.
			if (!empty($info['adjust'])) {
				eval($info['adjust']);
			}

			// What are the allowed values?
			$allowed_values = $this->rrule->{$prop};

			// BYDAY values could have numerical modifiers prepended to them.
			// We only want the plain weekday abbreviations here.
			if ($prop === 'byday') {
				foreach ($allowed_values as &$allowed_value) {
					$allowed_value = substr($allowed_value, -2);
				}
			}

			// These types of values can be negative to indicate that they are
			// counting from the end. Convert to real values.
			switch ($prop) {
				case 'byyearday':
					foreach ($allowed_values as &$allowed_value) {
						if ($allowed_value < 0) {
							$allowed_value += $current->format('L') ? 367 : 366;
						}
					}
					break;

				case 'bymonthday':
					foreach ($allowed_values as &$allowed_value) {
						if ($allowed_value < 0) {
							$allowed_value += $current->format('t') + 1;
						}
					}
					break;

				case 'byweekno':
					// Are there 52 or 53 numbered weeks in this year?
					// Checking Dec 28 using a Monday for our week start
					// will always give us the right answer.
					$num_weeks = (new \DateTime($current->format('Y') . '-12-28'))->format('W');

					foreach ($allowed_values as &$allowed_value) {
						if ($allowed_value < 0) {
							$allowed_value += $num_weeks + 1;
						}
					}
					break;
			 }

			$valid &= in_array($current_value, $allowed_values);
		}

		return (bool) $valid;
	}

	/**
	 * Applies BYSETPOS limitation to the reccurrence set.
	 */
	private function limitBySetPos(): void
	{
		if (empty($this->rrule->bysetpos)) {
			return;
		}

		switch ($this->rrule->freq) {
			case 'YEARLY':
				$pattern = '/^(\d{4})/';
				break;

			case 'MONTHLY':
				$pattern = '/^\d{4}(\d{2})/';
				break;

			case 'DAILY':
				$pattern = '/^\d{6}(\d{2})/';
				break;

			case 'HOURLY':
				$pattern = '/T(\d{2})/';
				break;

			case 'MINUTELY':
				$pattern = '/T\d{2}(\d{2})/';
				break;

			case 'SECONDLY':
				$pattern = '/T\d{4}(\d{2})/';
				break;
		}

		$groups = [];

		$prev_val = null;
		$group_key = -1;

		foreach ($this->occurrences as $string) {
			// Weekly.
			if (!isset($pattern)) {
				$val = self::calculateWeekNum(new \DateTimeImmutable($string), $this->rrule->wkst);
			}
			// Everything else.
			else {
				preg_match($pattern, $string, $matches);
				$val = $matches[1];
			}

			if ($val !== $prev_val) {
				$group_key++;
			}

			$groups[$group_key][] = $string;

			$prev_val = $val;
		}

		// BYSETPOS starts with an index of 1, not 0, for positive values,
		// so we have to adjust it.
		$bysetpos = array_map(
			fn ($setpos) => $setpos < 0 ? (int) $setpos : (int) $setpos - 1,
			$this->rrule->bysetpos,
		);

		$occurrences = [];

		foreach ($groups as $group_key => $strings) {
			foreach ($bysetpos as $setpos) {
				$occurrences = array_merge($occurrences, array_slice($strings, $setpos, 1));
			}
		}

		$this->occurrences = $occurrences;
	}

	/**
	 * Finds additional occurrences based on any BYxxx rule parts in the RRule.
	 *
	 * Expansions are occurrences that happen inside one iteration of the
	 * RRule's stated frequency. For example, if the frequency is set to HOURLY,
	 * that normally means that the event recurs on minute 0 of every hour (or
	 * whatever minute was set in DTSTART). But if the RRule's BYMINUTE property
	 * is set to '0,30', then the occurrences are expanded so that the event
	 * recurs at minute 0 and minute 30 of every hour.
	 *
	 * @param \DateTime $current An occurrence of the event to expand.
	 * @param string $break_after Name of a $this->by element. Used during
	 *    recursive calls to this method.
	 * @return Generator<\DateTimeImmutable>
	 */
	private function expand(\DateTime $current, ?string $break_after = null): \Generator
	{
		foreach ($this->by as $prop => $info) {
			// Do the expansions.
			if (!empty($this->rrule->{$prop}) && $info['is_expansion']) {
				$temp = \DateTime::createFromInterface($current);

				switch ($prop) {
					case 'bymonth':
						foreach ($this->rrule->{$prop} as $value) {
							if ($value != $temp->format('m')) {
								$temp->setDate((int) $temp->format('Y'), (int) $value, (int) $temp->format('d'));

								yield $temp;

								foreach ($this->expand($temp, 'byweekno') as $temp2) {
									yield $temp2;
								}
							}
						}
						break;

					case 'byweekno':
						foreach ($this->rrule->{$prop} as $value) {
							// Unfortunately, we can't use PHP's interpretation
							// of week numbering. PHP always numbers weeks based
							// on a Monday start. In contrast, RFC 5545 allows
							// arbitrary week starts and adjusts the week
							// numbering based on that. So we have to figure it
							// out manually.
							$temp->setDate((int) $temp->format('Y'), 1, 1);

							$first_wkst = 1;

							while (strtoupper(substr($temp->format('D'), 0, 2)) !== $this->rrule->wkst) {
								$temp->modify('+ 1 day');
								$first_wkst++;
							}

							if ($first_wkst >= 4) {
								$temp->modify('- 7 days');
							}

							$weeknum = 1;

							while (++$weeknum < $value) {
								$temp->modify('+ 7 days');
							}

							yield $temp;

							foreach ($this->expand($temp, 'byyearday') as $temp2) {
								yield $temp2;
							}
						}
						break;

					case 'byyearday':
						$current_value = $current->format('z');
						eval($info['adjust']);

						foreach ($this->rrule->{$prop} as $value) {
							if ($value != $current_value) {
								$temp = \DateTime::createFromFormat('Y z H:i:s e', $temp->format('Y ') . ($value - 1) . $temp->format(' H:i:s e'));

								yield $temp;

								foreach ($this->expand($temp, 'bymonthday') as $temp2) {
									yield $temp2;
								}
							}
						}
						break;

					case 'bymonthday':
						foreach ($this->rrule->{$prop} as $value) {
							if ($value < 0) {
								$value += $temp->format('t') + 1;
							}

							if ($value != $temp->format('d')) {
								$temp->setDate((int) $temp->format('Y'), (int) $temp->format('m'), (int) $value);

								yield $temp;

								foreach ($this->expand($temp, 'byday') as $temp2) {
									yield $temp2;
								}
							}
						}
						break;

					case 'byday':
						$current_value = $temp->format('D');
						eval($info['adjust']);

						// Special handling for yearly.
						if ($this->rrule->freq === 'YEARLY') {
							if (!empty($this->rrule->bymonth)) {
								foreach ($this->expandMonthByDay($temp, $this->rrule->byday, $current_value) as $temp2) {
									yield $temp2;
								}
							} else {
								foreach ($this->expandYearByDay($temp, $this->rrule->byday, $current_value) as $temp2) {
									yield $temp2;
								}
							}
						}
						// Special handling for monthly.
						elseif ($this->rrule->freq === 'MONTHLY') {
							foreach ($this->expandMonthByDay($temp, $this->rrule->byday, $current_value) as $temp2) {
								yield $temp2;
							}
						}
						// Special handling for weekly.
						elseif ($this->rrule->freq === 'WEEKLY') {
							$weeknum = self::calculateWeekNum($temp, $this->rrule->wkst);

							// Move temp to start of week.
							$temp->modify('+ 1 day');
							$temp->modify('previous ' . self::WEEKDAY_NAMES[$this->rrule->wkst] . ' ' . $temp->format('H:i:s e'));

							$temp_value = strtoupper(substr($temp->format('D'), 0, 2));

							foreach ($this->sortWeekdays($this->rrule->byday) as $value) {
								if ($value != $temp_value) {
									$temp->modify('next ' . self::WEEKDAY_NAMES[$value] . ' ' . $temp->format('H:i:s e'));
								}

								if (self::calculateWeekNum($temp, $this->rrule->wkst) === $weeknum) {
									yield $temp;

									foreach ($this->expand($temp, 'byhour') as $temp2) {
										yield $temp2;
									}
								}
							}
						}
						// Everything else.
						else {
							foreach ($this->rrule->byday as $value) {
								if ($value != $current_value) {
									$temp->modify('next ' . self::WEEKDAY_NAMES[$value] . ' ' . $temp->format('H:i:s e'));

									yield $temp;

									foreach ($this->expand($temp, 'byhour') as $temp2) {
										yield $temp2;
									}
								}
							}
						}
						break;

					case 'byhour':
						foreach ($this->rrule->byhour as $value) {
							if ($value != $temp->format('H')) {
								$temp->setTime((int) $value, (int) $temp->format('i'), (int) $temp->format('s'));

								yield $temp;

								foreach ($this->expand($temp, 'byminute') as $temp2) {
									yield $temp2;
								}
							}
						}
						break;

					case 'byminute':
						foreach ($this->rrule->byminute as $value) {
							if ($value != $temp->format('i')) {
								$temp->setTime((int) $temp->format('H'), (int) $value, (int) $temp->format('s'));

								yield $temp;

								foreach ($this->expand($temp, 'bysecond') as $temp2) {
									yield $temp2;
								}
							}
						}
						break;

					case 'bysecond':
						foreach ($this->rrule->bysecond as $value) {
							if ($value != $temp->format('s')) {
								$temp->setTime((int) $temp->format('H'), (int) $temp->format('i'), (int) $value);

								yield $temp;
							}
						}
						break;
				}
			}

			if (!empty($break_after) && $break_after == $prop) {
				break;
			}
		}
	}

	/**
	 * Used when expanding an occurrence that is part of a monthly recurrence
	 * set that has a byday rule, or part of a yearly recurrence set that has
	 * both a bymonth rule and a byday rule.
	 *
	 * @param \DateTime $current An occurrence of the event to expand.
	 * @param array $expansion_values Values from the byday rule.
	 * @param string The abbreviated name of the $current occurrence's weekday.
	 * @return Generator<\DateTimeImmutable>
	 */
	private function expandMonthByDay(\DateTime $current, array $expansion_values, string $current_value): \Generator
	{
		$upperlimit = clone $current;

		if ($this->frequency_interval->m === 1) {
			$upperlimit->add($this->frequency_interval);
		} elseif (!empty($this->rrule->bymonth) && in_array((($upperlimit->format('m') + 1) % 12), $this->rrule->bymonth)) {
			$upperlimit->modify('last day of ' . $upperlimit->format('F H:i:s e'));
			$upperlimit->modify('+ 1 day');
		} else {
			$upperlimit->setDate((int) $upperlimit->format('Y'), (int) $upperlimit->format('m'), 1);
			$upperlimit->modify('last day of ' . $upperlimit->format('F H:i:s e'));
		}

		$expansion_values = $this->sortWeekdays($expansion_values);

		foreach ($expansion_values as $k => $v) {
			// Separate out the numerical modifier (if any) from the day name.
			preg_match('/^([+-]?\d*)(MO|TU|WE|TH|FR|SA|SU)?/', $v, $matches);

			$expansion_values[$k] = [
				'modifier' => (int) ($matches[1] ?? 0),
				'weekday' => $matches[2],
			];
		}

		$temp = clone $current;

		$key = 0;
		$i = 0;

		while ($temp <= $upperlimit) {
			// Positive modifier means nth weekday of the month.
			// E.g.: '2TH' means the second Thursday.
			if ($expansion_values[$key]['modifier'] > 0) {
				// To work nicely with PHP's parsing of 'next <dayname>',
				// go to last day of previous month, then walk forward.
				$temp->setDate((int) $temp->format('Y'), (int) $temp->format('m'), 1);
				$temp->modify('- 1 day');

				// Go to first occurrence of the weekday in the month.
				$temp->modify('next ' . self::WEEKDAY_NAMES[$expansion_values[$key]['weekday']] . ' ' . $temp->format('H:i:s e'));

				// Move forward to the requested occurrence.
				if ($expansion_values[$key]['modifier'] > 1) {
					$temp->modify('+ ' . (($expansion_values[$key]['modifier'] - 1) * 7) . ' days');
				}

				if ($temp <= $upperlimit && $temp >= $this->limit_before) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit && $temp2 >= $this->limit_before) {
							yield $temp2;
						}
					}
				}

				if ($key === count($expansion_values) - 1) {
					break;
				}
			}
			// Negative modifier means nth last weekday of the month.
			// E.g.: '-2TH' means the second last Thursday.
			elseif ($expansion_values[$key]['modifier'] < 0) {
				// To work nicely with PHP's parsing of 'previous <dayname>',
				// go to first day of next month, then walk backward.
				$temp->setDate((int) $temp->format('Y'), (int) $temp->format('m') + 1, 1);

				// Go to last occurrence of the weekday in the month.
				$temp->modify('previous ' . self::WEEKDAY_NAMES[$expansion_values[$key]['weekday']] . ' ' . $temp->format('H:i:s e'));

				// Move backward to the requested occurrence.
				if ($expansion_values[$key]['modifier'] < -1) {
					$temp->modify('- ' . ((abs($expansion_values[$key]['modifier']) - 1) * 7) . ' days');
				}

				if ($temp <= $upperlimit && $temp >= $this->limit_before) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit && $temp2 >= $this->limit_before) {
							yield $temp2;
						}
					}
				}

				if ($key === count($expansion_values) - 1) {
					break;
				}
			}
			// No modifier means every matching weekday.
			// E.g.: 'TH' means every Thursday.
			else {
				// On the first iteration of this loop only, go to the last day
				// of the previous month.
				if ($i === 0) {
					$temp->setDate((int) $temp->format('Y'), (int) $temp->format('m'), 1);
					$temp->modify('- 1 day');
				}

				$temp->modify('+ 1 day');

				if ($temp <= $upperlimit && $temp >= $this->limit_before) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit && $temp2 >= $this->limit_before) {
							yield $temp2;
						}
					}
				}
			}

			$key++;
			$key %= count($expansion_values);
			$i++;
		}
	}

	/**
	 * Used when expanding an occurrence that is part of a yearly recurrence
	 * set that has a byday rule but not a bymonth rule.
	 *
	 * @param \DateTime $current An occurrence of the event to expand.
	 * @param array $expansion_values Values from the byday rule.
	 * @param string The abbreviated name of the $current occurrence's weekday.
	 * @return Generator<\DateTimeImmutable>
	 */
	private function expandYearByDay(\DateTime $current, array $expansion_values, string $current_value): \Generator
	{
		$upperlimit = clone $current;
		$upperlimit->add($this->frequency_interval);

		$expansion_values = $this->sortWeekdays($expansion_values);

		foreach ($expansion_values as $k => $v) {
			// Separate out the numerical modifier (if any) from the day name.
			preg_match('/^([+-]?\d*)(MO|TU|WE|TH|FR|SA|SU)?/', $v, $matches);

			$expansion_values[$k] = [
				'modifier' => (int) ($matches[1] ?? 0),
				'weekday' => $matches[2],
			];
		}

		$temp = clone $current;

		$key = 0;

		while ($temp <= $upperlimit) {
			// Positive modifier means nth weekday of the year.
			// E.g.: '2TH' means the second Thursday.
			if ($expansion_values[$key]['modifier'] > 0) {
				// To work nicely with PHP's parsing of 'next <dayname>',
				// go to last day of previous year, then walk forward.
				$temp->setDate((int) $temp->format('Y'), 1, 1);
				$temp->modify('- 1 day');

				// Go to first occurrence of the weekday in the year.
				$temp->modify('next ' . self::WEEKDAY_NAMES[$expansion_values[$key]['weekday']] . ' ' . $temp->format('H:i:s e'));

				// Move forward to the requested occurrence.
				if ($expansion_values[$key]['modifier'] > 1) {
					$temp->modify('+ ' . (($expansion_values[$key]['modifier'] - 1) * 7) . ' days');
				}

				if ($temp <= $upperlimit) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit) {
							yield $temp2;
						}
					}
				}
			}
			// Negative modifier means nth last weekday of the year.
			// E.g.: '-2TH' means the second last Thursday.
			elseif ($expansion_values[$key]['modifier'] < 0) {
				// To work nicely with PHP's parsing of 'previous <dayname>',
				// go to first day of next year, then walk backward.
				$temp->setDate((int) $temp->format('Y'), 12, 31);
				$temp->modify('+ 1 day');

				// Go to last occurrence of the weekday in the year.
				$temp->modify('previous ' . self::WEEKDAY_NAMES[$expansion_values[$key]['weekday']] . ' ' . $temp->format('H:i:s e'));

				// Move backward to the requested occurrence.
				if ($expansion_values[$key]['modifier'] < -1) {
					$temp->modify('- ' . ((abs($expansion_values[$key]['modifier']) - 1) * 7) . ' days');
				}

				if ($temp <= $upperlimit) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit) {
							yield $temp2;
						}
					}
				}
			}
			// No modifier means every matching weekday.
			// E.g.: 'TH' means every Thursday.
			else {
				$temp->modify('next ' . self::WEEKDAY_NAMES[$expansion_values[$key]['weekday']] . ' ' . $temp->format('H:i:s e'));

				if ($temp <= $upperlimit) {
					yield $temp;

					foreach ($this->expand($temp, 'byhour') as $temp2) {
						if ($temp2 <= $upperlimit) {
							yield $temp2;
						}
					}
				}
			}

			$key++;
			$key %= count($expansion_values);
		}
	}

	/**
	 * Sorts an array of weekday abbreviations so that $this->rrule->wkst is
	 * always the first item.
	 *
	 * @param array $weekdays An array of weekday abbreviations.
	 * @return array Sorted version of $weekdays.
	 */
	private function sortWeekdays(array $weekdays): array
	{
		$weekday_abbrevs = array_keys(self::WEEKDAY_NAMES);

		while (current($weekday_abbrevs) !== $this->rrule->wkst) {
			$temp = array_shift($weekday_abbrevs);
			$weekday_abbrevs[] = $temp;
		}

		// Handle strings with numerical modifiers correctly.
		$temp = [];

		foreach ($weekdays as $weekday) {
			$temp[substr($weekday, -2)][] = $weekday;
		}

		// Remove weekday abbreviations that aren't in $weekdays.
		$weekday_abbrevs = array_intersect($weekday_abbrevs, array_keys($temp));

		// Rebuild $weekdays.
		$weekdays = [];

		foreach ($weekday_abbrevs as $abbrev) {
			$weekdays = array_merge($weekdays, $temp[$abbrev]);
		}

		return $weekdays;
	}
}

?>