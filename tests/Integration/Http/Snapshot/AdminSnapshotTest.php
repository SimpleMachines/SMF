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
 * The administration and moderation centres, and the parts of the forum only an
 * administrator sees, as the templates render them today.
 *
 * Only pages reached by following a link. Nothing here submits a form or
 * follows a link that does something, since the forum these run against is
 * shared.
 *
 * Deliberately not here:
 *
 *  - Anything that fetches from simplemachines.org or a package server, since
 *    what comes back is not ours to pin down and may not come back at all.
 *  - The theme editor's view of a file, which shows the template's own source
 *    and so changes, by design, with every template that is converted.
 *  - The integration hooks list, which reads every source file on each request
 *    and takes longer than the client waits over a Windows bind mount.
 *  - phpinfo(), which describes the machine rather than the forum.
 *  - The error log. Its filters are built from whatever has been logged, and an
 *    empty log is a different page again, so it depends on what ran before it.
 */
#[CoversNothing]
class AdminSnapshotTest extends SnapshotTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * What an admin page's snapshot covers: the page, without the menu beside
	 * it, which is the same on every page and has a snapshot of its own.
	 */
	public const ADMIN = ['title', '.navigate_section', '#admin_content'];

	/**
	 * The rows of a log, which grows with everything anybody does. The list's
	 * headings and filters are still compared.
	 */
	public const LOG_ROWS = ['table.table_grid tbody'];

	/**
	 * A list's page links, and the box below the list that only holds them,
	 * neither of which is there at all until the list fills a second page.
	 */
	public const LIST_PAGES = ['.pagesection', 'table.table_grid + .flow_auto'];

	/**
	 * On a member's tracking pages, the addresses their errors came from and
	 * the other members seen at theirs, which is everybody who ever posted
	 * from the same machine.
	 */
	public const SHARED_ADDRESSES = ['#tracking dd:nth-of-type(n+3)'];

	/**
	 * Settings only one database engine has: PostgreSQL's fulltext search
	 * language, and the rule above it.
	 */
	public const ENGINE_SETTINGS = ['hr:has(+ dl #search_language)', 'dl:has(#search_language)'];

	/**
	 * The permission profiles, which are loaded in no particular order and
	 * come back from PostgreSQL in a different one from MySQL.
	 */
	public const PROFILE_LISTS = ['#admin_content table.table_grid tbody', 'select[name="copy_from"]', 'select[name="profile"]'];

	/**
	 * The list of every category and its boards on the board management page,
	 * as a heading and a list for each category.
	 */
	public const CATEGORY_LIST = ['#admin_content .windowbg > .sub_bar', '#admin_content .windowbg > form'];

	/**
	 * The versions of the database, PHP, the web server and the extensions,
	 * which describe the machine rather than the forum: Apache in the
	 * container, PHP's own server in CI, and MySQL or PostgreSQL either way.
	 */
	public const SUPPORT_INFORMATION = [
		'#admin_content .roundframe > .title_bar:not(#support_resources) + .padding',
		'#admin_content script',
	];

	/**
	 * Which cache accelerators PHP was built with, and the settings form, which
	 * has a section for each one it found. The form is the same settings
	 * template as every other settings page here.
	 */
	public const CACHE_SETTINGS = [
		'#admin_content .information + .information',
		'#admin_form_wrapper .windowbg',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Each page renders as its recorded snapshot.
	 *
	 * Expected: for each case in pages(), the page at its path, seen by
	 *           the administrator, reads the same as
	 *           snapshots/admin/<name>.txt.
	 */
	#[DataProvider('pages')]
	public function testThePageRendersAsItDid(string $name, string $path, array $roots = self::ADMIN, array $ignore = [], array $only = [], array $unordered = []): void
	{
		$this->assertPageMatchesSnapshot($name, $path, $roots, $ignore, $only, $unordered);
	}

	/**
	 * The chrome around every page renders as its recorded snapshot.
	 *
	 * Expected: the board index, seen by the administrator, with
	 *           #main_content_section left out, reads the same as
	 *           snapshots/admin/chrome.txt.
	 */
	public function testTheChromeRendersAsItDid(): void
	{
		$this->assertPageMatchesSnapshot('chrome', '', self::CHROME, self::CHROME_IGNORE);
	}

	/**
	 * The admin menu renders as its recorded snapshot.
	 *
	 * Expected: #genericmenu on ?action=admin reads the same as
	 *           snapshots/admin/admin_menu.txt.
	 */
	public function testTheAdminMenuRendersAsItDid(): void
	{
		$this->assertPageMatchesSnapshot('admin_menu', '?action=admin', ['#genericmenu']);
	}

	/**
	 * The moderation menu renders as its recorded snapshot.
	 *
	 * Expected: #genericmenu on ?action=moderate reads the same as
	 *           snapshots/admin/moderation_menu.txt.
	 */
	public function testTheModerationMenuRendersAsItDid(): void
	{
		$this->assertPageMatchesSnapshot('moderation_menu', '?action=moderate', ['#genericmenu']);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function audience(): string
	{
		return 'admin';
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
			// The forum, as an administrator sees it.
			['board_index', '', self::BOARD_INDEX, self::BOARD_INDEX_IGNORE],
			['message_index', '?board={board:Snapshot board}.0', self::PAGE],
			['display', '?topic={topic:Snapshot topic}.0', self::PAGE],
			['display_poll', '?topic={topic:Snapshot poll topic}.0', self::PAGE],
			['post_edit', '?action=post;msg={first_msg:Snapshot topic};topic={topic:Snapshot topic}.0', self::PAGE],
			['post_edit_poll', '?action=editpoll;topic={topic:Snapshot poll topic}.0', self::PAGE],
			['split_topic', '?action=splittopics;topic={topic:Snapshot topic}.0;at={first_msg:Snapshot topic}', self::PAGE, [], [], ['.split_messages']],
			['merge_topic', '?action=mergetopics;board={board:Snapshot board}.0;from={topic:Snapshot second topic}', self::PAGE, [], ['select[name="targetboard"] optgroup']],
			['move_topic', '?action=movetopic;current_board={board:Snapshot board};topic={topic:Snapshot second topic}.0', self::PAGE, [], ['select[name="toboard"] optgroup']],
			['track_ip', '?action=trackip;searchip=127.0.0.1', self::PAGE, self::LOG_ROWS, self::LIST_PAGES],

			// A member's profile, as an administrator sees it.
			['profile_summary', '?action=profile;u={member:snapshot_member}', self::PAGE],
			['profile_account', '?action=profile;u={member:snapshot_member};area=account', self::PAGE],
			['profile_forum', '?action=profile;u={member:snapshot_member};area=forumprofile', self::PAGE],
			['profile_permissions', '?action=profile;u={member:snapshot_member};area=permissions', self::PAGE, [], [], ['table.table_grid tbody']],
			['profile_issue_warning', '?action=profile;u={member:snapshot_member};area=issuewarning', self::PAGE],
			['profile_track_activity', '?action=profile;u={member:snapshot_member};area=tracking;sa=activity', self::PAGE, [...self::LOG_ROWS, ...self::SHARED_ADDRESSES], self::LIST_PAGES],
			['profile_track_ip', '?action=profile;u={member:snapshot_member};area=tracking;sa=ip', self::PAGE, self::LOG_ROWS, self::LIST_PAGES],
			['profile_track_edits', '?action=profile;u={member:snapshot_member};area=tracking;sa=edits', self::PAGE, [...self::LOG_ROWS, ...self::SHARED_ADDRESSES], self::LIST_PAGES],
			['profile_track_logins', '?action=profile;u={member:snapshot_member};area=tracking;sa=logins', self::PAGE, self::LOG_ROWS, self::LIST_PAGES],
			['profile_delete', '?action=profile;u={member:snapshot_member};area=deleteaccount', self::PAGE],

			// The moderation centre.
			['moderate_index', '?action=moderate;area=index'],
			['moderate_modlog', '?action=moderate;area=modlog', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['moderate_reported_posts', '?action=moderate;area=reportedposts'],
			['moderate_reported_posts_closed', '?action=moderate;area=reportedposts;sa=closed'],
			['moderate_reported_members', '?action=moderate;area=reportedmembers'],
			['moderate_reported_members_closed', '?action=moderate;area=reportedmembers;sa=closed'],
			['moderate_warnings', '?action=moderate;area=warnings'],
			['moderate_warning_log', '?action=moderate;area=warnings;sa=log'],
			['moderate_warning_templates', '?action=moderate;area=warnings;sa=templates'],
			['moderate_group_requests', '?action=moderate;area=groups;sa=requests'],
			['moderate_view_groups', '?action=moderate;area=viewgroups'],
			['moderate_watched_members', '?action=moderate;area=userwatch;sa=member'],
			['moderate_watched_posts', '?action=moderate;area=userwatch;sa=post'],
			['moderate_unapproved_posts', '?action=moderate;area=postmod;sa=posts'],
			['moderate_unapproved_topics', '?action=moderate;area=postmod;sa=topics'],
			['moderate_unapproved_attachments', '?action=moderate;area=attachmod'],

			// Administration: the centre itself.
			['admin_index', '?action=admin'],
			['admin_credits', '?action=admin;area=credits', self::ADMIN, self::SUPPORT_INFORMATION],

			// Configuration.
			// The time zones are named and ordered by whether daylight saving is
			// in effect today, so the list changes with the seasons.
			['features_basic', '?action=admin;area=featuresettings;sa=basic', self::ADMIN, ['#default_timezone']],
			['features_bbc', '?action=admin;area=featuresettings;sa=bbc'],
			['features_layout', '?action=admin;area=featuresettings;sa=layout'],
			['features_sig', '?action=admin;area=featuresettings;sa=sig'],
			['features_profile', '?action=admin;area=featuresettings;sa=profile'],
			['features_profile_field_new', '?action=admin;area=featuresettings;sa=profileedit'],
			['features_profile_field_edit', '?action=admin;area=featuresettings;sa=profileedit;fid=1'],
			['features_likes', '?action=admin;area=featuresettings;sa=likes'],
			['features_mentions', '?action=admin;area=featuresettings;sa=mentions'],
			['features_alerts', '?action=admin;area=featuresettings;sa=alerts'],
			['antispam', '?action=admin;area=antispam'],
			['languages', '?action=admin;area=languages'],
			['languages_add', '?action=admin;area=languages;sa=add'],
			['languages_edit', '?action=admin;area=languages;sa=edit'],
			['languages_settings', '?action=admin;area=languages;sa=settings'],
			['server_general', '?action=admin;area=serversettings;sa=general'],
			['server_database', '?action=admin;area=serversettings;sa=database', self::ADMIN, [], self::ENGINE_SETTINGS],
			['server_cookie', '?action=admin;area=serversettings;sa=cookie'],
			['server_security', '?action=admin;area=serversettings;sa=security'],
			['server_cache', '?action=admin;area=serversettings;sa=cache', self::ADMIN, self::CACHE_SETTINGS],
			['server_export', '?action=admin;area=serversettings;sa=export'],
			['server_loads', '?action=admin;area=serversettings;sa=loads'],
			['themes', '?action=admin;area=theme;sa=admin'],
			['themes_list', '?action=admin;area=theme;sa=list'],
			['themes_settings', '?action=admin;area=theme;sa=list;th=1'],
			['themes_reset', '?action=admin;area=theme;sa=reset'],
			['themes_reset_options', '?action=admin;area=theme;th=1;sa=reset;who=1'],
			['themes_edit', '?action=admin;area=theme;sa=edit'],
			['themes_copy', '?action=admin;area=theme;th=1;sa=copy'],
			['modsettings', '?action=admin;area=modsettings;sa=general'],

			// Layout.
			['manage_boards', '?action=admin;area=manageboards;sa=main', self::ADMIN, [], self::CATEGORY_LIST],
			['manage_boards_move', '?action=admin;area=manageboards;move={board:Snapshot child two}', self::ADMIN, [], self::CATEGORY_LIST],
			['manage_boards_new_category', '?action=admin;area=manageboards;sa=newcat', self::ADMIN, [], ['select[name="cat_order"] option']],
			['manage_boards_edit_category', '?action=admin;area=manageboards;sa=cat;cat={category}', self::ADMIN, [], ['select[name="cat_order"] option']],
			['manage_boards_new_board', '?action=admin;area=manageboards;sa=newboard;cat={category}', self::ADMIN, [], ['select[name="new_cat"] option'], self::PROFILE_LISTS],
			['manage_boards_edit_board', '?action=admin;area=manageboards;sa=board;boardid={board:Snapshot board}', self::ADMIN, [], ['select[name="new_cat"] option'], self::PROFILE_LISTS],
			['manage_boards_settings', '?action=admin;area=manageboards;sa=settings', self::ADMIN, [], ['#recycle_board option']],
			['news', '?action=admin;area=news;sa=editnews'],
			['news_mailing', '?action=admin;area=news;sa=mailingmembers'],
			['news_settings', '?action=admin;area=news;sa=settings'],
			['post_settings', '?action=admin;area=postsettings;sa=posts'],
			['post_settings_topics', '?action=admin;area=postsettings;sa=topics'],
			['post_settings_drafts', '?action=admin;area=postsettings;sa=drafts'],
			['post_settings_censor', '?action=admin;area=postsettings;sa=censor'],
			['manage_calendar', '?action=admin;area=managecalendar'],
			['manage_search', '?action=admin;area=managesearch;sa=method'],
			['manage_search_settings', '?action=admin;area=managesearch;sa=settings'],
			['manage_search_weights', '?action=admin;area=managesearch;sa=weights'],
			['search_engines', '?action=admin;area=sengines'],
			['smileys', '?action=admin;area=smileys;sa=editsets'],
			['smileys_set', '?action=admin;area=smileys;sa=modifyset;set=fugue'],
			['smileys_new_set', '?action=admin;area=smileys;sa=modifyset'],
			['smileys_settings', '?action=admin;area=smileys;sa=settings'],
			['attachments', '?action=admin;area=manageattachments;sa=attachments'],
			['attachments_avatars', '?action=admin;area=manageattachments;sa=avatars'],
			['attachments_browse', '?action=admin;area=manageattachments;sa=browse'],
			['attachments_browse_avatars', '?action=admin;area=manageattachments;sa=browse;avatars'],
			['attachments_browse_thumbs', '?action=admin;area=manageattachments;sa=browse;thumbs'],
			['attachments_maintenance', '?action=admin;area=manageattachments;sa=maintenance'],
			['attachments_paths', '?action=admin;area=manageattachments;sa=attachpaths'],

			// Members.
			['members', '?action=admin;area=viewmembers;sa=all', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['members_search', '?action=admin;area=viewmembers;sa=search'],
			['members_settings', '?action=admin;area=viewmembers;sa=settings'],
			['membergroups', '?action=admin;area=membergroups;sa=index'],
			['membergroups_add', '?action=admin;area=membergroups;sa=add;generalgroup', self::ADMIN, [], ['li.category']],
			['membergroups_add_post_group', '?action=admin;area=membergroups;sa=add;postgroup', self::ADMIN, [], ['li.category']],
			['membergroups_edit', '?action=admin;area=membergroups;sa=edit;group=2', self::ADMIN, [], ['li.category']],
			['membergroups_settings', '?action=admin;area=membergroups;sa=settings'],
			['permissions', '?action=admin;area=permissions;sa=index'],
			['permissions_group', '?action=admin;area=permissions;sa=modify;group=0'],
			['permissions_board', '?action=admin;area=permissions;sa=board', self::ADMIN, [], ['#admin_content .windowbg > .sub_bar', '#admin_content ul.perm_boards']],
			['permissions_profiles', '?action=admin;area=permissions;sa=profiles', self::ADMIN, [], [], self::PROFILE_LISTS],
			['permissions_settings', '?action=admin;area=permissions;sa=settings'],
			['registration', '?action=admin;area=regcenter;sa=register'],
			['registration_agreement', '?action=admin;area=regcenter;sa=agreement'],
			['registration_policy', '?action=admin;area=regcenter;sa=policy'],
			['registration_reserved', '?action=admin;area=regcenter;sa=reservednames'],
			['registration_settings', '?action=admin;area=regcenter;sa=settings'],
			['bans', '?action=admin;area=ban;sa=list'],
			['bans_add', '?action=admin;area=ban;sa=add'],
			['bans_browse', '?action=admin;area=ban;sa=browse;entity=ip'],
			['bans_log', '?action=admin;area=ban;sa=log', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['paid_subscriptions', '?action=admin;area=paidsubscribe'],
			['warnings', '?action=admin;area=warnings'],

			// Maintenance.
			['maintain_routine', '?action=admin;area=maintain;sa=routine'],
			['maintain_database', '?action=admin;area=maintain;sa=database'],
			['maintain_members', '?action=admin;area=maintain;sa=members'],
			// The board picker for pruning splits the categories into two
			// columns by how many there are, so which column the fixtures land in
			// is the forum's business. The same boards are in the two selects.
			['maintain_topics', '?action=admin;area=maintain;sa=topics', self::ADMIN, ['#rotPanel > div'], ['#id_board_from optgroup', '#id_board_to optgroup']],
			['scheduled_tasks', '?action=admin;area=scheduledtasks;sa=tasks', self::ADMIN, [], [], ['#scheduled_tasks tbody']],
			['scheduled_task_edit', '?action=admin;area=scheduledtasks;sa=taskedit;tid=3'],
			['scheduled_tasks_log', '?action=admin;area=scheduledtasks;sa=tasklog', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['scheduled_tasks_settings', '?action=admin;area=scheduledtasks;sa=settings'],
			['logs_settings', '?action=admin;area=logs;sa=settings'],
			['logs_admin', '?action=admin;area=logs;sa=adminlog', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['logs_moderation', '?action=admin;area=logs;sa=modlog', self::ADMIN, self::LOG_ROWS, self::LIST_PAGES],
			['mail_queue', '?action=admin;area=mailqueue;sa=browse'],
			['mail_settings', '?action=admin;area=mailqueue;sa=settings'],
			['mail_test', '?action=admin;area=mailqueue;sa=test'],
			['reports', '?action=admin;area=reports'],
			['packages', '?action=admin;area=packages;sa=browse'],
			['packages_options', '?action=admin;area=packages;sa=options'],
		]);
	}
}
