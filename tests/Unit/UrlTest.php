<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Config;
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
