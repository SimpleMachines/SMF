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
use SMF\IP;

#[CoversClass(IP::class)]
class IPTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * An IPv6 address is cast to its shortest, lowercase form.
	 *
	 * Expected: (string) new IP('2001:DB8::0001') returns '2001:db8::1'.
	 */
	public function testItNormalisesIPv6ToItsShortestForm(): void
	{
		$this->assertSame('2001:db8::1', (string) new IP('2001:DB8::0001'));
	}

	/**
	 * An IPv4-mapped IPv6 address keeps its dotted tail.
	 *
	 * Expected: (string) new IP('::ffff:1.2.3.4') returns '::ffff:1.2.3.4'.
	 */
	public function testItKeepsIPv4MappedAddressesIntact(): void
	{
		$this->assertSame('::ffff:1.2.3.4', (string) new IP('::ffff:1.2.3.4'));
	}

	/**
	 * The flags given to isValid() restrict validation to one address family.
	 *
	 * Expected: 1.2.3.4 is valid with FILTER_FLAG_IPV4 but not with
	 *           FILTER_FLAG_IPV6, and 2001:db8::1 is valid with
	 *           FILTER_FLAG_IPV6.
	 */
	public function testFlagsNarrowValidationToOneFamily(): void
	{
		$this->assertTrue((new IP('1.2.3.4'))->isValid(FILTER_FLAG_IPV4));
		$this->assertFalse((new IP('1.2.3.4'))->isValid(FILTER_FLAG_IPV6));
		$this->assertTrue((new IP('2001:db8::1'))->isValid(FILTER_FLAG_IPV6));
	}

	/**
	 * An address converts to hex and packed binary and back again.
	 *
	 * Expected: for 1.2.3.4, toHex() returns '01020304', toBinary() is four
	 *           bytes, and an IP built from that binary casts to '1.2.3.4'.
	 */
	public function testBinaryAndHexRoundTrip(): void
	{
		$ip = new IP('1.2.3.4');

		$this->assertSame('01020304', $ip->toHex());
		$this->assertSame(4, \strlen((string) $ip->toBinary()));
		$this->assertSame('1.2.3.4', (string) new IP((string) $ip->toBinary()));
	}

	/**
	 * An empty or unparseable value is not a valid address.
	 *
	 * Expected: isValid() is false for '', 'abcde' and '999.999.999.999'.
	 */
	public function testAnEmptyOrUnparseableValueIsNotValid(): void
	{
		$this->assertFalse((new IP(''))->isValid());
		$this->assertFalse((new IP('abcde'))->isValid());
		$this->assertFalse((new IP('999.999.999.999'))->isValid());
	}

	/**
	 * Any four or sixteen byte string is read as a packed address.
	 *
	 * The constructor accepts the packed binary form, and it cannot tell that
	 * apart from a four character string. This is a sharp edge worth pinning
	 * down: 'nope' is not rejected, it becomes an address.
	 *
	 * Expected: (string) new IP('nope') returns '110.111.112.101' and is
	 *           valid, and a sixteen character string is valid as an IPv6
	 *           address.
	 */
	public function testAnyFourByteStringIsReadAsAPackedAddress(): void
	{
		$this->assertSame('110.111.112.101', (string) new IP('nope'));
		$this->assertTrue((new IP('nope'))->isValid());

		// The same applies at 16 bytes, where it becomes an IPv6 address.
		$this->assertTrue((new IP('not an ip at all'))->isValid());
	}

	/**
	 * ip2range() reads a range whose ends are IPv6 addresses.
	 *
	 * The two ends of a range are only recognised as ends if they validate as
	 * addresses of any family.
	 *
	 * Expected: IP::ip2range('2001:db8::1-2001:db8::ff') has the low end
	 *           '2001:db8::1' and the high end '2001:db8::ff'.
	 * Guards:   the ends were validated as IPv4 only, so neither end of an
	 *           IPv6 range was one. The range fell through to the "one side is
	 *           a fragment" path, which read the address itself as a list of
	 *           octets to walk, and came back as 255.255.255.255.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/d530ce5f5 Introduced by "Implements SMF\IP"
	 * @link https://github.com/SimpleMachines/SMF/pull/9506
	 */
	public function testIp2RangeReadsARangeOfIPv6Addresses(): void
	{
		$range = IP::ip2range('2001:db8::1-2001:db8::ff');

		$this->assertSame('2001:db8::1', (string) $range['low']);
		$this->assertSame('2001:db8::ff', (string) $range['high']);
	}

	/**
	 * ip2range() reads an IPv6 range written with nothing elided.
	 *
	 * It is the same range as in testIp2RangeReadsARangeOfIPv6Addresses(), so
	 * the shortening on the way back out is the only difference between the
	 * two.
	 *
	 * Expected: IP::ip2range('2001:db8:0:0:0:0:0:1-2001:db8:0:0:0:0:0:ff') has
	 *           the low end '2001:db8::1' and the high end '2001:db8::ff'.
	 * Guards:   the ends were validated as IPv4 only, so neither end of an
	 *           IPv6 range was recognised as one.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/d530ce5f5 Introduced by "Implements SMF\IP"
	 * @link https://github.com/SimpleMachines/SMF/pull/9506
	 */
	public function testIp2RangeReadsAFullyWrittenIPv6Range(): void
	{
		$range = IP::ip2range('2001:db8:0:0:0:0:0:1-2001:db8:0:0:0:0:0:ff');

		$this->assertSame('2001:db8::1', (string) $range['low']);
		$this->assertSame('2001:db8::ff', (string) $range['high']);
	}

	/**
	 * ip2range() still reads a range whose ends are IPv4 addresses.
	 *
	 * Expected: IP::ip2range('1.2.3.4-1.2.3.9') has the low end '1.2.3.4' and
	 *           the high end '1.2.3.9'.
	 */
	public function testIp2RangeStillReadsARangeOfIPv4Addresses(): void
	{
		$range = IP::ip2range('1.2.3.4-1.2.3.9');

		$this->assertSame('1.2.3.4', (string) $range['low']);
		$this->assertSame('1.2.3.9', (string) $range['high']);
	}

	/**
	 * isValid() accepts well-formed addresses and rejects everything else.
	 *
	 * Expected: each case in validityProvider() has isValid() return the
	 *           stated boolean.
	 */
	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new IP($input))->isValid());
	}

	/**
	 * ip2range() fills a wildcard with the lowest and the highest value.
	 *
	 * Expected: each case in ip2RangeWildcardProvider() gives the stated low
	 *           and high ends.
	 */
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

	/**
	 * matchToCIDR() says whether an address lies inside a network.
	 *
	 * Expected: each case in cidrProvider() has matchToCIDR() return the
	 *           stated boolean.
	 */
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
