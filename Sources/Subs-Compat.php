<?php

/**
 * This file provides compatibility functions and code for older versions of
 * SMF and PHP, such as missing extensions or 64-bit vs 32-bit systems.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Punycode;

if (!defined('SMF')) {
	die('No direct access...');
}

/*********************************************
 * SMF\Config::$backward_compatibility support
 *********************************************/

if (!empty(SMF\Config::$backward_compatibility)) {
	/*
	 * In SMF 2.x, there was a file named Subs.php that was always loaded early in
	 * the startup process and that contained many utility functions. Everything
	 * else assumed that those functions were available. Subs.php went the way of
	 * the dodo in SMF 3.0 after all its functions were migrated elsewhere, but
	 * mods that rely on backward compatibilty support will still expect all those
	 * functions to be available. So if backward compatibilty support is enabled,
	 * we need to load a bunch of classes in order to make them available.
	 */
	class_exists('SMF\\Attachment');
	class_exists('SMF\\BBCodeParser');
	class_exists('SMF\\Logging');
	class_exists('SMF\\PageIndex');
	class_exists('SMF\\Theme');
	class_exists('SMF\\Time');
	class_exists('SMF\\TimeZone');
	class_exists('SMF\\Topic');
	class_exists('SMF\\Url');
	class_exists('SMF\\User');
	class_exists('SMF\\Graphics\\Image');
	class_exists('SMF\\WebFetch\\WebFetchApi');
}

/***************************
 * PHP version compatibility
 ***************************/

/*
 * Prevent fatal errors under PHP 8 when a disabled internal function is called.
 *
 * Before PHP 8, calling a disabled internal function merely generated a
 * warning that could be easily suppressed by the @ operator. But as of PHP 8
 * a disabled internal function is treated like it is undefined, which means
 * a fatal error will be thrown and execution will halt. SMF expects the old
 * behaviour, so these no-op polyfills make sure that is what happens.
 */
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
	// This is wrapped in a closure to keep the global namespace clean.
	call_user_func(function () {
		/*
		 * This array contains function names that meet the following conditions:
		 *
		 * 1. SMF assumes they are defined, even if disabled. Note that prior to
		 *    PHP 8, this was always true for internal functions.
		 *
		 * 2. Some hosts are known to disable them.
		 *
		 * 3. SMF can get by without them (as opposed to missing functions that
		 *    really SHOULD cause execution to halt).
		 */
		$optional_funcs = [
			'set_time_limit',
		];

		foreach ($optional_funcs as $func) {
			if (!function_exists($func)) {
				eval('function ' . $func . '() { trigger_error("' . $func . '() has been disabled", E_USER_WARNING); }');
			}
		}
	});
}

if (!function_exists('smf_crc32')) {
	/**
	 * Compatibility function.
	 * crc32 doesn't work as expected on 64-bit functions - make our own.
	 * https://php.net/crc32#79567
	 *
	 * @param string $number
	 * @return string The crc32 polynomial of $number
	 */
	function smf_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000) {
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}

/*****************
 * Polyfills, etc.
 *****************/

if (!function_exists('idn_to_ascii')) {
	// This is wrapped in a closure to keep the global namespace clean.
	call_user_func(function () {
		/**
		 * IDNA_* constants used as flags for the idn_to_* functions.
		 */
		$idna_constants = [
			'IDNA_DEFAULT' => 0,
			'IDNA_ALLOW_UNASSIGNED' => 1,
			'IDNA_USE_STD3_RULES' => 2,
			'IDNA_CHECK_BIDI' => 4,
			'IDNA_CHECK_CONTEXTJ' => 8,
			'IDNA_NONTRANSITIONAL_TO_ASCII' => 16,
			'IDNA_NONTRANSITIONAL_TO_UNICODE' => 32,
			'INTL_IDNA_VARIANT_2003' => 0,
			'INTL_IDNA_VARIANT_UTS46' => 1,
		];

		foreach ($idna_constants as $name => $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}
	});

	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_ASCII,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain The domain to convert, which must be UTF-8 encoded.
	 * @param int $flags A subset of possible IDNA_* flags.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name encoded in ASCII-compatible form, or false on failure.
	 */
	function idn_to_ascii($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		static $Punycode;

		if (!is_object($Punycode)) {
			$Punycode = new Punycode();
		}

		if (method_exists($Punycode, 'useStd3')) {
			$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		}

		if (method_exists($Punycode, 'useNonTransitional')) {
			$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_ASCII));
		}

		return $Punycode->encode($domain);
	}

	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_UNICODE,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain Domain to convert, in an IDNA ASCII-compatible format.
	 * @param int $flags Ignored in this compatibility function.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name in Unicode, encoded in UTF-8, or false on failure.
	 */
	function idn_to_utf8($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		static $Punycode;

		if (!is_object($Punycode)) {
			$Punycode = new Punycode();
		}

		$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_UNICODE));

		return $Punycode->decode($domain);
	}
}

?>