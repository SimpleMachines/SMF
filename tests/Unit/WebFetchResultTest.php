<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\WebFetch\APIs\CurlFetcher;
use SMF\WebFetch\APIs\SocketFetcher;

#[CoversClass(CurlFetcher::class)]
#[CoversClass(SocketFetcher::class)]
class WebFetchResultTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * result() returns null when nothing has been fetched.
	 *
	 * A fetcher that was never asked for anything, or one whose request was
	 * refused before a connection was attempted, has nothing in $response.
	 * WebFetchApi::fetch() asks for result('success') on exactly that path.
	 * The suite fails on warnings, so this needs no assertion about them.
	 *
	 * Expected: result('success'), result('body') and result() on a new
	 *           CurlFetcher and on a new SocketFetcher all return null.
	 * Guards:   result() worked out the last index as count() - 1, which is -1,
	 *           and read that, so every refused fetch emitted two warnings on
	 *           its way to returning false.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/4846a993a Introduced by "Implements SMF\WebFetch\WebFetchApi and related classes"
	 * @link https://github.com/SimpleMachines/SMF/pull/9535
	 */
	#[DataProvider('fetcherProvider')]
	public function testResultIsNullWhenNothingWasFetched(string $class): void
	{
		$fetcher = new $class();

		$this->assertNull($fetcher->result('success'));
		$this->assertNull($fetcher->result('body'));
		$this->assertNull($fetcher->result());
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string}>
	 */
	public static function fetcherProvider(): array
	{
		return [
			'curl' => [CurlFetcher::class],
			'socket' => [SocketFetcher::class],
		];
	}
}
