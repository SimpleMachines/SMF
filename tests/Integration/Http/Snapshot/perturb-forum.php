<?php

/*
 * Fills the forum with content that has nothing to do with the snapshots, to
 * check that they do not change when it does.
 *
 *   php tests/Integration/Http/Snapshot/perturb-forum.php [scale]
 *
 * ONLY RUN THIS ON A FORUM YOU ARE GOING TO THROW AWAY. It registers members,
 * creates categories and boards and posts, changes the administrator's profile,
 * and does not clean up after itself.
 *
 * Everything it makes is outside the fixture category, so a snapshot that
 * changes afterwards is showing somebody else's content, and needs scoping or
 * masking until it does not. The way to use it, after adding a page to the
 * snapshot tests:
 *
 *   1. install a fresh forum, and record the snapshots on it
 *   2. run this, as many times as you like, with any scale
 *   3. run the snapshot tests again, without recording; they should pass
 *
 * The scale is how many of each thing to make; the default of 10 is enough to
 * push the member list, the recent posts and the statistics past one of
 * everything, and 40 is enough to push most lists onto a second page.
 */

declare(strict_types=1);

use SMF\Board;
use SMF\Category;
use SMF\Db\DatabaseApi as Db;
use SMF\Msg;
use SMF\PersonalMessage\PM;
use SMF\User;
use SMF\Utils;

$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();

require_once dirname(__DIR__, 4) . '/SSI.php';

ob_end_clean();

$scale = max(1, (int) ($argv[1] ?? 10));
$run = substr(md5(uniqid('', true)), 0, 6);

$request = Db::$db->query(
	'SELECT id_member FROM {db_prefix}members WHERE id_group = {int:group} ORDER BY id_member LIMIT 1',
	['group' => 1],
);
[$admin] = Db::$db->fetch_row($request);
Db::$db->free_result($request);

User::setMe((int) $admin);
$_SESSION['admin_time'] = time();

// Members, with the sorts of names people have.
$names = ['Zoë Ünïcödé', 'O\'Brien & Sons', 'user_' . $run, 'Another Member', 'Ma Li', 'Ångström'];
$members = [];

for ($i = 0; $i < $scale; $i++) {
	$options = [
		'interface' => 'admin',
		'username' => 'perturb_' . $run . '_' . $i,
		'email' => 'perturb_' . $run . '_' . $i . '@example.com',
		'password' => 'perturb-password-1',
		'password_check' => 'perturb-password-1',
		'check_reserved_name' => false,
		'check_password_strength' => false,
		'check_email_ban' => false,
		'send_welcome_email' => false,
		'require' => 'nothing',
		'memberGroup' => 0,
	];

	$id = SMF\Actions\Register2::registerMember($options, true);

	if (is_int($id)) {
		$members[] = $id;

		Db::$db->query(
			'UPDATE {db_prefix}members SET real_name = {string:name} WHERE id_member = {int:id}',
			['id' => $id, 'name' => $names[$i % count($names)] . ' ' . $run . $i],
		);
	}
}

// A category with boards and child boards, and a child board of an existing
// board.
$category = Category::create(['cat_name' => 'Perturbation ' . $run, 'move_after' => 0]);

$boards = [];

$boards[] = Board::create([
	'board_name' => 'Perturbed board ' . $run,
	'move_to' => 'bottom',
	'target_category' => $category,
	'access_groups' => [-1, 0],
]);

$boards[] = Board::create([
	'board_name' => 'Perturbed child ' . $run,
	'move_to' => 'child',
	'target_category' => $category,
	'target_board' => $boards[0],
	'access_groups' => [-1, 0],
]);

$request = Db::$db->query(
	'SELECT id_board, id_cat FROM {db_prefix}boards WHERE name = {string:name} LIMIT 1',
	['name' => 'General Discussion'],
);
$general = Db::$db->fetch_assoc($request);
Db::$db->free_result($request);

if (is_array($general)) {
	$boards[] = (int) $general['id_board'];

	$boards[] = Board::create([
		'board_name' => 'Perturbed sub-board ' . $run,
		'move_to' => 'child',
		'target_category' => (int) $general['id_cat'],
		'target_board' => (int) $general['id_board'],
		'access_groups' => [-1, 0],
	]);
}

// Topics and replies, from the administrator, the new members and a guest.
$posters = array_merge([(int) $admin, 0], $members);

for ($i = 0; $i < $scale; $i++) {
	$board = $boards[$i % count($boards)];
	$poster = $posters[$i % count($posters)];

	$msgOptions = [
		'subject' => Utils::htmlspecialchars('Unrelated topic ' . $run . ' number ' . $i . ' with a long subject & <markup>'),
		'body' => Utils::htmlspecialchars('Body ' . $i . ' [b]bold[/b] :)'),
		'approved' => 1,
		'send_notifications' => false,
	];
	$topicOptions = [
		'board' => $board,
		'mark_as_read' => false,
		'sticky_mode' => $i % 5 === 0 ? 1 : null,
		'lock_mode' => $i % 7 === 0 ? 1 : null,
	];
	$posterOptions = ['id' => $poster, 'ip' => '127.0.0.1', 'update_post_count' => $poster !== 0];

	if ($poster === 0) {
		$posterOptions['name'] = 'A Guest ' . $run;
		$posterOptions['email'] = 'guest@example.com';
	}

	Msg::create($msgOptions, $topicOptions, $posterOptions);

	for ($j = 0; $j < 1 + $i % 3; $j++) {
		$replier = $posters[($i + $j + 1) % count($posters)];

		$replyOptions = [
			'subject' => $msgOptions['subject'],
			'body' => Utils::htmlspecialchars('Reply ' . $j . ' [quote]quoted[/quote]'),
			'approved' => 1,
			'send_notifications' => false,
		];
		$replyTopic = ['id' => $topicOptions['id'], 'board' => $board, 'mark_as_read' => false];
		$replyPoster = ['id' => $replier, 'ip' => '127.0.0.1', 'update_post_count' => $replier !== 0];

		if ($replier === 0) {
			$replyPoster['name'] = 'A Guest ' . $run;
			$replyPoster['email'] = 'guest@example.com';
		}

		Msg::create($replyOptions, $replyTopic, $replyPoster);
	}
}

// Personal messages, to the administrator and between the new members.
foreach (array_slice($members, 0, $scale) as $i => $from) {
	$to = [(int) $admin];

	if (isset($members[$i + 1])) {
		$to[] = $members[$i + 1];
	}

	PM::send(['to' => $to, 'bcc' => []], 'Unrelated message ' . $run . ' ' . $i, 'Hello.', true, [
		'id' => $from,
		'name' => 'perturb_' . $run . '_' . $i,
		'username' => 'perturb_' . $run . '_' . $i,
	]);
}

// The forum's own administrator is somebody else's to change, and nothing in
// the fixtures may depend on what their profile says.
Db::$db->query(
	'UPDATE {db_prefix}members
	SET signature = {string:signature},
		personal_text = {string:personal_text},
		website_title = {string:website_title},
		website_url = {string:website_url}
	WHERE id_member = {int:admin}',
	[
		'admin' => (int) $admin,
		'signature' => 'A signature the administrator added in run ' . $run . '.',
		'personal_text' => 'Personal text ' . $run,
		'website_title' => 'Site ' . $run,
		'website_url' => 'https://www.example.org/' . $run,
	],
);

echo 'added ', count($members), ' members, ', count($boards), ' boards and ', $scale, ' topics (run ', $run, ")\n";
