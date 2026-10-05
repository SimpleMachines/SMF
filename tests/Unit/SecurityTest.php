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
use PHPUnit\Framework\TestCase;
use SMF\Security;
use SMF\User;

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

	/**
	 * A wrong password is rejected when no older hashing scheme applies.
	 *
	 * Expected: checkPassword() with a bcrypt hash of a different password,
	 *           and no fallback to try, returns false.
	 * Guards:   with no older hashing scheme left to compare against, the result
	 *           was read from a variable the comparisons had never set, which
	 *           logged an "Undefined variable $is_correct" warning on every
	 *           failed login attempt.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/b44c6ea08 Introduced by "Prevents timing attacks against Security::checkPasswordFallbacks()"
	 * @link https://github.com/SimpleMachines/SMF/pull/9719
	 */
	public function testAWrongPasswordIsRejectedWhenNoFallbackApplies(): void
	{
		$member = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
		$member->passwd = Security::hashPassword('correct horse battery staple', self::COST);

		$this->assertFalse(Security::checkPassword('Tr0ub4dor&3', $member, 0, false, false));
	}

	/**
	 * A hash verifies against the password it was made from.
	 *
	 * Expected: hashVerifyPassword($password, hashPassword($password)) returns
	 *           true.
	 */
	public function testAHashVerifiesAgainstItsOwnPassword(): void
	{
		$hash = Security::hashPassword('correct horse battery staple', self::COST);

		$this->assertTrue(Security::hashVerifyPassword('correct horse battery staple', $hash));
	}

	/**
	 * A hash does not verify against a different password.
	 *
	 * Expected: hashVerifyPassword() returns false for the same password with
	 *           different capitalization and for an empty string.
	 */
	public function testAHashDoesNotVerifyAgainstAnythingElse(): void
	{
		$hash = Security::hashPassword('correct horse battery staple', self::COST);

		$this->assertFalse(Security::hashVerifyPassword('Correct horse battery staple', $hash));
		$this->assertFalse(Security::hashVerifyPassword('', $hash));
	}

	/**
	 * Hashing the same password twice gives two different hashes.
	 *
	 * Expected: two calls to hashPassword('same') return different strings.
	 */
	public function testHashingIsSaltedSoTheSamePasswordHashesDifferently(): void
	{
		$this->assertNotSame(
			Security::hashPassword('same', self::COST),
			Security::hashPassword('same', self::COST),
		);
	}

	/**
	 * Password hashes are bcrypt hashes.
	 *
	 * Expected: hashPassword() returns a string starting with '$2y$'.
	 */
	public function testHashesAreBcrypt(): void
	{
		$this->assertStringStartsWith('$2y$', Security::hashPassword('x', self::COST));
	}

	/**
	 * The cost factor given to hashPassword() ends up in the hash.
	 *
	 * Expected: hashPassword('x', 4) starts with '$2y$04$' and
	 *           hashPassword('x', 5) starts with '$2y$05$'.
	 */
	public function testTheCostFactorIsHonoured(): void
	{
		$this->assertStringStartsWith('$2y$04$', Security::hashPassword('x', 4));
		$this->assertStringStartsWith('$2y$05$', Security::hashPassword('x', 5));
	}

	/**
	 * Generated passwords are 20 characters long and differ each time.
	 *
	 * Expected: generatePassword() returns a string of length 20, and a second
	 *           call returns a different string.
	 */
	public function testGeneratedPasswordsAreDistinctAndNonTrivial(): void
	{
		$first = Security::generatePassword();

		$this->assertSame(20, \strlen($first));
		$this->assertNotSame($first, Security::generatePassword());
	}

	/**
	 * Generated validation codes are 10 characters long and differ each time.
	 *
	 * Expected: generateValidationCode() returns a string of length 10, and a
	 *           second call returns a different string.
	 */
	public function testGeneratedValidationCodesAreDistinctAndNonTrivial(): void
	{
		$first = Security::generateValidationCode();

		$this->assertSame(10, \strlen($first));
		$this->assertNotSame($first, Security::generateValidationCode());
	}
}
