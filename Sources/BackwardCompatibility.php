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

use function strtolower;

use const FILTER_FLAG_IPV6;

/**
 * Assists in providing backward compatibility with code written for earlier
 * versions of SMF.
 */
trait BackwardCompatibility
{
	/**
	 * Provides a way to export a class's public static properties and methods
	 * to global namespace.
	 *
	 * To do so:
	 *
	 *  1. Use this trait in the class.
	 *  2. At the *END* of the class's file, call its exportStatic() method.
	 *
	 * Although it might not seem that way at first glance, this approach
	 * conforms to section 2.3 of PSR 1, since executing this method is simply a
	 * dynamic means of declaring functions when the file is included; it has no
	 * other side effects.
	 *
	 * Regarding the $backcompat items:
	 *
	 * A class's static properties are not exported to global variables unless
	 * explicitly included in $backcompat['prop_names'].
	 *
	 * $backcompat['prop_names'] is a simple array where the keys are the names
	 * of one or more of a class's static properties, and the values are the
	 * names of global variables. In each case, the global variable will be set
	 * to a reference to the static property. Static properties that are not
	 * named in this array will not be exported.
	 *
	 * Adding non-static properties to the $backcompat arrays will
	 * produce runtime errors. It is the responsibility of the developer to make
	 * sure not to do this.
	 */
	public static function exportStatic(): void
	{
		// Do nothing if backward compatibility has been turned off.
		if (empty(Config::$backward_compatibility)) {
			return;
		}

		if (!isset(self::$backcompat['prop_names'])) {
			return;
		}

		// Get any backward compatibility settings.
		self::$backcompat['prop_names'] = self::$backcompat['prop_names'] ?? [];

		// The property names are simple enough to deal with...
		foreach (self::$backcompat['prop_names'] as $static => $global) {
			$GLOBALS[$global] = &self::${$static};
		}

	}

	/**
	 * Called by Subs-Compat.php BackwardCompatibility wrapper functions to provide subaction
	 * execution for existing mods
	 *
	 * @param null|string $sa
	 * @param bool $return_config
	 * @return null|array
	 */
	public static function subActionProvider(?string $sa = null, bool $return_config = false, ?string $activity = null): ?array
	{
		if ($return_config) {
			return self::getConfigVars();
		}

		self::load();

		if (is_string($sa)) {
			// make sure its lowecase
			if (array_key_exists($sa, self::$subactions)) {
				self::$obj->subaction = $sa;
			}
		}

		if(is_string($activity)) {
			self::$obj->activity = $activity;
		}

		self::$obj->execute();
	}

	/**
	 * Usage: Only valid when composed in Unicode\Utf8String
	 * @param string $calledFuntion
	 * @param string $string
	 * @param string|null $case
	 * @param bool $simple
	 * @param string|null $form
	 * @param int|null $level
	 * @param string|null $substitute
	 * @return string|bool
	 */
	public static function utf8StringFactory(
		string $calledFuntion,
		string $string,
		string $case = null,
		bool $simple = false,
		string $form = null,
		int $level   = null,
		string $substitute = null
	): string|bool {
		return match($calledFuntion) {
			'utf8_strtolower'    => self::create($string)->convertCase('lower'),
			'utf8_strtoupper'    => self::create($string)->convertCase('upper'),
			'utf8_casefold'      => self::create($string)->convertCase('fold'),
			'utf8_convert_case'  => self::create($string)->convertCase($case, $simple),
			'utf8_normalize_d'   => self::create($string)->normalize('d'),
			'utf8_normalize_kd'  => self::create($string)->normalize('kd'),
			'utf8_normalize_c'   => self::create($string)->normalize('c'),
			'utf8_normalize_kc'  => self::create($string)->normalize('kc'),
			'utf8_is_normalized' => self::create($string)->isNormalized($form),
			'utf8_normalize_kc_casefold' => self::create($string)->normalize('kc_casefold'),
			'utf8_sanitize_invisibles'   => self::create($string)->sanitizeInvisibles($level, $substitute),
		};
	}

	/**
	 * Usage: Only valid when composed in SMF\IP
	 * @param string $calledFunction
	 * @param string $ip
	 * @param bool $bool_if_invalid
	 * @return self|string|bool
	 */
	public static function ipCheckFactory(
		string $calledFunction,
		string $ip,
		bool $return_bool_if_invalid = true
	): self|string|bool {
		return match($calledFunction) {
			'isValidIP'    => (new self($ip))->isValid(),
			'isValidIPv6'  => (new self($ip))->isValid(FILTER_FLAG_IPV6),
			'host_from_ip' => (new self($ip))->getHost(0),
			'inet_ptod'    => (new self($ip))->toBinary(),
			'inet_dtop'    => new self($ip),
			'expandIPv6'   => (function($ip, $return_bool_if_invalid): string|false {
				$instance = new self($ip);
				if ($return_bool_if_invalid && !$instance->isValid(FILTER_FLAG_IPV6)) {
					return false;
				}
				return $instance->expand();
			})($ip, $return_bool_if_invalid),
		};
	}

	/**
	 * Usage: Use only when composed in SMF\Profile
	 * @param string $calledFunction
	 * @param null|int $id
	 * @param null|bool $force_reload
	 * @param null|string $area
	 * @param null|bool $defaultSettings
	 * @param null|array $fields
	 * @param null|bool $sanitize
	 * @param null|bool $return_errors
	 * @param null|int $id_theme
	 * @return array|bool|null
	 */
	public static function profileProvider(
		string $calledFunction,
		?int $id = null,
		bool $force_reload = false,
		string $area = 'summary',
		bool $defaultSettings = false,
		array $fields = [],
		bool $sanitize = true,
		bool $return_errors = false,
		?int $id_theme = null,
	): array|bool|null {

		if (! isset(self::$loaded[$id])) {
			self::load($id);
		}
		return match($calledFunction) {
			'profileLoadGroups'	  => (function($id) {
										self::$loaded[$id]->loadAssignableGroups();
										return true;
									})($id),
			'loadProfileFields'	  => (function($id, $force_reload) {
										self::$loaded[$id]->loadStandardFields($force_reload);
									})($id, $force_reload),
			'loadCustomFields'	  => (function($id, $area) {
										self::$loaded[$id]->loadCustomFields($area);
									})($id, $area),
			'loadThemeOptions'	  => (function($id, $defaultSettings) {
										self::$loaded[$id]->loadThemeOptions($defaultSettings);
									})($id, $defaultSettings),
			'setupProfileContext' => (function($id, $fields) {
										self::$member->setupContext($fields);
									})($id, $fields),
			'makeCustomFieldChanges' => (function($id, $area, $sanitize, $return_errors): ?array {
											$_REQUEST['sa'] = $area;
											self::$member->post_sanitized = !$sanitize;
											self::$member->save();

											if (!empty($return_errors)) {
												return self::$member->cf_save_errors;
											}
										})($id, $area, $sanitize, $return_errors),
			'makeThemeChanges'       => (function($id, $id_theme) {
											self::$member->new_data['id_theme'] = $id_theme;
											self::$member->save();
										})($id, $id_theme),
		};
	}
	/**
	 * Usage: Use only when composed in SMF\Url
	 * @param string $calledFunction
	 * @param string $target
	 * @param int $component
	 * @param int $flags
	 * @return mixed
	 */
	public static function urlProvider(
		string $calledFunction,
		string $target,
		int $component = -1,
		int $flags = 0,
	): mixed {
		return match($calledFunction) {
			'parse_iri'           => (new self($target))->parse($component),
			'validate_iri'        => (new self($target))->validate($flags)->url === '' ? false : $target,
			'sanitize_iri'        => (new self($target))->sanitize(),
			'normalize_iri'       => (new self($target))->normalize(),
			'iri_to_url'          => (new self($target))->toAscii(),
			'url_to_iri'          => (new self($target))->toUtf8(),
			'get_proxied_url'     => (new self($target))->proxied(),
			'ssl_cert_found'      => (new self($target))->hasSSL(),
			'httpsRedirectActive' => (new self($target))->redirectsToHttps(),
		};
	}

	/**
	 *
	 * @param int $memID
	 * @param null|string $sa
	 * @param bool $updateRequest
	 * @param bool $loadSelfFirst
	 * @param bool $loadProfile
	 * @param bool $defaultSettings
	 * @return void
	 */
	public static function profileSubActionProvider(
		int $memID,
		?string $sa = null,
		bool $updateRequest = false,
		bool $loadSelfFirst = true,
		bool $loadProfile = false,
		bool $defaultSettings = false,
	): void {

		if ($updateRequest) {
			$u = $_REQUEST['u'] ?? null;
			$_REQUEST['u'] = $memID;
		}

		if ($loadSelfFirst) {
			self::load();
			if ($loadProfile) {
				SMF\Profile::load($memID);
			}
		} else {
			if ($loadProfile) {
				SMF\Profile::load($memID);
			}
			self::load();
		}

		if ($updateRequest) {
			$_REQUEST['u'] = $u;
		}

		if (isset($sa)) {
			self::$obj->subaction = $sa;
		}

		self::$obj->execute();
	}

	/**
	 *
	 * @param null|string $suggest_type
	 * @param bool $callHandler
	 * @return mixed
	 */
	public static function autoSuggestProvider(?string $suggest_type = null, bool $callHandler = false)
	{
		if (! $callHandler && isset($suggest_type)) {
			self::load();
			self::$obj->suggest_type = $suggest_type;
			self::$obj->execute();
		}

		if ($callHandler) {
			if (isset($suggest_type)) {
				return self::checkRegistered($suggest_type);
			} else {
				self::call();
			}
		}
	}
}

?>