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

namespace SMF;

/**
 * Extends \DateTime with some extra features for SMF.
 */
class Time extends \DateTime implements \ArrayAccess
{
	use BackwardCompatibility, ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'getDateOrTimeFormat' => 'get_date_or_time_format',
			'getDateFormat' => false,
			'getTimeFormat' => false,
			'getShortDateFormat' => false,
			'getShortTimeFormat' => false,
		),
	);

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
	protected static array $formats = array();

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct(string $datetime = 'now', ?\DateTimeZone $timezone = null)
	{
		if (!isset(self::$user_tz))
			self::$user_tz = new \DateTimeZone(User::getTimezone());

		parent::__construct($datetime, $timezone ?? self::$user_tz);
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		switch ($prop)
		{
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
					$this->format('m'),
					$this->format('d')
				);
				break;

			case 'month':
				$this->setDate(
					$this->format('Y'),
					(int) $value,
					$this->format('d')
				);
				break;

			case 'day':
				$this->setDate(
					$this->format('Y'),
					$this->format('m'),
					(int) $value
				);
				break;

			case 'hour':
				$this->setTime(
					(int) $value,
					$this->format('i'),
					$this->format('s')
				);
				break;

			case 'minute':
				$this->setTime(
					$this->format('H'),
					(int) $value,
					$this->format('s')
				);
				break;

			case 'second':
				$this->setTime(
					$this->format('H'),
					$this->format('i'),
					(int) $value
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
				if (in_array($value, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC)))
					$this->setTimezone(timezone_open($value));
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
		switch ($prop)
		{
			case 'datetime':
				$value = $this->format('Y-m-d H:i:s');
				break;

			case 'date':
				$value = $this->format('Y-m-d');
				break;

			case 'time':
				$value = $this->format('H:i:s');
				break;

			case 'date_orig':
				$value = timeformat(strtotime($this->format('Y-m-d H:i:s')), self::getDateFormat());
				break;

			case 'time_orig':
				$value = timeformat(strtotime($this->format('Y-m-d H:i:s')), self::getShortTimeFormat());
				break;

			case 'date_local':
				$value = timeformat($this->getTimestamp(), self::getDateFormat());
				break;

			case 'time_local':
				$value = timeformat($this->getTimestamp(), self::getShortTimeFormat());
				break;

			case 'year':
				$value = $this->format('Y');
				break;

			case 'month':
				$value = $this->format('m');
				break;

			case 'day':
				$value = $this->format('d');
				break;

			case 'hour':
				$value = $this->format('H');
				break;

			case 'minute':
				$value = $this->format('i');
				break;

			case 'second':
				$value = $this->format('s');
				break;

			case 'iso_gmdate':
				$tz = $this->getTimezone();
				$this->setTimezone(timezone_open('UTC'));
				$value = $this->format('c');
				$this->setTimezone($tz);
				break;

			case 'timestamp':
				$value = $this->getTimestamp();
				break;

			case 'tz':
			case 'tzid':
			case 'timezone':
				$value = $this->format('e');
				break;

			case 'tz_abbrev':
				$value = preg_replace('/^[+-]/', 'UTC$0', $this->format('T'));
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
		switch ($prop)
		{
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

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets a version of a strftime format that only shows the date or time
	 * components.
	 *
	 * @param string $type Either 'date' or 'time'.
	 * @param string $format A strftime format to process.
	 *    Defaults to User::$me->time_format.
	 * @return string A strftime format string.
	 */
	public static function getDateOrTimeFormat(string $type = '', string $format = '')
	{
		// If the format is invalid, fall back to defaults.
		if (strpos($format, '%') === false)
		{
			$format = !empty(User::$me->time_format) ? User::$me->time_format : (!empty(Config::$modSettings['time_format']) ? Config::$modSettings['time_format'] : '%F %k:%M');
		}

		$orig_format = $format;

		// Have we already done this?
		if (isset(self::$formats[$orig_format][$type]))
			return self::$formats[$orig_format][$type];

		if ($type === 'date')
		{
			$specifications = array(
				// Day
				'%a' => '%a', '%A' => '%A', '%e' => '%e', '%d' => '%d', '%j' => '%j', '%u' => '%u', '%w' => '%w',
				// Week
				'%U' => '%U', '%V' => '%V', '%W' => '%W',
				// Month
				'%b' => '%b', '%B' => '%B', '%h' => '%h', '%m' => '%m',
				// Year
				'%C' => '%C', '%g' => '%g', '%G' => '%G', '%y' => '%y', '%Y' => '%Y',
				// Time
				'%H' => '', '%k' => '', '%I' => '', '%l' => '', '%M' => '', '%p' => '', '%P' => '',
				'%r' => '', '%R' => '', '%S' => '', '%T' => '', '%X' => '', '%z' => '', '%Z' => '',
				// Time and Date Stamps
				'%c' => '%x', '%D' => '%D', '%F' => '%F', '%s' => '%s', '%x' => '%x',
				// Miscellaneous
				'%n' => '', '%t' => '', '%%' => '%%',
			);

			$default_format = '%F';
		}
		elseif ($type === 'time')
		{
			$specifications = array(
				// Day
				'%a' => '', '%A' => '', '%e' => '', '%d' => '', '%j' => '', '%u' => '', '%w' => '',
				// Week
				'%U' => '', '%V' => '', '%W' => '',
				// Month
				'%b' => '', '%B' => '', '%h' => '', '%m' => '',
				// Year
				'%C' => '', '%g' => '', '%G' => '', '%y' => '', '%Y' => '',
				// Time
				'%H' => '%H', '%k' => '%k', '%I' => '%I', '%l' => '%l', '%M' => '%M', '%p' => '%p', '%P' => '%P',
				'%r' => '%r', '%R' => '%R', '%S' => '%S', '%T' => '%T', '%X' => '%X', '%z' => '%z', '%Z' => '%Z',
				// Time and Date Stamps
				'%c' => '%X', '%D' => '', '%F' => '', '%s' => '%s', '%x' => '',
				// Miscellaneous
				'%n' => '', '%t' => '', '%%' => '%%',
			);

			$default_format = '%k:%M';
		}
		// Invalid type requests just get the full format string.
		else
		{
			return $format;
		}

		// Separate the specifications we want from the ones we don't.
		$wanted = array_filter($specifications);
		$unwanted = array_diff(array_keys($specifications), $wanted);

		// First, make any necessary substitutions in the format.
		$format = strtr($format, $wanted);

		// Next, strip out any specifications and literal text that we don't want.
		$format_parts = preg_split('~%[' . (strtr(implode('', $unwanted), array('%' => ''))) . ']~u', $format);

		foreach ($format_parts as $p => $f)
		{
			if (strpos($f, '%') === false)
				unset($format_parts[$p]);
		}

		$format = implode('', $format_parts);

		// Finally, strip out any unwanted leftovers.
		// For info on the charcter classes used here, see https://www.php.net/manual/en/regexp.reference.unicode.php and https://www.regular-expressions.info/unicode.html
		$format = preg_replace(
			array(
				// Anything that isn't a specification, punctuation mark, or whitespace.
				'~(?<!%)\p{L}|[^\p{L}\p{P}\s]~u',
				// Repeated punctuation marks (except %), possibly separated by whitespace.
				'~(?'.'>([^%\P{P}])\s*(?=\1))*~u',
				'~([^%\P{P}])(?'.'>\1(?!$))*~u',
				// Unwanted trailing punctuation and whitespace.
				'~(?'.'>([\p{Pd}\p{Ps}\p{Pi}\p{Pc}]|[^%\P{Po}])\s*)*$~u',
				// Unwanted opening punctuation and whitespace.
				'~^\s*(?'.'>([\p{Pd}\p{Pe}\p{Pf}\p{Pc}]|[^%\P{Po}])\s*)*~u',
				// Runs of horizontal whitespace.
				'~\s+~',
			),
			array(
				'',
				'$1',
				'$1$2',
				'',
				'',
				' ',
			),
			$format
		);

		// Gotta have something...
		if (empty($format))
			$format = $default_format;

		// Remember what we've done.
		self::$formats[$orig_format][$type] = trim($format);

		return self::$formats[$orig_format][$type];
	}

	/**
	 * Returns a strftime format for showing dates.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any time components.
	 *
	 * @param string $format A strftime format to process.
	 *    Defaults to User::$me->time_format.
	 * @return string A strftime format string.
	 */
	public static function getDateFormat(string $format = ''): string
	{
		return self::getDateOrTimeFormat('date', $format);
	}

	/**
	 * Returns a strftime format for showing times.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any date components.
	 *
	 * @param string $format A strftime format to process.
	 *    Defaults to User::$me->time_format.
	 * @return string A strftime format string.
	 */
	public static function getTimeFormat(string $format = ''): string
	{
		return self::getDateOrTimeFormat('time', $format);
	}

	/**
	 * Returns a compact strftime format for showing dates.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without the year, time components, or extra fluff.
	 *
	 * @param string $format A strftime format to process.
	 *    Defaults to User::$me->time_format.
	 * @return string A strftime format string.
	 */
	public static function getShortDateFormat(string $format = ''): string
	{
		if (isset(self::$short_date_format) && $format === '')
			return self::$short_date_format;

		$short_date_format = strtr(Time::getDateFormat($format), array(
			'%Y' => '',
			'%y' => '',
			'%G' => '',
			'%g' => '',
			'%C' => '',
			'%D' => '%m/%d',
			'%c' => '%m-%d',
			'%F' => '%m-%d',
			'%x' => '%m-%d',
		));

		$short_date_format = Utils::normalizeSpaces($short_date_format, true, true, array('replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true));

		$short_date_format = preg_replace('/^([\s\p{C}]|[^%\P{P}])*|[\s\p{P}\p{C}]*$/', '', $short_date_format);

		if ($format === '')
			self::$short_date_format = $short_date_format;

		return $short_date_format;
	}

	/**
	 * Returns a compact strftime format for showing times.
	 *
	 * Returned string will be based on the current user's preferred strftime
	 * format string, but without any date components or extra fluff.
	 *
	 * @param string $format A strftime format to process.
	 *    Defaults to User::$me->time_format.
	 * @return string A strftime format string.
	 */
	public static function getShortTimeFormat(string $format = ''): string
	{
		if (isset(self::$short_time_format) && $format === '')
			return self::$short_time_format;

		$short_time_format = strtr(Time::getTimeFormat($format), array(
			'%I' => '%l',
			'%H' => '%k',
			'%S' => '',
			'%r' => '%l:%M %p',
			'%R' => '%k:%M',
			'%T' => '%l:%M',
		));

		$short_time_format = Utils::normalizeSpaces($short_time_format, true, true, array('replace_tabs' => true, 'collapse_hspace' => true, 'no_breaks' => true));

		$short_time_format = preg_replace('~:(?=\s|$|%[pPzZ])~', '', $short_time_format);

		if ($format === '')
			self::$short_time_format = $short_time_format;

		return $short_time_format;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Time::exportStatic'))
	Time::exportStatic();

?>