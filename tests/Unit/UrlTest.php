<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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

	#[DataProvider('validityProvider')]
	public function testValidity(string $input, bool $expected): void
	{
		$this->assertSame($expected, (new Url($input))->isValid());
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
}
