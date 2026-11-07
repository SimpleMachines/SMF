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

	/**
	 * A generated UUID is written as five lowercase hex groups.
	 *
	 * Expected: (string) Uuid::create(4) matches 8-4-4-4-12 lowercase hex digits.
	 */
	public function testGeneratedUuidsLookLikeUuids(): void
	{
		$this->assertMatchesRegularExpression(
			'~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~',
			(string) Uuid::create(4),
		);
	}

	/**
	 * Two generated UUIDs are not the same.
	 *
	 * Expected: the strings of two Uuid::create(4) calls differ.
	 */
	public function testGeneratedUuidsAreDistinct(): void
	{
		$this->assertNotSame((string) Uuid::create(4), (string) Uuid::create(4));
	}

	/**
	 * Generated UUIDs always report the RFC 4122 variant.
	 *
	 * Expected: getVariant() returns 1 for versions 4 and 7.
	 */
	public function testTheVariantIsAlwaysTheRfcOne(): void
	{
		$this->assertSame(1, Uuid::create(4)->getVariant());
		$this->assertSame(1, Uuid::create(7)->getVariant());
	}

	/**
	 * The nil UUID is read back unchanged and reports version zero.
	 *
	 * Expected: Uuid::createFromString() of the all-zero UUID casts back to
	 *           the same string, and getVersion() returns 0.
	 */
	public function testTheNilUuidRoundTripsAndReportsVersionZero(): void
	{
		$uuid = Uuid::createFromString(self::NIL);

		$this->assertSame(self::NIL, (string) $uuid);
		$this->assertSame(0, $uuid->getVersion());
	}

	/**
	 * The binary form of a UUID is sixteen bytes long.
	 *
	 * Expected: strlen(Uuid::create(4)->getBinary()) is 16.
	 */
	public function testTheBinaryFormIsSixteenBytes(): void
	{
		$this->assertSame(16, \strlen(Uuid::create(4)->getBinary()));
	}

	/**
	 * The short form of a UUID is twenty-two characters long.
	 *
	 * Expected: strlen(Uuid::create(4)->getShortForm()) is 22.
	 */
	public function testTheShortFormIsTwentyTwoCharacters(): void
	{
		$this->assertSame(22, \strlen(Uuid::create(4)->getShortForm()));
	}

	/**
	 * The lowercase short form is twenty-six lowercase base 32 characters.
	 *
	 * Expected: getShortForm(true) is 26 characters long and matches
	 *           ^[0-9a-z]+$.
	 */
	public function testShortFormLowercaseIsTwentySixCharactersAndOnlyLowercaseBase32Chars(): void
	{
		$short = Uuid::create(4)->getShortForm(true);

		$this->assertSame(26, \strlen($short));
		// Crockford-style base32 alternative alphabet (digits + lowercase letters w/o ambiguous chars)
		$this->assertMatchesRegularExpression('~^[0-9a-z]+$~', $short);
	}

	/**
	 * Compressing a UUID and expanding the result gives the UUID back.
	 *
	 * Expected: Uuid::expand(Uuid::compress($uuid)) returns $uuid.
	 */
	public function testCompressAndExpandRoundTrip(): void
	{
		$uuid = (string) Uuid::create(4);

		$this->assertSame($uuid, Uuid::expand(Uuid::compress($uuid)));
	}

	/**
	 * Strict parsing refuses a string that is not a UUID.
	 *
	 * Expected: Uuid::createFromString('not-a-uuid', true) throws a
	 *           ValueError.
	 */
	public function testStrictParsingRejectsRubbish(): void
	{
		$this->expectException(\ValueError::class);

		Uuid::createFromString('not-a-uuid', true);
	}

	/**
	 * A version 7 UUID created later sorts after one created earlier.
	 *
	 * Version 7 puts a millisecond timestamp in the high bits, so the string
	 * form is monotonic. That is the whole point of using it for keys.
	 *
	 * Expected: strcmp() of the string of a version 7 UUID and one created
	 *           a millisecond later is negative.
	 */
	public function testVersionSevenUuidsSortByCreationOrder(): void
	{
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

	/**
	 * Uuid::create() produces a UUID of the version it was asked for.
	 *
	 * Expected: for each version in versionProvider(), getVersion() returns
	 *           that version.
	 */
	#[DataProvider('versionProvider')]
	public function testCreateProducesTheRequestedVersion(int $version): void
	{
		$this->assertSame($version, Uuid::create($version)->getVersion());
	}

	/**
	 * A UUID survives compression to binary, base 64 and base 32.
	 *
	 * Expected: for each version in versionProvider(), compress() gives 16,
	 *           22 and 26 characters respectively, and expand() of each
	 *           returns the original UUID.
	 */
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

	/**
	 * createFromString() reads the binary, base 64 and base 32 forms.
	 *
	 * Expected: for each version in versionProvider(), Uuid::createFromString()
	 *           of each compressed form casts back to the original UUID.
	 */
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

	/**
	 * A UUID parsed from its canonical string has the matching binary form.
	 *
	 * Expected: for each version in versionProvider(),
	 *           Uuid::createFromString($uuid)->getBinary() equals
	 *           Uuid::compress($uuid, Uuid::COMPRESS_BINARY).
	 */
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
