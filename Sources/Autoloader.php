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

namespace SMF;

/*
 * An autoloader for certain classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(function ($class) {
	static $hook_value = '';

	static $class_map = [
		// Some special cases.
		'ReCaptcha\\' => 'ReCaptcha/',
		'MatthiasMullie\\Minify\\' => 'minify/src/',
		'MatthiasMullie\\PathConverter\\' => 'minify/path-converter/src/',

		// In general, the SMF namespace maps to $sourcedir.
		'SMF\\' => '',
	];

	// Ensure $sourcedir is set to something valid.
	if (class_exists('SMF\\Config', false) && isset(Config::$sourcedir)) {
		$sourcedir = Config::$sourcedir;
	}

	if (empty($sourcedir) || !is_dir($sourcedir)) {
		$sourcedir = __DIR__;
	}

	// Do any third-party scripts want in on the fun?
	if (!defined('SMF_INSTALLING') && class_exists('SMF\\Config', false) && $hook_value !== (Config::$modSettings['integrate_autoload'] ?? '')) {
		if (!class_exists('SMF\\IntegrationHook', false) && is_file($sourcedir . '/IntegrationHook.php')) {
			require_once $sourcedir . '/IntegrationHook.php';
		}

		if (class_exists('SMF\\IntegrationHook', false)) {
			$hook_value = Config::$modSettings['integrate_autoload'];
			IntegrationHook::call('integrate_autoload', [&$class_map]);
		}
	}

	foreach ($class_map as $prefix => $dirname) {
		// Does the class use the namespace prefix?
		$len = strlen($prefix);

		if (strncmp($prefix, $class, $len) !== 0) {
			continue;
		}

		// Get the relative class name.
		$relative_class = substr($class, $len);

		// Replace the namespace prefix with the base directory, replace namespace
		// separators with directory separators in the relative class name, append
		// with .php
		$filename = $dirname . strtr($relative_class, '\\', '/') . '.php';

		// Failsafe: Never load a file named index.php.
		if (basename($filename) === 'index.php') {
			return;
		}

		// If the file exists, require it.
		if (file_exists($filename = $sourcedir . '/' . $filename)) {
			require $filename;

			return;
		}
	}
});

?>