<?php

/*
 * An autoloader for ReCaptcha\Foo classes. This should be require()d
 * by the user before attempting to instantiate any of the ReCaptcha
 * classes.
 */

spl_autoload_register(function ($class) {
	if (substr($class, 0, 10) !== 'ReCaptcha\\') {
		/*
		 * If the class does not lie under the "ReCaptcha" namespace,
		 * then we can exit immediately.
		 */
		return;
	}

	/*
	 * All of the classes have names like "ReCaptcha\Foo", so we need
	 * to replace the backslashes with frontslashes if we want the
	 * name to map directly to a location in the filesystem.
	 */
	$class = str_replace('\\', '/', $class);

	// Check under the current directory.
	$path = __DIR__.'/'.$class.'.php';
	if (is_readable($path)) {
		require_once $path;
		return;
	}
});
