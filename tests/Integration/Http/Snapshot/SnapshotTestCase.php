<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http\Snapshot;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Tests\Integration\Http\HttpTestCase;
use SMF\Tests\Support\DomSnapshot;
use SMF\Tests\Support\HttpClient;
use SMF\Tests\Support\HttpResponse;

/**
 * Base class for the template snapshot tests.
 *
 * These pin down what the templates emit today, so that moving them to another
 * template engine can be checked page by page: a page that renders differently
 * afterwards fails, and the diff says what changed.
 *
 * What gets compared is DomSnapshot's description of the page, not the HTML,
 * so whitespace, attribute order and the forum's own data do not count - see
 * that class for the rules. Pages that list content are pointed at the content
 * fixture-forum.php makes, so a snapshot does not change when somebody posts
 * elsewhere on the forum.
 *
 * The errors a page logs are part of its snapshot too. A converted template that
 * renders the same page while logging an undefined index has still regressed,
 * and a page that logs something today is recorded as doing so rather than
 * failing, which is the point of freezing the current state.
 *
 * To record the snapshots afresh, after a change that is meant to alter the
 * output:
 *
 *   SMF_UPDATE_SNAPSHOTS=1 .dev/test.sh --engine mysql --testsuite snapshot
 *
 * and read the diff before committing it.
 *
 * What the snapshots cannot make up for is configuration. The admin pages show
 * the forum's settings, so a forum set up differently from a new one - with the
 * admin password prompt switched off, say - reads differently there too. Record
 * on a freshly installed forum, and compare against one; that is also what CI
 * installs. Install with the web server's user, not root: a Settings_bak.php
 * the forum cannot write puts a warning on every settings page.
 */
abstract class SnapshotTestCase extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * What a page's snapshot covers unless told otherwise: its title, its
	 * breadcrumbs and everything below them. The rest of the page is the same
	 * on every page and has a snapshot of its own; see ChromeSnapshotTest.
	 */
	public const PAGE = ['title', '.navigate_section', '#content_section'];

	/**
	 * For pages that do not use the forum's layout at all, such as the
	 * printable version of a topic.
	 */
	public const WHOLE_PAGE = ['head', 'body'];

	/**
	 * The layout around every page: the head, the header, the menu, the user
	 * area and the footer. Each audience has one snapshot of it, taken on the
	 * board index, rather than every page carrying its own copy.
	 */
	public const CHROME = ['head', 'body'];

	/**
	 * The page itself, which the other snapshots cover.
	 */
	public const CHROME_IGNORE = ['#main_content_section'];

	/**
	 * The board index, less every board that is not a fixture, since those are
	 * somebody else's content.
	 */
	public const BOARD_INDEX = ['title', '#category_{category}', '#category_{category}_boards', '#info_center'];

	/**
	 * Who is online, and how many, which changes from one second to the next.
	 * It is also where the wording changes between "1 guest" and "2 guests".
	 */
	public const BOARD_INDEX_IGNORE = ['#upshrink_stats > p:nth-of-type(2)'];

	/**
	 * Parts of the page that change between two identical requests whatever
	 * the templates do.
	 */
	public const ALWAYS_IGNORE = [
		// The verification honeypot, whose field Verifier names at random on
		// every request so that a bot cannot learn to leave it alone.
		'.vv_special input',

		// The bar drawn for each entry of a top ten. A count of nothing draws
		// an empty one, and whether any entry has a count of nothing depends
		// on how busy the rest of the forum is.
		'.statsbar > .bar',
	];

	/**
	 * Parts of the page that are there or not depending on the forum's data,
	 * and are left out without a trace.
	 *
	 * The count of unread messages or alerts beside a menu item, which is only
	 * drawn when there are some.
	 */
	public const ALWAYS_DROP = ['.amt'];

	/**
	 * What every fixture's name starts with, for narrowing a list of everything
	 * on the forum to the fixtures. See DomSnapshot::describe().
	 */
	public const FIXTURE_MARKER = 'Snapshot';

	/**
	 * Text that is the forum's data wherever it appears, and is not in a link
	 * that DomSnapshot would recognise as such. See DomSnapshot::describe().
	 *
	 * A post count group changes as its member posts, so the administrator's
	 * reads "Newbie" or "Hero Member" depending on how the forum has been used.
	 * When a member was last active reads "Today" or "3 days ago" depending on
	 * when the list is looked at.
	 */
	public const MASKS = ['.postgroup', 'td.last_active'];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The last error logged before the page being compared was
	 *     requested.
	 */
	private int $watermark = 0;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array|null Where the fixtures are, once they have been built.
	 */
	private static ?array $fixtures = null;

	/**
	 * @var array Signed in clients, by audience, shared by every test in the
	 *     process. Signing in once per test would spend most of the run waiting
	 *     for flood control.
	 */
	private static array $clients = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Fetches a page and compares it with its snapshot.
	 *
	 * The status is part of the snapshot rather than asserted, so that a page
	 * which today turns the visitor away, or redirects, is pinned down as doing
	 * exactly that.
	 *
	 * @param string $name The snapshot's name, which is also its file name.
	 * @param string $path The page, as HttpClient::get() takes it, with
	 *     {board:Name}, {topic:Subject}, {member:name} and friends standing for
	 *     the fixtures' ids.
	 * @param array $roots What to compare. See DomSnapshot::describe().
	 * @param array $ignore What to leave out.
	 * @param array $only Lists of everything on the forum, to narrow to the
	 *     entries that mention the fixtures.
	 * @param array $unordered Lists in whatever order the database returns.
	 */
	public function assertPageMatchesSnapshot(string $name, string $path, array $roots = self::PAGE, array $ignore = [], array $only = [], array $unordered = []): void
	{
		// Some pages count as a search, and a search within a few seconds of the
		// last is turned away. Pages follow each other faster than that.
		$this->forgetFloodControl();

		$this->assertMatchesSnapshot($name, $this->http->get($this->resolve($path)), $roots, $ignore, $only, $unordered);
	}

	/**
	 * Compares a response with its snapshot.
	 *
	 * @param string $name The snapshot's name.
	 * @param HttpResponse $response What came back.
	 * @param array $roots What to compare.
	 * @param array $ignore What to leave out.
	 * @param array $only Lists to narrow to the fixtures.
	 * @param array $unordered Lists in whatever order the database returns.
	 */
	public function assertMatchesSnapshot(string $name, HttpResponse $response, array $roots = self::PAGE, array $ignore = [], array $only = [], array $unordered = []): void
	{
		$actual = $this->describe($response, $roots, array_merge(static::ALWAYS_IGNORE, $ignore), $only, $unordered);
		$file = $this->snapshotDirectory() . '/' . $name . '.txt';

		if (getenv('SMF_UPDATE_SNAPSHOTS') === '1') {
			if (!is_dir(\dirname($file))) {
				mkdir(\dirname($file), 0o777, true);
			}

			file_put_contents($file, $actual);
		}

		if (!is_file($file)) {
			$this->fail(
				'there is no snapshot for ' . $name . ' yet. Record one with SMF_UPDATE_SNAPSHOTS=1 '
				. 'and read it before committing it.',
			);
		}

		$this->assertSame(
			(string) file_get_contents($file),
			$actual,
			$name . ' (' . $response->url . ') no longer renders as it did. If the change is '
			. 'intended, record it again with SMF_UPDATE_SNAPSHOTS=1.',
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Who the tests in the class are looking at the forum as.
	 *
	 * @return string 'guest', 'member' or 'admin'.
	 */
	abstract public static function audience(): string;

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The audience's browser, once there is one.
	 *
	 * A new browser costs a request to arrive at the forum and more to sign in,
	 * and at a second or so a page over a Windows bind mount, making one per test
	 * only to throw it away doubled the time the suite took.
	 *
	 * @return HttpClient The browser.
	 */
	protected function client(): HttpClient
	{
		return self::$clients[static::audience()] ?? parent::client();
	}

	protected function setUp(): void
	{
		parent::setUp();

		self::$fixtures ??= $this->buildFixtures();

		$audience = static::audience();

		if (!isset(self::$clients[$audience])) {
			self::$clients[$audience] = $this->http;

			// The first request of a new session regenerates it, so a page recorded
			// or a token taken from that request belongs to a session that is
			// already gone. Arrive at the forum the way a person would first.
			$this->http->get('');

			if ($audience === 'admin') {
				$this->signInAsAdmin();
				$this->passSecurity('?action=admin', self::adminPassword());
			} elseif ($audience === 'member') {
				$this->signInAsMember();
				$this->passSecurity('?action=profile;area=account', (string) ($this->fixtures()['member_password'] ?? ''));
			}
		}

		$this->http = self::$clients[$audience];

		// The fixture member is signed in by one class and looked at by the
		// others, which would then show them as online or not depending on the
		// order the classes ran in and how long ago that was. Removing them from
		// the who's online log makes them offline to everybody else, while
		// leaving their session alone; their own next request puts them back.
		Db::$db->query(
			'DELETE FROM {db_prefix}log_online
			WHERE id_member IN ({array_int:members})',
			[
				'members' => array_values($this->fixtures()['members'] ?? [0]),
			],
		);

		// Taken after signing in, which is not what is being tested.
		$row = $this->queryRow('SELECT COALESCE(MAX(id_error), 0) AS id_error FROM {db_prefix}log_errors');
		$this->watermark = (int) ($row['id_error'] ?? 0);
	}

	/**
	 * The fixtures, as fixture-forum.php described them.
	 *
	 * @return array The fixtures.
	 */
	protected function fixtures(): array
	{
		return self::$fixtures ?? [];
	}

	/**
	 * Replaces the fixture placeholders in a path with ids.
	 *
	 * @param string $path The path.
	 * @return string The path with ids in it.
	 */
	protected function resolve(string $path): string
	{
		$fixtures = $this->fixtures();

		return (string) preg_replace_callback(
			'~\{(board|topic|first_msg|member|category|admin|pm)(?::([^}]+))?\}~',
			function (array $match) use ($fixtures, $path): string {
				$id = match ($match[1]) {
					'board' => $fixtures['boards'][$match[2]] ?? null,
					'topic' => $fixtures['topics'][$match[2]] ?? null,
					'first_msg' => $fixtures['first_msgs'][$match[2]] ?? null,
					'member' => $fixtures['members'][$match[2]] ?? null,
					'category' => $fixtures['category'] ?? null,
					'admin' => $fixtures['admin'] ?? null,
					'pm' => $fixtures['pm'] ?? null,
				};

				$this->assertNotNull($id, 'no fixture for ' . $match[0] . ' in ' . $path);

				return (string) $id;
			},
			$path,
		);
	}

	/**
	 * Describes a response, with the errors it logged.
	 *
	 * @param HttpResponse $response The response.
	 * @param array $roots What to describe.
	 * @param array $ignore What to leave out.
	 * @param array $only Lists to narrow to the fixtures.
	 * @param array $unordered Lists in whatever order the database returns.
	 * @return string The description.
	 */
	protected function describe(HttpResponse $response, array $roots, array $ignore, array $only = [], array $unordered = []): string
	{
		// Where the forum is installed shows up in the server settings, and is
		// /var/www/html in the container and a runner's home directory in CI.
		// The name is whatever it was installed with, and the image proxy's
		// secret is made up by the installer and shown in the server settings.
		$snapshot = new DomSnapshot([
			$this->http->base_url => '{boardurl}',
			Config::$boardurl => '{boardurl}',
			Config::$boarddir => '{boarddir}',
			Config::$mbname => '{forum_name}',
			Config::$image_proxy_secret => '{secret}',
		]);

		// Described first, since that is what finds the session on the page for
		// the masking of the location to use.
		$body = $snapshot->describe(
			$response->body,
			array_map([$this, 'resolve'], $roots),
			array_map([$this, 'resolve'], $ignore),
			static::MASKS,
			array_fill_keys($only, self::FIXTURE_MARKER) + array_fill_keys(static::ALWAYS_DROP, null),
			$unordered,
		);

		$description = 'status ' . $response->status . "\n";

		if (isset($response->headers['location'])) {
			$description .= 'location ' . $snapshot->mask((string) $response->headers['location'], true) . "\n";
		}

		$description .= $body;

		$errors = $this->loggedErrors($snapshot);

		if ($errors !== []) {
			$description .= "\n= errors logged\n" . implode("\n", $errors) . "\n";
		}

		return $description;
	}

	/**
	 * What the forum logged since the test started, masked like the page.
	 *
	 * The file and line are left out, since they move whenever anything above
	 * them in the file changes.
	 *
	 * @param DomSnapshot $snapshot For its masking rules.
	 * @return array One line per distinct error, sorted.
	 */
	protected function loggedErrors(DomSnapshot $snapshot): array
	{
		$request = Db::$db->query(
			'SELECT id_error, error_type, message
			FROM {db_prefix}log_errors
			WHERE id_error > {int:watermark}',
			[
				'watermark' => $this->watermark,
			],
		);

		$errors = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Once a request has logged something the watermark moves on, so the
			// next comparison in the same test only reports what came after it.
			$this->watermark = max($this->watermark, (int) $row['id_error']);

			$message = html_entity_decode((string) $row['message'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$message = trim((string) preg_replace('~\s+~u', ' ', strip_tags($message)));

			$errors[] = '[' . trim((string) $row['error_type']) . '] ' . $snapshot->mask($message);
		}

		Db::$db->free_result($request);

		$errors = array_unique($errors);
		sort($errors);

		return $errors;
	}

	/**
	 * Where this class keeps its snapshots.
	 *
	 * @return string The directory.
	 */
	protected function snapshotDirectory(): string
	{
		return __DIR__ . '/snapshots/' . static::audience();
	}

	/**
	 * Signs in as the fixture member.
	 */
	protected function signInAsMember(): void
	{
		$response = $this->submitForm($this->http->get('?action=login'), [
			'user' => 'snapshot_member',
			'passwrd' => (string) ($this->fixtures()['member_password'] ?? ''),
		], '//form[contains(@action, "action=login2")]');

		$this->assertSignedIn(true, 'could not sign in as the fixture member: ' . $response->errorText());
	}

	/**
	 * Gets through the password prompt put in front of the admin area, and of
	 * a member's own account settings, when the forum has it switched on.
	 *
	 * Once given, the password is good for the rest of the session, so the
	 * pages behind it are compared rather than the prompt. A forum with the
	 * prompt switched off never shows it, and the pages read the same.
	 *
	 * @param string $path A page behind the prompt.
	 * @param string $password The password to give.
	 */
	protected function passSecurity(string $path, string $password): void
	{
		$page = $this->http->get($path);
		$form = '//form[.//input[@name="admin_pass"]]';

		if ($page->xpath($form)->length === 0) {
			return;
		}

		$response = $this->submitForm($page, ['admin_pass' => $password], $form);

		$this->assertSame(
			0,
			$response->xpath($form)->length,
			$path . ' asked for the password again: ' . $response->errorText(),
		);
	}

	/**
	 * Builds the fixtures, in a process of its own.
	 *
	 * @return array Where they are.
	 */
	private function buildFixtures(): array
	{
		$script = __DIR__ . '/fixture-forum.php';

		$process = proc_open(
			[PHP_BINARY, $script],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			Config::$boarddir,
		);

		$this->assertIsResource($process, 'could not run ' . $script);

		$out = (string) stream_get_contents($pipes[1]);
		$err = (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$status = proc_close($process);
		$fixtures = json_decode($out, true);

		$this->assertSame(0, $status, 'building the fixtures failed: ' . $err . $out);
		$this->assertIsArray($fixtures, 'the fixture script printed no JSON: ' . $err . $out);

		return $fixtures;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Keys each case by its name, which is what PHPUnit reports.
	 *
	 * @param array $cases The cases, each starting with its name.
	 * @return array The same, keyed.
	 */
	protected static function cases(array $cases): array
	{
		$keyed = [];

		foreach ($cases as $case) {
			$keyed[$case[0]] = $case;
		}

		return $keyed;
	}
}
