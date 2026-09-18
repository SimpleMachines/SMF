<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use DatabaseConnection;
use ForumTest as ForumT;
use ForumTestAction;
use ForumTestActionExecuted;
use PHPUnit\Framework\TestCase;
use SMF\Forum;
use SMF\IntegrationHook;
use UserRepository;

class ForumTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testExecuteRegistersServicesBeforePreflight(): void
	{
		$action_called = false;

		$action = ForumTestAction::load();

		Forum::$actions['test_services'] = ['', $action::class];
		$_REQUEST['action'] = 'test_services';

		$forum = new ForumT();

		IntegrationHook::add('integrate_services', 'my_integrated_service', false, '$boarddir/tests/fixtures/integrationhooks.php');

		try {
			$forum->execute();
		} catch (ForumTestActionExecuted) {
			$action_called = true;
		} finally {
			unset(Forum::$actions['test_services']);
			$_REQUEST['action'] = '';
			//~ IntegrationHook::remove('integrate_services', 'my_integrated_services', false);
		}

		$this->assertTrue($action_called);
		$action = Forum::getCurrentAction();

		$this->assertInstanceOf(UserRepository::class, $action->user_repository);
		$this->assertInstanceOf(DatabaseConnection::class, $action->database_connection);
		$this->assertInstanceOf(DatabaseConnection::class, $action->user_repository->db);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function setUpBeforeClass(): void
	{
		require_once TESTS_BOARDDIR . '/tests/fixtures/DatabaseConnection.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/UserRepository.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/ForumTest.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/ForumTestAction.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/ForumTestActionExecuted.php';
	}
}
