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
	 * explicitly included in $backcompat['prop_names'].
	 *
	 * $backcompat['prop_names'] is a simple array where the keys are the names
	 * of one or more of a class's static properties, and the values are the
	 * names of global variables. In each case, the global variable will be set
	 * to a reference to the static property. Static properties that are not
	 * named in this array will not be exported.
	 *
	 * Adding non-static properties to the $backcompat arrays will produce
	 * runtime errors. It is the responsibility of the developer to make sure
	 * not to do this.
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

}

?>