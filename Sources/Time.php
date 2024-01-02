<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * Extends \DateTime with some extra features for SMF.
 */
class Time extends \DateTime implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'create' => 'create',
			'strftime' => 'smf_strftime',
			'gmstrftime' => 'smf_gmstrftime',
			'getDateOrTimeFormat' => 'get_date_or_time_format',
			'timeformat' => 'timeformat',
			'forumTime' => 'forum_time',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Used for translating strftime format to DateTime format.
	 *
	 * Keys are strftime format specifiers, without the leading '%'.
	 * Values are DateTime format specifiers, some of which will need further
	 * processing.
	 *
	 * Note: %c produces locale-specific output in the original strftime
	 * library, but in this class its output will always use ISO 8601 format.
	 * This is due to the lack of locale support in the base DateTime class.
	 */
	public const FORMAT_EQUIVALENTS = [
		// Day
		'a' => 'D', // Complex: prefer Lang::$txt strings if available.
		'A' => 'l', // Complex: prefer Lang::$txt strings if available.
		'e' => 'j', // Complex: sprintf to prepend whitespace.
		'd' => 'd',
		'j' => 'z', // Complex: must add one and then sprintf to prepend zeros.
		'u' => 'N',
		'w' => 'w',
		// Week
		'U' => 'z_w_0', // Complex: calculated from these other values.
		'V' => 'W',
		'W' => 'z_w_1', // Complex: calculated from these other values.
		// Month
		'b' => 'M', // Complex: prefer Lang::$txt strings if available.
		'B' => 'F', // Complex: prefer Lang::$txt strings if available.
		'm' => 'm',
		// Year
		'C' => 'Y', // Complex: Get 'Y' then truncate to first two digits.
		'g' => 'o', // Complex: Get 'o' then truncate to last two digits.
		'G' => 'o', // Complex: Get 'o' then sprintf to ensure four digits.
		'y' => 'y',
		'Y' => 'Y',
		// Time
		'H' => 'H',
		'k' => 'G',
		'I' => 'h',
		'l' => 'g', // Complex: sprintf to prepend whitespace.
		'M' => 'i',
		'p' => 'A', // Complex: prefer Lang::$txt strings if available.
		'P' => 'a', // Complex: prefer Lang::$txt strings if available.
		'S' => 's',
		'z' => 'O',
		'Z' => 'T',
		// Time and Date Stamps
		'c' => 'c',
		's' => 'U',
		// Miscellaneous
		'n' => "\n",
		't' => "\t",
		'%' => '%',
	];

	/**
	 * Makes life easier when translating strftime format to DateTime format.
	 *
	 * Keys are short strftime formats. Values are expanded strftime formats.
	 *
	 * Note: %x and %X produce locale-specific output in the original strftime
	 * library, but in this class their output will always use ISO 8601 format.
	 * This is due to the lack of locale support in the base DateTime class.
	 */
	public const FORMAT_SHORT_FORMS = [
		'%h' => '%b',
		'%r' => '%I:%M:%S %p',
		'%R' => '%H:%M',
		'%T' => '%H:%M:%S',
		'%X' => '%H:%M:%S',
		'%D' => '%m/%d/%y',
		'%F' => '%Y-%m-%d',
		'%x' => '%Y-%m-%d',
	];

	/**
	 * A regular expression to match all known strftime format specifiers.
	 */
	public const REGEX_STRFTIME = '%([ABCDFGHIMPRSTUVWXYZabcdeghjklmnprstuwxyz%])';

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * \DateTimeZone instance for the user's time zone.
	 */
	protected static object $user_tz;

	/**
	 * @var string
	 *
	 * Short version of user's preferred time format.
	 */
	protected static string $short_time_format;

	/**
	 * @var string
	 *
	 * Short version of user's preferred date format, with the year.
	 */
	protected static string $short_date_format;

	/**
	 * @var string
	 *
	 * Short version of user's preferred date format, without the year.
	 */
	protected static string $date_format_no_year;

	/**
	 * @var array
	 *
	 * Processed date and time format strings.
	 */
	protected static array $formats = [];

	/**
	 * @var array
	 *
	 * Timestamps for today at midnight according to different time zones.
	 */
	protected static array $today;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * This is similar to the \DateTime constructor, with the following changes:
	 *
	 *  - The second parameter will not be ignored if the first parameter is a
	 *    Unix timestamp.
	 *
	 *  - The default time zone is the user's time zone, rather than the
	 *    system's default time zone.
	 *
	 *  - The second parameter can be a \DateTimeZone object or a valid time
	 *    zone identifier string. If a string is passed and that string is not a
	 *    valid time zone identifier, it will be silently discarded in favour of
	 *    the current user's time zone.
	 *
	 * @param string $datetime A date/time string that PHP can understand, or a
	 *    Unix timestamp.
	 * @param \DateTimeZone|string $timezone The time zone of $datetime, either
	 *    as a \DateTimeZone object or as a time zone identifier string.
	 *    Defaults to the current user's time zone.
	 */
	public function __construct(string $datetime = 'now', \DateTimeZone|string|null $timezone = null)
	{
		if (!isset(self::$user_tz)) {
			self::$user_tz = new \DateTimeZone(User::getTimezone());
		}

		if (is_string($timezone) && ($timezone = @timezone_open($timezone)) === false) {
			unset($timezone);
		}

		parent::__construct($datetime, $timezone ?? self::$user_tz);

		// If $datetime was a Unix timestamp, force the time zone to be the one we were told to use.
		// Honestly, it's a mystery why the \DateTime class doesn't do this itself already...
		if (str_starts_with($datetime, '@')) {
			$this->setTimezone($timezone ?? self::$user_tz);
		}
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		switch ($prop) {
			case 'datetime':
			case 'date':
			case 'time':
			case 'date_orig':
			case 'time_orig':
				$this->modify($value);
				break;

			case 'date_local':
			case 'time_local':
				$tz = $this->getTimezone();
				$this->setTimezone(self::$user_tz);
				$this->modify($value);
				$this->setTimezone($tz);
				break;

			case 'year':
				$this->setDate(
					(int) $value,
					$this->format('m', false, false),
					$this->format('d', false, false),
				);
				break;

			case 'month':
				$this->setDate(
					$this->format('Y', false, false),
					(int) $value,
					$this->format('d', false, false),
				);
				break;

			case 'day':
				$this->setDate(
					$this->format('Y', false, false),
					$this->format('m', false, false),
					(int) $value,
				);
				break;

			case 'hour':
				$this->setTime(
					(int) $value,
					$this->format('i', false, false),
					$this->format('s', false, false),
				);
				break;

			case 'minute':
				$this->setTime(
					$this->format('H', false, false),
					(int) $value,
					$this->format('s', false, false),
				);
				break;

			case 'second':
				$this->setTime(
					$this->format('H', false, false),
					$this->format('i', false, false),
					(int) $value,
				);
				break;

			case 'iso_gmdate':
				$tz = $this->getTimezone();
				$this->setTimezone(timezone_open('UTC'));
				$this->modify($value);
				$this->setTimezone($tz);
				break;

			case 'timestamp':
				$this->setTimestamp((int) $value);
				break;

			case 'tz':
			case 'tzid':
			case 'timezone':
				if (in_array($value, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) {
					$this->setTimezone(timezone_open($value));
				}
				break;

			// Read only.
			case 'tz_abbrev':
				break;

			default:
				$this->custom[$prop] = $value;
				break;
		}
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 */
	public function __get(string $prop): mixed
	{
		switch ($prop) {
			case 'datetime':
				$value = $this->format('Y-m-d H:i:s', false, false);
				break;

			case 'date':
				$value = $this->format('Y-m-d', false, false);
				break;

			case 'time':
				$value = $this->format('H:i:s', false, false);
				break;

			case 'date_orig':
				$value = $this->format(self::getDateFormat());
				break;

			case 'time_orig':
				$value = $this->format(self::getShortTimeFormat());
				break;

			case 'date_local':
				$value = (clone $this)->setTimezone(self::$user_tz)->format(self::getDateFormat());
				break;

			case 'time_local':
				$value = (clone $this)->setTimezone(self::$user_tz)->format(self::getShortTimeFormat());
				break;

			case 'year':
				$value = $this->format('Y', false, false);
				break;

			case 'month':
				$value = $this->format('m', false, false);
				break;

			case 'day':
				$value = $this->format('d', false, false);
				break;

			case 'hour':
				$value = $this->format('H', false, false);
				break;

			case 'minute':
				$value = $this->format('i', false, false);
				break;

			case 'second':
				$value = $this->format('s', false, false);
				break;

			case 'iso_gmdate':
				$value = (clone $this)->setTimezone(new \DateTimeZone('UTC'))->format('c', false, false);
				break;

			case 'timestamp':
				$value = $this->getTimestamp();
				break;

			case 'tz':
			case 'tzid':
			case 'timezone':
				$value = $this->format('e', false, false);
				break;

			case 'tz_abbrev':
				$value = preg_replace('/^[+-]/', 'UTC$0', $this->format('T', false, false));
				break;

			default:
				$value = $this->custom[$prop] ?? null;
				break;
		}

		return $value;
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 */
	public function __isset(string $prop): bool
	{
		switch ($prop) {
			case 'datetime':
			case 'date':
			case 'time':
			case 'date_orig':
			case 'time_orig':
			case 'date_local':
			case 'time_local':
			case 'year':
			case 'month':
			case 'day':
			case 'hour':
			case 'minute':
			case 'second':
			case 'iso_gmdate':
			case 'timestamp':
			case 'tz':
			case 'tzid':
			case 'timezone':
			case 'tz_abbrev':
				return true;

			default:
				return isset($this->custom[$prop]);
		}
	}

	/**
	 * Like DateTime::format(), except that it can accept both DateTime format
	 * specifiers and strftime format specifiers (but not both at once).
	 *
	 * This does not use the system's strftime library or locale setting when
	 * formatting using strftime format specifiers, so results may vary in a few
	 * cases from the results of strftime():
	 *
	 *  - %a, %A, %b, %B, %p, %P: Output will use SMF's language strings
	 *    to localize these values. If SMF's language strings have not
	 *    been loaded, PHP's default English strings will be used.
	 *
	 *  - %c, %x, %X: Output will always use ISO 8601 format.
	 *
	 * @param string $format The format string to use. Defaults to the current
	 *    user's preferred time format.
	 * @param bool $relative Whether to show "yesterday" and "today" for recent
	 *    dates. Defaults to true if $format is empty, or false otherwise.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically.
	 * @return string The formatted date and time.
	 */
	public function format(?string $format = null, ?bool $relative = null, ?bool $strftime = null): string
	{
		// If given no format, assume $relative is true.
		if (!isset($relative)) {
			$relative = !isset($format);
		}

		// If were we explicitly told not to use strftime format, save ourselves some work.
		if (isset($format) && !$relative && $strftime === false) {
			return date_format($this, $format);
		}

		// We need some sort of format.
		$format = $format ?? User::$me->time_format ?? Config::$modSettings['time_format'] ?? '%F %k:%M';

		// If necessary, autodetect the format type.
		$strftime = $strftime ?? self::isStrftimeFormat($format);

		// A few substitutions to make life easier.
		if ($strftime) {
			$format = strtr($format, self::FORMAT_SHORT_FORMS);
		}

		// Today and Yesterday?
		$prefix = '';

		if ($relative && Config::$modSettings['todayMod'] >= 1) {
			$tzid = date_format($this, 'e');

			if (!isset(self::$today[$tzid])) {
				self::$today[$tzid] = strtotime('today ' . $tzid);
			}

			// Tomorrow? We don't support the future. ;)
			if ($this->getTimestamp() >= self::$today[$tzid] + 86400) {
				$prefix = '';
			}
			// Today.
			elseif ($this->getTimestamp() >= self::$today[$tzid]) {
				$prefix = Lang::$txt['today'] ?? '';
			}
			// Yesterday.
			elseif (Config::$modSettings['todayMod'] > 1 && $this->getTimestamp() >= self::$today[$tzid] - 86400) {
				$prefix = Lang::$txt['yesterday'] ?? '';
			}
		}

		$format = !empty($prefix) ? self::getTimeFormat($format) : $format;

		// If we aren't using strftime format, things are easy.
		if (!$strftime) {
			return $prefix . date_format($this, $format);
		}

		// Translate from strftime format to DateTime format.
		$parts = preg_split('/' . self::REGEX_STRFTIME . '/', $format, 0, PREG_SPLIT_DELIM_CAPTURE);

		$placeholders = [];
		$complex = false;

		for ($i = 0; $i < count($parts); $i++) {
			// Parts that are not strftime formats.
			if ($i % 2 === 0 || !isset(self::FORMAT_EQUIVALENTS[$parts[$i]])) {
				if ($parts[$i] === '') {
					continue;
				}

				$placeholder = "\xEE\x84\x80" . $i . "\xEE\x84\x81";

				$placeholders[$placeholder] = $parts[$i];
				$parts[$i] = $placeholder;
			}
			// Parts that need localized strings.
			elseif (in_array($parts[$i], ['a', 'A', 'b', 'B'])) {
				switch ($parts[$i]) {
					case 'a':
						$min = 0;
						$max = 6;
						$key = 'days_short';
						$f = 'w';
						$placeholder_end = "\xEE\x84\x83";
						break;

					case 'A':
						$min = 0;
						$max = 6;
						$key = 'days';
						$f = 'w';
						$placeholder_end = "\xEE\x84\x82";
						break;

					case 'b':
						$min = 1;
						$max = 12;
						$key = 'months_short';
						$f = 'n';
						$placeholder_end = "\xEE\x84\x85";
						break;

					case 'B':
						$min = 1;
						$max = 12;
						$key = 'months';
						$f = 'n';
						$placeholder_end = "\xEE\x84\x84";
						break;
				}

				$placeholder = "\xEE\x84\x80" . $f . $placeholder_end;

				// Check whether Lang::$txt contains all expected strings.
				// If not, use English default.
				$txt_strings_exist = true;

				for ($num = $min; $num <= $max; $num++) {
					if (!isset(Lang::$txt[$key][$num])) {
						$txt_strings_exist = false;
						break;
					}

					$placeholders[str_replace($f, $num, $placeholder)] = Lang::$txt[$key][$num];
				}

				$parts[$i] = $txt_strings_exist ? $placeholder : self::FORMAT_EQUIVALENTS[$parts[$i]];
			} elseif (in_array($parts[$i], ['p', 'P'])) {
				if (!isset(Lang::$txt['time_am']) || !isset(Lang::$txt['time_pm'])) {
					continue;
				}

				$placeholder = "\xEE\x84\x90" . self::FORMAT_EQUIVALENTS[$parts[$i]] . "\xEE\x84\x91";

				switch ($parts[$i]) {
					// Upper case.
					case 'p':
						$placeholders[str_replace(self::FORMAT_EQUIVALENTS[$parts[$i]], 'AM', $placeholder)] = Utils::strtoupper(Lang::$txt['time_am']);
						$placeholders[str_replace(self::FORMAT_EQUIVALENTS[$parts[$i]], 'PM', $placeholder)] = Utils::strtoupper(Lang::$txt['time_pm']);
						break;

					// Lower case.
					case 'P':
						$placeholders[str_replace(self::FORMAT_EQUIVALENTS[$parts[$i]], 'am', $placeholder)] = Utils::strtolower(Lang::$txt['time_am']);
						$placeholders[str_replace(self::FORMAT_EQUIVALENTS[$parts[$i]], 'pm', $placeholder)] = Utils::strtolower(Lang::$txt['time_pm']);
						break;
				}

				$parts[$i] = $placeholder;
			}
			// Parts that will need further processing.
			elseif (in_array($parts[$i], ['j', 'C', 'U', 'W', 'G', 'g', 'e', 'l'])) {
				$complex = true;

				switch ($parts[$i]) {
					case 'j':
						$placeholder_end = "\xEE\x84\xA1";
						break;

					case 'C':
						$placeholder_end = "\xEE\x84\xA2";
						break;

					case 'U':
					case 'W':
						$placeholder_end = "\xEE\x84\xA3";
						break;

					case 'G':
						$placeholder_end = "\xEE\x84\xA4";
						break;

					case 'g':
						$placeholder_end = "\xEE\x84\xA5";
						break;

					case 'e':
					case 'l':
						$placeholder_end = "\xEE\x84\xA6";
				}

				$parts[$i] = "\xEE\x84\xA0" . self::FORMAT_EQUIVALENTS[$parts[$i]] . $placeholder_end;
			}
			// Parts with simple equivalents.
			else {
				$parts[$i] = self::FORMAT_EQUIVALENTS[$parts[$i]];
			}
		}

		// The main event.
		$result = strtr(date_format($this, implode('', $parts)), $placeholders);

		// Deal with the complicated ones.
		if ($complex) {
			$result = preg_replace_callback(
				'/\xEE\x84\xA0([\d_]+)(\xEE\x84(?:[\xA1-\xAF]))/',
				function ($matches) {
					switch ($matches[2]) {
						// %j
						case "\xEE\x84\xA1":
							$replacement = sprintf('%03d', (int) $matches[1] + 1);
							break;

						// %C
						case "\xEE\x84\xA2":
							$replacement = substr(sprintf('%04d', $matches[1]), 0, 2);
							break;

						// %U and %W
						case "\xEE\x84\xA3":
							list($day_of_year, $day_of_week, $first_day) = explode('_', $matches[1]);
							$replacement = sprintf('%02d', floor(((int) $day_of_year - (int) $day_of_week + (int) $first_day) / 7) + 1);
							break;

						// %G
						case "\xEE\x84\xA4":
							$replacement = sprintf('%04d', $matches[1]);
							break;

						// %g
						case "\xEE\x84\xA5":
							$replacement = substr(sprintf('%04d', $matches[1]), -2);
							break;

						// %e and %l
						case "\xEE\x84\xA6":
							$replacement = sprintf('%2d', $matches[1]);
							break;

						// Shouldn't happen, but just in case...
						default:
							$replacement = $matches[1];
							break;
					}

					return $replacement;
				},
				$result,
			);
		}

		return $prefix . $result;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constuctor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $datetime A date/time string that PHP can understand, or a
	 *    Unix timestamp.
	 * @param \DateTimeZone|string $timezone The time zone of $datetime, either
	 *    as a \DateTimeZone object or as a time zone identifier string.
	 *    Defaults to the current user's time zone.
	 */
	public static function create(string $datetime = 'now', \DateTimeZone|string|null $timezone = null): object
	{
		return new self($datetime, $timezone);
	}

	/**
	 * Replacement for strftime() that is compatible with PHP 8.1+.
	 *
	 * @param string $format A strftime() format string.
	 * @param int|null $timestamp A Unix timestamp.
	 *     If null, defaults to the current time.
	 * @param string|null $tzid Time zone identifier.
	 *     If null, uses default time zone.
	 * @return string The formatted date and time.
	 */
	public static function strftime(string $format, ?int $timestamp = null, ?string $tzid = null): string
	{
		// Set default values as necessary.
		if (!isset($timestamp)) {
			$timestamp = time();
		}

		if (!isset($tzid)) {
			$tzid = date_default_timezone_get();
		}

		$date = new self('@' . $timestamp);
		$date->setTimezone(new \DateTimeZone($tzid));

		return $date->format($format, false, true);
	}

	/**
	 * Replacement for gmstrftime() that is compatible with PHP 8.1+.
	 *
	 * Calls self::strftime() with the $tzid parameter set to 'UTC'.
	 *
	 * @param string $format A strftime() format string.
	 * @param int|null $timestamp A Unix timestamp.
	 *     If null, defaults to the current time.
	 * @return string The formatted date and time.
	 */
	public static function gmstrftime(string $format, ?int $timestamp = null): string
	{
		return self::strftime($format, $timestamp, 'UTC');
	}

	/**
	 * Returns a strftime format or DateTime format for showing dates.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any time components.
	 *
	 * @param string $format A strftime format or DateTime format to process.
	 *    Defaults to User::$me->time_format.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically.
	 * @return string A strftime format or DateTime format string.
	 */
	public static function getDateFormat(string $format = '', ?bool $strftime = null): string
	{
		return self::getDateOrTimeFormat('date', $format, $strftime);
	}

	/**
	 * Returns a strftime format or DateTime format for showing times.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any date components.
	 *
	 * @param string $format A strftime format or DateTime format to process.
	 *    Defaults to User::$me->time_format.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically.
	 * @return string A strftime format or DateTime format string.
	 */
	public static function getTimeFormat(string $format = '', ?bool $strftime = null): string
	{
		return self::getDateOrTimeFormat('time', $format, $strftime);
	}

	/**
	 * Returns a compact strftime format or DateTime format for showing dates.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without the year, time components, or extra fluff.
	 *
	 * @param string $format A strftime format or DateTime format to process.
	 *    Defaults to User::$me->time_format.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically.
	 * @return string A strftime format or DateTime format string.
	 */
	public static function getShortDateFormat(string $format = '', ?bool $strftime = null): string
	{
		if (isset(self::$short_date_format) && $format === '') {
			return self::$short_date_format;
		}

		$date_format = self::getDateFormat($format, $strftime);
		$strftime = $strftime ?? self::isStrftimeFormat($date_format);

		if ($strftime) {
			$substitutions = [
				'%Y' => '',
				'%y' => '',
				'%G' => '',
				'%g' => '',
				'%C' => '',
				'%D' => '%m/%d',
				'%c' => '%m-%d',
				'%F' => '%m-%d',
				'%x' => '%m-%d',
			];

			$date_format = strtr($date_format, $substitutions);

			$date_format = Utils::normalizeSpaces($date_format, true, true, ['replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true]);

			$date_format = preg_replace('/^([\p{Z}\p{C}]|[^%\P{P}])*|[\p{Z}\p{C}\p{P}]*$/u', '', $date_format);
		} else {
			$date_format = preg_replace('/(?<!\\\\)[LoXxYy]/', '', $date_format);

			$date_format = Utils::normalizeSpaces($date_format, true, true, ['replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true]);

			$date_format = preg_replace('/^[\p{Z}\p{C}\p{P}]*|[\p{Z}\p{C}\p{P}]*$/u', '', $date_format);
		}

		if ($format === '') {
			self::$short_date_format = $date_format;
		}

		return $date_format;
	}

	/**
	 * Returns a compact strftime format or DateTime format for showing times.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any date components or extra fluff.
	 *
	 * @param string $format A strftime format or DateTime format to process.
	 *    Defaults to User::$me->time_format.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically.
	 * @return string A strftime format or DateTime format string.
	 */
	public static function getShortTimeFormat(string $format = '', ?bool $strftime = null): string
	{
		if (isset(self::$short_time_format) && $format === '') {
			return self::$short_time_format;
		}

		$time_format = self::getTimeFormat($format, $strftime);
		$strftime = $strftime ?? self::isStrftimeFormat($time_format);

		if ($strftime) {
			$substitutions = [
				'%I' => '%l',
				'%H' => '%k',
				'%S' => '',
				'%r' => '%l:%M %p',
				'%R' => '%k:%M',
				'%T' => '%l:%M',
			];

			$time_format = strtr($time_format, $substitutions);

			$time_format = Utils::normalizeSpaces($time_format, true, true, ['replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true]);

			$time_format = preg_replace('/:(?=\p{Z}|$|%[pPzZ])/u', '', $time_format);

			$time_format = preg_replace('/^([\p{Z}\p{C}]|[^%\P{P}])*|[\p{Z}\p{C}\p{P}]*$/u', '', $time_format);
		} else {
			$substitutions = [
				'H' => 'G',
				'h' => 'g',
				's' => '',
				'u' => '',
				'v' => '',
			];

			$time_format = preg_replace_callback(
				'/(?<!\\\\)[' . implode('', array_keys($substitutions)) . ']/',
				fn ($m) => $substitutions[$m],
				$time_format,
			);

			$time_format = Utils::normalizeSpaces($time_format, true, true, ['replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true]);

			$time_format = preg_replace('/:(?=\p{Z}|$|[aAeOPpTZ])/u', '', $time_format);

			$time_format = preg_replace('/^[\p{Z}\p{C}\p{P}]*|[\p{Z}\p{C}\p{P}]*$/u', '', $time_format);
		}

		if ($format === '') {
			self::$short_time_format = $time_format;
		}

		return $time_format;
	}

	/**
	 * Gets a version of a strftime format or DateTime format that only shows
	 * the date or time components.
	 *
	 * @param string $type Either 'date' or 'time'.
	 * @param string $format A strftime format or DateTime format to process.
	 *    Defaults to User::$me->time_format.
	 * @param bool $strftime True if $format uses strftime format specifiers,
	 *    false if it uses DateTime format specifiers. If null, attempts to
	 *    detect the format type automatically. Ignored if $format is empty.
	 * @return string A strftime format or DateTime format string.
	 */
	public static function getDateOrTimeFormat(string $type = '', string $format = '', ?bool $strftime = null): string
	{
		// If the format is empty, fall back to defaults.
		if ($format === '') {
			$strftime = null;

			$format = !empty(User::$me->time_format) ? User::$me->time_format : (!empty(Config::$modSettings['time_format']) ? Config::$modSettings['time_format'] : '%F %k:%M');
		}

		$strftime = $strftime ?? self::isStrftimeFormat($format);

		if ($strftime) {
			return self::strftimePartialFormat($type, strtr($format, self::FORMAT_SHORT_FORMS));
		}

		return self::datetimePartialFormat($type, $format);
	}

	/**
	 * Backward compatibility wrapper for the format method.
	 *
	 * @param int $log_time A timestamp.
	 * @param bool|string $show_today Whether to show "Today"/"Yesterday" or
	 *    just a date. If a string is specified, that is used to temporarily
	 *    override the date format.
	 * @param string $tzid Time zone identifier string of the time zone to use.
	 *    If empty, the user's time zone will be used.
	 *    If set to a valid time zone identifier, that will be used.
	 *    Otherwise, the value of Config::$modSettings['default_timezone'] will
	 *    be used.
	 * @return string A formatted time string
	 */
	public static function timeformat(int $log_time, bool|string $show_today = true, ?string $tzid = null): string
	{
		// For backward compatibility, replace empty values with the user's time
		// zone and replace anything invalid with the forum's default time zone.
		$tzid = empty($tzid) ? User::getTimezone() : (($tzid === 'forum' || @timezone_open((string) $tzid) === false) ? Config::$modSettings['default_timezone'] : $tzid);

		$date = new self('@' . $log_time);
		$date->setTimezone(new \DateTimeZone($tzid));

		return is_bool($show_today) ? $date->format(null, $show_today) : $date->format($show_today);
	}

	/**
	 * Backward compatibility method.
	 *
	 * @deprecated since 2.1
	 * @param bool $use_user_offset This parameter is deprecated and ignored.
	 * @param int $timestamp A timestamp (null to use current time).
	 * @return int Seconds since the Unix epoch.
	 */
	public static function forumTime($use_user_offset = true, $timestamp = null)
	{
		return !isset($timestamp) ? time() : (int) $timestamp;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Gets a version of a strftime format that only shows the date or time
	 * components.
	 *
	 * @param string $type Either 'date' or 'time'.
	 * @param string $format A strftime format to process.
	 * @return string A strftime format string.
	 */
	protected static function strftimePartialFormat(string $type, string $format): string
	{
		$orig_format = $format;

		// Have we already done this?
		if (isset(self::$formats[$orig_format][$type])) {
			return self::$formats[$orig_format][$type];
		}

		if ($type === 'date') {
			$specifications = [
				// Day
				'%a' => '%a', '%A' => '%A', '%e' => '%e', '%d' => '%d',
				'%j' => '%j', '%u' => '%u', '%w' => '%w',
				// Week
				'%U' => '%U', '%V' => '%V', '%W' => '%W',
				// Month
				'%b' => '%b', '%B' => '%B', '%h' => '%h', '%m' => '%m',
				// Year
				'%C' => '%C', '%g' => '%g', '%G' => '%G', '%y' => '%y',
				'%Y' => '%Y',
				// Time
				'%H' => '', '%k' => '', '%I' => '', '%l' => '', '%M' => '',
				'%p' => '', '%P' => '', '%r' => '', '%R' => '', '%S' => '',
				'%T' => '', '%X' => '', '%z' => '', '%Z' => '',
				// Time and Date Stamps
				'%c' => '%x', '%D' => '%D', '%F' => '%F', '%s' => '%s',
				'%x' => '%x',
				// Miscellaneous
				'%n' => '', '%t' => '', '%%' => '%%',
			];

			$default_format = '%F';
		} elseif ($type === 'time') {
			$specifications = [
				// Day
				'%a' => '', '%A' => '', '%e' => '', '%d' => '', '%j' => '',
				'%u' => '', '%w' => '',
				// Week
				'%U' => '', '%V' => '', '%W' => '',
				// Month
				'%b' => '', '%B' => '', '%h' => '', '%m' => '',
				// Year
				'%C' => '', '%g' => '', '%G' => '', '%y' => '', '%Y' => '',
				// Time
				'%H' => '%H', '%k' => '%k', '%I' => '%I', '%l' => '%l',
				'%M' => '%M', '%p' => '%p', '%P' => '%P', '%r' => '%r',
				'%R' => '%R', '%S' => '%S', '%T' => '%T', '%X' => '%X',
				'%z' => '%z', '%Z' => '%Z',
				// Time and Date Stamps
				'%c' => '%X', '%D' => '', '%F' => '', '%s' => '%s', '%x' => '',
				// Miscellaneous
				'%n' => '', '%t' => '', '%%' => '%%',
			];

			$default_format = '%k:%M';
		}
		// Invalid type requests just get the full format string.
		else {
			return $format;
		}

		// Separate the specifications we want from the ones we don't.
		$wanted = array_filter($specifications);
		$unwanted = array_diff(array_keys($specifications), $wanted);

		// First, make any necessary substitutions in the format.
		$format = strtr($format, $wanted);

		// Next, strip out any specifications and literal text that we don't want.
		$format_parts = preg_split('~%[' . (strtr(implode('', $unwanted), ['%' => ''])) . ']~u', $format);

		foreach ($format_parts as $p => $f) {
			if (strpos($f, '%') === false) {
				unset($format_parts[$p]);
			}
		}

		$format = implode('', $format_parts);

		// Finally, strip out any unwanted leftovers.
		// For info on the charcter classes used here, see https://www.php.net/manual/en/regexp.reference.unicode.php and https://www.regular-expressions.info/unicode.html
		$format = preg_replace(
			[
				// Anything that isn't a specification, punctuation mark, or whitespace.
				'~(?<!%)\p{L}|[^\p{L}\p{P}\p{Z}]~u',
				// Repeated punctuation marks (except %), possibly separated by whitespace.
				'~(?' . '>([^%\P{P}])\p{Z}*(?=\1))*~u',
				'~([^%\P{P}])(?' . '>\1(?!$))*~u',
				// Unwanted trailing punctuation and whitespace.
				'~(?' . '>([\p{Pd}\p{Ps}\p{Pi}\p{Pc}]|[^%\P{Po}])\p{Z}*)*$~u',
				// Unwanted opening punctuation and whitespace.
				'~^\p{Z}*(?' . '>([\p{Pd}\p{Pe}\p{Pf}\p{Pc}]|[^%\P{Po}])\p{Z}*)*~u',
				// Runs of horizontal whitespace.
				'~\p{Z}+~',
			],
			[
				'',
				'$1',
				'$1$2',
				'',
				'',
				' ',
			],
			$format,
		);

		// Gotta have something...
		if (empty($format)) {
			$format = $default_format;
		}

		// Remember what we've done.
		self::$formats[$orig_format][$type] = trim($format);

		return self::$formats[$orig_format][$type];
	}

	/**
	 * Gets a version of a DateTime format that only shows the date or time
	 * components.
	 *
	 * @param string $type Either 'date' or 'time'.
	 * @param string $format A DateTime format to process.
	 * @return string A DateTime format string.
	 */
	protected static function datetimePartialFormat(string $type, string $format): string
	{
		$orig_format = $format;

		// Have we already done this?
		if (isset(self::$formats[$orig_format][$type])) {
			return self::$formats[$orig_format][$type];
		}

		if ($type === 'date') {
			$specifications = [
				// Day
				'd' => 'd', 'D' => 'D', 'j' => 'j', 'l' => 'l', 'N' => 'N',
				'S' => 'S', 'w' => 'w', 'z' => 'z',
				// Week
				'W' => 'W',
				// Month
				'F' => 'F', 'm' => 'm', 'M' => 'M', 'n' => 'n', 't' => 't',
				// Year
				'L' => 'L', 'X' => 'X', 'x' => 'x', 'Y' => 'Y', 'y' => 'y',
				'o' => 'o',
				// Time
				'a' => '', 'A' => '', 'B' => '', 'g' => '', 'G' => '',
				'h' => '', 'H' => '', 'i' => '', 's' => '', 'u' => '',
				'v' => '',
				// Time zone
				'e' => '', 'I' => '', 'O' => '', 'P' => '', 'p' => '',
				'T' => '', 'Z' => '',
				// Time and Date Stamps
				'c' => 'Y-m-d', 'r' => 'D, d M Y', 'U' => 'U',
			];

			$default_format = 'Y-m-d';
		} elseif ($type === 'time') {
			$specifications = [
				// Day
				'd' => '', 'D' => '', 'j' => '', 'l' => '', 'N' => '',
				'S' => '', 'w' => '', 'z' => '',
				// Week
				'W' => '',
				// Month
				'F' => '', 'm' => '', 'M' => '', 'n' => '', 't' => '',
				// Year
				'L' => '', 'X' => '', 'x' => '', 'Y' => '', 'y' => '',
				'o' => '',
				// Time
				'a' => 'a', 'A' => 'A', 'B' => 'B', 'g' => 'g', 'G' => 'G',
				'h' => 'h', 'H' => 'H', 'i' => 'i', 's' => 's', 'u' => 'u',
				'v' => 'v',
				// Time zone
				'e' => 'e', 'I' => 'I', 'O' => 'O', 'P' => 'P', 'p' => 'p',
				'T' => 'T', 'Z' => 'Z',
				// Time and Date Stamps
				'c' => 'H:i:sP', 'r' => 'H:i:s O', 'U' => 'U',
			];

			$default_format = 'G:i';
		}
		// Invalid type requests just get the full format string.
		else {
			return $format;
		}

		// Separate the specifications we want from the ones we don't.
		$wanted = array_filter($specifications);
		$unwanted = array_diff(array_keys($specifications), $wanted);

		// Make any necessary substitutions in the format.
		$format = preg_replace_callback(
			'/(?<!\\\\)[' . implode('', array_keys($specifications)) . ']/',
			fn ($m) => $specifications[$m],
			$format,
		);

		// Finally, strip out any unwanted leftovers.
		// For info on the charcter classes used here, see https://www.php.net/manual/en/regexp.reference.unicode.php and https://www.regular-expressions.info/unicode.html
		$format = preg_replace(
			[
				// Anything that isn't a specification, punctuation mark, or whitespace.
				'~\\\\\p{L}|[^\p{L}\p{P}\p{Z}]~u',
				// Repeated punctuation marks, possibly separated by whitespace.
				'~(?' . '>(\p{P})\p{Z}*(?=\1))*~u',
				'~(\p{P})(?' . '>\1(?!$))*~u',
				// Unwanted trailing punctuation and whitespace.
				'~(?' . '>(\p{P})\p{Z}*)*$~u',
				// Unwanted opening punctuation and whitespace.
				'~^\p{Z}*(?' . '>(\p{P})\p{Z}*)*~u',
				// Runs of horizontal whitespace.
				'~\p{Z}+~',
			],
			[
				'',
				'$1',
				'$1$2',
				'',
				'',
				' ',
			],
			$format,
		);

		// Gotta have something...
		if (empty($format)) {
			$format = $default_format;
		}

		// Remember what we've done.
		self::$formats[$orig_format][$type] = trim($format);

		return self::$formats[$orig_format][$type];
	}

	/**
	 * Figures out whether the passed format is a strftime format.
	 *
	 * @param string $format The format string.
	 * @return bool Whether is is a strftime format.
	 */
	protected static function isStrftimeFormat(string $format): bool
	{
		return (bool) preg_match('/' . self::REGEX_STRFTIME . '/', $format);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Time::exportStatic')) {
	Time::exportStatic();
}

?>