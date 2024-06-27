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

namespace SMF\Localization;

use SMF\Config;
use SMF\Lang;
use SMF\Time;
use SMF\User;
use SMF\Utils;

use function SMF\Unicode\currencies;

/**
 * Provides the ability to process ICU MessageFormat strings, regardless whether
 * the host has the intl extension installed.
 */
class MessageFormatter
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Official, unambiguous, long-form symbols for certain currencies.
	 *
	 * Note that not all currencies have long-form symbols. For currencies that
	 * do not have long-form symbols, the raw currency code is used instead.
	 */
	public const CURRENCY_SYMBOLS = [
		'AUD' => 'A$',
		'BRL' => 'R$',
		'CAD' => 'CA$',
		'CNY' => 'CN¥',
		'EUR' => '€',
		'GBP' => '£',
		'HKD' => 'HK$',
		'ILS' => '₪',
		'INR' => '₹',
		'JPY' => '¥',
		'KRW' => '₩',
		'MXN' => 'MX$',
		'NZD' => 'NZ$',
		'PHP' => '₱',
		'TWD' => 'NT$',
		'USD' => 'US$',
		'VND' => '₫',
		'XAF' => 'FCFA',
		'XCD' => 'EC$',
		'XOF' => "F\u{202F}CFA",
		'XPF' => 'CFPF',
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Rules for determining the correct pluralization category to use for any
	 * given number in any given language.
	 */
	public static array $plural_rules;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Instances of \MessageFormatter.
	 */
	private static $message_formatters = [];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Formats a MessageFormat string using the supplied arguments.
	 *
	 * @param string $message The MessageFormat string.
	 * @param array $args Arguments to use in the MessageFormat string.
	 * @return string The formatted string.
	 */
	public static function formatMessage(string $message, array $args = []): string
	{
		if ($args === [] || !str_contains($message, '{') || !str_contains($message, '}')) {
			return $message;
		}

		// Avoid issues with MessageFormat syntax characters in $args.
		// These placeholders are Unicode characters in the Private Use block.
		$placeholders = [
			'{' => "\u{E731}",
			'}' => "\u{E732}",
			"'" => "\u{E733}",
		];

		foreach ($args as $arg => $value) {
			if (!is_string($value)) {
				continue;
			}

			$args[$arg] = strtr($value, $placeholders);
		}

		// Use the intl extension's MessageFormatter class if available.
		if (class_exists('\MessageFormatter')) {
			if (!isset(self::$message_formatters[Lang::$txt['lang_locale']][$message])) {
				self::$message_formatters[Lang::$txt['lang_locale']][$message] = new \MessageFormatter(Lang::$txt['lang_locale'], $message);
			}

			$fmt = self::$message_formatters[Lang::$txt['lang_locale']][$message]->format(array_filter($args, 'is_scalar'));

			return $fmt == false ? '' : strtr($fmt, array_flip($placeholders));
		}

		// Parse $message and build the finalized string.
		$final = '';

		$parts = preg_split('/(?={' . Utils::buildRegex(array_keys($args), '/') . ')({(?' . '>[^{}]|(?1))*})/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach ($parts as $part_num => $part) {
			// Not in a code section
			if ($part_num % 2 === 0) {
				$final .= $part;
				continue;
			}

			// Simple substitution
			if (!str_contains($part, ',')) {
				foreach ($args as $arg => $value) {
					if (trim(trim($part, '{}')) == $arg) {
						$final .= $value;
						continue 2;
					}
				}
			}

			list($arg_name, $fmt_type, $rest) = array_pad(explode(',', substr($part, 1, -1), 3), 3, '');

			$arg_name = trim($arg_name);
			$fmt_type = trim($fmt_type);
			$rest = trim($rest);

			switch ($fmt_type) {
				// @todo Implement 'spellout' properly.
				case 'spellout':
				case 'number':
					if (str_starts_with($rest, '::')) {
						$final .= self::applyNumberSkeleton($args[$arg_name] ?? 0, ltrim(substr($rest, 2)));
					} else {
						switch ($rest) {
							case 'integer':
								$final .= self::applyNumberSkeleton((int) $args[$arg_name], '');
								break;

							case 'percent':
								$final .= self::applyNumberSkeleton(round(($args[$arg_name] ?? 0), 2), 'percent scale/100');
								break;

							// For this one, we need to figure out a default currency to use...
							case 'currency':
								// If paid subscriptions are set up, use that currency.
								if (isset(Config::$modSettings['paid_currency'])) {
									$currency = Config::$modSettings['paid_currency'];
								}
								// Try to guess the currency based on country.
								else {
									require_once Config::$sourcedir . '/Unicode/Currencies.php';
									$country_currencies = country_currencies();

									// If the admin wants to prioritize a certain country, use that.
									if (!empty(Config::$modSettings['timezone_priority_countries'])) {
										$cc = explode(',', Config::$modSettings['timezone_priority_countries'])[0];
									}
									// Guess based on the locale.
									else {
										[$lang, $cc] = explode('_', Lang::$txt['lang_locale']);

										if (!isset($country_currencies[$cc])) {
											switch ($lang) {
												case 'cs':
													$cc = 'CZ';
													break;

												case 'de':
													$cc = 'DE';
													break;

												case 'en':
													$cc = 'US';
													break;

												case 'sr':
													$cc = 'SR';
													break;

												case 'zh':
													$cc = 'CN';
													break;

												default:
													$cc = '';
													break;
											}
										}
									}

									$currency = $country_currencies[$cc] ?? 'XXX';
								}

								$final .= self::applyNumberSkeleton($args[$arg_name] ?? 0, 'currency/' . $currency);

								break;

							default:
								$skeleton = is_int($args[$arg_name] + 0) ? '' : '.000';
								$final .= self::applyNumberSkeleton($args[$arg_name] ?? 0, $skeleton);
								break;
						}
					}

					break;

				case 'ordinal':
					$final .= self::formatMessage(Lang::$txt['ordinal'], [$args[$arg_name]]);
					break;

				case 'date':
					if ($args[$arg_name] instanceof \DateTimeInterface) {
						$args[$arg_name] = Time::createFromInterface($args[$arg_name]);
					} elseif (is_numeric($args[$arg_name])) {
						$args[$arg_name] = new Time('@' . $args[$arg_name], User::getTimezone());
					} elseif (is_string($args[$arg_name])) {
						$args[$arg_name] = date_create($args[$arg_name]);

						if ($args[$arg_name] === false) {
							$args[$arg_name] = new Time();
						} else {
							$args[$arg_name] = Time::createFromInterface($args[$arg_name]);
						}
					} else {
						$args[$arg_name] = new Time();
					}

					// Trying to produce the same output as \IntlDateFormatter
					// would require a lot of complex code, so we're just going
					// for simple fallbacks here.
					switch ($rest) {
						case 'full':
						case 'long':
							$fmt = Time::getDateFormat();
							$relative = true;
							break;

						case 'short':
							$fmt = 'Y-m-d';
							$relative = false;

						case 'medium':
						default:
							$fmt = Time::getShortDateFormat();
							$relative = true;
							break;
					}

					$final .= $args[$arg_name]->format($fmt, $relative);
					break;

				case 'time':
					if ($args[$arg_name] instanceof \DateTimeInterface) {
						$args[$arg_name] = Time::createFromInterface($args[$arg_name]);
					} elseif (is_numeric($args[$arg_name])) {
						$args[$arg_name] = new Time('@' . $args[$arg_name], User::getTimezone());
					} elseif (is_string($args[$arg_name])) {
						$args[$arg_name] = date_create($args[$arg_name]);

						if ($args[$arg_name] === false) {
							$args[$arg_name] = new Time();
						} else {
							$args[$arg_name] = Time::createFromInterface($args[$arg_name]);
						}
					} else {
						$args[$arg_name] = new Time();
					}

					// Trying to produce the same output as \IntlDateFormatter
					// would require a lot of complex code, so we're just going
					// for simple fallbacks here.
					switch ($rest) {
						case 'full':
						case 'long':
							$fmt = Time::getTimeFormat();
							break;

						case 'short':
						case 'medium':
						default:
							$fmt = Time::getShortTimeFormat();
							break;
					}

					$final .= $args[$arg_name]->format($fmt);
					break;

				case 'duration':
					// Input is a number of seconds.
					$args[$arg_name] = (int) $args[$arg_name];

					$seconds = sprintf('%02d', $args[$arg_name] % 60);
					$minutes = sprintf('%02d', $args[$arg_name] >= 60 ? (int) ($args[$arg_name] / 60) % 60 : 0);
					$hours = sprintf('%02d', $args[$arg_name] >= 3600 ? (int) ($args[$arg_name] / 3600) : 0);

					if ($hours === '00' && $minutes === '00') {
						$final .= Lang::getTxt('number_of_seconds', [$seconds]);
					} else {
						$final .= ltrim(implode(':', [$hours, $minutes, $seconds]), '0:');
					}

					break;

				case 'plural':
					if (str_starts_with($rest, 'offset:')) {
						preg_match('/^offset:(\d+)/', $rest, $offset_matches);
						$offset = $offset_matches[1];
						$rest = trim(substr($rest, strlen($offset_matches[0])));
					} else {
						$offset = 0;
					}

					$arg_offset = $args[$arg_name] - $offset;

					preg_match_all('/(?P<rule>=\d+|zero|one|two|few|many|other)\b\s*(?P<sel>{(?' . '>[^{}]|(?2))*})/', $rest, $cases, PREG_SET_ORDER);

					foreach ($cases as $case) {
						$case['sel'] = substr($case['sel'], 1, -1);

						if (
							(
								str_starts_with($case['rule'], '=')
								&& $args[$arg_name] == substr($case['rule'], 1)
							)
							|| $case['rule'] === self::getPluralizationCategory($arg_offset, 'cardinal')
						) {
							$final .= strtr(self::formatMessage($case['sel'], $args), ['#' => $arg_offset]);
							break 2;
						}
					}

					break;

				case 'selectordinal':
					if (str_starts_with($rest, 'offset:')) {
						preg_match('/^offset:(\d+)/', $rest, $offset_matches);
						$offset = $offset_matches[1];
						$rest = trim(substr($rest, strlen($offset_matches[0])));
					} else {
						$offset = 0;
					}

					$arg_offset = $args[$arg_name] - $offset;

					preg_match_all('/(?P<rule>=\d+|zero|one|two|few|many|other)\b\s*(?P<sel>{(?' . '>[^{}]|(?2))*})/', $rest, $cases, PREG_SET_ORDER);

					foreach ($cases as $case) {
						$case['sel'] = substr($case['sel'], 1, -1);

						if (
							(
								str_starts_with($case['rule'], '=')
								&& $args[$arg_name] == substr($case['rule'], 1)
							)
							|| $case['rule'] === self::getPluralizationCategory($arg_offset, 'ordinal')
						) {
							$final .= strtr(self::formatMessage($case['sel'], $args), ['#' => $arg_offset]);
							break 2;
						}
					}

					break;

				case 'select':
					preg_match_all('/\b(?P<rule>\w+)\b\s*(?P<sel>{(?' . '>[^{}]|(?2))*})/', $rest, $cases, PREG_SET_ORDER);

					foreach ($cases as $case) {
						$case['sel'] = substr($case['sel'], 1, -1);

						if ($args[$arg_name] == $case['rule']) {
							$final .= self::formatMessage($case['sel'], $args);
							break 2;
						}
					}

					break;

				default:
					if (is_scalar($args[$arg_name]) || $args[$arg_name] instanceof \Stringable) {
						$final .= (string) $args[$arg_name];
					} else {
						$final .= $part;
					}
					break;
			}
		}

		return strtr($final, array_flip($placeholders));
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Gets the correct pluralization category to use for a number in the
	 * current language.
	 *
	 * @param int|float|string $num The number.
	 * @param string $type Either 'cardinal' or 'ordinal'. Default: 'cardinal'.
	 * @return string The pluralization category to use.
	 */
	protected static function getPluralizationCategory(int|float|string $num, string $type = 'cardinal'): string
	{
		// Check the input.
		if (!is_numeric($num)) {
			throw new \ValueError();
		}

		if (!in_array($type, ['cardinal', 'ordinal'])) {
			throw new \ValueError();
		}

		// CLDR pluralization rules use the operands 'n', 'i', 'v', 'w', 'f', 't', and 'c'.
		if (is_string($num) && (str_contains($num, 'e') || str_contains($num, 'E'))) {
			$c = substr(strtolower($num), strpos($num, 'e') + 1);
			$num = (float) $num;
		} else {
			$c = 0;
		}

		$n = abs($num + 0);
		$i = (int) $num;
		$f = substr((string) $num, strpos((string) $num, '.') + 1);
		$t = rtrim($f, '0');
		$w = strlen($t);
		$v = strlen($f);

		// Ensure we have our pluralization rules.
		if (empty(self::$plural_rules)) {
			require_once Config::$sourcedir . '/Unicode/Plurals.php';
			self::$plural_rules = \SMF\Unicode\plurals();
		}

		// Evaluate the pluralization rules to find a match.
		$lang = substr(Lang::$txt['lang_locale'], 0, strpos(Lang::$txt['lang_locale'], '_'));

		foreach (self::$plural_rules[$lang][$type] as $category => $rule) {
			if ($rule($n, $i, $v, $w, $f, $t, $c)) {
				return $category;
			}
		}

		// We should never get here, but just in case...
		return 'other';
	}

	/**
	 * Formats a number according to ICU 60+ NumberFormatter "skeletons".
	 * https://unicode-org.github.io/icu/userguide/format_parse/numbers/skeletons.html
	 *
	 * NumberFormatter skeletons are different from, and more flexible than,
	 * the legacy NumberFormat system that is supported by the intl extension's
	 * NumberFormatter class. Moreover, support for skeletons is incorporated
	 * directly into MessageFormatter, removing the need for a separate class.
	 *
	 * This method supports a subset of all NumberFormatter skeletons.
	 * Unsupported skeletons include those for formatting measurement units,
	 * accounting notation, special rounding precision modes for handling cash
	 * in certain currencies, and alternative numbering systems. None of these
	 * are necessary for SMF's needs, so the supported subset is sufficient to
	 * support SMF on servers that do not have the intl extension installed.
	 *
	 * @param int|float|string $number A number.
	 * @param istring $skeleton The ICU number skeleton.
	 * @return string A formatted number.
	 */
	protected static function applyNumberSkeleton(int|float|string $number, string $skeleton): string
	{
		// For simplicity, standardize alternative notations to a single form.
		$skeleton = strtr($skeleton, [
			'%x100' => 'percent scale/100',
			',_' => 'group-off',
			',_' => 'group-min2',
			',!' => 'group-on-aligned',
			'+!' => 'sign-always',
			'+_' => 'sign-never',
			'+?' => 'sign-except-zero',
			'+-' => 'sign-negative',
			'()' => 'sign-accounting',
			'()!' => 'sign-accounting-always',
			'()?' => 'sign-accounting-except-zero',
			'()-' => 'sign-accounting-negative',
			'KK' => 'compact-long',
			'K' => 'compact-short',
			'precision-integer' => '.',
			'precision-unlimited' => '.*',
			'sign-auto' => '',
		]);

		$skeleton = preg_replace_callback_array(
			[
				'/(?<=\s|^)(EE?)((?:\+[!?])?)(0+)(?=\s|$)/' => function ($matches) {
					$long_form = $matches[0] === 'EE' ? 'engineering' : 'scientific';

					switch ($matches[1]) {
						case '+!':
							$long_form .= '/sign-always';
							break;

						case '+?':
							$long_form .= '/sign-except-zero';
							break;
					}

					if (strlen($matches[3]) > 1) {
						$long_form .= '/*' . str_repeat('e', strlen($matches[3]));
					}
				},
				'/(?<=\s|^)0+(?=\s|$)/' => fn ($matches) => 'integer-width/*' . $matches[0],
			],
			$skeleton,
		);

		// Split the skeleton into tokens.
		$tokens = preg_split('/\s+/', $skeleton);

		// Tokens consist of a stem and zero or more options.
		// Options are appended to the token using the slash character.
		foreach ($tokens as $key => $token) {
			$tokens[$key] = array_pad(explode('/', $token, 2), 2, '');
		}

		// For consistent processing below, we want to handle the tokens in
		// a particular order.
		$preferred_order = [
			// Tokens that affect how to manipulate the number.
			'rounding-mode-ceiling',
			'rounding-mode-floor',
			'rounding-mode-down',
			'rounding-mode-up',
			'rounding-mode-half-even',
			'rounding-mode-half-down',
			'rounding-mode-half-up',
			'rounding-mode-unnecessary',
			'sign-auto',
			'sign-always',
			'sign-never',
			'sign-except-zero',

			// Tokens that manipulate the number.
			'scale',
			'.',
			'@',
			'precision-increment',
			'integer-width',
			'integer-width-trunc',

			// Tokens that affect formatting at the end.
			'group-auto',
			'group-off',
			'group-min2',
			'group-on-aligned',
			'group-thousands',
			'decimal-auto',
			'decimal-always',
			'percent',
			'permille',
			'currency',

			// Unsupported.
			'precision-currency-standard',
			'precision-currency-cash',
			'base-unit',
			'measure-unit',
			'unit',
			'per-measure-unit',
			'unit-width-narrow',
			'unit-width-short',
			'unit-width-full-name',
			'unit-width-iso-code',
			'unit-width-hidden',
			'latin',
			'numbering-system',
			'sign-negative',
			'sign-accounting',
			'sign-accounting-always',
			'sign-accounting-except-zero',
			'sign-accounting-negative',
		];

		usort($tokens, fn ($a, $b) => array_search(str_starts_with($a[0], '.') || str_starts_with($a[0], '@') ? substr($a[0], 0, 1) : $a[0], $preferred_order) <=> array_search(str_starts_with($b[0], '.') || str_starts_with($b[0], '@') ? substr($b[0], 0, 1) : $b[0], $preferred_order));

		// A few variables that will affect how we manipulate and format numbers below.
		$round = fn (int|float $number, int $precision = 0) => round($number, $precision, PHP_ROUND_HALF_EVEN);
		$group = 'thousands';
		$flags = '0';

		// Work through the tokens.
		foreach ($tokens as $token) {
			list($stem, $options) = $token;
			$options = explode('/', $options);

			// Float precision format.
			if (str_starts_with($stem, '.')) {
				$significant_integers = strlen(strval(intval($number + 0)));
				$significant_decimals = (int) strpos(strrev(strval($number)), '.');

				preg_match('/\.(0*)(#*)(\*?)/', $stem, $matches);

				if (!empty($matches[3])) {
					$precision = $significant_decimals;
				} else {
					$precision = min(max($significant_decimals, strlen($matches[1] ?? '')), strlen($matches[1] ?? '') + strlen($matches[2] ?? ''));
				}

				if (!empty($options)) {
					foreach ($options as $option) {
						if (
							str_ends_with($option, '@*')
							|| str_ends_with($option, '@r')
							|| str_ends_with($option, '@s')
							|| str_ends_with($option, '#r')
							|| str_ends_with($option, '#s')
						) {
							if (str_ends_with($option, '#r')) {
								$option = strtr($option, ['#' => '@', 'r' => '*']);
							}

							preg_match('/(@+)(#*)([*rs]?)/', $option, $matches);

							if (!empty($matches[1]) && (!empty($matches[1]) || !empty($matches[3]))) {
								$min_sig = strlen($matches[1]);

								if (empty($matches[2])) {
									if ($matches[3] === '*') {
										$precision = min(max($precision, $min_sig - $significant_integers), $significant_decimals);
									} elseif ($matches[3] === 's') {
										$precision = min($precision, $min_sig - $significant_integers);
									} else {
										$precision = max($precision, $min_sig - $significant_integers);
									}
								}
							}
						}
					}
				}

				$number = sprintf("%{$flags}.{$precision}F", $round($number, $precision));

				if (!empty($options) && in_array('w', $options) && $number == (int) $number) {
					$number = strval(intval($number));
				}
			}
			// Significant digits format.
			elseif (str_starts_with($stem, '@')) {
				$significant_integers = strlen(strval(intval($number + 0)));
				$significant_decimals = (int) strpos(strrev(strval($number)), '.');

				preg_match('/(@+)(#*)(\*?)/', $stem, $matches);

				if (!empty($matches[3])) {
					$precision = max($significant_decimals, strlen($matches[1]) - $significant_integers);
					$number = sprintf("%{$flags}.{$precision}F", $round($number, $precision));
				} elseif (!empty($matches[2])) {
					$precision = min(max($significant_decimals, strlen($matches[1]) - $significant_integers), strlen($matches[1]) + strlen($matches[2]) - $significant_integers);

					if ($precision >= 0) {
						$number = sprintf("%{$flags}.{$precision}F", $round($number, $precision));
					} else {
						$number = (string) $round($number, $precision);
					}
				} else {
					$precision = min(max($significant_decimals, strlen($matches[1]) - $significant_integers), strlen($matches[1]) - $significant_integers);

					$number = sprintf("%{$flags}.{$precision}F", $round($number, $precision));
				}

				if (!empty($options) && in_array('w', $options) && $number == (int) $number) {
					$number = strval(intval($number));
				}
			} else {
				switch ($stem) {
					case 'rounding-mode-ceiling':
						$round = fn (int|float $number, int $precision = 0) => ceil($number * (10 ** $precision)) / (10 ** $precision);
						break;

					case 'rounding-mode-floor':
						$round = fn (int|float $number, int $precision = 0) => floor($number * (10 ** $precision)) / (10 ** $precision);
						break;

					case 'rounding-mode-up':
						$round = fn (int|float $number, int $precision = 0) => ($number >= 0 ? ceil($number * (10 ** $precision)) : floor($number * (10 ** $precision))) / (10 ** $precision);
						break;

					case 'rounding-mode-down':
						$round = fn (int|float $number, int $precision = 0) => ($number < 0 ? ceil($number * (10 ** $precision)) : floor($number * (10 ** $precision))) / (10 ** $precision);
						break;

					case 'rounding-mode-half-even':
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, PHP_ROUND_HALF_EVEN);
						break;

					case 'rounding-mode-half-odd':
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, PHP_ROUND_HALF_ODD);
						break;

					case 'rounding-mode-half-ceiling':
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, $number >= 0 ? PHP_ROUND_HALF_UP : PHP_ROUND_HALF_DOWN);
						break;

					case 'rounding-mode-half-floor':
						$rounding_mode = $number < 0 ? PHP_ROUND_HALF_UP : PHP_ROUND_HALF_DOWN;
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, $number < 0 ? PHP_ROUND_HALF_UP : PHP_ROUND_HALF_DOWN);
						break;

					case 'rounding-mode-half-down':
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, PHP_ROUND_HALF_DOWN);
						break;

					case 'rounding-mode-half-up':
						$round = fn (int|float $number, int $precision = 0) => round($number, $precision, PHP_ROUND_HALF_UP);
						break;

					case 'scale':
						if (is_numeric($options[0])) {
							$number *= $options[0];
						}
						break;

					case 'precision-increment':
						$number /= $options[0];
						$number = $round($number);
						$number *= $options[0];

						if (is_float($number)) {
							$precision = is_float($options[0] + 0) ? strlen($options[0]) - strlen(strval(intval($options[0] + 0))) - 1 : 0;

							$number = sprintf("%0.{$precision}F", $number);
						}

						break;

					case 'integer-width':
						preg_match('/(\*?)(#*)(0*)/', $options[0], $matches);

						$min = strlen($matches[3] ?? '');
						$max = !empty($matches[1]) ? PHP_INT_MAX : strlen($matches[2] ?? '') + strlen($matches[3] ?? '');

						$number = explode('.', rtrim(sprintf('%' . (is_float($number + 0) ? 'F' : 'd'), $number), '0'));
						$number[0] = substr(sprintf("%{$flags}{$min}d", $number[0]), -$max);
						$number = implode('.', $number);

						break;

					case 'integer-width-trunc':
						$number = rtrim(sprintf('%' . (is_float($number + 0) ? 'F' : 'd'), $number), '0');
						$number = str_contains($number, '.') ? substr($number, strpos($number, '.')) : '0';
						break;

					case 'percent':
						$post_processing[] = fn ($number) => strtr(Lang::$txt['percent_format'], ['{0}' => $number]);
						break;

					case 'permille':
						$post_processing[] = fn ($number) => strtr(Lang::$txt['percent_format'], ['{0}' => $number, '%' => '‰']);
						break;

					case 'currency':
						require_once Config::$sourcedir . '/Unicode/Currencies.php';

						$currencies = currencies();

						if (!isset($currencies[$options[0]])) {
							$options[0] = 'DEFAULT';
						}

						$currency = $currencies[$options[0]];

						if ($currency['digits'] === 0) {
							$number = strval(intval($number));
						} else {
							$number = sprintf('%0.' . $currency['digits'] . 'F', $number);
						}

						$post_processing[] = fn ($number) => (in_array(substr($number, 0, 1), ['-', '+']) ? substr($number, 0, 1) : '') . strtr(Lang::$txt['currency_format'], ['{0}' => in_array(substr($number, 0, 1), ['-', '+']) ? substr($number, 1) : $number, '¤' => self::CURRENCY_SYMBOLS[$options[0]] ?? ($options[0] === 'DEFAULT' ? '¤' : $options[0] . "\u{A0}")]);

						break;

					case 'group-auto':
						// Some languages group digits by something other than
						// thousands, but the only options we can support here
						// are 'thousands' or none at all.
						$group = !empty(Lang::$digit_group_separator) ? 'thousands' : 'off';
						break;

					case 'group-off':
						// No grouping.
						$group = 'off';
						break;

					case 'group-min2':
						// Group by thousands, but only for values greater than
						// or equal to 10000.
						$group = 'min2';
						break;

					case 'group-on-aligned':
						// For some languages this is supposed to behave a bit
						// differently than 'thousands' does, but we don't have
						// the ability to support those fine distinctions here.
						$group = 'thousands';
						break;

					case 'group-thousands':
						// Force grouping by thousands.
						$group = 'thousands';
						break;

					case 'sign-always':
						$flags = '+' . $flags;
						break;

					case 'sign-never':
						$number = abs($number + 0);
						break;

					case 'sign-except-zero':
						$flags = (($number + 0) !== 0 ? '+' : '') . $flags;
						break;

					case 'decimal-auto':
						// This is the default behaviour.
						break;

					case 'decimal-always':
						// Insert a trailing decimal separator, even for integers.
						$post_processing[] = fn ($number) => !str_contains($number, Lang::$decimal_separator ?? '.') ? $number . (Lang::$decimal_separator ?? '.') : $number;
						break;
				}
			}
		}

		// Ensure $number is a string.
		if (is_float($number)) {
			$precision = (int) strpos(strrev(strval($number)), '.');
			$number = sprintf("%{$flags}.{$precision}F", $number);
		} elseif (is_int($number)) {
			$number = sprintf("%{$flags}d", $number);
		}

		// Apply the relevant grouping to the number.
		switch ($group) {
			case 'off':
				break;

			case 'min2':
				if (abs($number + 0) < 10000) {
					break;
				}
				// no break

			case 'thousands':
				$number = explode('.', $number);

				$prefix = substr($number[0], 0, strcspn($number[0], '1234567890'));
				$number[0] = substr($number[0], strcspn($number[0], '1234567890'));

				while (strlen($number[0]) % 3 !== 0) {
					$number[0] = ' ' . $number[0];
				}

				$number[0] = $prefix . ltrim(implode(',', str_split($number[0], 3)));

				$number = implode('.', $number);

				break;
		}

		// Use the correct decimal and grouping separators for the user's language.
		$number = strtr($number, ['.' => Lang::$decimal_separator ?? '.', ',' => Lang::$digit_group_separator ?? ',']);

		// Apply any final formatting (percent signs, currency symbols, etc.)
		if (!empty($post_processing)) {
			foreach ($post_processing as $fn) {
				$number = $fn($number);
			}
		}

		return $number;
	}
}

?>