<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The pages a visitor sees before signing in, as the templates render them today.
 *
 * Pages a guest is not allowed to see are here too, since the page that turns
 * them away is a template as well.
 */
#[CoversNothing]
class GuestSnapshotTest extends SnapshotTestCase
{
	/****************
	 * Public methods
	 ****************/

	#[DataProvider('pages')]
	public function testThePageRendersAsItDid(string $name, string $path, array $roots = self::PAGE, array $ignore = [], array $only = [], array $unordered = []): void
	{
		$this->assertPageMatchesSnapshot($name, $path, $roots, $ignore, $only, $unordered);
	}

	public function testTheChromeRendersAsItDid(): void
	{
		$this->assertPageMatchesSnapshot('chrome', '', self::CHROME, self::CHROME_IGNORE);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function audience(): string
	{
		return 'guest';
	}

	/**
	 * The pages, as name, path, and optionally what to compare and what to
	 * leave out of it.
	 *
	 * @return array The cases.
	 */
	public static function pages(): array
	{
		return self::cases([
			// Browsing.
			['board_index', '', self::BOARD_INDEX, self::BOARD_INDEX_IGNORE],
			['message_index', '?board={board:Snapshot board}.0'],
			['message_index_child', '?board={board:Snapshot child one}.0'],
			['message_index_empty', '?board={board:Snapshot empty board}.0'],
			['display', '?topic={topic:Snapshot topic}.0'],
			['display_poll', '?topic={topic:Snapshot poll topic}.0'],
			['display_child', '?topic={topic:Snapshot child topic}.0'],
			['printpage', '?action=printpage;topic={topic:Snapshot topic}.0', self::WHOLE_PAGE],
			['recent', '?action=recent;c={category}'],
			['unread', '?action=unread'],
			['post', '?action=post;board={board:Snapshot board}.0'],
			['not_found', '?action=smf_snapshot_no_such_action'],
			['no_such_topic', '?topic=999999999.0'],

			// Signing in and up.
			['login', '?action=login'],
			['login_popup', '?action=login;ajax', self::WHOLE_PAGE],
			['reminder', '?action=reminder'],
			['activate', '?action=activate'],
			['signup', '?action=signup'],
			['agreement', '?action=agreement'],

			// Everything else a guest can try.
			['search', '?action=search', self::PAGE, [], ['.boardslist > ul > li']],
			['search_results', '?action=search2;search=bold;brd[{board:Snapshot board}]={board:Snapshot board}'],
			// Narrowed to the fixture members by a search, since the list of everybody
			// grows a page at a time.
			['member_list', '?action=mlist;sa=search;search=snapshot;fields=name'],
			['profile', '?action=profile;u={member:snapshot_member}'],
			['stats', '?action=stats'],
			['groups', '?action=groups'],
			['help', '?action=help'],
			['help_popup', '?action=helpadmin;help=registration_agreement', self::WHOLE_PAGE],
			['credits', '?action=credits'],
			['theme_picker', '?action=theme;sa=pick'],
		]);
	}
}
