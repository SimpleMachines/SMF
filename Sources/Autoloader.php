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

namespace SMF;


$loader = require Config::$vendordir . '/autoload.php';
$third_party_mappers = [];

// Ensure $sourcedir is set to something valid.
if (class_exists(Config::class, false) && isset(Config::$sourcedir)) {
	$sourcedir = Config::$sourcedir;
}

if (empty($sourcedir) || !is_dir($sourcedir)) {
	$sourcedir = __DIR__;
}

// Do any third-party scripts want in on the fun?
if (!defined('SMF_INSTALLING') && class_exists(Config::class, false)) {
	if (!class_exists(IntegrationHook::class, false) && is_file($sourcedir . '/IntegrationHook.php')) {
		require_once $sourcedir . '/IntegrationHook.php';
	}

	if (class_exists(IntegrationHook::class, false)) {
		IntegrationHook::call('integrate_autoload', [&$third_party_mappers]);
	}
}

foreach ($third_party_mappers as $prefix => $dirname) {
	$loader->addPsr4($prefix, $dirname);
}

?>