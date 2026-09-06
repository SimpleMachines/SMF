<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\User;

/**
 * Base class for tests that need a real, installed forum.
 *
 * Everything here exists because the unit suite cannot have it: a database
 * connection, $modSettings as the forum actually stores it, and a current user.
 *
 * Each test runs inside a transaction that is rolled back afterwards, so tests
 * can write freely without ordering themselves around each other. Two things
 * that does not cover:
 *
 *  - DDL. MySQL commits implicitly on CREATE, ALTER and DROP, so a test that
 *    changes the schema has to put it back itself.
 *  - Anything a test causes to happen in another process, such as a request made
 *    over HTTP. That runs on its own connection and will not see the open
 *    transaction, nor be undone by the rollback.
 */
abstract class IntegrationTestCase extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Copy of Config::$modSettings taken before the test ran.
	 */
	private array $mod_settings_backup = [];

	/**
	 * @var int Highest id_error before the test ran.
	 */
	private int $error_watermark = 0;

	/***********************
	 * Public static methods
	 ***********************/

	public static function setUpBeforeClass(): void
	{
		$reason = Installation::unavailableReason();

		if ($reason !== '') {
			self::markTestSkipped(
				'no forum to test against: ' . $reason
				. '. Run .docker/install-forum.sh --engine mysql',
			);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		Db::$db->transaction('begin');

		// $modSettings is a plain static array, so a test that calls
		// updateModSettings() changes it for everything that runs after it. The
		// rollback puts the table back, not the copy in memory.
		$this->mod_settings_backup = Config::$modSettings;

		$this->error_watermark = $this->lastErrorId();
	}

	protected function tearDown(): void
	{
		Db::$db->transaction('rollback');

		Config::$modSettings = $this->mod_settings_backup;

		// Hooks added with permanent: false live in $modSettings, so restoring it
		// above has already removed them. This only puts the switch back.
		IntegrationHook::$enabled = true;

		parent::tearDown();
	}

	/**
	 * Becomes the given member for the rest of the test.
	 *
	 * This is the seam Login2::DoLogin() itself uses once it has checked the
	 * password, so everything downstream - permissions, bans, logging - behaves
	 * as it would for a real login, with no cookie and no request involved.
	 *
	 * Note that User::$me is a typed static and cannot be unset once assigned,
	 * so this outlives the test. Say who you are rather than assuming.
	 *
	 * @param int $id The member to become.
	 */
	protected function actingAs(int $id): void
	{
		User::setMe($id);
	}

	/**
	 * The id of an administrator, for actingAs().
	 *
	 * @return int The lowest member id in group 1.
	 */
	protected function adminId(): int
	{
		$request = Db::$db->query(
			'SELECT id_member
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group}
			ORDER BY id_member
			LIMIT 1',
			[
				'admin_group' => 1,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		$this->assertNotEmpty($row, 'the forum has no administrator');

		return (int) $row['id_member'];
	}

	/**
	 * Registers a hook for the duration of the test.
	 *
	 * permanent: false keeps it in Config::$modSettings and out of the database,
	 * so tearDown() removes it by restoring that array.
	 *
	 * @param string $name The hook to add to, e.g. 'integrate_verify_user'.
	 * @param string $function The callable, in any form Utils::getCallable() takes.
	 */
	protected function hook(string $name, string $function): void
	{
		IntegrationHook::add($name, $function, false);
	}

	/**
	 * Asserts the forum logged nothing since the test started.
	 *
	 * Most of what goes wrong in SMF is recorded here rather than shown, so a
	 * page that returned the right thing while quietly logging an undefined index
	 * has still regressed.
	 *
	 * @param string $message Optional context for the failure.
	 */
	protected function assertNoErrorsLogged(string $message = ''): void
	{
		$request = Db::$db->query(
			'SELECT error_type, message, file, line
			FROM {db_prefix}log_errors
			WHERE id_error > {int:watermark}
			ORDER BY id_error',
			[
				'watermark' => $this->error_watermark,
			],
		);

		$this->assertNotFalse(
			$request,
			rtrim($message . "\n") . 'could not read the error log, so this proves nothing',
		);

		$errors = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$errors[] = \sprintf(
				'  [%s] %s (%s:%d)',
				$row['error_type'],
				html_entity_decode((string) $row['message'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				$row['file'],
				$row['line'],
			);
		}

		Db::$db->free_result($request);

		$this->assertSame(
			[],
			$errors,
			rtrim($message . "\n") . 'the forum logged ' . \count($errors) . " error(s):\n" . implode("\n", $errors),
		);
	}

	/**
	 * Runs a query and returns its first row.
	 *
	 * Exists because a failed query is not an exception here. MySQL returns
	 * false and carries on; PostgreSQL returns false and additionally puts the
	 * surrounding transaction into a failed state, so every later query in the
	 * same test returns false too. Handing that false to fetch_assoc() produces
	 * a TypeError about argument #1, which says nothing about what went wrong.
	 *
	 * @param string $sql The query, in SMF's dialect.
	 * @param array $params Its parameters.
	 * @return array|null The first row, or null when there were none.
	 */
	protected function queryRow(string $sql, array $params = []): ?array
	{
		$request = Db::$db->query($sql, $params);

		$this->assertNotFalse(
			$request,
			"the query failed:\n" . trim($sql)
			. "\non PostgreSQL this also aborts the transaction, so every query after it fails too",
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return \is_array($row) ? $row : null;
	}

	/**
	 * Reads a setting straight out of the table.
	 *
	 * Bypasses Config::$modSettings and its cache, so what comes back is what
	 * the database actually holds rather than what the process believes.
	 *
	 * @param string $variable The setting to read.
	 * @return string|null The value, or null when there is no such row.
	 */
	protected function rawSetting(string $variable): ?string
	{
		$row = $this->queryRow(
			'SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			[
				'variable' => $variable,
			],
		);

		return $row === null ? null : (string) $row['value'];
	}

	/**
	 * The highest id_error currently in the log.
	 *
	 * @return int The id, or 0 when nothing has ever been logged.
	 */
	private function lastErrorId(): int
	{
		$request = Db::$db->query(
			'SELECT COALESCE(MAX(id_error), 0) AS id_error
			FROM {db_prefix}log_errors',
			[],
		);

		if ($request === false) {
			return 0;
		}

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return (int) ($row['id_error'] ?? 0);
	}
}
