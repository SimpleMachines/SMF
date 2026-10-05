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

namespace SMF\Tests\Integration\Http\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The pages an ordinary member sees, as the templates render them today.
 *
 * Signed in as the member fixture-forum.php registers, who has posted in the
 * fixture boards and nowhere else, and has been sent one personal message.
 */
#[CoversNothing]
class MemberSnapshotTest extends SnapshotTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Each page renders as its recorded snapshot.
	 *
	 * Expected: for each case in pages(), the page at its path, seen by
	 *           the fixture member, reads the same as
	 *           snapshots/member/<name>.txt.
	 */
	#[DataProvider('pages')]
	public function testThePageRendersAsItDid(string $name, string $path, array $roots = self::PAGE, array $ignore = [], array $only = [], array $unordered = []): void
	{
		$this->assertPageMatchesSnapshot($name, $path, $roots, $ignore, $only, $unordered);
	}

	/**
	 * The chrome around every page renders as its recorded snapshot.
	 *
	 * Expected: the board index, seen by the fixture member, with
	 *           #main_content_section left out, reads the same as
	 *           snapshots/member/chrome.txt.
	 */
	public function testTheChromeRendersAsItDid(): void
	{
		$this->assertPageMatchesSnapshot('chrome', '', self::CHROME, self::CHROME_IGNORE);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function audience(): string
	{
		return 'member';
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
			['unread', '?action=unread;boards={board:Snapshot board}'],
			['unread_replies', '?action=unreadreplies;boards={board:Snapshot board}'],

			// Posting.
			['post_new_topic', '?action=post;board={board:Snapshot board}.0'],
			['post_reply', '?action=post;topic={topic:Snapshot topic}.0'],
			['post_new_poll', '?action=post;board={board:Snapshot board}.0;poll'],
			['post_in_locked_topic', '?action=post;topic={topic:Snapshot poll topic}.0'],
			['report_to_moderator', '?action=reporttm;topic={topic:Snapshot topic}.0;msg={first_msg:Snapshot topic}'],

			// Personal messages.
			['pm_inbox', '?action=pm'],
			['pm_outbox', '?action=pm;f=sent'],
			['pm_read', '?action=pm;pmsg={pm}'],
			['pm_send', '?action=pm;sa=send'],
			['pm_reply', '?action=pm;sa=send;f=inbox;pmsg={pm};quote;u={member:snapshot_other}'],
			['pm_search', '?action=pm;sa=search'],
			['pm_labels', '?action=pm;sa=manlabels'],
			['pm_rules', '?action=pm;sa=manrules'],
			['pm_settings', '?action=pm;sa=settings'],
			['pm_prune', '?action=pm;sa=prune'],

			// The member's own profile.
			['profile_summary', '?action=profile'],
			['profile_statistics', '?action=profile;area=statistics'],
			['profile_posts', '?action=profile;area=showposts'],
			['profile_topics', '?action=profile;area=showposts;sa=topics'],
			['profile_attachments', '?action=profile;area=showposts;sa=attach'],
			['profile_alerts', '?action=profile;area=showalerts'],
			['profile_drafts', '?action=profile;area=showdrafts'],
			['profile_permissions', '?action=profile;area=permissions', self::PAGE, [], [], ['table.table_grid tbody']],
			['profile_account', '?action=profile;area=account'],
			['profile_forum', '?action=profile;area=forumprofile'],
			['profile_theme', '?action=profile;area=theme'],
			['profile_notification', '?action=profile;area=notification'],
			['profile_notification_topics', '?action=profile;area=notification;sa=topics'],
			['profile_notification_boards', '?action=profile;area=notification;sa=boards'],
			['profile_ignore_boards', '?action=profile;area=ignoreboards'],
			['profile_buddies', '?action=profile;area=lists;sa=buddies'],
			['profile_ignore_list', '?action=profile;area=lists;sa=ignore'],
			['profile_groups', '?action=profile;area=groupmembership'],
			['profile_delete', '?action=profile;area=deleteaccount'],

			// Somebody else's profile.
			['other_profile_summary', '?action=profile;u={member:snapshot_other}'],
			['other_profile_posts', '?action=profile;u={member:snapshot_other};area=showposts'],

			// Everything else a member can reach.
			['search', '?action=search', self::PAGE, [], ['.boardslist > ul > li']],
			['search_results', '?action=search2;search=bold;brd[{board:Snapshot board}]={board:Snapshot board}'],
			// Narrowed to the fixture members by a search, since the list of everybody
			// grows a page at a time.
			['member_list', '?action=mlist;sa=search;search=snapshot;fields=name'],
			['member_list_search', '?action=mlist;sa=search'],
			['groups', '?action=groups'],
			['stats', '?action=stats'],
			['help', '?action=help'],
			['credits', '?action=credits'],
			['likes', '?action=likes;sa=view;ltype=msg;like={first_msg:Snapshot topic}'],
			['theme_picker', '?action=theme;sa=pick'],
		]);
	}
}
