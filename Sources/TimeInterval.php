<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

/**
 * Extends \DateInterval with some extra features for SMF.
 */
class TimeInterval extends \DateInterval implements \Stringable
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * Number of years.
	 */
	public int $y {
		get => $this->base->y ?? 0;
	}

	/**
	 * @var int
	 *
	 * Number of months.
	 */
	public int $m {
		get => $this->base->m ?? 0;
	}

	/**
	 * @var int
	 *
	 * Number of days.
	 */
	public int $d {
		get => $this->base->d ?? 0;
	}

	/**
	 * @var int
	 *
	 * Number of hours.
	 */
	public int $h {
		get => $this->base->h ?? 0;
	}

	/**
	 * @var int
	 *
	 * Number of minutes.
	 */
	public int $i {
		get => $this->base->i ?? 0;
	}

	/**
	 * @var int
	 *
	 * Number of seconds.
	 */
	public int $s {
		get => $this->base->s ?? 0;
	}

	/**
	 * @var float
	 *
	 * Number of microseconds, as a fraction of a second.
	 */
	public float $f {
		get => $this->base->f ?? 0.0;
	}

	/**
	 * @var int
	 *
	 * Is 1 if the interval represents a negative time period and 0 otherwise.
	 */
	public int $invert {
		get => $this->base->invert ?? 0;
	}

	/**
	 * @var mixed
	 *
	 * Total number of days in the interval, or false if unknown.
	 */
	public mixed $days {
		get => $this->base->days;
	}

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var \DateInterval
	 *
	 * Underlying \DateInterval instance.
	 *
	 * The \DateInterval class has some strange quirks that make it difficult
	 * to extend in the normal fashion. Most notably, the only reliable way to
	 * work with $this->days in an extending class is to internally store a
	 * \DateInterval instance and then use property hooks to access the stored
	 * instance's data.
	 */
	private \DateInterval $base;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * Like \DateInterval::__construct(), with the following changes:
	 *
	 *  - Accepts fractional values for whatever the smallest unit in $duration
	 *    is. In other words, this class supports the complete spec for ISO 8601
	 *    durations, not just a subset of the spec.
	 *
	 *  - If $duration specifies only days, hours, minutes, and/or seconds, then
	 *    the days property will be set to the appropriate integer value. For
	 *    example, if $duration is 'P45D', then $this->days will be set to 45.
	 *    This differs from \DateInterval, where the days property is only set
	 *    if the \DateInterval object was created by \DateTimeInterface::diff().
	 *
	 * @param string $duration An ISO 8601 duration string.
	 */
	public function __construct(string $duration)
	{
		// First, check for duration string (e.g. 'P1DT2H').
		// Matches could be just 'P' if alt format was used.
		$valid = preg_match('/P(?:(?P<y>[\d\.]+)Y)?(?:(?P<m>[\d\.]+)M)?(?:(?P<w>[\d\.]+)W)?(?:(?P<d>[\d\.]+)D)?(?:T(?:(?P<h>[\d\.]+)H)?(?:(?P<i>[\d\.]+)M)?(?:(?P<s>[\d\.]+)S)?)?/', $duration, $matches) && $matches[0] !== 'P';

		// Next, check for alt format (e.g. 'P0000-00-01T02:00:00').
		if (!$valid) {
			$valid = preg_match('/P(?P<y>\d+)-?(?P<m>\d+)-?(?P<w>\d+)-?(?P<d>\d+)T(?P<h>\d+):?(?P<i>\d+):?(?P<s>[\d\.]+)/', $duration, $matches);
		}

		if (!$valid) {
			throw new \ValueError();
		}

		// Clean up $matches.
		$matches = array_map(
			// Quick way to cast to int or float without extra logic.
			fn($v) => $v + 0,
			// Filter out the stuff we don't need.
			array_filter(
				$matches,
				fn($v, $k) => !\is_int($k) && $v !== '',
				ARRAY_FILTER_USE_BOTH,
			),
		);

		// For simplicity, convert weeks to days.
		if (!empty($matches['w'])) {
			$matches['d'] = ($matches['d'] ?? 0) + $matches['w'] * 7;
			unset($matches['w']);
		}

		// Figure out if we have any fractional values.
		$frac = [
			'prop' => null,
			'value' => null,
		];

		$props = [
			's' => [
				'frac_prop' => 'f',
				'multiplier' => 1,
				'unit' => 'S',
			],
			'i' => [
				'frac_prop' => 's',
				'multiplier' => 60,
				'unit' => 'M',
			],
			'h' => [
				'frac_prop' => 'i',
				'multiplier' => 60,
				'unit' => 'H',
			],
			'd' => [
				'frac_prop' => 'h',
				'multiplier' => 24,
				'unit' => 'D',
			],
			'm' => [
				'frac_prop' => 'd',
				// This is calibrated so that 'P0.5M' means 'P15D' but 'P0.99M' means 'P28D'.
				'multiplier' => !isset($matches['m']) || fmod($matches['m'], 1.0) <= 0.5 ? 30 : 32 - (4 * fmod($matches['m'], 1.0)),
				'unit' => 'M',
			],
			'y' => [
				'frac_prop' => 'm',
				'multiplier' => 12,
				'unit' => 'Y',
			],
		];

		$can_be_fractional = true;

		foreach ($props as $prop => $info) {
			if (!isset($matches[$prop])) {
				continue;
			}

			if (\is_float($matches[$prop])) {
				if (!$can_be_fractional) {
					throw new \ValueError();
				}

				$frac['prop'] = $info['frac_prop'];
				$frac['value'] = round(($matches[$prop] - (int) $matches[$prop]) * $info['multiplier'], 6);

				$matches[$prop] = (int) $matches[$prop];
			}

			// ISO 8601 only allows the smallest provided unit to be fractional.
			$can_be_fractional = false;
		}

		if (!isset($frac['prop'])) {
			// If we have no fractional values, creating the base object is easy.
			$this->base = new parent($duration);
		} else {
			// Rebuild $duration without the fractional value.
			$duration = 'P';

			foreach (array_reverse($props) as $prop => $info) {
				if ($prop === 'h') {
					$duration .= 'T';
				}

				if (!empty($matches[$prop])) {
					$duration .= $matches[$prop] . $info['unit'];
				}
			}

			$duration = rtrim($duration, 'PT');

			// Create the base object.
			$this->base = new parent($duration);

			// Finally, set the fractional value.
			$this->base->{$frac['prop']} += $frac['value'];
		}

		// If possible, set the value of $this->base->days.
		if (empty($matches['y']) && empty($matches['m'])) {
			$now = new \DateTimeImmutable();
			$this->base = $now->diff($now->add($this->base));
		}
	}

	/**
	 * Formats the object as a string.
	 *
	 * @param string $format The format string.
	 * @return string The formatted value.
	 */
	public function format(string $format): string
	{
		return $this->base->format($format);
	}

	/**
	 * Formats the object as a string so it can be reconstructed later.
	 *
	 * @return string A ISO 8601 duration string suitable for reconstructing
	 *    this object.
	 */
	public function __toString(): string
	{
		$format = 'P';

		if ($this->base->days !== false) {
			$format .= '%aDT';

			foreach (['h', 'i', 's'] as $prop) {
				if (!empty($this->{$prop}) || ($prop === 's' && !empty($this->base->f))) {
					$format .= '%' . $prop . ($prop === 'i' ? 'M' : strtoupper($prop));
				}
			}
		} else {
			foreach (['y', 'm', 'd', 'h', 'i', 's'] as $prop) {
				if ($prop === 'h') {
					$format .= 'T';
				}

				if (!empty($this->{$prop}) || ($prop === 's' && !empty($this->base->f))) {
					$format .= '%' . $prop . ($prop === 'i' ? 'M' : strtoupper($prop));
				}
			}
		}

		$string = rtrim($this->base->format($format), 'PT');

		if ($string === '') {
			$string = 'PT0S';
		}

		if (!empty($this->base->f)) {
			$string = preg_replace_callback('/\d+(?=S)/', fn($m) => $m[0] + $this->base->f, $string);
		}

		return $string;
	}

	/**
	 * Formats the object as a string that can be parsed by strtotime().
	 *
	 * @return string A strtotime parsable string suitable for reconstructing
	 *    this object.
	 */
	public function toParsable(): string
	{
		$result = [];

		if ($this->base->days !== false) {
			$props = [
				'invert' => null,
				'days' => 'day',
				'h' => 'hour',
				'i' => 'minute',
				's' => 'second',
				'f' => 'microsecond',
			];
		} else {
			$props = [
				'invert' => null,
				'y' => 'year',
				'm' => 'month',
				'd' => 'day',
				'h' => 'hour',
				'i' => 'minute',
				's' => 'second',
				'f' => 'microsecond',
			];
		}

		foreach ($props as $prop => $string) {
			if (empty($this->base->{$prop})) {
				continue;
			}

			switch ($prop) {
				case 'invert':
					$result[] = '-';
					break;

				case 'days':
					$result[] = $this->base->format('%a') . ' ' . $string . ($this->base->format('%a') > 1 ? 's' : '');
					break;

				default:
					$result[] = $this->base->format('%' . $prop) . ' ' . $string . ($this->base->format('%' . $prop) > 1 ? 's' : '');
					break;
			}
		}

		if (empty($result)) {
			$result[] = '0 seconds';
		}

		return implode(' ', $result);
	}

	/**
	 * Formats the interval as a human-readable string in the current user's
	 * language.
	 *
	 * @param array $format_chars Properties to include in the output.
	 *    Allowed values in this array: 'y', 'm', 'd', 'h', 'i', 's', 'f', 'a'.
	 *    Note that when 'f' is included, it will always be combined with 's' in
	 *    order to produce a single float value in the output.
	 * @return string A human-readable string.
	 */
	public function localize(array $format_chars = ['y', 'm', 'd']): string
	{
		$result = [];

		$txt_keys = [
			'y' => 'number_of_years',
			'm' => 'number_of_months',
			'd' => 'number_of_days',
			'a' => 'number_of_days',
			'h' => 'number_of_hours',
			'i' => 'number_of_minutes',
			's' => 'number_of_seconds',
			'f' => 'number_of_seconds',
		];

		foreach ($format_chars as $c) {
			// Don't include a bunch of useless "0 <unit>" substrings.
			if (empty($this->base->{$c}) || !isset($txt_keys[$c])) {
				continue;
			}

			switch ($c) {
				case 'f':
					if (!\in_array('s', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->base->s + (float) $this->base->f], file: 'General');
					}
					break;

				case 's':
					if (\in_array('f', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->base->s + (float) $this->base->f], file: 'General');
					} else {
						$result[] = Lang::getTxt($txt_keys[$c], [$this->base->s], file: 'General');
					}
					break;

				default:
					$result[] = Lang::getTxt($txt_keys[$c], [$this->base->{$c}], file: 'General');
					break;
			}
		}

		// If all requested properties were empty, output a single "0 <unit>"
		// for the smallest unit requested.
		if (empty($result)) {
			foreach ($txt_keys as $c => $k) {
				if (\in_array($c, $format_chars)) {
					$result = [Lang::getTxt($txt_keys[$c], [0], file: 'General')];
				}
			}
		}

		return Lang::sentenceList($result);
	}

	/**
	 * Converts this interval to a number of seconds.
	 *
	 * Because months have variable lengths, leap years exist, etc., it is
	 * necessary to provide a reference date that the interval will measure
	 * from in order to calculate the exact number of seconds.
	 *
	 * @param \DateTimeInterface $when Reference date that this interval will
	 *    be added to in order to calculate the exact number of seconds.
	 * @return int|float Number of seconds in this interval, counting from the
	 *    reference date.
	 */
	public function toSeconds(\DateTimeInterface $when): int|float
	{
		$later = \DateTime::createFromInterface($when);
		$later->add($this->base);

		$fmt = !empty($this->base->f) ? 'U.u' : 'U';

		return ($later->format($fmt) - $when->format($fmt));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convert a \DateInterval object into a TimeInterval object.
	 *
	 * @param \DateInterval $interval A \DateInterval object.
	 * @return TimeInterval A TimeInterval object.
	 */
	public static function createFromDateInterval(\DateInterval $interval): static
	{
		return new self($interval->format('P' . ($interval->days !== false ? '%aD' : '%yY%mM%dD') . 'T%hH%iM%s.%FS'));
	}

	/**
	 * Creates a TimeInterval object from the relative parts of a string that
	 * can be parsed by strtotime().
	 *
	 * @param string $datetime A string that can be parsed by strtotime().
	 * @return TimeInterval A TimeInterval object.
	 */
	public static function createFromDateString(string $datetime): static
	{
		// Create the basic \DateInterval.
		$interval = parent::createFromDateString($datetime);

		// \DateInterval::createFromDateString() doesn't populate the $days
		// property. But since $datetime must contain relative date values,
		// we may be able coerce it into one that does have a value for $days.
		// This only works reliably if the year and month values are empty.
		if ($interval->format('%y') === '0' && $interval->format('%m') === '0') {
			$now = new \DateTimeImmutable();
			$interval = $now->diff($now->add($interval));
		}

		return self::createFromDateInterval($interval);
	}
}
