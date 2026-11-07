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
use SMF\Punycode;

#[CoversClass(Punycode::class)]
class PunycodeTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * An all-ASCII domain passes through encode() unchanged.
	 *
	 * Expected: encode('example.com') returns 'example.com'.
	 */
	public function testAsciiDomainsPassThroughUnchanged(): void
	{
		$this->assertSame('example.com', (new Punycode())->encode('example.com'));
	}

	/**
	 * encode() and decode() are inverses for internationalised domains.
	 *
	 * Expected: each case in domainProvider() encodes to the expected ASCII
	 *           form and decodes back to the original.
	 */
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
