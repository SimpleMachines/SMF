<?php

use SMF\Services\ErrorHandlerService;

// List of all services registered for SMF, example:
//'db' => [
//	'arguments' => [$db_server, $db_user],
//	'shared' => true  // false will create a new instance everytime
//],
return [
    ErrorHandlerService::class => [
        'shared' => true,
    ],
];
