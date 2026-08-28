<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Security;

#[CoversClass(Security::class)]
class SecurityTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * bcrypt's cheapest cost. These tests care about behaviour, not about how
	 * long the hash takes.
	 */
	private const COST = 4;

	/****************
	 * Public methods
	 ****************/

	public function testAHashVerifiesAgainstItsOwnPassword(): void
	{
		$hash = Security::hashPassword('correct horse battery staple', self::COST);

		$this->assertTrue(Security::hashVerifyPassword('correct horse battery staple', $hash));
	}

	public function testAHashDoesNotVerifyAgainstAnythingElse(): void
	{
		$hash = Security::hashPassword('correct horse battery staple', self::COST);

		$this->assertFalse(Security::hashVerifyPassword('Correct horse battery staple', $hash));
		$this->assertFalse(Security::hashVerifyPassword('', $hash));
	}

	public function testHashingIsSaltedSoTheSamePasswordHashesDifferently(): void
	{
		$this->assertNotSame(
			Security::hashPassword('same', self::COST),
			Security::hashPassword('same', self::COST),
		);
	}

	public function testHashesAreBcrypt(): void
	{
		$this->assertStringStartsWith('$2y$', Security::hashPassword('x', self::COST));
	}

	public function testTheCostFactorIsHonoured(): void
	{
		$this->assertStringStartsWith('$2y$04$', Security::hashPassword('x', 4));
		$this->assertStringStartsWith('$2y$05$', Security::hashPassword('x', 5));
	}

	public function testGeneratedPasswordsAreDistinctAndNonTrivial(): void
	{
		$first = Security::generatePassword();

		$this->assertSame(20, \strlen($first));
		$this->assertNotSame($first, Security::generatePassword());
	}

	public function testGeneratedValidationCodesAreDistinctAndNonTrivial(): void
	{
		$first = Security::generateValidationCode();

		$this->assertSame(10, \strlen($first));
		$this->assertNotSame($first, Security::generateValidationCode());
	}
}
