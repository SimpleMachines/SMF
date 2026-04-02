<?php

use SMF\Services\ErrorHandlerService;
use SMF\Services\ModSettingsService;
use SMF\Services\SettingsService;

// List of all services registered for SMF, example:
//'db' => [
//	'arguments' => [$db_server, $db_user],
//	'shared' => true  // false will create a new instance everytime
//],
return [
	// Settings.php configuration service
	SettingsService::class => [
		'shared' => true,
	],
	// Database settings service
	ModSettingsService::class => [
		'shared' => true,
	],
	// Error handler service
	ErrorHandlerService::class => [
		'shared' => true,
	],
];
