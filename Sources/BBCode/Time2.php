<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Time;
use SMF\TimeInterval;
use SMF\Utils;

/**
 * Represents the unparsed_content version of the time BBCode.
 */
class Time2 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'time';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<time class="bbc_time" datetime="$1">$1</time>';

	/**
	 *
	 */
	public ?string $disabled_content = '$1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$text = $date = $data;

		// Get sanitized versions of the data.
		$sanitized_text = $sanitized_date = ltrim(Time::sanitize(Time::convertToEnglish($date)), '@');

		// Special case if $date is actually a duration string.
		if (preg_match('/^P((\d+D)(T(\d+[HMS])+)?|T(\d+[HMS])+)$/', $date)) {
			$bbc->content = '<time class="bbc_time" datetime="' . $date . '">$1</time>';

			return;
		}

		if (preg_match('/^\d+\.?\d*\s+(year|month|week|day|hour|minute|second)s?$/', $sanitized_date)) {
			try {
				$duration = TimeInterval::createFromDateInterval(\DateInterval::createFromDateString($sanitized_date));

				$bbc->content = '<time class="bbc_time" datetime="' . (string) $duration . '">$1</time>';

				return;
			} catch (\Throwable $e) {
			}
		}

		// Special handling for [time=absolute]...[/time]
		if ($date === 'absolute') {
			if ($sanitized_text === '') {
				$bbc->content = '<span class="bbc_time">$1</span>';

				return;
			}

			$time = Time::create((is_numeric($sanitized_text) ? '@' : '') . $sanitized_text);

			$when = !empty((int) $time->format('u')) ? $time->format('Y-m-d\TH:i:s.uP') : $time->format('Y-m-d\TH:i:sP');

			if (is_numeric(ltrim($text, '@'))) {
				$text = Utils::normalizeSpaces(Utils::entityDecode($time->format(null, false)), true, true, ['collapse_hspace' => true]);
			}
		}

		// If there is a valid date value already, use it.
		if (!isset($when) && (is_numeric($sanitized_date) || (!empty($sanitized_date) && strtotime($sanitized_date) !== false))) {
			$when = $sanitized_date;
		}

		// Special handling for month and day with no year.
		if (!isset($when) && preg_match('/^(0?\d|1[0-2])-(0?\d|[12]\d|3[01])$/', $date, $matches)) {
			$when = sprintf('%1$02d-%2$02d', $matches[1], $matches[2]);

			$bbc->content = '<time class="bbc_time" datetime="' . $when . '">$1</time>';

			return;
		}

		// If we have a text value, try that.
		if (!isset($when) && (is_numeric($sanitized_text) || (!empty($sanitized_text) && strtotime($sanitized_text) !== false))) {
			$when = $sanitized_text;
		}

		// PHP's date parser gets confused by AM/PM combined with fractional seconds.
		if (
			!isset($when)
			&& preg_match('/[AaPp]\.?[Mm]\.?/', $sanitized_text, $matches)
			&& preg_match('/\d\.\d+/', $sanitized_text)
		) {
			$parsed = date_parse($sanitized_text);

			if (is_int($parsed['hour']) && is_int($parsed['minute'])) {
				if (
					$parsed['hour'] === 12
					&& str_starts_with(strtolower($matches[0]), 'a')
				) {
					$sanitized_text = preg_replace(
						'~(?<!:)12:' . sprintf('%02d', $parsed['minute']) . '~',
						'00:' . sprintf('%02d', $parsed['minute']),
						$sanitized_text,
					);
				} elseif (
					$parsed['hour'] < 12
					&& $parsed['hour'] > 0
					&& str_starts_with(strtolower($matches[0]), 'p')
				) {
					$sanitized_text = preg_replace(
						'~(?<!:)0?' . $parsed['hour'] . ':' . sprintf('%02d', $parsed['minute']) . '~',
						($parsed['hour'] + 12) . ':' . sprintf('%02d', $parsed['minute']),
						$sanitized_text,
					);
				}

				$sanitized_text = str_replace($matches[0], '', $sanitized_text);

				if (strtotime($sanitized_text) !== false) {
					$when = $sanitized_text;
				}
			}
		}

		// Found nothing parsable.
		if (!isset($when)) {
			$bbc->content = '<span class="bbc_time">$1</span>';

			return;
		}

		// We have a (probably) parsable value, so try to work with it.
		try {
			if (is_numeric($when)) {
				$time = Time::create('@' . $when);

				// Get the formatted value for the datetime attribute.
				$when = !empty((int) $time->format('u')) ? $time->format('Y-m-d\TH:i:s.uP') : $time->format('Y-m-d\TH:i:sP');

				// Replace the raw Unix timestamp with something more pleasant.
				$text = Utils::normalizeSpaces(Utils::entityDecode($time->format(null, false)), true, true, ['collapse_hspace' => true]);
			} else {
				// Parse the date.
				$parsed = date_parse($when);

				// Is this time implied to be a 12 hour post-meridian time?
				// E.g.: "today at 2:30" with no AM or PM usually means 2:30 PM
				// in locales that use 12 hour time.
				if (
					// Time is between 1:00 and 7:59.
					is_int($parsed['hour'])
					&& $parsed['hour'] > 0
					&& $parsed['hour'] < 8
					// No explicit AM or PM was given.
					&& !preg_match('/[AaPp]\.?[Mm]\.?/', $when)
					// The hour was given with a single digit (no preceeding zero).
					&& !preg_match('/(?<!:)0' . $parsed['hour'] . '\b/', $when)
					// The user's time format normally expects 12-hour time.
					&& preg_match(Time::isStrftimeFormat(Time::getTimeFormat()) ? '/%[Il]/' : '/[gh]/', Time::getTimeFormat())
				) {
					// Update $when to add 12 hours.
					$when = preg_replace('/(?<!:)' . $parsed['hour'] . '\b/', (string) ($parsed['hour'] + 12), $when);

					// Re-parse.
					$parsed = date_parse($when);
				}

				$time = Time::create($when);

				// Special handling for relative dates.
				if (
					$parsed['year'] === false
					&& $parsed['month'] === false
					&& $parsed['day'] === false
				) {
					$temp = strtolower($when);

					$relative_date_words = [
						'yesterday', 'midnight', 'today', 'tomorrow', 'sun',
						'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'day', 'week',
						'fortnight', 'forthnight', 'month', 'year',
					];

					foreach ($relative_date_words as $word) {
						if (str_contains($temp, $word)) {
							$parsed['year'] = (int) $time->format('Y');

							if ($word !== 'year') {
								$parsed['month'] = (int) $time->format('m');

								if ($word !== 'month') {
									$parsed['day'] = (int) $time->format('d');
								}
							}

							if (
								$parsed['hour'] === 0
								&& $parsed['minute'] === 0
								&& $parsed['second'] === 0
								&& $parsed['fraction'] === 0.0
							) {
								$parsed['hour'] = false;
								$parsed['minute'] = false;
								$parsed['second'] = false;
								$parsed['fraction'] = false;
							}

							if ($word !== 'year' && $word !== 'month') {
								break;
							}
						}
					}
				}

				// If any time parts are populated, populate any missing ones.
				if (
					!is_bool($parsed['hour'])
					|| !is_bool($parsed['minute'])
					|| !is_bool($parsed['second'])
					|| !is_bool($parsed['fraction'])
					|| $parsed['is_localtime']
				) {
					$parsed['hour'] = (int) $parsed['hour'];
					$parsed['minute'] = (int) $parsed['minute'];
					$parsed['second'] = (int) $parsed['second'];

					// If we have time parts and at least some date parts,
					// populate all the date parts.
					if (
						$parsed['year'] !== false
						|| $parsed['month'] !== false
						|| $parsed['day'] !== false
					) {
						$temp = $time;

						if ($time < date_create('now')) {
							if ($parsed['year'] === false) {
								$temp->modify('+1 year');
							} elseif ($parsed['month'] === false) {
								$temp->modify('+1 month');
							} elseif ($parsed['day'] === false) {
								$temp->modify('+1 day');
							}
						}

						$parsed['year'] = (int) ($parsed['year'] || $temp->format('Y'));
						$parsed['month'] = (int) ($parsed['month'] || $temp->format('m'));
						$parsed['day'] = (int) ($parsed['day'] || $temp->format('d'));
					}
				}

				// Special case for a year and month with no day of the month.
				if (
					$parsed['year'] !== false
					&& $parsed['month'] !== false
					&& $parsed['day'] === 1
					&& $parsed['hour'] === false
					&& $parsed['minute'] === false
					&& $parsed['second'] === false
					&& $parsed['fraction'] === false
					&& $parsed['is_localtime'] === false
					&& !preg_match('/sun|mon|tue|wed|thu|fri|sat/i', $when)
				) {
					$month_names = [
						1 => 'jan(uary)?',
						2 => 'feb(ruary)?',
						3 => 'mar(ch)?',
						4 => 'apr(il)?',
						5 => 'may',
						6 => 'jun(e)?',
						7 => 'jul(y)?',
						8 => 'aug(ust)?',
						9 => 'sep(t(ember)?)?',
						10 => 'oct(ober)?',
						11 => 'nov(ember)?',
						12 => 'dec(ember)?',
					];

					$temp = str_replace((string) $parsed['year'], '', $when);

					if (preg_match('/' . $month_names[$parsed['month']] . '/', $temp)) {
						$temp = preg_replace('/' . $month_names[$parsed['month']] . '/', '', $temp);
					} else {
						$temp = preg_replace('/0?' . $parsed['month'] . '/', '', $temp, 1);
					}

					if (!str_contains((string) $parsed['day'], $when)) {
						$parsed['day'] = false;
					}
				}

				// Now figure out the appropriate format for the datetime attribute.
				$key_chars = [
					'year' => 'Y',
					'month' => 'm',
					'day' => 'd',
					'hour' => 'H',
					'minute' => 'i',
					'second' => 's',
					'fraction' => 'u',
					'is_localtime' => 'P',
				];

				foreach ($key_chars as $key => $char) {
					$fmt[$char] = $parsed[$key] === false ? false : $char;
				}

				$fmt_string = implode('-', array_filter(
					array_slice($fmt, 0, 3),
					fn($arg) => $arg !== false,
				));

				if ($fmt_string !== '' && $fmt['H'] !== false) {
					$fmt_string .= '\\T';
				}

				$fmt_string .= implode(':', array_filter(
					array_slice($fmt, 3, 3),
					fn($arg) => $arg !== false,
				));

				if ($fmt['u'] !== false && !empty((int) $time->format('u'))) {
					$fmt_string .= (empty($fmt_string) ? '0' : '') . '.u';
				}

				if ($fmt_string !== '' && $fmt['P'] !== false) {
					$fmt_string .= 'P';
				}

				if ($fmt_string === '') {
					$fmt_string = 'Y-m-d\TH:i:sP';
				}

				// Get the formatted value for the datetime attribute.
				$when = $time->format($fmt_string);
			}

			$bbc->content = '<time class="bbc_time" datetime="' . $when . '">$1</time>';
			$data = $text;
		} catch (\Throwable $e) {
			$bbc->content = '<span class="bbc_time">$1</span>';
		}
	}
}

?>