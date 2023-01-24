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
	 * explicitly included in $backcompat['prop_names']. In contrast, a class's
	 * static methods are automatically exported as global functions unless
	 * explicitly excluded by setting their entry in $backcompat['func_names']
	 * to false.
	 *
	 * $backcompat['prop_names'] is a simple array where the keys are the names
	 * of one or more of a class's static properties, and the values are the
	 * names of global variables. In each case, the global variable will be set
	 * to a reference to the static property. Static properties that are not
	 * named in this array will not be exported.
	 *
	 * $backcompat['func_names'] allows you to manually specify the global
	 * function names that any given methods should be mapped to. These
	 * manually specified function names will ignore and override the effects
	 * of $backcompat['func_underscores'] and $backcompat['func_prefix'].
	 * If you want to prevent a static method from being exported to global
	 * namespace at all, set its $backcompat['func_names'] entry to false.
	 *
	 * For convenience, two additional $backcompat options are available:
	 * $backcompat['func_underscores'] and $backcompat['func_prefix'].
	 *
	 * If $backcompat['func_underscores'] is true, method names in camelCase will
	 * have underscores inserted into them (e.g.: 'parseIri' --> 'parse_iri').
	 * This should usually only be set to true if the majority of the methods in
	 * a class were converted from functions that used underscores in their
	 * names. If you need to map method names to function names in a mixture of
	 * both styles, choose one style for the default and then deal with the
	 * exceptions manually via $backcompat['func_names'].
	 *
	 * If $backcompat['func_prefix'] is set to a string, that string will be
	 * prepended to the function name. If $backcompat['func_underscores'] is
	 * true, the prefix will be joined to the rest of the function name by an
	 * underscore, but the prefix itself will always be used as-is.
	 *
	 * If instead $backcompat['func_prefix'] is set to true, a prefix derived
	 * from the fully qualified class name will be prepended to the func name.
	 * This prefix will respect the value of $backcompat['func_underscores'].
	 * In most cases, at least for SMF's code, $backcompat['func_underscores']
	 * should be true when $backcompat['func_prefix'] is true.
	 *
	 * $backcompat['func_underscores'] and $backcompat['func_prefix'] don't apply
	 * to functions that are manually named in $backcompat['func_names'].
	 */
	public static function exportStatic(): void
	{
		// Do nothing if backward compatibility has been turned off.
		if (empty(\SMF\Config::$backward_compatibility))
			return;

		$reflector = new \ReflectionClass(__CLASS__);

		// Get any backward compatibility settings.
		$backcompat = $reflector->getStaticPropertyValue('backcompat', array());

		$backcompat['func_underscores'] = !empty($backcompat['func_underscores']);
		$backcompat['func_prefix'] = $backcompat['func_prefix'] ?? '';
		$backcompat['func_names'] = $backcompat['func_names'] ?? array();
		$backcompat['prop_names'] = $backcompat['prop_names'] ?? array();

		// The property names are simple enough to deal with...
		foreach ($backcompat['prop_names'] as $static => $global)
			$GLOBALS[$global] = &self::${$static};

		// Get the public static functions.
		$static_methods = array_intersect($reflector->getMethods(\ReflectionMethod::IS_STATIC), $reflector->getMethods(\ReflectionMethod::IS_PUBLIC));

		// Filter out the parent class's public static functions.
		if (is_object($parent_reflector = $reflector->getParentClass()))
		{
			$static_methods = array_map('json_decode', array_diff(array_map(__CLASS__ . '::methodAsJson', $static_methods), array_map(__CLASS__ . '::methodAsJson', array_intersect($parent_reflector->getMethods(\ReflectionMethod::IS_STATIC), $parent_reflector->getMethods(\ReflectionMethod::IS_PUBLIC)))));
		}

		foreach ($static_methods as $method)
		{
			$method = (array) $method;

			// Skip the static methods supplied by this trait.
			if (method_exists(__TRAIT__, $method['name']))
				continue;

			// If the name is manually set to false (or anything invalid), skip this method.
			if (isset($backcompat['func_names'][$method['name']]) && !is_string($backcompat['func_names'][$method['name']]))
			{
				continue;
			}

			// What global function name shall we use for this method?
			$func = $backcompat['func_underscores'] ? self::toUnderscores($method['name']) : $method['name'];

			// Allow overriding the function name.
			if (isset($backcompat['func_names'][$method['name']]))
			{
				$func = $backcompat['func_names'][$method['name']];
			}
			// Allow manually specified prefixes.
			elseif ($backcompat['func_prefix'] !== true)
			{
				if ($backcompat['func_underscores'] && substr($backcompat['func_prefix'], -1) !== '_')
				{
					$backcompat['func_prefix'] .= '_';
				}

				$func = strtolower($backcompat['func_prefix'] . $func);
			}
			// Avoid name conflicts with existing functions.
			else
			{
				if ($backcompat['func_underscores'])
				{
					$full_prefix = strtolower(strtr($method['class'], '\\', '_')) . '_';
				}
				else
				{
					$full_prefix = str_replace(' ', '', ucwords(strtr($method['class'], '\\', ' ')));
				}

				$i = 0;
				$prefix = '';
				$class_parts = explode('\\', $method['class']);
				while ($backcompat['func_prefix'] === true || function_exists($prefix . $func))
				{
					$prefix = array_slice($class_parts, $i--);

					if ($backcompat['func_underscores'])
					{
						$prefix = strtolower(implode('_', $prefix)) . '_';
					}
					else
					{
						$prefix = str_replace(' ', '', ucwords(implode(' ', $prefix)));
					}

					if ($prefix === $full_prefix || $i < -count($class_parts))
						break;
				}

				$func = strtolower($prefix . $func);
			}

			// If function already exists, die violently.
			if (function_exists($func))
				throw new \Exception("Function $func already exists", 1);

			// Here's where the magic happens.
			eval('function ' . $func . '(...$args) { return ' . $method['class'] . '::' . $method['name'] . '(...$args); }');
		}
	}

	/**
	 * Converts a camelCase string to an underscore separated string.
	 * For example, this converts "fooBarBaz" to "foo_bar_baz".
	 *
	 * @param string $string A (possibly) camelCase string.
	 * @return string An underscore separated string.
	 */
	public static function toUnderscores(string $string): string
	{
		return strtolower(preg_replace('/(?<=\P{Lu})\p{Lu}/', '_$0', $string));
	}

	/**
	 * Converts an underscore separated string to a camelCase string.
	 * For example, this converts "foo_bar_baz" to "fooBarBaz".
	 *
	 * @param string $string A (possibly) underscore separated string.
	 * @return string A camelCase string.
	 */
	public static function toCamelCase(string $string): string
	{
		return preg_replace_callback(
			'/_(\p{L})/',
			function ($matches)
			{
				return strtoupper($matches[1]);
			},
			strtolower($string)
		);
	}

	/**
	 * Small callback function used by exportStatic(). Pay this no mind.
	 *
	 * @param object $method A \ReflectorMethod object.
	 * @return string A JSON string.
	 */
	private static function methodAsJson(object $method): string
	{
		return json_encode((array) $method);
	}
}

?>