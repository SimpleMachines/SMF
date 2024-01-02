<?php

/**
 * This is a lightweight proxy for serving images, generally meant to be used alongside SSL
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
	define('SMF', 'PROXY');
}

if (SMF == 'PROXY') {
	// Initialize.
	require_once __DIR__ . '/index.php';

	$proxy = new SMF\ProxyServer();
	$proxy->serve();
}
// In case a old mod included this file in order to load the ProxyServer class.
else {
	class_exists('SMF\\ProxyServer');
}

?>