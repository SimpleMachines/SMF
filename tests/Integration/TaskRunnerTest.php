<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversMethod;
use SMF\Config;
use SMF\Services\ErrorHandlerService;
use SMF\TaskRunner;

/**
 * What cron.php logs when a background task throws.
 */
#[CoversMethod(TaskRunner::class, 'handleException')]
class TaskRunnerTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * The exception's message is logged.
	 *
	 * It was looked up as the key of a language string, which almost no
	 * exception message is, and the lookup came back empty. Every failing
	 * background task was logged as a blank "cron" error with a file and a
	 * line, and nothing to say what went wrong.
	 */
	public function testTheExceptionMessageIsLogged(): void
	{
		Config::$modSettings['enableErrorLogging'] = '1';

		TaskRunner::handleException(new \RuntimeException('The task fell over in a way worth reading about.'));

		[$message, $type] = $this->lastLogged();

		$this->assertSame('cron', $type);
		$this->assertSame('The task fell over in a way worth reading about.', $message);
	}

	/**
	 * An exception carrying the key of a language string logs that string.
	 */
	public function testALanguageStringKeyIsLoggedAsTheString(): void
	{
		Config::$modSettings['enableErrorLogging'] = '1';

		TaskRunner::handleException(new \RuntimeException('unsubscribe_invalid'));

		[$message] = $this->lastLogged();

		$this->assertStringContainsString('does not appear to be valid', $message);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The message and type of the error most recently logged.
	 *
	 * ErrorHandlerService::log() collects errors in a batch and writes them
	 * when the request ends, so the one just logged is not in log_errors yet.
	 * It keeps the last one it was given, to skip repeats, and that is read
	 * here instead.
	 *
	 * @return array The message and the error type.
	 */
	private function lastLogged(): array
	{
		$last = new \ReflectionMethod(ErrorHandlerService::class, 'log')->getStaticVariables()['last_error'] ?? null;

		$this->assertIsArray($last, 'nothing was logged');

		return [(string) $last[4], (string) $last[6]];
	}
}
