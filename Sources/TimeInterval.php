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
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * Like \DateInterval::__construct(), except that this accepts fractional
	 * values for whatever the smallest unit in $duration is. In other words,
	 * this class supports the complete spec for ISO 8601 durations, not just
	 * a subset of the spec.
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
			// If we have no fractional values, construction is easy.
			parent::__construct($duration);
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

			if ($duration === '') {
				$duration = 'PT0S';
			}

			// Construct.
			parent::__construct($duration);

			// Finally, set the fractional value.
			$this->{$frac['prop']} += $frac['value'];
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
		if (
			str_contains($format, '%a')
			&& $this->days === false
			&& $this->y === 0
			&& $this->m === 0
		) {
			$format = str_replace('%a', '%d', $format);
		}

		return parent::format($format);
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

		if ($this->days !== false) {
			$format .= '%aDT';

			foreach (['h', 'i', 's'] as $prop) {
				if (!empty($this->{$prop}) || ($prop === 's' && !empty($this->f))) {
					$format .= '%' . $prop . ($prop === 'i' ? 'M' : strtoupper($prop));
				}
			}
		} else {
			foreach (['y', 'm', 'd', 'h', 'i', 's'] as $prop) {
				if ($prop === 'h') {
					$format .= 'T';
				}

				if (!empty($this->{$prop}) || ($prop === 's' && !empty($this->f))) {
					$format .= '%' . $prop . ($prop === 'i' ? 'M' : strtoupper($prop));
				}
			}
		}

		$string = rtrim($this->format($format), 'PT');

		if ($string === '') {
			$string = 'PT0S';
		}

		if (!empty($this->f)) {
			$string = preg_replace_callback('/\d+(?=S)/', fn($m) => $m[0] + $this->f, $string);
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

		if ($this->days !== false) {
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
			if (empty($this->{$prop})) {
				continue;
			}

			switch ($prop) {
				case 'invert':
					$result[] = '-';
					break;

				case 'days':
					$result[] = $this->format('%a') . ' ' . $string . ($this->format('%a') > 1 ? 's' : '');
					break;

				default:
					$result[] = $this->format('%' . $prop) . ' ' . $string . ($this->format('%' . $prop) > 1 ? 's' : '');
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
	 * @param array $format_chars Units to include in the output.
	 *    Allowed values in this array: 'y', 'm', 'd', 'a', 'h', 'i', 's', 'f'.
	 *    When 'f' is requested, it will always be combined with 's' in order to
	 *    produce a single float value in the output.
	 *    If 'a' is requested but $this->days is false, then 'y', 'm', 'd' will
	 *    be used instead.
	 *    The order of characters in this array is irrelevant. The requested
	 *    units will always be ordered from largest to smallest in the output.
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

		if (\in_array('a', $format_chars) && !\is_int($this->days)) {
			$format_chars = array_unique(array_merge(
				['y', 'm', 'd'],
				array_diff($format_chars, ['a']),
			));
		}

		foreach ($txt_keys as $c => $k) {
			// Don't include a bunch of useless "0 <unit>" substrings.
			if (empty($this->{$c}) || !\in_array($c, $format_chars)) {
				continue;
			}

			switch ($c) {
				case 'f':
					if (!\in_array('s', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->s + (float) $this->f], file: 'General');
					}
					break;

				case 's':
					if (\in_array('f', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->s + (float) $this->f], file: 'General');
					} else {
						$result[] = Lang::getTxt($txt_keys[$c], [$this->s], file: 'General');
					}
					break;

				default:
					$result[] = Lang::getTxt($txt_keys[$c], [$this->{$c}], file: 'General');
					break;
			}
		}

		// If all requested properties were empty, output a single "0 <unit>"
		// for the smallest unit requested.
		if (empty($result)) {
			foreach (array_reverse($txt_keys) as $c => $k) {
				if (\in_array($c, $format_chars)) {
					$result = [Lang::getTxt($txt_keys[$c], [0], file: 'General')];
					break;
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
		$later->add($this);

		$fmt = !empty($this->f) ? 'U.u' : 'U';

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
