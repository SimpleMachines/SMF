<?php

/**
 * This file allows external access to SMF data via Server Side Includes (SSI).
 *
 * The standard SSI functions are declared in ./Sources/ServerSideIncludes.php.
 * All standard SSI functions can be called using either of the following forms:
 *
 *  - `SMF\ServerSideIncludes::funcname();`
 *  - `ssi_funcname();`
 *
 * Mods can add more SSI functions using the integrate_SSI hook.
 *
 * External scripts can set several global variables to control SSI behaviour.
 * For more information about these variables and what they do, see the
 * "Properties that allow external scripts to control SSI behaviour" section
 * of ./Sources/ServerSideIncludes.php.
 *
 * See the ssi_examples.php file for examples of how to use SSI functions.
 *
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

if (!defined('SMF')) {
	define('SMF', 'SSI');
}

// Initialize.
require_once __DIR__ . '/index.php';

$ssi = new SMF\ServerSideIncludes();
$ssi->execute();

?>