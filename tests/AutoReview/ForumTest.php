<?php

declare(strict_types=1);

namespace SMF\Tests\AutoReview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\ActionInterface;
use SMF\Forum;

#[CoversClass(Forum::class)]
class ForumTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Tests that every action definition has the expected structure.
	 *
	 * Each action must contain a source file and a handler. The handler must
	 * either be an ActionInterface implementation or a callable.
	 */
	public function testActionDefinitions(): void
	{
		foreach (Forum::$actions as $action => $definition) {
			$this->assertIsArray($definition, "Action '{$action}' must be an array.");
			$this->assertIsList($definition, "Action '{$action}' must be a list.");
			$this->assertCount(2, $definition, "Action '{$action}' must contain exactly two elements.");
			$this->assertIsString($definition[0], "Action '{$action}' file must be a string.");

			$handler = $definition[1];

			$this->assertTrue(
				(\is_string($handler) && is_a($handler, ActionInterface::class, true)) || \is_callable($handler),
				"Action '{$action}' handler must implement ActionInterface or be callable.",
			);
		}
	}

	/**
	 * Tests that all renamed actions point to actions that exist.
	 */
	public function testRenamedActionsExist(): void
	{
		$this->assertSame(
			[],
			array_diff(Forum::$renamed_actions, array_keys(Forum::$actions)),
			'Every renamed action must reference an existing action.',
		);
	}

	/**
	 * Tests that all actions excluded from logging exist.
	 */
	public function testUnloggedActionsExist(): void
	{
		$this->assertSame(
			[],
			array_diff_key(Forum::$unlogged_actions, Forum::$actions),
			'Every unlogged action must reference an existing action.',
		);
	}

	/**
	 * Tests that all actions accessible to guests exist.
	 */
	public function testGuestAccessActionsExist(): void
	{
		$this->assertSame(
			[],
			array_diff(
				Forum::$guest_access_actions,
				array_keys(Forum::$actions),
			),
			'Every guest-access action must reference an existing action.',
		);
	}
}
