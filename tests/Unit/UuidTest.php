<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Uuid;

#[CoversClass(Uuid::class)]
class UuidTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const NIL = '00000000-0000-0000-0000-000000000000';

	/****************
	 * Public methods
	 ****************/

	public function testGeneratedUuidsLookLikeUuids(): void
	{
		$this->assertMatchesRegularExpression(
			'~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~',
			(string) Uuid::create(4),
		);
	}

	public function testGeneratedUuidsAreDistinct(): void
	{
		$this->assertNotSame((string) Uuid::create(4), (string) Uuid::create(4));
	}

	public function testTheVariantIsAlwaysTheRfcOne(): void
	{
		$this->assertSame(1, Uuid::create(4)->getVariant());
		$this->assertSame(1, Uuid::create(7)->getVariant());
	}

	public function testTheNilUuidRoundTripsAndReportsVersionZero(): void
	{
		$uuid = Uuid::createFromString(self::NIL);

		$this->assertSame(self::NIL, (string) $uuid);
		$this->assertSame(0, $uuid->getVersion());
	}

	public function testTheBinaryFormIsSixteenBytes(): void
	{
		$this->assertSame(16, \strlen(Uuid::create(4)->getBinary()));
	}

	public function testTheShortFormIsTwentyTwoCharacters(): void
	{
		$this->assertSame(22, \strlen(Uuid::create(4)->getShortForm()));
	}

	public function testCompressAndExpandRoundTrip(): void
	{
		$uuid = (string) Uuid::create(4);

		$this->assertSame($uuid, Uuid::expand(Uuid::compress($uuid)));
	}

	public function testStrictParsingRejectsRubbish(): void
	{
		$this->expectException(\ValueError::class);

		Uuid::createFromString('not-a-uuid', true);
	}

	public function testVersionSevenUuidsSortByCreationOrder(): void
	{
		// Version 7 puts a millisecond timestamp in the high bits, so the string
		// form is monotonic. That is the whole point of using it for keys.
		$first = (string) Uuid::create(7);
		usleep(2000);
		$second = (string) Uuid::create(7);

		$this->assertLessThan(0, strcmp($first, $second));
	}

	#[DataProvider('versionProvider')]
	public function testCreateProducesTheRequestedVersion(int $version): void
	{
		$this->assertSame($version, Uuid::create($version)->getVersion());
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{int}>
	 */
	public static function versionProvider(): array
	{
		return [
			'v4 random' => [4],
			'v7 time ordered' => [7],
		];
	}
}
