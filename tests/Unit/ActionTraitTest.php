<?php

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

	public function testLoadReturnsAnInstanceOfTheClassItWasCalledOn(): void
	{
		$this->assertInstanceOf(Login2::class, Login2::load());
		$this->assertInstanceOf(Logout::class, Logout::load());
	}

	public function testLoadIsStillCorrectWhenTheParentWasLoadedFirst(): void
	{
		// $obj is a static property declared in the trait, so it is shared with
		// every descendant that does not redeclare it. Loading the parent first
		// used to leave the parent's instance in the slot the child reads.
		Login2::load();

		$this->assertInstanceOf(Logout::class, Logout::load());
		$this->assertInstanceOf(Login::class, Login::load());
	}

	public function testLoadIsStillCorrectWhenTheChildWasLoadedFirst(): void
	{
		Logout::load();

		$this->assertInstanceOf(Login2::class, Login2::load());
	}

	public function testTheSameProblemInAnUnrelatedHierarchy(): void
	{
		// Eleven action classes extend another action and none redeclare $obj,
		// so this is not specific to the login hierarchy. Notify is abstract and
		// so cannot be loaded at all; Agreement and Unread are the other pairs
		// with a concrete parent.
		Agreement::load();
		Unread::load();

		$this->assertInstanceOf(AgreementAccept::class, AgreementAccept::load());
		$this->assertInstanceOf(UnreadReplies::class, UnreadReplies::load());
	}

	public function testLoadCachesTheInstanceItReturns(): void
	{
		$this->assertSame(Login2::load(), Login2::load());
	}
}
