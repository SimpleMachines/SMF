<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\Alert;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Handles liking posts and displaying the list of who liked a post.
 */
class Like implements ActionInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'like';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * @todo Do delete, insert, and count really need to be sub-actions? They
	 * are never used as sub-actions in practice. Instead, they are only ever
	 * called internally by the like() method. Moreover, the control flow
	 * regarding hooks, etc., assumes that they are only called by like().
	 */
	public static array $subactions = [
		'like' => 'like',
		'view' => 'view',
		'delete' => 'delete',
		'insert' => 'insert',
		'count' => 'count',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Know if a request comes from an ajax call or not.
	 * Depends on $_GET['js'] been set.
	 */
	protected $js = false;

	/**
	 * @var string
	 *
	 * If filled, its value will contain a string matching a key
	 * on a language var Lang::$txt[$this->error]
	 */
	protected $error = false;

	/**
	 * @var string
	 *
	 * The unique type to like, needs to be unique and it needs to be no longer
	 * than 6 characters, only numbers and letters are allowed.
	 */
	protected $type = '';

	/**
	 * @var string
	 *
	 * A generic string used if you need to pass any extra info.
	 * It gets set via $_GET['extra'].
	 */
	protected $extra = false;

	/**
	 * @var int
	 *
	 * A valid ID to identify the content being liked.
	 */
	protected $content = 0;

	/**
	 * @var int
	 *
	 * The number of times the content has been liked.
	 */
	protected $num_likes = 0;

	/**
	 * @var bool
	 *
	 * If the current user has already liked this content.
	 */
	protected $already_liked = false;

	/**
	 * @var array
	 *
	 * Mostly used for external integration. Needs to be filled as an array
	 * with the following keys:
	 *
	 * 'can_like'   bool|string  True if the current user can actually like
	 *                           this content, or a Lang::$txt key for an
	 *                           error message if not.
	 *
	 * 'redirect'    string      URL to redirect to after the like is submitted.
	 *                           If not set, will redirect to the forum index.
	 *
	 * 'type'        string      6 character unique identifier for the content.
	 *                           Must match what was sent in $_GET['ltype']
	 *
	 * 'flush_cache' bool        If true, reset the like content's cache entry
	 *                           after a new entry has been inserted. Optional.
	 *
	 * 'callback'    callable    Optional function or method to call immediately
	 *                           after like data has been inserted or deleted.
	 *                           If set, the callback will be called before the
	 *                           integrate_issue_like hook.
	 *
	 * 'json'        bool        If true, the class will return a JSON object as
	 *                           a response instead of HTML. Default: false.
	 */
	protected $valid_likes = [
		'can_like' => false,
		'redirect' => '',
		'type' => '',
		'flush_cache' => '',
		'callback' => false,
		'json' => false,
	];

	/**
	 * @var int
	 *
	 * The topic ID. Used for liking messages.
	 */
	protected $id_topic = 0;

	/**
	 * @var bool
	 *
	 * Whether respond() will be executed as normal.
	 *
	 * If this is set to false it indicates the method already implemented
	 * its own way to send back a response.
	 */
	protected $set_response = true;

	/**
	 * @var mixed
	 *
	 * Data for the response.
	 */
	protected $data;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 */
	protected static $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * The main handler.
	 *
	 * Verifies permissions (whether the user can see the content in question),
	 * dispatch different method for different sub-actions.
	 *
	 * Accessed from index.php?action=likes
	 */
	public function execute(): void
	{
		// Make sure the user can see and like your content.
		$this->check();

		if (is_string($this->error)) {
			$this->respond();

			return;
		}

		// So at this point, whatever type of like the user supplied and the
		// item of content in question, we know it exists.
		// Now we need to figure out what we're doing with that.
		if (isset(self::$subactions[$this->subaction])) {
			// Guest can only view likes.
			if ($this->subaction != 'view') {
				User::$me->kickIfGuest();
			}

			User::$me->checkSession('get');

			// Call the appropriate method.
			if (method_exists($this, self::$subactions[$this->subaction])) {
				call_user_func([$this, self::$subactions[$this->subaction]]);
			} else {
				call_user_func(self::$subactions[$this->subaction]);
			}
		}

		// Send the response.
		$this->respond();
	}

	/**
	 * A simple getter for all protected properties.
	 *
	 * This is meant to give read-only access to hooked functions.
	 *
	 * @param string $property The name of the property to get.
	 * @return mixed Either return the property or false if there isn't a
	 *    property with that name.
	 */
	public function get($property = ''): mixed
	{
		return property_exists($this, $property) ? $this->$property : false;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Wrapper for constructor. Ensures only one instance is created.
	 *
	 * @return An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets the basic data needed for the rest of the process.
	 * Protected to force instantiation via load().
	 */
	protected function __construct()
	{
		if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === '_count') {
			$_REQUEST['sa'] = 'count';
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		$this->type = $_GET['ltype'] ?? '';
		$this->content = (int) ($_GET['like'] ?? 0);
		$this->js = isset($_GET['js']);
		$this->extra = $_GET['extra'] ?? false;

		// We do not want to output debug information here.
		if ($this->js) {
			Config::$db_show_debug = false;
		}
	}

	/**
	 * Performs basic checks on the data provided, checks for a valid msg like.
	 *
	 * Calls integrate_valid_likes hook for retrieving all the data needed and
	 * apply checks based on the data provided.
	 */
	protected function check(): void
	{
		// This feature is currently disable.
		if (empty(Config::$modSettings['enable_likes'])) {
			$this->error = 'like_disable';

			return;
		}

		// Zerothly, they did indicate some kind of content to like, right?
		preg_match('~^([a-z0-9\-\_]{1,6})~i', $this->type, $matches);

		$this->type = $matches[1] ?? '';

		if ($this->type == '' || $this->content <= 0) {
			$this->error = 'cannot_';

			return;
		}

		// First we need to verify whether the user can see the type of content.
		// This is set up to be extensible, so we'll check for the one type we
		// do know about, and if it's not that, we'll defer to any hooks.
		if ($this->type == 'msg') {
			// So we're doing something off a like. We need to verify that it
			// exists, and that the current user can see it. Fortunately, this
			// is quite easy to do for messages - and we'll get the topic ID
			// while we're at it, because we need it later for other things.
			$request = Db::$db->query(
				'',
				'SELECT m.id_topic, m.id_member
				FROM {db_prefix}messages AS m
				WHERE {query_see_message_board}
					AND m.id_msg = {int:msg}',
				[
					'msg' => $this->content,
				],
			);

			if (Db::$db->num_rows($request) == 1) {
				list($this->id_topic, $topicOwner) = Db::$db->fetch_row($request);
			}
			Db::$db->free_result($request);

			if (empty($this->id_topic)) {
				$this->error = 'cannot_';

				return;
			}

			// So we know what topic it's in and more importantly we know the
			// user can see it. If we're not viewing, we need some info set up.
			$this->valid_likes['type'] = 'msg';
			$this->valid_likes['flush_cache'] = 'likes_topic_' . $this->id_topic . '_' . User::$me->id;
			$this->valid_likes['redirect'] = 'topic=' . $this->id_topic . '.msg' . $this->content . '#msg' . $this->content;

			$this->valid_likes['can_like'] = (User::$me->id == $topicOwner ? 'cannot_like_content' : (User::$me->allowedTo('likes_like') ? true : 'cannot_like_content'));
		} else {
			/*
			 * MOD AUTHORS: This will give you whatever the user offers up in
			 * terms of liking, e.g. $this->type=msg, $this->content=1.
			 *
			 * When you hook this, check $this->type first. If it is not
			 * something your mod worries about, return false.
			 *
			 * Otherwise, return an array according to the documentation for
			 * $this->valid_likes. Determine (however you need to) that the user
			 * can see and can_like the relevant liked content (and it exists).
			 * Remember that users can't like their own content.
			 *
			 * If the user can like it, you MUST return your type in the 'type'
			 * key of the returned array.
			 *
			 * See also issueLike() for further notes.
			 */
			$can_like = IntegrationHook::call('integrate_valid_likes', [$this->type, $this->content, $this->subaction, $this->js, $this->extra]);

			$found = false;

			if (!empty($can_like)) {
				$can_like = (array) $can_like;

				foreach ($can_like as $result) {
					if ($result !== false) {
						// Match the type with what we already have.
						if (!isset($result['type']) || $result['type'] != $this->type) {
							$this->error = 'not_valid_like_type';

							return;
						}

						// Fill out the rest.
						$this->type = $result['type'];
						$this->valid_likes = array_merge($this->valid_likes, $result);

						$found = true;
						break;
					}
				}
			}

			if (!$found) {
				$this->error = 'cannot_';

				return;
			}
		}

		// Is the user able to like this?
		// Viewing a list of likes doesn't require this permission.
		if ($this->subaction != 'view' && isset($this->valid_likes['can_like']) && is_string($this->valid_likes['can_like'])) {
			$this->error = $this->valid_likes['can_like'];

			return;
		}
	}

	/**
	 * Deletes an entry from user_likes table.
	 */
	protected function delete(): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
				AND id_member = {int:id_member}',
			[
				'like_content' => $this->content,
				'like_type' => $this->type,
				'id_member' => User::$me->id,
			],
		);

		// Are we calling this directly? If so, set the data for the response.
		if ($this->subaction == __FUNCTION__) {
			$this->data = __FUNCTION__;
		}

		// Check to see if there is an unread alert to delete as well...
		Alert::deleteWhere(
			[
				'content_id = {int:like_content}',
				'content_type = {string:like_type}',
				'id_member_started = {int:id_member_started}',
				'content_action = {string:content_action}',
				'is_read = {int:unread}',
			],
			[
				'like_content' => $this->content,
				'like_type' => $this->type,
				'id_member_started' => User::$me->id,
				'content_action' => 'like',
				'unread' => 0,
			],
		);
	}

	/**
	 * Inserts a new entry on user_likes table.
	 * Creates a background task for the inserted entry.
	 */
	protected function insert(): void
	{
		// Any last minute changes? Temporarily turn the passed properties to
		// normal vars to prevent unexpected behaviour with other methods using
		// these properties.
		$type = $this->type;
		$content = $this->content;
		$user = (array) User::$me;
		$time = time();

		IntegrationHook::call('integrate_issue_like_before', [&$type, &$content, &$user, &$time]);

		// Insert the like.
		Db::$db->insert(
			'insert',
			'{db_prefix}user_likes',
			[
				'content_id' => 'int',
				'content_type' => 'string-6',
				'id_member' => 'int',
				'like_time' => 'int',
			],
			[
				$content,
				$type,
				$user['id'],
				$time,
			],
			[
				'content_id',
				'content_type',
				'id_member',
			],
		);

		// Add a background task to process sending alerts.
		// MOD AUTHORS: you can add your own background task for your own custom
		// like event using the "integrate_issue_like" hook or your callback,
		// both are immediately called after this.
		if ($this->type == 'msg') {
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\Likes_Notify',
					Utils::jsonEncode([
						'content_id' => $content,
						'content_type' => $type,
						'sender_id' => $user['id'],
						'sender_name' => $user['name'],
						'time' => $time,
					]),
					0,
				],
				['id_task'],
			);
		}

		// Are we calling this directly? If so, set the data for the response.
		if ($this->subaction == __FUNCTION__) {
			$this->data = __FUNCTION__;
		}
	}

	/**
	 * Sets $this->num_likes to the actual number of likes that the content has.
	 */
	protected function count(): void
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}',
			[
				'like_content' => $this->content,
				'like_type' => $this->type,
			],
		);
		list($this->num_likes) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($this->subaction == __FUNCTION__) {
			$this->data = $this->num_likes;
		}
	}

	/**
	 * Performs a like action, either like or unlike.
	 *
	 * Counts the total of likes and calls a hook after the event.
	 */
	protected function like(): void
	{
		// Safety first!
		if (empty($this->type) || empty($this->content)) {
			$this->error = 'cannot_';

			return;
		}

		// Do we already like this?
		$request = Db::$db->query(
			'',
			'SELECT content_id, content_type, id_member
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
				AND id_member = {int:id_member}',
			[
				'like_content' => $this->content,
				'like_type' => $this->type,
				'id_member' => User::$me->id,
			],
		);
		$this->already_liked = Db::$db->num_rows($request) != 0;
		Db::$db->free_result($request);

		if ($this->already_liked) {
			$this->delete();
		} else {
			$this->insert();
		}

		// Now, how many people like this content now? We *could* just +1 / -1
		// the relevant container but that has proven to become unstable.
		$this->count();

		// Update the likes count for messages.
		if ($this->type == 'msg') {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET likes = {int:num_likes}
				WHERE id_msg = {int:id_msg}',
				[
					'id_msg' => $this->content,
					'num_likes' => $this->num_likes,
				],
			);
		}
		// Any callbacks?
		elseif (!empty($this->valid_likes['callback'])) {
			$call = Utils::getCallable($this->valid_likes['callback']);

			if (!empty($call)) {
				call_user_func_array($call, [$this]);
			}
		}

		// Sometimes there might be other things that need updating after we do this like.
		IntegrationHook::call('integrate_issue_like', [$this]);

		// Now some clean up. This is provided here for any like handlers that
		// want to do any cache flushing.
		// This way a like handler doesn't need to explicitly declare anything
		// in integrate_issue_like, but do so in integrate_valid_likes where it
		// absolutely has to exist.
		if (!empty($this->valid_likes['flush_cache'])) {
			CacheApi::put($this->valid_likes['flush_cache'], null);
		}

		// All done, start building the data to pass as response.
		$this->data = [
			'id_topic' => !empty($this->id_topic) ? $this->id_topic : 0,
			'id_content' => $this->content,
			'count' => $this->num_likes,
			'can_like' => $this->valid_likes['can_like'],
			'already_liked' => empty($this->already_liked),
			'type' => $this->type,
		];
	}

	/**
	 * This is for viewing the people who liked a thing.
	 *
	 * Accessed from index.php?action=likes;view and should generally load in a
	 * popup.
	 *
	 * We use a template for this in case themers want to style it.
	 */
	protected function view(): void
	{
		// Firstly, load what we need. We already know we can see this, so that's something.
		Utils::$context['likers'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, like_time
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
			ORDER BY like_time DESC',
			[
				'like_content' => $this->content,
				'like_type' => $this->type,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['likers'][$row['id_member']] = ['timestamp' => $row['like_time']];
		}
		Db::$db->free_result($request);

		// Now to get member data, including avatars and so on.
		$members = array_keys(Utils::$context['likers']);
		$loaded = User::load($members);

		if (count($loaded) != count($members)) {
			$members = array_diff($members, array_map(fn ($member) => $member->id, $loaded));

			foreach ($members as $not_loaded) {
				unset(Utils::$context['likers'][$not_loaded]);
			}
		}

		foreach (Utils::$context['likers'] as $liker => $dummy) {
			if (!isset(User::$loaded[$liker])) {
				unset(Utils::$context['likers'][$liker]);

				continue;
			}

			Utils::$context['likers'][$liker]['profile'] = User::$loaded[$liker]->format();
			Utils::$context['likers'][$liker]['time'] = !empty($dummy['timestamp']) ? Time::create('@' . $dummy['timestamp'])->format() : '';
		}

		$count = count(Utils::$context['likers']);
		$title_base = isset(Lang::$txt['likes_' . $count]) ? 'likes_' . $count : 'likes_n';
		Utils::$context['page_title'] = strip_tags(sprintf(Lang::$txt[$title_base], '', Lang::numberFormat($count)));

		// Lastly, setting up for display.
		Theme::loadTemplate('Likes');
		Lang::load('Help'); // For the close window button.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'popup';

		// We already took care of our response so there is no need to bother with respond().
		$this->set_response = false;
	}

	/**
	 * Checks if the user can use JavaScript and acts accordingly.
	 * Calls the appropriate sub-template for each method
	 * Handles error messages.
	 */
	protected function respond(): void
	{
		// Don't do anything if someone else has already take care of the response.
		if (!$this->set_response) {
			return;
		}

		// Want a JSON response, do they?
		if ($this->valid_likes['json']) {
			$this->sendJsonReponse();

			return;
		}

		// Set everything up for display.
		Theme::loadTemplate('Likes');
		Utils::$context['template_layers'] = [];

		// If there are any errors, process them first.
		if ($this->error) {
			// If this is a generic error, set it up good.
			if ($this->error == 'cannot_') {
				$this->error = $this->subaction == 'view' ? 'cannot_view_likes' : 'cannot_like_content';
			}

			// Is this request coming from an AJAX call?
			if ($this->js) {
				Utils::$context['sub_template'] = 'generic';
				Utils::$context['data'] = Lang::$txt[$this->error] ?? Lang::$txt['like_error'];
			}
			// Nope? Then just do a redirect to whatever URL was provided.
			else {
				Utils::redirectexit(!empty($this->valid_likes['redirect']) ? $this->valid_likes['redirect'] . ';error=' . $this->error : '');
			}

			return;
		}

		// A like operation.

		// Not an AJAX request so send the user back to the previous
		// location or the main page.
		if (!$this->js) {
			Utils::redirectexit(!empty($this->valid_likes['redirect']) ? $this->valid_likes['redirect'] : '');
		}

		// These fine gentlemen all share the same template.
		$generic = ['delete', 'insert', 'count'];

		if (in_array($this->subaction, $generic)) {
			Utils::$context['sub_template'] = 'generic';
			Utils::$context['data'] = Lang::$txt['like_' . $this->data] ?? $this->data;
		}
		// Directly pass the current called sub-action and the data
		// generated by its associated Method.
		else {
			Utils::$context['sub_template'] = $this->subaction;
			Utils::$context['data'] = $this->data;
		}
	}

	/**
	 * Outputs a JSON-encoded response.
	 */
	protected function sendJsonReponse(): void
	{
		$print = [
			'data' => $this->data,
		];

		// If there is an error, send it.
		if ($this->error) {
			if ($this->error == 'cannot_') {
				$this->error = $this->subaction == 'view' ? 'cannot_view_likes' : 'cannot_like_content';
			}

			$print['error'] = $this->error;
		}

		// Do you want to add something at the very last minute?
		IntegrationHook::call('integrate_likes_json_response', [&$print]);

		// Print the data.
		Utils::serverResponse(Utils::jsonEncode($print));

		die;
	}

	/***************************
	 * Mysterious static methods
	 ***************************/

	/**
	 * What's this? I dunno, what are you talking about? Never seen this before, nope. No sir.
	 */
	public static function BookOfUnknown()
	{
		echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>The Book of Unknown, ', @$_GET['verse'] == '2:18' ? '2:18' : '4:16', '</title>
		<style>
			em
			{
				font-size: 1.3em;
				line-height: 0;
			}
		</style>
	</head>
	<body style="background-color: #444455; color: white; font-style: italic; font-family: serif;">
		<div style="margin-top: 12%; font-size: 1.1em; line-height: 1.4; text-align: center;">';

		if (!isset($_GET['verse']) || ($_GET['verse'] != '2:18' && $_GET['verse'] != '22:1-2')) {
			$_GET['verse'] = '4:16';
		}

		if ($_GET['verse'] == '2:18') {
			echo '
			Woe, it was that his name wasn\'t <em>known</em>, that he came in mystery, and was recognized by none.&nbsp;And it became to be in those days <em>something</em>.&nbsp; Something not yet <em id="unknown" name="[Unknown]">unknown</em> to mankind.&nbsp; And thus what was to be known the <em>secret project</em> began into its existence.&nbsp; Henceforth the opposition was only <em>weary</em> and <em>fearful</em>, for now their match was at arms against them.';
		} elseif ($_GET['verse'] == '4:16') {
			echo '
			And it came to pass that the <em>unbelievers</em> dwindled in number and saw rise of many <em>proselytizers</em>, and the opposition found fear in the face of the <em>x</em> and the <em>j</em> while those who stood with the <em>something</em> grew stronger and came together.&nbsp; Still, this was only the <em>beginning</em>, and what lay in the future was <em id="unknown" name="[Unknown]">unknown</em> to all, even those on the right side.';
		} elseif ($_GET['verse'] == '22:1-2') {
			echo '
			<p>Now <em>behold</em>, that which was once the secret project was <em id="unknown" name="[Unknown]">unknown</em> no longer.&nbsp; Alas, it needed more than <em>only one</em>, but yet even thought otherwise.&nbsp; It became that the opposition <em>rumored</em> and lied, but still to no avail.&nbsp; Their match, though not <em>perfect</em>, had them outdone.</p>
			<p style="margin: 2ex 1ex 0 1ex; font-size: 1.05em; line-height: 1.5; text-align: center;">Let it continue.&nbsp; <em>The end</em>.</p>';
		}

		echo '
		</div>
		<div style="margin-top: 2ex; font-size: 2em; text-align: right;">';

		if ($_GET['verse'] == '2:18') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', Config::$scripturl, '?action=about:unknown;verse=4:16" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 2:18</span>';
		} elseif ($_GET['verse'] == '4:16') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', Config::$scripturl, '?action=about:unknown;verse=22:1-2" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 4:16</span>';
		} elseif ($_GET['verse'] == '22:1-2') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong>The Book of Unknown</strong>, 22:1-2</span>';
		}

		echo '
		</div>
	</body>
</html>';

		Utils::obExit(false);
	}
}

?>