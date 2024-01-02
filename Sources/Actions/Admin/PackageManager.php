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

if (!defined('SMF')) {
	die('No direct access...');
}

// Just an alias to help people looking for the package manager in the wrong namespace.
class_alias('SMF\\PackageManager\\PackageManager', 'SMF\\Actions\\Admin\\PackageManager');

?>