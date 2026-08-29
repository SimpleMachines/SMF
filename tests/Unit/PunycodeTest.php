<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Punycode;

#[CoversClass(Punycode::class)]
class PunycodeTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testAsciiDomainsPassThroughUnchanged(): void
	{
		$this->assertSame('example.com', (new Punycode())->encode('example.com'));
	}

	#[DataProvider('domainProvider')]
	public function testEncodeAndDecodeAreInverses(string $unicode, string $ascii): void
	{
		$punycode = new Punycode();

		$this->assertSame($ascii, $punycode->encode($unicode));
		$this->assertSame($unicode, $punycode->decode($ascii));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function domainProvider(): array
	{
		return [
			'german umlaut' => ['münchen.de', 'xn--mnchen-3ya.de'],
			'multiple labels' => ['münchen.beispiel.de', 'xn--mnchen-3ya.beispiel.de'],
		];
	}
}
