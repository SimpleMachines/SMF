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

	public function testShortFormLowercaseIsTwentySixCharactersAndOnlyLowercaseBase32Chars(): void
	{
		$short = Uuid::create(4)->getShortForm(true);

		$this->assertSame(26, \strlen($short));
		// Crockford-style base32 alternative alphabet (digits + lowercase letters w/o ambiguous chars)
		$this->assertMatchesRegularExpression('~^[0-9a-z]+$~', $short);
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

		// Sleeping for a millisecond is not the same as one having passed.
		// Windows' timer granularity is coarser than that, so both values can
		// land in the same millisecond, where the timestamps are identical and
		// only the random bits are left to order them -- which they do about
		// half the time. Wait until the clock the generator reads has actually
		// moved on.
		$millisecond = static fn(): int => (int) (microtime(true) * 1000);
		$started = $millisecond();

		do {
			usleep(250);
		} while ($millisecond() === $started);

		$second = (string) Uuid::create(7);

		$this->assertLessThan(0, strcmp($first, $second));
	}

	#[DataProvider('versionProvider')]
	public function testCreateProducesTheRequestedVersion(int $version): void
	{
		$this->assertSame($version, Uuid::create($version)->getVersion());
	}

	#[DataProvider('versionProvider')]
	public function testBinaryBase64AndBase32Roundtrips(int $version): void
	{
		$uuid = (string) Uuid::create($version);

		// Binary roundtrip
		$binary = Uuid::compress($uuid, Uuid::COMPRESS_BINARY);
		$this->assertSame(16, \strlen($binary));
		$this->assertSame(strtolower($uuid), strtolower(Uuid::expand($binary)));

		// Base64 roundtrip
		$b64 = Uuid::compress($uuid, Uuid::COMPRESS_BASE64);
		$this->assertSame(22, \strlen($b64));
		$this->assertSame(strtolower($uuid), strtolower(Uuid::expand($b64)));

		// Base32 roundtrip
		$b32 = Uuid::compress($uuid, Uuid::COMPRESS_BASE32);
		$this->assertSame(26, \strlen($b32));
		$this->assertSame(strtolower($uuid), strtolower(Uuid::expand($b32)));
	}

	#[DataProvider('versionProvider')]
	public function testCreateFromStringAcceptsBinaryBase64AndBase32(int $version): void
	{
		$uuid = (string) Uuid::create($version);

		$binary = Uuid::compress($uuid, Uuid::COMPRESS_BINARY);
		$this->assertSame(strtolower($uuid), strtolower((string) Uuid::createFromString($binary)));

		$b64 = Uuid::compress($uuid, Uuid::COMPRESS_BASE64);
		$this->assertSame(strtolower($uuid), strtolower((string) Uuid::createFromString($b64)));

		$b32 = Uuid::compress($uuid, Uuid::COMPRESS_BASE32);
		$this->assertSame(strtolower($uuid), strtolower((string) Uuid::createFromString($b32)));
	}

	#[DataProvider('versionProvider')]
	public function testParsedCanonicalHasConsistentBinaryRepresentation(int $version): void
	{
		$uuid = (string) Uuid::create($version);

		$binary = Uuid::compress($uuid, Uuid::COMPRESS_BINARY);

		$parsed = Uuid::createFromString($uuid);
		$this->assertSame($binary, $parsed->getBinary());
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
