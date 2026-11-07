<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use SMF\Infrastructure\Container;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Services\ErrorHandlerService;

/**
 * Tests error logging against an installed forum.
 */
#[CoversClass(ErrorHandler::class)]
#[CoversClass(ErrorHandlerService::class)]
class ErrorHandlerServiceTest extends IntegrationTestCase
{
	/**
	 * Verifies that a full error batch is written to the error log.
	 */
	public function testFullBatchIsWrittenToErrorLog(): void
	{
		$marker = 'ErrorHandlerServiceTest-' . bin2hex(random_bytes(8));
		$service = new ErrorHandlerService();
		$service->batch_size = 2;

		$this->assertNoErrorsLogged();
		$last_error_id = $this->lastErrorId();

		$service->log($marker . '-0',  'Test');

		$num_errors =  $this->queryRow(
			'SELECT message
			FROM {db_prefix}log_errors
			WHERE message LIKE {string:pattern}
				AND id_error > {int:last_error_id}',
			[
				'pattern' => $marker . '-%',
				'last_error_id' => $last_error_id,
			],
		);

		$this->assertNull($num_errors);

		ErrorHandler::log($marker . '-1',  'Test');

		$num_errors = $this->queryRow(
			'SELECT COUNT(*)
			FROM {db_prefix}log_errors
			WHERE message LIKE {string:pattern}
				AND id_error > {int:last_error_id}',
			[
				'pattern' => $marker . '-%',
				'last_error_id' => $last_error_id,
			],
		);

		$this->assertEquals('2', current($num_errors));
	}

	/**
	 * Verifies that undefined errors use the undefined_vars error type.
	 */
	public function tesstUndefinedErrors(): void
	{
		$marker = 'Undefined variable: $my_special_error_' . bin2hex(random_bytes(8));
		Config::$modSettings['enableErrorLogging'] = '1';

		$this->assertNoErrorsLogged();
		$last_error_id = $this->lastErrorId();

		$error_reporting = error_reporting();
		error_reporting(E_USER_WARNING);
		set_error_handler(ErrorHandler::call(...));
		trigger_error($marker, E_USER_WARNING);
		restore_error_handler();
		error_reporting($error_reporting);

		$row = $this->queryRow(
			'SELECT error_type
			FROM {db_prefix}log_errors
			WHERE message = {string:message}
			ORDER BY id_error DESC
			LIMIT 1',
			[
				'message' => '%: ' . $marker,
			],
		);

		$this->assertNotNull($row);
		$this->assertSame('undefined_vars', $row['error_type']);
	}

/**
 * Verifies that undefined errors use the undefined_vars error type and log a backtrace.
 */
public function testUndefinedErrors(): void
{
	$marker = 'Undefined variable: $my_special_error_' . bin2hex(random_bytes(8));
	Config::$modSettings['enableErrorLogging'] = '1';

	$this->assertNoErrorsLogged();
	$last_error_id = $this->lastErrorId();

	$error_reporting = error_reporting();
	error_reporting(E_USER_WARNING);
	set_error_handler(ErrorHandler::call(...));
	trigger_error($marker, E_USER_WARNING);
	restore_error_handler();
	error_reporting($error_reporting);

	$row = $this->queryRow(
		'SELECT error_type, backtrace
		FROM {db_prefix}log_errors
		WHERE message = {string:message}
		ORDER BY id_error DESC
		LIMIT 1',
		[
			'message' => E_USER_WARNING . ': ' . $marker,
		],
	);

	$this->assertNotNull($row);
	$this->assertSame('undefined_vars', $row['error_type']);

	$backtrace = json_decode($row['backtrace'], true);

	$this->assertIsArray($backtrace);
	$this->assertIsList($backtrace);
	$this->assertNotEmpty($backtrace);

	$this->assertArrayHasKey('file', $backtrace[0]);
	$this->assertArrayHasKey('function', $backtrace[0]);
	$this->assertSame(__FILE__, $backtrace[0]['file']);
	$this->assertSame('trigger_error', $backtrace[0]['function']);
}
}
