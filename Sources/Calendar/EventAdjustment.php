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
 * Used to apply adjustments to properties of EventOccurrence objects.
 */
class EventAdjustment
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The ID of the first affected occurrence of the event.
	 */
	public string $id;

	/**
	 * @var bool
	 *
	 * Determines whether future event occurrences are affected or not.
	 */
	public bool $affects_future = false;

	/**
	 * @var SMF\TimeInterval
	 *
	 * A TimeInterval object representing how much to the adjust the start of
	 * the affected occurrences compared to their original start date.
	 */
	public ?TimeInterval $offset;

	/**
	 * @var SMF\TimeInterval
	 *
	 * A TimeInterval object representing the duration of the affected
	 * occurrences of the event.
	 */
	public ?TimeInterval $duration;

	/**
	 * @var string
	 *
	 * Location for the affected occurrences of this event.
	 */
	public ?string $location;

	/**
	 * @var string
	 *
	 * Title for the affected occurrences of the event.
	 */
	public ?string $title;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID string of the first affected event occurrence.
	 * @param bool $affects_future Whether future occurrences are also affected.
	 *    Default: false.
	 * @param ?array $offset Array representation of SMF\TimeInterval data.
	 * @param ?array $duration Array representation of SMF\TimeInterval data.
	 * @param ?string $location Location string.
	 * @param ?string $title Title string.
	 */
	public function __construct(
		string $id,
		bool $affects_future = false,
		?array $offset = null,
		?array $duration = null,
		?string $location = null,
		?string $title = null,
	) {
		$this->id = $id;
		$this->affects_future = $affects_future;

		foreach (['offset', 'duration'] as $prop) {
			if (!isset(${$prop})) {
				continue;
			}

			if (!empty(${$prop}['f'])) {
				${$prop}['s'] += ${$prop}['f'];
				unset(${$prop}['f']);
			}

			$string = 'P';

			foreach (['y', 'm', 'd', 'h', 'i', 's'] as $key) {
				if ($key === 'h') {
					$string .= 'T';
				}

				if (!empty(${$prop}[$key])) {
					$string .= ${$prop}[$key] . ($key === 'i' ? 'M' : strtoupper($key));
				}
			}

			$this->{$prop} = new TimeInterval(rtrim($string, 'PT'));
			$this->{$prop}->invert = (int) ${$prop}['invert'];
		}

		$this->location = $location;
		$this->title = $title;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a batch of EventAdjustment instances from JSON data.
	 *
	 * @param string $json JSON string containing a batch of adjustment data.
	 * @return array Instances of this class.
	 */
	public static function createBatch(string $json): array
	{
		$adjustments = [];

		foreach (json_decode($json, true) as $key => $value) {
			$key = $value['id'] ?? $key;

			$adjustments[$key] = new self(
				$key,
				$value['affects_future'] ?? false,
				$value['offset'] ?? null,
				$value['duration'] ?? null,
				$value['location'] ?? null,
				$value['title'] ?? null,
			);
		}

		ksort($adjustments);

		return $adjustments;
	}
}

?>