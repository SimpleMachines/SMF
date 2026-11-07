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

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use SMF\Actions\Agreement;
use SMF\Actions\AgreementAccept;
use SMF\Actions\Login;
use SMF\Actions\Login2;
use SMF\Actions\Logout;
use SMF\Actions\Unread;
use SMF\Actions\UnreadReplies;
use SMF\ActionTrait;

#[CoversTrait(ActionTrait::class)]
class ActionTraitTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * load() returns an instance of the class it was called on.
	 *
	 * Expected: Login2::load() returns a Login2 and Logout::load() returns a Logout.
	 */
	public function testLoadReturnsAnInstanceOfTheClassItWasCalledOn(): void
	{
		$this->assertInstanceOf(Login2::class, Login2::load());
		$this->assertInstanceOf(Logout::class, Logout::load());
	}

	/**
	 * load() returns the right class when the parent was loaded first.
	 *
	 * Expected: after Login2::load(), Logout::load() returns a Logout and
	 *           Login::load() returns a Login.
	 * Guards:   $obj is a static property declared in the trait, so it was shared
	 *           with every descendant that does not redeclare it. Loading the
	 *           parent first left the parent's instance in the slot the child
	 *           reads, so Logout::load() returned a Login2 and failed its
	 *           static return type. A banned member got a fatal error instead
	 *           of being logged out.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/5642a041f Introduced by "Move a bunch of repeat functions to a trait"
	 * @link https://github.com/SimpleMachines/SMF/pull/9321
	 */
	public function testLoadIsStillCorrectWhenTheParentWasLoadedFirst(): void
	{
		Login2::load();

		$this->assertInstanceOf(Logout::class, Logout::load());
		$this->assertInstanceOf(Login::class, Login::load());
	}

	/**
	 * load() returns the right class when the child was loaded first.
	 *
	 * Expected: after Logout::load(), Login2::load() returns a Login2.
	 */
	public function testLoadIsStillCorrectWhenTheChildWasLoadedFirst(): void
	{
		Logout::load();

		$this->assertInstanceOf(Login2::class, Login2::load());
	}

	/**
	 * load() returns the right class in other action hierarchies too.
	 *
	 * Expected: after Agreement::load() and Unread::load(), AgreementAccept::load()
	 *           returns an AgreementAccept and UnreadReplies::load() returns an
	 *           UnreadReplies.
	 * Guards:   Eleven action classes extend another action and none
	 *           redeclare $obj, so the shared slot was not specific to the
	 *           login hierarchy.
	 *           Notify is abstract and so cannot be loaded at all; Agreement
	 *           and Unread are the other pairs with a concrete parent.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/5642a041f Introduced by "Move a bunch of repeat functions to a trait"
	 * @link https://github.com/SimpleMachines/SMF/pull/9321
	 */
	public function testTheSameProblemInAnUnrelatedHierarchy(): void
	{
		Agreement::load();
		Unread::load();

		$this->assertInstanceOf(AgreementAccept::class, AgreementAccept::load());
		$this->assertInstanceOf(UnreadReplies::class, UnreadReplies::load());
	}

	/**
	 * load() returns the same instance every time.
	 *
	 * Expected: Login2::load() called twice returns the identical object.
	 */
	public function testLoadCachesTheInstanceItReturns(): void
	{
		$this->assertSame(Login2::load(), Login2::load());
	}
}
