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
use SMF\Config;
use SMF\IP;
use SMF\Url;

#[CoversClass(Url::class)]
class UrlTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A URL exposes its host, path, query, fragment and port as properties.
	 *
	 * Expected: new Url('https://user@a.example.com:8080/a/b?c=d#f') has host
	 *           'a.example.com', path '/a/b', query 'c=d', fragment 'f' and
	 *           port 8080.
	 */
	public function testItExposesTheParsedComponents(): void
	{
		$url = new Url('https://user@a.example.com:8080/a/b?c=d#f');

		$this->assertSame('a.example.com', $url->host);
		$this->assertSame('/a/b', $url->path);
		$this->assertSame('c=d', $url->query);
		$this->assertSame('f', $url->fragment);
		$this->assertSame(8080, $url->port);
	}

	/**
	 * A component that the URL does not contain is not set.
	 *
	 * Expected: new Url('https://example.com') has no query and no fragment, so
	 *           isset() is false for both.
	 */
	public function testMissingComponentsAreNotSet(): void
	{
		$url = new Url('https://example.com');

		$this->assertFalse(isset($url->query));
		$this->assertFalse(isset($url->fragment));
	}

	/**
	 * Casting a URL back to a string returns the URL it was built from.
	 *
	 * Expected: (string) new Url('https://example.com/a/b?c=d#f') returns that
	 *           same string.
	 */
	public function testCastingBackToStringPreservesTheUrl(): void
	{
		$original = 'https://example.com/a/b?c=d#f';

		$this->assertSame($original, (string) new Url($original));
	}

	/**
	 * toAscii() punycodes an internationalised host.
	 *
	 * Expected: toAscii() on 'https://münchen.de/' gives
	 *           'https://xn--mnchen-3ya.de/'.
	 */
	public function testToAsciiPunycodesAnInternationalisedHost(): void
	{
		$this->assertSame(
			'https://xn--mnchen-3ya.de/',
			(string) (new Url('https://münchen.de/'))->toAscii(),
		);
	}

	/**
	 * toAscii() percent-encodes a non-ASCII path.
	 *
	 * Expected: toAscii() on 'https://münchen.de/straße' gives
	 *           'https://xn--mnchen-3ya.de/stra%C3%9Fe'.
	 */
	public function testToAsciiPercentEncodesANonAsciiPath(): void
	{
		$this->assertSame(
			'https://xn--mnchen-3ya.de/stra%C3%9Fe',
			(string) (new Url('https://münchen.de/straße'))->toAscii(),
		);
	}

	/**
	 * toUtf8() reverses the punycoding of a host.
	 *
	 * Expected: toUtf8() on 'https://xn--mnchen-3ya.de/' gives the host
	 *           'münchen.de'.
	 */
	public function testToUtf8ReversesPunycode(): void
	{
		$this->assertSame(
			'münchen.de',
			(new Url('https://xn--mnchen-3ya.de/'))->toUtf8()->host,
		);
	}

	/**
	 * The scheme is reported exactly as it was written.
	 *
	 * Schemes are case insensitive, but the class does not normalise them, so a
	 * caller comparing the property against 'https' must lowercase it first.
	 *
	 * Expected: new Url('HTTPS://example.com') has the scheme 'HTTPS'.
	 */
	public function testTheSchemeIsReportedExactlyAsItWasWritten(): void
	{
		$this->assertSame('HTTPS', (new Url('HTTPS://example.com'))->scheme);
	}

	/**
	 * isScheme() matches the scheme against one name or a list of names.
	 *
	 * Expected: https://example.com matches 'https' and ['http', 'https'], and
	 *           does not match 'ftp'.
	 */
	public function testIsSchemeMatchesTheSchemeAsWritten(): void
	{
		$this->assertTrue((new Url('https://example.com'))->isScheme('https'));
		$this->assertTrue((new Url('https://example.com'))->isScheme(['http', 'https']));
		$this->assertFalse((new Url('https://example.com'))->isScheme('ftp'));
	}

	/**
	 * isScheme() ignores case on both sides of the comparison.
	 *
	 * RFC 3986 section 3.1 makes scheme names case insensitive. The scheme is
	 * not normalised on parsing, so the comparison has to fold it.
	 *
	 * Expected: isScheme('https') is true for HTTPS://example.com,
	 *           isScheme('HTTPS') is true for https://example.com, and
	 *           isScheme(['http', 'https']) is true for HtTp://example.com.
	 * Guards:   isScheme() compared the scheme as written with in_array(), so a
	 *           URL written with an uppercase scheme did not match its own
	 *           name.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/2029bc6c3 Introduced by "Fix avatar issues"
	 * @link https://github.com/SimpleMachines/SMF/pull/9325
	 */
	public function testIsSchemeIgnoresCaseOnBothSides(): void
	{
		$this->assertTrue((new Url('HTTPS://example.com'))->isScheme('https'));
		$this->assertTrue((new Url('https://example.com'))->isScheme('HTTPS'));
		$this->assertTrue((new Url('HtTp://example.com'))->isScheme(['http', 'https']));
	}

	/**
	 * A URL with an uppercase http or https scheme is still a website.
	 *
	 * Expected: isWebsite() is true for HTTP://example.com and
	 *           HTTPS://example.com, and false for ftp://example.com.
	 * Guards:   isWebsite() relies on isScheme(), so HTTP:// and HTTPS:// URLs
	 *           stopped being recognised as websites.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/2029bc6c3 Introduced by "Fix avatar issues"
	 * @link https://github.com/SimpleMachines/SMF/pull/9325
	 */
	public function testAnUppercaseSchemeIsStillAWebsite(): void
	{
		$this->assertTrue((new Url('HTTP://example.com'))->isWebsite());
		$this->assertTrue((new Url('HTTPS://example.com'))->isWebsite());
		$this->assertFalse((new Url('ftp://example.com'))->isWebsite());
	}

	/**
	 * An uppercase DATA: URI is recognised as a data URI.
	 *
	 * User's avatar handling asks isScheme('data') to decide whether the value
	 * is an inline image or a remote address.
	 *
	 * Expected: isScheme('data') is true for 'DATA:image/png;base64,AAAA'.
	 * Guards:   a DATA: URI was not recognised as one, so the avatar handling
	 *           in User treated it as though it were a remote address.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/2029bc6c3 Introduced by "Fix avatar issues"
	 * @link https://github.com/SimpleMachines/SMF/pull/9325
	 */
	public function testAnUppercaseDataUriIsRecognised(): void
	{
		$this->assertTrue((new Url('DATA:image/png;base64,AAAA'))->isScheme('data'));
	}

	/**
	 * An IPv6 host is reported with its brackets.
	 *
	 * The brackets are part of the authority, not part of the address, so the
	 * host comes back with them still on it. Anything wanting to treat the host
	 * as an address has to take them off first, as proxied() does.
	 *
	 * Expected: new Url('http://[2001:db8::1]/pic.png') has the host
	 *           '[2001:db8::1]'.
	 */
	public function testAnIPv6HostIsWrittenInBrackets(): void
	{
		$this->assertSame('[2001:db8::1]', (new Url('http://[2001:db8::1]/pic.png'))->host);
	}

	/**
	 * isValid() accepts absolute URLs and rejects bare words and the empty
	 * string.
	 *
	 * Expected: each case in validityProvider() gives the expected result.
	 */
	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new Url($input))->isValid());
	}

	/**
	 * proxied() leaves hosts that are private or reserved addresses alone.
	 *
	 * Expected: with the image proxy switched on, each case in
	 *           proxiedProvider() is returned unchanged when its host is a
	 *           private, loopback or documentation address, and is routed
	 *           through https://forum.test-site.com/forum/proxy.php when its
	 *           host is a global address.
	 * Guards:   filter_var() does not accept an address in brackets, so every
	 *           IPv6 literal read as a name rather than an address and went to
	 *           the proxy, private and reserved ranges included.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/1c9e53bf7 Introduced by "Avoids some unnecessary image proxy usage"
	 * @link https://github.com/SimpleMachines/SMF/pull/9507
	 */
	#[DataProvider('proxiedProvider')]
	public function testProxiedLeavesUnroutableHostsAlone(string $url, bool $expected): void
	{
		$this->withProxySettings(function () use ($url, $expected): void {
			$proxied = (string) Url::create($url)->proxied();

			if ($expected) {
				$this->assertStringStartsWith(
					'https://forum.test-site.com/forum/proxy.php?request=',
					$proxied,
				);
			} else {
				$this->assertSame($url, $proxied);
			}
		});
	}

	/**
	 * isFetchSafe() judges an address written out in the URL without a lookup.
	 *
	 * Expected: each case in fetchSafeLiteralProvider() is fetch safe only when
	 *           its address is globally routable, in both IPv4 and IPv6.
	 */
	#[DataProvider('fetchSafeLiteralProvider')]
	public function testIsFetchSafeJudgesLiteralAddresses(string $url, bool $expected): void
	{
		$this->assertSame($expected, Url::create($url)->isFetchSafe(['http', 'https']));
	}

	/**
	 * isFetchSafe() refuses hosts under reserved top-level domains.
	 *
	 * These never reach the resolver: they are refused on the name alone, which
	 * is the only reason this case can live in a unit test.
	 *
	 * Expected: each URL in fetchSafeReservedTldProvider() is not fetch safe.
	 */
	#[DataProvider('fetchSafeReservedTldProvider')]
	public function testIsFetchSafeRejectsReservedTlds(string $url): void
	{
		$this->assertFalse(Url::create($url)->isFetchSafe(['http', 'https']));
	}

	/**
	 * isFetchSafe() only accepts the schemes it is given.
	 *
	 * Expected: each case in fetchSafeSchemeProvider() is fetch safe only when
	 *           the URL has a scheme in the allowed list and a host.
	 */
	#[DataProvider('fetchSafeSchemeProvider')]
	public function testIsFetchSafeHonoursTheAllowedSchemes(string $url, array $schemes, bool $expected): void
	{
		$this->assertSame($expected, Url::create($url)->isFetchSafe($schemes));
	}

	/**
	 * isFetchSafe() with no list of schemes allows any scheme that can be
	 * fetched.
	 *
	 * Expected: isFetchSafe() is true for 'http://93.184.216.34/x' and
	 *           'ftp://93.184.216.34/x', and false for 'javascript:alert(1)'.
	 */
	public function testIsFetchSafeWithNoSchemesAllowsAnythingWeCanFetch(): void
	{
		$this->assertTrue(Url::create('http://93.184.216.34/x')->isFetchSafe());
		$this->assertTrue(Url::create('ftp://93.184.216.34/x')->isFetchSafe());
		$this->assertFalse(Url::create('javascript:alert(1)')->isFetchSafe());
	}

	/**
	 * isFetchSafe() leaves the URL it was asked about untouched.
	 *
	 * Expected: after isFetchSafe(['http', 'https']) on
	 *           'https://93.184.216.34:8443/a/b?c=d#e', the URL still casts to
	 *           that same string and still has the host '93.184.216.34'.
	 * Guards:   the host was swapped for a literal address, so the request went
	 *           out with the wrong SNI name, the wrong Host header, and a
	 *           certificate that could not match.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/94b25ba27 Introduced by "WebFetchApi::isFetchSafe() → WebFetchApi::makeSafe()"
	 * @link https://github.com/SimpleMachines/SMF/issues/9533
	 */
	public function testIsFetchSafeLeavesTheUrlAlone(): void
	{
		$url = new Url('https://93.184.216.34:8443/a/b?c=d#e');

		$url->isFetchSafe(['http', 'https']);

		$this->assertSame('https://93.184.216.34:8443/a/b?c=d#e', (string) $url);
		$this->assertSame('93.184.216.34', $url->host);
	}

	/**
	 * isFetchSafe() leaves an internationalised host in UTF-8.
	 *
	 * Expected: with xn--mnchen-3ya.smf-unit-tests resolving to 93.184.216.34,
	 *           isFetchSafe() is true for
	 *           'https://münchen.smf-unit-tests/straße', and the URL still
	 *           reads that way afterwards.
	 */
	public function testIsFetchSafeLeavesAnInternationalisedHostInUtf8(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/straße');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => ['93.184.216.34']], function () use ($url): void {
			$this->assertTrue($url->isFetchSafe(['http', 'https']));
		});

		$this->assertSame('https://münchen.smf-unit-tests/straße', (string) $url);
	}

	/**
	 * isFetchSafe() judges a name by every address it resolves to.
	 *
	 * Expected: each case in fetchSafeResolutionProvider() is fetch safe only
	 *           when the host resolved to at least one address and every
	 *           address is globally routable.
	 */
	#[DataProvider('fetchSafeResolutionProvider')]
	public function testIsFetchSafeJudgesWhatTheHostResolvesTo(array $ips, bool $expected): void
	{
		$this->withResolvedHosts(['resolved.smf-unit-tests' => $ips], function () use ($expected): void {
			$this->assertSame($expected, Url::create('http://resolved.smf-unit-tests/x')->isFetchSafe(['http', 'https']));
		});
	}

	/**
	 * resolvesTo() matches any of the addresses the host resolves to.
	 *
	 * Expected: with a host resolving to 93.184.216.34 and 93.184.216.35,
	 *           resolvesTo() is true for each of them and false for
	 *           93.184.216.36.
	 */
	public function testResolvesToMatchesAnyOfTheKnownAddresses(): void
	{
		$this->withResolvedHosts(['resolved.smf-unit-tests' => ['93.184.216.34', '93.184.216.35']], function (): void {
			$url = Url::create('http://resolved.smf-unit-tests/x');

			$this->assertTrue($url->resolvesTo(new IP('93.184.216.34')));
			$this->assertTrue($url->resolvesTo(new IP('93.184.216.35')));
			$this->assertFalse($url->resolvesTo(new IP('93.184.216.36')));
		});
	}

	/**
	 * resolvesTo() compares addresses rather than their written form.
	 *
	 * SMF\IP puts v6 addresses through inet_ntop(inet_pton()), so the expanded
	 * and the compressed spelling are the same address by the time they are
	 * compared.
	 *
	 * Expected: for 'http://[2606:4700:4700::1111]/x', resolvesTo() is true for
	 *           the expanded 2606:4700:4700:0000:0000:0000:0000:1111 and false
	 *           for 2606:4700:4700::1112.
	 */
	public function testResolvesToComparesAddressesRatherThanTheirWrittenForm(): void
	{
		$url = new Url('http://[2606:4700:4700::1111]/x');

		$this->assertTrue($url->resolvesTo(new IP('2606:4700:4700:0000:0000:0000:0000:1111')));
		$this->assertFalse($url->resolvesTo(new IP('2606:4700:4700::1112')));
	}

	/**
	 * getIPs() finds the addresses of an internationalised host and leaves the
	 * URL as it was written.
	 *
	 * getIPs() punycodes the host, looks that up, and then puts the URL back
	 * into UTF-8 before returning. Both conversions re-parse the URL, so the
	 * host is spelled one way going in and another coming out, and the answer
	 * has to be found under the spelling it was stored with.
	 *
	 * Expected: with xn--mnchen-3ya.smf-unit-tests resolving to 93.184.216.34,
	 *           getIPs() on 'https://münchen.smf-unit-tests/x' returns that one
	 *           address, and the URL and its host keep their UTF-8 spelling.
	 */
	public function testGetIPsSurvivesTheRoundTripThroughAscii(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/x');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => ['93.184.216.34']], function () use ($url): void {
			$ips = $url->getIPs();

			$this->assertCount(1, $ips);
			$this->assertSame('93.184.216.34', (string) $ips[0]);
		});

		// And the URL is still spelled the way we wrote it.
		$this->assertSame('https://münchen.smf-unit-tests/x', (string) $url);
		$this->assertSame('münchen.smf-unit-tests', $url->host);
	}

	/**
	 * getIPs() returns an empty array for an internationalised host that
	 * resolves to nothing.
	 *
	 * Expected: with no addresses for xn--mnchen-3ya.smf-unit-tests, getIPs()
	 *           on 'https://münchen.smf-unit-tests/x' returns [].
	 */
	public function testGetIPsReturnsAnArrayForAnInternationalisedHostThatResolvesToNothing(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/x');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => []], function () use ($url): void {
			$this->assertSame([], $url->getIPs());
		});
	}

	/**
	 * resolvesTo() works on a URL whose host is internationalised.
	 *
	 * get_ips_for_url() and url_resolves_to() reach getIPs() and resolvesTo()
	 * without converting the URL first, so this is the shape the compatibility
	 * layer hands them.
	 *
	 * Expected: with xn--mnchen-3ya.smf-unit-tests resolving to 93.184.216.34,
	 *           resolvesTo() on 'https://münchen.smf-unit-tests/x' is true for
	 *           93.184.216.34 and false for 10.0.0.1.
	 */
	public function testResolvesToWorksOnAnInternationalisedHost(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/x');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => ['93.184.216.34']], function () use ($url): void {
			$this->assertTrue($url->resolvesTo(new IP('93.184.216.34')));
			$this->assertFalse($url->resolvesTo(new IP('10.0.0.1')));
		});
	}

	/**
	 * proxied() decides without asking the resolver.
	 *
	 * proxied() runs for every image in every post, and only decides whether to
	 * route through proxy.php, which does its own checking. An empty answer
	 * from the resolver would read as unsafe to isFetchSafe(), so a URL that
	 * comes back proxied shows that proxied() never asked.
	 *
	 * Expected: with the image proxy on and images.smf-unit-tests seeded as
	 *           resolving to nothing, proxied() on
	 *           'http://images.smf-unit-tests/pic.png' returns a URL under
	 *           https://forum.test-site.com/forum/proxy.php.
	 */
	public function testProxiedDoesNotConsultTheResolver(): void
	{
		$this->withResolvedHosts(['images.smf-unit-tests' => []], function (): void {
			$this->withProxySettings(function (): void {
				$this->assertStringStartsWith(
					'https://forum.test-site.com/forum/proxy.php?request=',
					(string) Url::create('http://images.smf-unit-tests/pic.png')->proxied(),
				);
			});
		});
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
			'https' => ['https://example.com', true],
			'http with path' => ['http://example.com/a/b', true],
			'bare word' => ['notaurl', false],
			'empty' => ['', false],
		];
	}

	/**
	 * The point of the proxy is to serve an http image over https without
	 * telling the person's browser to go and fetch it. Sending it at a host
	 * only the server can reach turns it into a request the server makes on
	 * behalf of whoever pasted the address, which is the shape of an SSRF, so
	 * the private and reserved ranges are excluded.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function proxiedProvider(): array
	{
		return [
			// The IPv6 cases are the ones that regressed: filter_var() does not
			// accept an address in brackets, so every one of these read as a
			// name rather than an address and went to the proxy.
			'ipv6 private' => ['http://[fd00::1]/pic.png', false],
			'ipv6 loopback' => ['http://[::1]/pic.png', false],
			'ipv6 documentation' => ['http://[2001:db8::1]/pic.png', false],
			'ipv6 global' => ['http://[2606:4700:4700::1111]/pic.png', true],

			// The IPv4 side is the control: it was already right and stays right.
			'ipv4 private' => ['http://10.0.0.1/pic.png', false],
			'ipv4 loopback' => ['http://127.0.0.1/pic.png', false],
			'ipv4 global' => ['http://93.184.216.34/pic.png', true],
		];
	}

	/**
	 * Addresses written out in the URL, so no lookup happens at all.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function fetchSafeLiteralProvider(): array
	{
		return [
			'ipv4 loopback' => ['http://127.0.0.1/x', false],
			'ipv4 private' => ['http://10.0.0.1/x', false],
			'ipv4 private 192' => ['http://192.168.1.1/x', false],
			'ipv4 link local' => ['http://169.254.169.254/x', false],
			'ipv4 global' => ['http://93.184.216.34/x', true],
			'ipv6 loopback' => ['http://[::1]/x', false],
			'ipv6 unique local' => ['http://[fd00::1]/x', false],
			'ipv6 documentation' => ['http://[2001:db8::1]/x', false],
			'ipv6 global' => ['http://[2606:4700:4700::1111]/x', true],
		];
	}

	/**
	 * Names that are never in public DNS, per RFC 2606 and RFC 6761.
	 *
	 * @return array<string, array{string}>
	 */
	public static function fetchSafeReservedTldProvider(): array
	{
		return [
			'localhost' => ['http://localhost/x'],
			'local' => ['http://box.local/x'],
			'internal' => ['http://box.internal/x'],
			'test' => ['http://box.test/x'],
			'invalid' => ['http://box.invalid/x'],
			'example' => ['http://box.example/x'],
			'onion' => ['http://box.onion/x'],
		];
	}

	/**
	 * @return array<string, array{string, array<string>, bool}>
	 */
	public static function fetchSafeSchemeProvider(): array
	{
		return [
			'http when http is allowed' => ['http://93.184.216.34/x', ['http', 'https'], true],
			'ftp when only http is allowed' => ['ftp://93.184.216.34/x', ['http', 'https'], false],
			'ftp when ftp is allowed' => ['ftp://93.184.216.34/x', ['ftp', 'ftps'], true],
			'no scheme' => ['//93.184.216.34/x', ['http', 'https'], false],
			'no host' => ['mailto:someone@example.com', ['http', 'https'], false],
		];
	}

	/**
	 * What a name resolves to decides the answer. Every address has to be
	 * globally routable, because we will not know which one gets used.
	 *
	 * @return array<string, array{array<string>, bool}>
	 */
	public static function fetchSafeResolutionProvider(): array
	{
		return [
			'all global' => [['93.184.216.34', '93.184.216.35'], true],
			'all private' => [['10.0.0.1'], false],
			'one private among global' => [['93.184.216.34', '127.0.0.1'], false],
			'global v6' => [['2606:4700:4700::1111'], true],
			'link local v6' => [['fe80::1'], false],
			// Nothing came back. dns_get_record() never sees names that only
			// exist in the system's hosts file, so this is not proof that the
			// name is harmless.
			'nothing at all' => [[], false],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Runs $test with the image proxy switched on, then puts the settings back.
	 *
	 * These are typed statics with no default, so they start out uninitialised
	 * and there is no way to put them back into that state. Restoring the
	 * disabled state is the nearest thing: it is what Config::load() writes
	 * when the proxy is off, and every check on the way in asks empty(), which
	 * reads an uninitialised property and a false one alike.
	 *
	 * @param callable $test The assertions to run.
	 */
	/**
	 * Runs $test with the given hosts already resolved, then puts the cache
	 * back as it was.
	 *
	 * Url::getIPs() remembers what a host resolved to for the rest of the
	 * request. Seeding that cache is what lets a unit test say what a name
	 * resolves to without a resolver, without a network, and without a result
	 * that changes depending on where the suite is run.
	 *
	 * @param array<string, array<string>> $hosts Host name to addresses.
	 * @param callable $test The assertions to run.
	 */
	protected function withResolvedHosts(array $hosts, callable $test): void
	{
		$property = new \ReflectionProperty(Url::class, 'ips');

		// Typed, and declared with no default, so it starts out uninitialised
		// and reading it before anything has resolved would throw.
		$previous = $property->isInitialized() ? $property->getValue() : [];

		$property->setValue(null, array_map(
			fn(array $ips): array => array_map(fn(string $ip): IP => new IP($ip), $ips),
			$hosts,
		));

		try {
			$test();
		} finally {
			$property->setValue(null, $previous);
		}
	}

	protected function withProxySettings(callable $test): void
	{
		$enabled = Config::$image_proxy_enabled ?? false;
		$secret = Config::$image_proxy_secret ?? '';
		$boardurl = Config::$boardurl ?? '';

		Config::$image_proxy_enabled = true;
		Config::$image_proxy_secret = 'smfisawesome';
		Config::$boardurl = 'https://forum.test-site.com/forum';

		try {
			$test();
		} finally {
			Config::$image_proxy_enabled = $enabled;
			Config::$image_proxy_secret = $secret;
			Config::$boardurl = $boardurl;
		}
	}
}
