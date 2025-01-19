<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
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
	 * Like \DateInterval::__construct(), except that it can accept fractional
	 * values for whatever the smallest unit is. In other words, it supports the
	 * complete spec for ISO 8601 durations, not just a subset of the spec.
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
				fn($v, $k) => !is_int($k) && $v !== '',
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

			if (is_float($matches[$prop])) {
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

			// Construct.
			parent::__construct(rtrim($duration, 'PT'));

			// Finally, set the fractional value.
			$this->{$frac['prop']} += $frac['value'];
		}
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

		foreach (['y', 'm', 'd', 'h', 'i', 's'] as $prop) {
			if ($prop === 'h') {
				$format .= 'T';
			}

			if (!empty($this->{$prop}) || ($prop === 's' && !empty($this->f))) {
				$format .= '%' . $prop . ($prop === 'i' ? 'M' : strtoupper($prop));
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

		$props = [
			'invert' => null,
			'y' => 'years',
			'm' => 'months',
			'd' => 'days',
			'h' => 'hours',
			'i' => 'minutes',
			's' => 'seconds',
			'f' => 'microseconds',
		];

		foreach ($props as $prop => $string) {
			if (empty($this->{$prop})) {
				continue;
			}

			switch ($prop) {
				case 'invert':
					$result[] = '-';
					break;

				default:
					$result[] = $this->format('%' . $prop) . ' ' . $string;
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
			if (empty($this->{$c}) || !isset($txt_keys[$c])) {
				continue;
			}

			switch ($c) {
				case 'f':
					if (!in_array('s', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->s + (float) $this->f]);
					}
					break;

				case 's':
					if (in_array('f', $format_chars)) {
						$result[] = Lang::getTxt($txt_keys[$c], [(float) $this->s + (float) $this->f]);
					} else {
						$result[] = Lang::getTxt($txt_keys[$c], [$this->s]);
					}
					break;

				default:
					$result[] = Lang::getTxt($txt_keys[$c], [$this->{$c}]);
					break;
			}
		}

		// If all requested properties were empty, output a single "0 <unit>"
		// for the smallest unit requested.
		if (empty($result)) {
			foreach ($txt_keys as $c => $k) {
				if (in_array($c, $format_chars)) {
					$result = [Lang::getTxt($txt_keys[$c], [0])];
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
	 * @param string $object A \DateInterval object.
	 * @return TimeInterval A TimeInterval object.
	 */
	public static function createFromDateInterval(\DateInterval $object): static
	{
		$new = new TimeInterval('P0D');

		foreach (['y', 'm', 'd', 'h', 'i', 's', 'f', 'invert'] as $prop) {
			$new->{$prop} = $object->{$prop};
		}

		return $new;
	}
}

?>