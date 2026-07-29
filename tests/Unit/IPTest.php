<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\IP;

#[CoversClass(IP::class)]
class IPTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testItNormalisesIPv6ToItsShortestForm(): void
	{
		$this->assertSame('2001:db8::1', (string) new IP('2001:DB8::0001'));
	}

	public function testItKeepsIPv4MappedAddressesIntact(): void
	{
		$this->assertSame('::ffff:1.2.3.4', (string) new IP('::ffff:1.2.3.4'));
	}

	public function testFlagsNarrowValidationToOneFamily(): void
	{
		$this->assertTrue((new IP('1.2.3.4'))->isValid(FILTER_FLAG_IPV4));
		$this->assertFalse((new IP('1.2.3.4'))->isValid(FILTER_FLAG_IPV6));
		$this->assertTrue((new IP('2001:db8::1'))->isValid(FILTER_FLAG_IPV6));
	}

	public function testBinaryAndHexRoundTrip(): void
	{
		$ip = new IP('1.2.3.4');

		$this->assertSame('01020304', $ip->toHex());
		$this->assertSame(4, \strlen((string) $ip->toBinary()));
		$this->assertSame('1.2.3.4', (string) new IP((string) $ip->toBinary()));
	}

	public function testAnEmptyOrUnparseableValueIsNotValid(): void
	{
		$this->assertFalse((new IP(''))->isValid());
		$this->assertFalse((new IP('abcde'))->isValid());
		$this->assertFalse((new IP('999.999.999.999'))->isValid());
	}

	public function testAnyFourByteStringIsReadAsAPackedAddress(): void
	{
		// The constructor accepts the packed binary form, and it cannot tell that
		// apart from a four character string. This is a sharp edge worth pinning
		// down: 'nope' is not rejected, it becomes an address.
		$this->assertSame('110.111.112.101', (string) new IP('nope'));
		$this->assertTrue((new IP('nope'))->isValid());

		// The same applies at 16 bytes, where it becomes an IPv6 address.
		$this->assertTrue((new IP('not an ip at all'))->isValid());
	}

	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new IP($input))->isValid());
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function validityProvider(): array
	{
		return [
			'ipv4' => ['192.168.0.1', true],
			'ipv4 broadcast' => ['255.255.255.255', true],
			'ipv6' => ['2001:db8::1', true],
			'ipv6 loopback' => ['::1', true],
			'octet out of range' => ['256.1.1.1', false],
			'too few octets' => ['1.2.3', false],
			'empty' => ['', false],
			// Any 4 or 16 byte string is read as a packed address instead, so a
			// rubbish value only fails validation at some other length. See
			// testAnyFourByteStringIsReadAsAPackedAddress().
			'words' => ['not an ip address at all', false],
		];
	}
}
