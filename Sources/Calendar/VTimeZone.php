<?php

/**
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

namespace SMF\Calendar;

/**
 * Represents iCalendar time zone data, which RFC 5545 labels as a VTIMEZONE.
 *
 * Primarily used for building VTIMEZONE data that must be included in exported
 * iCalendar documents in order to conform to RFC 5545.
 */
abstract class VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid;

	/**
	 * @var array
	 *
	 * Data about which metazone label to use for this time zone at any given
	 * date and time.
	 */
	public array $metazones;

	/**
	 * @var array
	 *
	 * Data for the VTIMEZONE components.
	 */
	public array $components;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Builds an iCalendar component for this time zone.
	 *
	 * @param \DateTimeInterface $range_start The earliest date for which to get
	 *    time zone data.
	 * @param \DateTimeInterface $range_end The latest date for which to get
	 *    time zone data.
	 * @return string A VTIMEZONE component for an iCalendar document.
	 */
	public function export(\DateTimeInterface $range_start, \DateTimeInterface $range_end): string
	{
		$filecontents = [
			'BEGIN:VTIMEZONE',
			'TZID:' . $this->tzid,
		];

		$included = [];

		foreach ($this->components as $component) {
			$dtstart = new \DateTimeImmutable($component['DTSTART'], new \DateTimeZone($this->tzid));

			// Stop if this component starts after the end of the requested range.
			if ($range_end < $dtstart) {
				break;
			}

			// Skip if this component ends before the start of the requested range.
			if (
				isset($component['RRULE'])
				&& str_contains($component['RRULE'], ';UNTIL=')
				&& $range_start > new \DateTimeImmutable(substr($component['RRULE'], strpos($component['RRULE'], ';UNTIL=') + 7), new \DateTimeZone($this->tzid))
			) {
				continue;
			}

			// Exclude any unnecessary one-time components from before the range start.
			if ($dtstart < $range_start) {
				foreach ($included as $key => $inc) {
					if (
						$inc['type'] === $component['type']
						&& !isset($inc['RRULE'])
						&& $dtstart > new \DateTimeImmutable($inc['DTSTART'], new \DateTimeZone($this->tzid))
					) {
						unset($included[$key]);
					}
				}
			}

			$included[] = $component;
		}

		// Build the file contents.
		foreach ($included as $component) {
			$filecontents[] = 'BEGIN:' . $component['type'];

			foreach ($component as $prop => $value) {
				if ($prop !== 'type') {
					$filecontents[] = $prop . ':' . $value;
				}
			}

			$filecontents[] = 'END:' . $component['type'];
		}

		$filecontents[] = 'END:VTIMEZONE';

		return implode("\r\n", $filecontents);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads an instance of this class for the specified time zone identifier.
	 *
	 * @param string $tzid A time zone identifier string.
	 * @throws \ValueError if $tzid is not a valid time zone identifier.
	 * @return self An instance of this class for the time zone.
	 */
	public static function load(string $tzid): self
	{
		$class = self::getClassFromTzid($tzid);

		if (!class_exists($class)) {
			throw new \ValueError();
		}

		return new $class();
	}

	/**
	 * Checks whether a VTimeZone class exists for the specified time zone.
	 *
	 * @param string $tzid A time zone identifier string.
	 * @return bool Whether a VTimeZone class exists for the time zone.
	 */
	public static function exists(string $tzid): bool
	{
		return class_exists(self::getClassFromTzid($tzid));
	}

	/**
	 * Gets the fully qualified class name of a VTimeZone class for the given
	 * time zone identifier.
	 *
	 * @param string $tzid A time zone identifier string.
	 * @return string The fully qualified class name for the time zone.
	 */
	public static function getClassFromTzid(string $tzid): string
	{
		return __NAMESPACE__ . '\\VTimeZones\\' . strtr($tzid, ['/' => '\\', '+' => '', '-' => '_']);
	}
}
