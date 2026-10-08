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

use DatabaseConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleAction;
use SimpleActionExecuted;
use SimpleDependencyAwareAction;
use SimpleForum;
use SMF\ActionInterface;
use SMF\Actions\BoardIndex;
use SMF\Actions\Display;
use SMF\Actions\MessageIndex;
use SMF\Config;
use SMF\Forum;
use SMF\IntegrationHook;
use UserRepository;

class ForumTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Executing the forum registers services before the action is preflighted.
	 *
	 * Expected: execute() on an action that depends on services, with an
	 *           integrate_services hook registered, runs the action with its
	 *           UserRepository and DatabaseConnection injected.
	 */
	public function testExecuteRegistersServicesBeforePreflight(): void
	{
		$action_called = false;

		Forum::$actions['test_services'] = ['', SimpleDependencyAwareAction::class];
		$_REQUEST['action'] = 'test_services';

		IntegrationHook::add('integrate_services', 'my_integrated_service', false, '$boarddir/tests/fixtures/integrationhooks.php');
		$forum = new SimpleForum();

		try {
			$forum->execute();
		} catch (SimpleActionExecuted) {
			$action_called = true;
		}

		unset(Forum::$actions['test_services']);
		IntegrationHook::remove('integrate_services', 'my_integrated_service', false, '$boarddir/tests/fixtures/integrationhooks.php');

		$this->assertTrue($action_called);
		$action = Forum::getCurrentAction();
		$action::clearStatcics();
		SimpleForum::clearStatcics();

		$this->assertNull(Forum::getCurrentAction());
		unset($_REQUEST['action']);
		$this->assertInstanceOf(UserRepository::class, $action->user_repository);
		$this->assertInstanceOf(DatabaseConnection::class, $action->database_connection);
		$this->assertInstanceOf(DatabaseConnection::class, $action->user_repository->db);
	}

	/**
	 * An unknown action resolves to no current action.
	 *
	 * Expected: getCurrentAction() returns null for an unregistered action.
	 */
	public function testUnknownAction(): void
	{
		$_REQUEST['action'] = 'unit_test_action';
		$action = Forum::getCurrentAction();

		$this->setCurrentAction(null);
		unset($_REQUEST['action']);

		$this->assertNull($action);
	}

	/**
	 * An unknown action is handled by the configured fallback action.
	 *
	 * Expected: with integrate_fallback_action set, getCurrentAction() returns an
	 *           instance of that action for an unregistered action.
	 */
	public function testFallbackAction(): void
	{
		Config::$modSettings['integrate_fallback_action'] = SimpleAction::class;

		$_REQUEST['action'] = 'unit_test_action';
		$action = Forum::getCurrentAction();

		unset($_REQUEST['action'], Config::$modSettings['integrate_fallback_action']);
		$this->setCurrentAction(null);

		$this->assertInstanceOf(ActionInterface::class, $action);
		$this->assertInstanceOf(SimpleAction::class, $action);
	}

	/**
	 * The request parameters select the implied action.
	 *
	 * Expected: each case in provideImpliedActions() makes getCurrentAction() return
	 *           an instance of the expected action.
	 */
	#[DataProvider('provideImpliedActions')]
	public function testImpliedAction(array $get, string $expected): void
	{
		$_GET = $get;

		$action = Forum::getCurrentAction();

		$_GET = [];
		$this->setCurrentAction(null);

		$this->assertInstanceOf(ActionInterface::class, $action);
		$this->assertInstanceOf($expected, $action);
	}

	/**
	 * The configured default action wins when nothing else is requested.
	 *
	 * Expected: with integrate_default_action set and no action, topic or board,
	 *           getCurrentAction() returns an instance of the configured action.
	 */
	public function testDefaultAction(): void
	{
		Config::$modSettings['integrate_default_action'] = SimpleAction::class . ',something_else';

		$action = Forum::getCurrentAction();

		unset(Config::$modSettings['integrate_default_action']);
		$this->setCurrentAction(null);

		$this->assertInstanceOf(ActionInterface::class, $action);
		$this->assertInstanceOf(SimpleAction::class, $action);
	}

	/**
	 * Tests adding and removing an action.
	 */
	public function SimpleAction(): void
	{
		$action = 'unit_test_action';
		$original_actions = Forum::$actions;

		Forum::addAction($action, '', SimpleAction::class);

		$this->assertArrayHasKey($action, Forum::$actions);
		$this->assertSame(
			['', SimpleAction::class],
			Forum::$actions[$action],
		);

		Forum::removeAction($action);

		$this->assertArrayNotHasKey($action, Forum::$actions);
		$this->assertSameSize(Forum::$actions, $original_actions);

		Forum::addAction('unit_test_action', '', SimpleAction::class);
		$_REQUEST['action'] = 'unit_test_action';

		$action = Forum::getCurrentAction();

		Forum::$actions = $original_actions;
		$this->setCurrentAction(null);
		unset($_REQUEST['action']);

		$this->assertInstanceOf(ActionInterface::class, $action);
		$this->assertInstanceOf(SimpleAction::class, $action);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function setUpBeforeClass(): void
	{
		require_once TESTS_BOARDDIR . '/tests/fixtures/DatabaseConnection.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/UserRepository.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/SimpleForum.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/SimpleAction.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/SimpleDependencyAwareAction.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/SimpleActionExecuted.php';
	}

	/**
	 * Provides request parameters and their corresponding implied actions.
	 *
	 * @return array<string, array{array<string, string>, string}>
	 */
	public static function provideImpliedActions(): array
	{
		return [
			'topic' => [
				['topic' => '1'],
				Display::class,
			],
			'board' => [
				['board' => '1'],
				MessageIndex::class,
			],
			'default' => [
				[],
				BoardIndex::class,
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Restores Forum::$current_action for test isolation.
	 */
	private function setCurrentAction(?ActionInterface $action): void
	{
		$property = new \ReflectionProperty(Forum::class, 'current_action');
		$property->setValue(null, $action);
	}
}
