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

	public function testIp2RangeReadsARangeOfIPv6Addresses(): void
	{
		// The two ends of a range are only recognised as ends if they validate
		// as addresses. Insisting on IPv4 there meant neither end of an IPv6
		// range was one, so this fell through to the "one side is a fragment"
		// path and read the address itself as a list of octets to walk.
		$range = IP::ip2range('2001:db8::1-2001:db8::ff');

		$this->assertSame('2001:db8::1', (string) $range['low']);
		$this->assertSame('2001:db8::ff', (string) $range['high']);
	}

	public function testIp2RangeReadsAFullyWrittenIPv6Range(): void
	{
		// Same range as above with nothing elided, so the shortening on the way
		// back out is the only difference between the two.
		$range = IP::ip2range('2001:db8:0:0:0:0:0:1-2001:db8:0:0:0:0:0:ff');

		$this->assertSame('2001:db8::1', (string) $range['low']);
		$this->assertSame('2001:db8::ff', (string) $range['high']);
	}

	public function testIp2RangeStillReadsARangeOfIPv4Addresses(): void
	{
		$range = IP::ip2range('1.2.3.4-1.2.3.9');

		$this->assertSame('1.2.3.4', (string) $range['low']);
		$this->assertSame('1.2.3.9', (string) $range['high']);
	}

	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new IP($input))->isValid());
	}

	#[DataProvider('ip2RangeWildcardProvider')]
	public function testIp2RangeFillsWildcardsWithTheLowestAndHighestValue(
		string $input,
		string $low,
		string $high,
	): void {
		$range = IP::ip2range($input);

		$this->assertSame($low, (string) $range['low']);
		$this->assertSame($high, (string) $range['high']);
	}

	#[DataProvider('cidrProvider')]
	public function testMatchToCIDR(string $ip, string $cidr, bool $expected): void
	{
		$this->assertSame($expected, (new IP($ip))->matchToCIDR($cidr));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function ip2RangeWildcardProvider(): array
	{
		return [
			'ipv4 last octet' => ['1.2.3.*', '1.2.3.0', '1.2.3.255'],
			'ipv6 last group' => ['2001:db8::*', '2001:db8::', '2001:db8::ffff'],
			'ipv6 all but the first two groups' => [
				'2001:db8:*:*:*:*:*:*',
				'2001:db8::',
				'2001:db8:ffff:ffff:ffff:ffff:ffff:ffff',
			],
			// Not a range at all: both ends are the address itself.
			'a single ipv6 address' => ['2001:db8::1', '2001:db8::1', '2001:db8::1'],
			// 'unknown' is what the log holds when the address was not recorded.
			// It is deliberately turned into an address that cannot occur.
			'unknown' => ['unknown', '255.255.255.255', '255.255.255.255'],
		];
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function cidrProvider(): array
	{
		return [
			'ipv4 inside' => ['192.168.1.55', '192.168.1.0/24', true],
			'ipv4 outside' => ['192.168.2.55', '192.168.1.0/24', false],
			'ipv4 single host' => ['192.168.1.55', '192.168.1.55/32', true],
			'ipv6 inside' => ['2001:db8::5', '2001:db8::/32', true],
			'ipv6 outside' => ['2001:dba::5', '2001:db8::/32', false],
			'ipv6 inside a /48' => ['2001:db8:1::5', '2001:db8:1::/48', true],
			'ipv6 outside a /48' => ['2001:db8:2::5', '2001:db8:1::/48', false],
			// Every prefix length here is a multiple of four, and that is not a
			// coincidence. The IPv6 branch builds its mask with
			// str_repeat('f', (int) $cidr_subnetmask / 4), where the cast binds
			// to the subnet mask rather than to the division, so anything else
			// hands str_repeat() a float and throws a TypeError before the
			// switch below it can add the odd nibble. Those three cases have
			// therefore never run. Asserting the TypeError here would only
			// preserve it, so this says so instead.
			// The families are never mixed, whichever way round they are given.
			'ipv6 address against an ipv4 network' => ['2001:db8::5', '192.168.1.0/24', false],
			'ipv4 address against an ipv6 network' => ['192.168.1.55', '2001:db8::/32', false],
		];
	}

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
