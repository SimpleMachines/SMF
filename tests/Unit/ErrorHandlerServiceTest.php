<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Services\ErrorHandlerService;

/**
 * Covers the re-entrancy guard in SMF\Services\ErrorHandlerService.
 *
 * Logging an error is allowed to produce one more error, but a third means
 * whatever the method depends on is failing every time it is called, so it
 * stops rather than going round again. With error logging switched off none
 * of that machinery is reached, which keeps this on the near side of the
 * database.
 */
#[CoversClass(ErrorHandlerService::class)]
class ErrorHandlerServiceTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool Whether enableErrorLogging was set before the test.
	 */
	private bool $had_setting = false;

	/**
	 * @var mixed enableErrorLogging as it was before the test.
	 */
	private mixed $setting = null;

	/****************
	 * Public methods
	 ****************/

	public function testErrorsThatAreNotLoggedDoNotCountAsALoop(): void
	{
		// The guard counts calls that are on the stack, and the count was only
		// cleared at the very end of the method. Returning early because
		// logging is off skipped that, so the count climbed on every call and
		// the third unrelated error in a request died with 'loop detected',
		// which in a test run takes the whole process with it.
		$service = new ErrorHandlerService();

		foreach (range(1, 5) as $i) {
			$this->assertSame('error ' . $i, $service->log('error ' . $i));
		}
	}

	public function testTheMessageIsHandedBackUnchangedWhenNothingIsLogged(): void
	{
		// Callers use the return value to build what they show, as in
		// die(ErrorHandler::log($msg)), so it has to come back whether the
		// error was recorded or not.
		$service = new ErrorHandlerService();

		$this->assertSame('something went wrong', $service->log('something went wrong'));
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		$this->had_setting = isset(Config::$modSettings['enableErrorLogging']);
		$this->setting = Config::$modSettings['enableErrorLogging'] ?? null;

		Config::$modSettings['enableErrorLogging'] = false;
	}

	/**
	 * PHPUnit does not reset SMF's statics between tests, so a setting left
	 * behind here would leak into every test that follows.
	 */
	protected function tearDown(): void
	{
		unset(Config::$modSettings['enableErrorLogging']);

		if ($this->had_setting) {
			Config::$modSettings['enableErrorLogging'] = $this->setting;
		}
	}
}
