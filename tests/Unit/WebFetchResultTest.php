<?php

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
	 * A fetcher that was never asked for anything, or one whose request was
	 * refused before a connection was attempted, has nothing in $response.
	 * result() worked out the last index as count() - 1, which is -1, and read
	 * that. WebFetchApi::fetch() asks for result('success') on exactly that
	 * path, so every refused fetch emitted two warnings on its way to
	 * returning false.
	 *
	 * The suite fails on warnings, so this needs no assertion about them.
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
