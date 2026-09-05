<?php

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

	public function testItExposesTheParsedComponents(): void
	{
		$url = new Url('https://user@a.example.com:8080/a/b?c=d#f');

		$this->assertSame('a.example.com', $url->host);
		$this->assertSame('/a/b', $url->path);
		$this->assertSame('c=d', $url->query);
		$this->assertSame('f', $url->fragment);
		$this->assertSame(8080, $url->port);
	}

	public function testMissingComponentsAreNotSet(): void
	{
		$url = new Url('https://example.com');

		$this->assertFalse(isset($url->query));
		$this->assertFalse(isset($url->fragment));
	}

	public function testCastingBackToStringPreservesTheUrl(): void
	{
		$original = 'https://example.com/a/b?c=d#f';

		$this->assertSame($original, (string) new Url($original));
	}

	public function testToAsciiPunycodesAnInternationalisedHost(): void
	{
		$this->assertSame(
			'https://xn--mnchen-3ya.de/',
			(string) (new Url('https://münchen.de/'))->toAscii(),
		);
	}

	public function testToAsciiPercentEncodesANonAsciiPath(): void
	{
		$this->assertSame(
			'https://xn--mnchen-3ya.de/stra%C3%9Fe',
			(string) (new Url('https://münchen.de/straße'))->toAscii(),
		);
	}

	public function testToUtf8ReversesPunycode(): void
	{
		$this->assertSame(
			'münchen.de',
			(new Url('https://xn--mnchen-3ya.de/'))->toUtf8()->host,
		);
	}

	public function testTheSchemeIsReportedExactlyAsItWasWritten(): void
	{
		// Schemes are case insensitive, but this does not normalise them, so a
		// caller comparing against 'https' must lowercase first.
		$this->assertSame('HTTPS', (new Url('HTTPS://example.com'))->scheme);
	}

	public function testIsSchemeMatchesTheSchemeAsWritten(): void
	{
		$this->assertTrue((new Url('https://example.com'))->isScheme('https'));
		$this->assertTrue((new Url('https://example.com'))->isScheme(['http', 'https']));
		$this->assertFalse((new Url('https://example.com'))->isScheme('ftp'));
	}

	public function testIsSchemeIgnoresCaseOnBothSides(): void
	{
		// RFC 3986 section 3.1: scheme names are case insensitive. The scheme is
		// not normalised on parsing, so the comparison has to fold it.
		$this->assertTrue((new Url('HTTPS://example.com'))->isScheme('https'));
		$this->assertTrue((new Url('https://example.com'))->isScheme('HTTPS'));
		$this->assertTrue((new Url('HtTp://example.com'))->isScheme(['http', 'https']));
	}

	public function testAnUppercaseSchemeIsStillAWebsite(): void
	{
		$this->assertTrue((new Url('HTTP://example.com'))->isWebsite());
		$this->assertTrue((new Url('HTTPS://example.com'))->isWebsite());
		$this->assertFalse((new Url('ftp://example.com'))->isWebsite());
	}

	public function testAnUppercaseDataUriIsRecognised(): void
	{
		// User's avatar handling asks isScheme('data') to decide whether the
		// value is an inline image or a remote address.
		$this->assertTrue((new Url('DATA:image/png;base64,AAAA'))->isScheme('data'));
	}

	public function testAnIPv6HostIsWrittenInBrackets(): void
	{
		// The brackets are part of the authority, not part of the address, so
		// the host comes back with them still on it. Anything wanting to treat
		// the host as an address has to take them off first, which is what
		// proxied() was not doing.
		$this->assertSame('[2001:db8::1]', (new Url('http://[2001:db8::1]/pic.png'))->host);
	}

	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new Url($input))->isValid());
	}

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

	#[DataProvider('fetchSafeLiteralProvider')]
	public function testIsFetchSafeJudgesLiteralAddresses(string $url, bool $expected): void
	{
		$this->assertSame($expected, Url::create($url)->isFetchSafe(['http', 'https']));
	}

	#[DataProvider('fetchSafeReservedTldProvider')]
	public function testIsFetchSafeRejectsReservedTlds(string $url): void
	{
		// These never reach the resolver: they are refused on the name alone,
		// which is the only reason this case can live in a unit test.
		$this->assertFalse(Url::create($url)->isFetchSafe(['http', 'https']));
	}

	#[DataProvider('fetchSafeSchemeProvider')]
	public function testIsFetchSafeHonoursTheAllowedSchemes(string $url, array $schemes, bool $expected): void
	{
		$this->assertSame($expected, Url::create($url)->isFetchSafe($schemes));
	}

	public function testIsFetchSafeWithNoSchemesAllowsAnythingWeCanFetch(): void
	{
		$this->assertTrue(Url::create('http://93.184.216.34/x')->isFetchSafe());
		$this->assertTrue(Url::create('ftp://93.184.216.34/x')->isFetchSafe());
		$this->assertFalse(Url::create('javascript:alert(1)')->isFetchSafe());
	}

	public function testIsFetchSafeLeavesTheUrlAlone(): void
	{
		// The whole point of the exercise. When the host was swapped for a
		// literal address, the request went out with the wrong SNI name, the
		// wrong Host header, and a certificate that could not match.
		$url = new Url('https://93.184.216.34:8443/a/b?c=d#e');

		$url->isFetchSafe(['http', 'https']);

		$this->assertSame('https://93.184.216.34:8443/a/b?c=d#e', (string) $url);
		$this->assertSame('93.184.216.34', $url->host);
	}

	public function testIsFetchSafeLeavesAnInternationalisedHostInUtf8(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/straße');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => ['93.184.216.34']], function () use ($url): void {
			$this->assertTrue($url->isFetchSafe(['http', 'https']));
		});

		$this->assertSame('https://münchen.smf-unit-tests/straße', (string) $url);
	}

	#[DataProvider('fetchSafeResolutionProvider')]
	public function testIsFetchSafeJudgesWhatTheHostResolvesTo(array $ips, bool $expected): void
	{
		$this->withResolvedHosts(['resolved.smf-unit-tests' => $ips], function () use ($expected): void {
			$this->assertSame($expected, Url::create('http://resolved.smf-unit-tests/x')->isFetchSafe(['http', 'https']));
		});
	}

	public function testResolvesToMatchesAnyOfTheKnownAddresses(): void
	{
		$this->withResolvedHosts(['resolved.smf-unit-tests' => ['93.184.216.34', '93.184.216.35']], function (): void {
			$url = Url::create('http://resolved.smf-unit-tests/x');

			$this->assertTrue($url->resolvesTo(new IP('93.184.216.34')));
			$this->assertTrue($url->resolvesTo(new IP('93.184.216.35')));
			$this->assertFalse($url->resolvesTo(new IP('93.184.216.36')));
		});
	}

	public function testResolvesToComparesAddressesRatherThanTheirWrittenForm(): void
	{
		// SMF\IP puts v6 addresses through inet_ntop(inet_pton()), so the
		// expanded and the compressed spelling are the same address by the
		// time we compare them.
		$url = new Url('http://[2606:4700:4700::1111]/x');

		$this->assertTrue($url->resolvesTo(new IP('2606:4700:4700:0000:0000:0000:0000:1111')));
		$this->assertFalse($url->resolvesTo(new IP('2606:4700:4700::1112')));
	}

	public function testGetIPsSurvivesTheRoundTripThroughAscii(): void
	{
		// getIPs() punycodes the host, looks that up, and then puts the URL
		// back into UTF-8 before returning. Both conversions re-parse the URL,
		// so the host is spelled one way going in and another coming out, and
		// reaching for the answer by host name afterwards reaches for a key
		// that was never written.
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

	public function testGetIPsReturnsAnArrayForAnInternationalisedHostThatResolvesToNothing(): void
	{
		$url = new Url('https://münchen.smf-unit-tests/x');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => []], function () use ($url): void {
			$this->assertSame([], $url->getIPs());
		});
	}

	public function testResolvesToWorksOnAnInternationalisedHost(): void
	{
		// get_ips_for_url() and url_resolves_to() reach getIPs() and
		// resolvesTo() without converting the URL first, so this is the shape
		// the compatibility layer hands them.
		$url = new Url('https://münchen.smf-unit-tests/x');

		$this->withResolvedHosts(['xn--mnchen-3ya.smf-unit-tests' => ['93.184.216.34']], function () use ($url): void {
			$this->assertTrue($url->resolvesTo(new IP('93.184.216.34')));
			$this->assertFalse($url->resolvesTo(new IP('10.0.0.1')));
		});
	}

	public function testProxiedDoesNotConsultTheResolver(): void
	{
		// proxied() runs for every image in every post, and only decides whether
		// to route through proxy.php, which does its own checking. An empty
		// answer here would read as "unsafe" to isFetchSafe(), so if this comes
		// back proxied then proxied() never asked.
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
