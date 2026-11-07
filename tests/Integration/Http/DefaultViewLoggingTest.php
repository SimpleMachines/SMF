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

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * The pages a visitor reaches without naming an action are logged like any
 * other page.
 *
 * The board index, a board and a topic have no ?action= in their URL, so the
 * forum works out which action they are before deciding whether to log the
 * visit. That makes them the requests most easily left out of the logging by
 * a change to how the action is found, and they are also the pages people
 * spend nearly all of their time on. When they go unlogged, Who's Online shows
 * hardly anyone and the hit counter barely moves, with nothing in the error
 * log to say so.
 *
 * Every request here is made as a guest with no cookies. User::logOnline()
 * skips a session it logged in the last few seconds, so a request made in the
 * same session as the one before it would not be logged whatever the forum
 * thought of the page.
 */
#[CoversNothing]
class DefaultViewLoggingTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The user agent HttpClient sends, which logOnline() stores in the url
	 * column. It is how these tests find their own rows in log_online without
	 * knowing which address the forum sees them arrive from.
	 */
	private const USER_AGENT = 'SMF test suite';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array<string, ?string> The settings this test changed, and what they
	 *    were before. Null for one that was not set, which updateModSettings()
	 *    takes as an instruction to remove it again.
	 */
	private array $settings_backup = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * A visit to a page with no action in its URL shows the visitor in Who's
	 * Online and counts a hit.
	 *
	 * Expected: fetching each case in defaultViews() as a guest leaves one row
	 *           in log_online carrying this client's user agent, and adds one
	 *           to the hits in log_activity.
	 * Guards:   in 2.1, moving the logging below the code that picks the
	 *           default action meant the board index, boards and topics
	 *           returned before it was reached, so nobody browsing them was
	 *           shown as online or counted.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9778
	 */
	#[DataProvider('defaultViews')]
	public function testTheVisitIsLogged(string $path, string $name): void
	{
		$hits = $this->totalHits();

		$this->visitAsNewGuest($path);

		$this->assertSame(
			1,
			$this->ownOnlineRows(),
			$name . ' (' . $path . ') did not show the visitor in Who\'s Online',
		);

		$this->assertSame(
			$hits + 1,
			$this->totalHits(),
			$name . ' (' . $path . ') was not counted as a hit',
		);

		$this->assertNoErrorsLogged($name . ' (' . $path . ') logged something.' . "\n");
	}

	/**
	 * A visit to an action that is not logged leaves Who's Online and the hit
	 * counter alone.
	 *
	 * This is here to show the assertions above can tell a logged visit from
	 * one that was not, rather than passing on whatever the forum does.
	 *
	 * Expected: fetching ?action=helpadmin, whose canBeLogged() is false, as a
	 *           guest leaves no row in log_online carrying this client's user
	 *           agent, and does not change the hits in log_activity.
	 */
	public function testAnUnloggedActionIsNotLogged(): void
	{
		$hits = $this->totalHits();

		$this->visitAsNewGuest('?action=helpadmin;help=manage_boards');

		$this->assertSame(0, $this->ownOnlineRows(), 'helpadmin showed the visitor in Who\'s Online');
		$this->assertSame($hits, $this->totalHits(), 'helpadmin was counted as a hit');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The pages a visitor reaches without an action in the URL.
	 *
	 * The board and topic are the ones every fresh install has: the first
	 * board, and the welcome topic in it.
	 *
	 * @return array The cases, path and a readable name.
	 */
	public static function defaultViews(): array
	{
		return [
			'board index' => ['', 'the board index'],
			'board' => ['?board=1.0', 'the first board'],
			'topic' => ['?topic=1.0', 'the welcome topic'],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Turns hit counting on for the length of the test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		foreach (['trackStats' => '1', 'hitStats' => '1'] as $name => $value) {
			$this->settings_backup[$name] = $this->rawSetting($name);
		}

		Config::updateModSettings(['trackStats' => '1', 'hitStats' => '1']);
	}

	protected function tearDown(): void
	{
		$this->forgetOwnOnlineRows();

		if ($this->settings_backup !== []) {
			Config::updateModSettings($this->settings_backup);
			$this->settings_backup = [];
		}

		parent::tearDown();
	}

	/**
	 * Fetches a page as a guest the forum has never seen before.
	 *
	 * Removing this client's rows from log_online first matters as much as
	 * dropping the cookies. A guest's row is keyed by IP address and written
	 * with INSERT IGNORE, so a row left by an earlier test would stay where it
	 * was and be counted as this visit.
	 *
	 * @param string $path Where to go, as HttpClient::get() takes it.
	 */
	private function visitAsNewGuest(string $path): void
	{
		$this->forgetOwnOnlineRows();
		$this->http->forgetCookies();
		$this->fetch($path);
	}

	/**
	 * Counts the rows in log_online written for this client.
	 *
	 * @return int How many there are.
	 */
	private function ownOnlineRows(): int
	{
		$row = $this->queryRow(
			'SELECT COUNT(*) AS num_rows
			FROM {db_prefix}log_online
			WHERE id_member = {int:guest}
				AND url LIKE {string:user_agent}',
			[
				'guest' => 0,
				'user_agent' => '%' . self::USER_AGENT . '%',
			],
		);

		return (int) $row['num_rows'];
	}

	/**
	 * Removes the rows in log_online written for this client.
	 */
	private function forgetOwnOnlineRows(): void
	{
		Db::$db->query(
			'DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:guest}
				AND url LIKE {string:user_agent}',
			[
				'guest' => 0,
				'user_agent' => '%' . self::USER_AGENT . '%',
			],
		);
	}

	/**
	 * Adds up the hits in log_activity.
	 *
	 * Summed over every day rather than read for today, so a test that runs
	 * across midnight, or in a process whose idea of today differs from the
	 * forum's, still compares like with like.
	 *
	 * @return int The total.
	 */
	private function totalHits(): int
	{
		$row = $this->queryRow(
			'SELECT COALESCE(SUM(hits), 0) AS hits
			FROM {db_prefix}log_activity',
		);

		return (int) $row['hits'];
	}
}
