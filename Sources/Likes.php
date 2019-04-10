<?php

/**
 * This file contains liking posts and displaying the list of who liked a post.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Class Likes
 */
class Likes
{
	/**
	 * @var boolean Know if a request comes from an ajax call or not, depends on $_GET['js'] been set.
	 */
	protected $_js = false;

	/**
	 * @var string If filled, its value will contain a string matching a key on a language var $txt[$this->_error]
	 */
	protected $_error = false;

	/**
	 * @var string The unique type to like, needs to be unique and it needs to be no longer than 6 characters, only numbers and letters are allowed.
	 */
	protected $_type = '';

	/**
	 * @var string A generic string used if you need to pass any extra info. It gets set via $_GET['extra'].
	 */
	protected $_extra = false;

	/**
	 * @var integer a valid ID to identify your like content.
	 */
	protected $_content = 0;

	/**
	 * @var integer The number of times your content has been liked.
	 */
	protected $_numLikes = 0;

	/**
	 * @var boolean If the current user has already liked this content.
	 */
	protected $_alreadyLiked = false;

	/**
	 * @var array $_validLikes mostly used for external integration, needs to be filled as an array with the following keys:
	 * => 'can_like' boolean|string whether or not the current user can actually like your content.
	 * for can_like: Return a boolean true if the user can, otherwise return a string, the string will be used as key in a regular $txt language error var. The code assumes you already loaded your language file. If no value is returned or the $txt var isn't set, the code will use a generic error message.
	 * => 'redirect' string To add support for non JS users, It is highly encouraged to set a valid URL to redirect the user to, if you don't provide any, the code will redirect the user to the main page. The code only performs a light check to see if the redirect is valid so be extra careful while building it.
	 * => 'type' string 6 letters or numbers. The unique identifier for your content, the code doesn't check for duplicate entries, if there are 2 or more exact hook calls, the code will take the first registered one so make sure you provide a unique identifier. Must match with what you sent in $_GET['ltype'].
	 * => 'flush_cache' boolean this is optional, it tells the code to reset your like content's cache entry after a new entry has been inserted.
	 * => 'callback' callable optional, useful if you don't want to issue a separate hook for updating your data, it is called immediately after the data was inserted or deleted and before the actual hook. Uses call_helper(); so the same format for your function/method can be applied here.
	 * => 'json' boolean optional defaults to false, if true the Like class will return a json object as response instead of HTML.
	 */
	protected $_validLikes = array(
		'can_like' => false,
		'redirect' => '',
		'type' => '',
		'flush_cache' => '',
		'callback' => false,
		'json' => false,
	);

	/**
	 * @var array The current user info ($user_info).
	 */
	protected $_user;

	/**
	 * @var integer The topic ID, used for liking messages.
	 */
	protected $_idTopic = 0;

	/**
	 * @var boolean to know if response(); will be executed as normal. If this is set to false it indicates the method already solved its own way to send back a response.
	 */
	protected $_setResponse = true;

	/**
	 * Likes::__construct()
	 *
	 * Sets the basic data needed for the rest of the process.
	 */
	public function __construct()
	{
		global $db_show_debug;

		$this->_type = isset($_GET['ltype']) ? $_GET['ltype'] : '';
		$this->_content = isset($_GET['like']) ? (int) $_GET['like'] : 0;
		$this->_js = isset($_GET['js']) ? true : false;
		$this->_sa = isset($_GET['sa']) ? $_GET['sa'] : 'like';
		$this->_extra = isset($_GET['extra']) ? $_GET['extra'] : false;

		// We do not want to output debug information here.
		if ($this->_js)
			$db_show_debug = false;
	}

	/**
	 * Likes::call()
	 *
	 * The main handler. Verifies permissions (whether the user can see the content in question), dispatch different method for different sub-actions.
	 * Accessed from index.php?action=likes
	 */
	public function call()
	{
		global $context;

		$this->_user = $context['user'];

		// Make sure the user can see and like your content.
		$this->check();

		$subActions = array(
			'like',
			'view',
			'delete',
			'insert',
			'_count',
		);

		// So at this point, whatever type of like the user supplied and the item of content in question,
		// we know it exists, now we need to figure out what we're doing with that.
		if (in_array($this->_sa, $subActions) && !is_string($this->_error))
		{
			// To avoid ambiguity, turn the property to a normal var.
			$call = $this->_sa;

			// Guest can only view likes.
			if ($call != 'view')
				is_not_guest();

			checkSession('get');

			// Call the appropriate method.
			$this->$call();
		}

		// else An error message.
		$this->response();
	}

	/**
	 * Likes::get()
	 *
	 * A simple getter for all protected properties.
	 * Accessed from index.php?action=likes
	 *
	 * @param string $property The name of the property to get.
	 * @return mixed Either return the property or false if there isn't a property with that name.
	 */
	public function get($property = '')
	{
		// All properties inside Likes are protected, thus, an underscore is used.
		$property = '_' . $property;
		return property_exists($this, $property) ? $this->$property : false;
	}

	/**
	 * Likes::check()
	 *
	 * Performs basic checks on the data provided, checks for a valid msg like.
	 * Calls integrate_valid_likes hook for retrieving all the data needed and apply checks based on the data provided.
	 */
	protected function check()
	{
		global $smcFunc, $modSettings;

		// This feature is currently disable.
		if (empty($modSettings['enable_likes']))
			return $this->_error = 'like_disable';

		// Zerothly, they did indicate some kind of content to like, right?
		preg_match('~^([a-z0-9\-\_]{1,6})~i', $this->_type, $matches);
		$this->_type = isset($matches[1]) ? $matches[1] : '';

		if ($this->_type == '' || $this->_content <= 0)
			return $this->_error = 'cannot_';

		// First we need to verify if the user can see the type of content or not. This is set up to be extensible,
		// so we'll check for the one type we do know about, and if it's not that, we'll defer to any hooks.
		if ($this->_type == 'msg')
		{
			// So we're doing something off a like. We need to verify that it exists, and that the current user can see it.
			// Fortunately for messages, this is quite easy to do - and we'll get the topic id while we're at it, because
			// we need this later for other things.
			$request = $smcFunc['db_query']('', '
				SELECT m.id_topic, m.id_member
				FROM {db_prefix}messages AS m
				WHERE {query_see_message_board}
					AND m.id_msg = {int:msg}',
				array(
					'msg' => $this->_content,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 1)
				list ($this->_idTopic, $topicOwner) = $smcFunc['db_fetch_row']($request);

			$smcFunc['db_free_result']($request);
			if (empty($this->_idTopic))
				return $this->_error = 'cannot_';

			// So we know what topic it's in and more importantly we know the user can see it.
			// If we're not viewing, we need some info set up.
			$this->_validLikes['type'] = 'msg';
			$this->_validLikes['flush_cache'] = 'likes_topic_' . $this->_idTopic . '_' . $this->_user['id'];
			$this->_validLikes['redirect'] = 'topic=' . $this->_idTopic . '.msg' . $this->_content . '#msg' . $this->_content;

			$this->_validLikes['can_like'] = ($this->_user['id'] == $topicOwner ? 'cannot_like_content' : (allowedTo('likes_like') ? true : 'cannot_like_content'));
		}

		else
		{
			// Modders: This will give you whatever the user offers up in terms of liking, e.g. $this->_type=msg, $this->_content=1
			// When you hook this, check $this->_type first. If it is not something your mod worries about, return false.
			// Otherwise, fill an array according to the docs for $this->_validLikes. Determine (however you need to) that the user can see and can_like the relevant liked content (and it exists) Remember that users can't like their own content.
			// If the user can like it, you MUST return your type in the 'type' key back.
			// See also issueLike() for further notes.
			$can_like = call_integration_hook('integrate_valid_likes', array($this->_type, $this->_content, $this->_sa, $this->_js, $this->_extra));

			$found = false;
			if (!empty($can_like))
			{
				$can_like = (array) $can_like;
				foreach ($can_like as $result)
				{
					if ($result !== false)
					{
						// Match the type with what we already have.
						if (!isset($result['type']) || $result['type'] != $this->_type)
							return $this->_error = 'not_valid_like_type';

						// Fill out the rest.
						$this->_type = $result['type'];
						$this->_validLikes = array_merge($this->_validLikes, $result);
						$found = true;
						break;
					}
				}
			}

			if (!$found)
				return $this->_error = 'cannot_';
		}

		// Does the user can like this? Viewing a list of likes doesn't require this permission.
		if ($this->_sa != 'view' && isset($this->_validLikes['can_like']) && is_string($this->_validLikes['can_like']))
			return $this->_error = $this->_validLikes['can_like'];
	}

	/**
	 * Likes::delete()
	 *
	 * Deletes an entry from user_likes table, needs 3 properties: $_content, $_type and $_user['id'].
	 */
	protected function delete()
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
				AND id_member = {int:id_member}',
			array(
				'like_content' => $this->_content,
				'like_type' => $this->_type,
				'id_member' => $this->_user['id'],
			)
		);

		// Are we calling this directly? if so, set a proper data for the response. Do note that __METHOD__ returns both the class name and the function name.
		if ($this->_sa == __FUNCTION__)
			$this->_data = __FUNCTION__;
	}

	/**
	 * Likes::insert()
	 *
	 * Inserts a new entry on user_likes table. Creates a background task for the inserted entry.
	 */
	protected function insert()
	{
		global $smcFunc;

		// Any last minute changes? Temporarily turn the passed properties to normal vars to prevent unexpected behaviour with other methods using these properties.
		$type = $this->_type;
		$content = $this->_content;
		$user = $this->_user;
		$time = time();

		call_integration_hook('integrate_issue_like_before', array(&$type, &$content, &$user, &$time));

		// Insert the like.
		$smcFunc['db_insert']('insert',
			'{db_prefix}user_likes',
			array('content_id' => 'int', 'content_type' => 'string-6', 'id_member' => 'int', 'like_time' => 'int'),
			array($content, $type, $user['id'], $time),
			array('content_id', 'content_type', 'id_member')
		);

		// Add a background task to process sending alerts.
		// Mod author, you can add your own background task for your own custom like event using the "integrate_issue_like" hook or your callback, both are immediately called after this.
		if ($this->_type == 'msg')
			$smcFunc['db_insert']('insert',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/Likes-Notify.php', 'Likes_Notify_Background', $smcFunc['json_encode'](array(
					'content_id' => $content,
					'content_type' => $type,
					'sender_id' => $user['id'],
					'sender_name' => $user['name'],
					'time' => $time,
				)), 0),
				array('id_task')
			);

		// Are we calling this directly? if so, set a proper data for the response. Do note that __METHOD__ returns both the class name and the function name.
		if ($this->_sa == __FUNCTION__)
			$this->_data = __FUNCTION__;
	}

	/**
	 * Likes::_count()
	 *
	 * Sets $_numLikes with the actual number of likes your content has, needs two properties: $_content and $_view. When called directly it will return the number of likes as response.
	 */
	protected function _count()
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_member)
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}',
			array(
				'like_content' => $this->_content,
				'like_type' => $this->_type,
			)
		);
		list ($this->_numLikes) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// If you want to call this directly, fill out _data property too.
		if ($this->_sa == __FUNCTION__)
			$this->_data = $this->_numLikes;
	}

	/**
	 * Likes::like()
	 *
	 * Performs a like action, either like or unlike. Counts the total of likes and calls a hook after the event.
	 */
	protected function like()
	{
		global $smcFunc;

		// Safety first!
		if (empty($this->_type) || empty($this->_content))
			return $this->_error = 'cannot_';

		// Do we already like this?
		$request = $smcFunc['db_query']('', '
			SELECT content_id, content_type, id_member
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
				AND id_member = {int:id_member}',
			array(
				'like_content' => $this->_content,
				'like_type' => $this->_type,
				'id_member' => $this->_user['id'],
			)
		);
		$this->_alreadyLiked = (bool) $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		if ($this->_alreadyLiked)
			$this->delete();

		else
			$this->insert();

		// Now, how many people like this content now? We *could* just +1 / -1 the relevant container but that has proven to become unstable.
		$this->_count();

		// Update the likes count for messages.
		if ($this->_type == 'msg')
			$this->msgIssueLike();

		// Any callbacks?
		elseif (!empty($this->_validLikes['callback']))
		{
			$call = call_helper($this->_validLikes['callback'], true);

			if (!empty($call))
				call_user_func_array($call, array($this));
		}

		// Sometimes there might be other things that need updating after we do this like.
		call_integration_hook('integrate_issue_like', array($this));

		// Now some clean up. This is provided here for any like handlers that want to do any cache flushing.
		// This way a like handler doesn't need to explicitly declare anything in integrate_issue_like, but do so
		// in integrate_valid_likes where it absolutely has to exist.
		if (!empty($this->_validLikes['flush_cache']))
			cache_put_data($this->_validLikes['flush_cache'], null);

		// All done, start building the data to pass as response.
		$this->_data = array(
			'id_topic' => !empty($this->_idTopic) ? $this->_idTopic : 0,
			'id_content' => $this->_content,
			'count' => $this->_numLikes,
			'can_like' => $this->_validLikes['can_like'],
			'already_liked' => empty($this->_alreadyLiked),
			'type' => $this->_type,
		);
	}

	/**
	 * Likes::msgIssueLike()
	 *
	 * Partly it indicates how it's supposed to work and partly it deals with updating the count of likes
	 * attached to this message now.
	 */
	function msgIssueLike()
	{
		global $smcFunc;

		if ($this->_type !== 'msg')
			return;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET likes = {int:num_likes}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $this->_content,
				'num_likes' => $this->_numLikes,
			)
		);

		// Note that we could just as easily have cleared the cache here, or set up the redirection address
		// but if your liked content doesn't need to do anything other than have the record in smf_user_likes,
		// there's no point in creating another function unnecessarily.
	}

	/**
	 * Likes::view()
	 *
	 * This is for viewing the people who liked a thing.
	 * Accessed from index.php?action=likes;view and should generally load in a popup.
	 * We use a template for this in case themers want to style it.
	 */
	function view()
	{
		global $smcFunc, $txt, $context, $memberContext;

		// Firstly, load what we need. We already know we can see this, so that's something.
		$context['likers'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, like_time
			FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
			ORDER BY like_time DESC',
			array(
				'like_content' => $this->_content,
				'like_type' => $this->_type,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['likers'][$row['id_member']] = array('timestamp' => $row['like_time']);

		// Now to get member data, including avatars and so on.
		$members = array_keys($context['likers']);
		$loaded = loadMemberData($members);
		if (count($loaded) != count($members))
		{
			$members = array_diff($members, $loaded);
			foreach ($members as $not_loaded)
				unset ($context['likers'][$not_loaded]);
		}

		foreach ($context['likers'] as $liker => $dummy)
		{
			$loaded = loadMemberContext($liker);
			if (!$loaded)
			{
				unset ($context['likers'][$liker]);
				continue;
			}

			$context['likers'][$liker]['profile'] = &$memberContext[$liker];
			$context['likers'][$liker]['time'] = !empty($dummy['timestamp']) ? timeformat($dummy['timestamp']) : '';
		}

		$count = count($context['likers']);
		$title_base = isset($txt['likes_' . $count]) ? 'likes_' . $count : 'likes_n';
		$context['page_title'] = strip_tags(sprintf($txt[$title_base], '', comma_format($count)));

		// Lastly, setting up for display.
		loadTemplate('Likes');
		loadLanguage('Help'); // For the close window button.
		$context['template_layers'] = array();
		$context['sub_template'] = 'popup';

		// We already took care of our response so there is no need to bother with respond();
		$this->_setResponse = false;
	}

	/**
	 * Likes::response()
	 *
	 * Checks if the user can use JavaScript and acts accordingly.
	 * Calls the appropriate sub-template for each method
	 * Handles error messages.
	 */
	protected function response()
	{
		global $context, $txt;

		// Don't do anything if someone else has already take care of the response.
		if (!$this->_setResponse)
			return;

		// Want a json response huh?
		if ($this->_validLikes['json'])
			return $this->jsonResponse();

		// Set everything up for display.
		loadTemplate('Likes');
		$context['template_layers'] = array();

		// If there are any errors, process them first.
		if ($this->_error)
		{
			// If this is a generic error, set it up good.
			if ($this->_error == 'cannot_')
				$this->_error = $this->_sa == 'view' ? 'cannot_view_likes' : 'cannot_like_content';

			// Is this request coming from an ajax call?
			if ($this->_js)
			{
				$context['sub_template'] = 'generic';
				$context['data'] = isset($txt[$this->_error]) ? $txt[$this->_error] : $txt['like_error'];
			}

			// Nope?  then just do a redirect to whatever URL was provided.
			else
				redirectexit(!empty($this->_validLikes['redirect']) ? $this->_validLikes['redirect'] . ';error=' . $this->_error : '');

			return;
		}

		// A like operation.
		else
		{
			// Not an ajax request so send the user back to the previous location or the main page.
			if (!$this->_js)
				redirectexit(!empty($this->_validLikes['redirect']) ? $this->_validLikes['redirect'] : '');

			// These fine gentlemen all share the same template.
			$generic = array('delete', 'insert', '_count');
			if (in_array($this->_sa, $generic))
			{
				$context['sub_template'] = 'generic';
				$context['data'] = isset($txt['like_' . $this->_data]) ? $txt['like_' . $this->_data] : $this->_data;
			}

			// Directly pass the current called sub-action and the data generated by its associated Method.
			else
			{
				$context['sub_template'] = $this->_sa;
				$context['data'] = $this->_data;
			}
		}
	}

	/**
	 * Outputs a JSON-encoded response
	 */
	protected function jsonResponse()
	{
		global $smcFunc;

		$print = array(
			'data' => $this->_data,
		);

		// If there is an error, send it.
		if ($this->_error)
		{
			if ($this->_error == 'cannot_')
				$this->_error = $this->_sa == 'view' ? 'cannot_view_likes' : 'cannot_like_content';

			$print['error'] = $this->_error;
		}

		// Do you want to add something at the very last minute?
		call_integration_hook('integrate_likes_json_response', array(&$print));

		// Print the data.
		smf_serverResponse($smcFunc['json_encode']($print));
		die;
	}
}

/**
 * What's this?  I dunno, what are you talking about?  Never seen this before, nope.  No sir.
 */
function BookOfUnknown()
{
	global $context, $scripturl;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
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

	if (!isset($_GET['verse']) || ($_GET['verse'] != '2:18' && $_GET['verse'] != '22:1-2'))
		$_GET['verse'] = '4:16';

	if ($_GET['verse'] == '2:18')
		echo '
			Woe, it was that his name wasn\'t <em>known</em>, that he came in mystery, and was recognized by none.&nbsp;And it became to be in those days <em>something</em>.&nbsp; Something not yet <em id="unknown" name="[Unknown]">unknown</em> to mankind.&nbsp; And thus what was to be known the <em>secret project</em> began into its existence.&nbsp; Henceforth the opposition was only <em>weary</em> and <em>fearful</em>, for now their match was at arms against them.';
	elseif ($_GET['verse'] == '4:16')
		echo '
			And it came to pass that the <em>unbelievers</em> dwindled in number and saw rise of many <em>proselytizers</em>, and the opposition found fear in the face of the <em>x</em> and the <em>j</em> while those who stood with the <em>something</em> grew stronger and came together.&nbsp; Still, this was only the <em>beginning</em>, and what lay in the future was <em id="unknown" name="[Unknown]">unknown</em> to all, even those on the right side.';
	elseif ($_GET['verse'] == '22:1-2')
		echo '
			<p>Now <em>behold</em>, that which was once the secret project was <em id="unknown" name="[Unknown]">unknown</em> no longer.&nbsp; Alas, it needed more than <em>only one</em>, but yet even thought otherwise.&nbsp; It became that the opposition <em>rumored</em> and lied, but still to no avail.&nbsp; Their match, though not <em>perfect</em>, had them outdone.</p>
			<p style="margin: 2ex 1ex 0 1ex; font-size: 1.05em; line-height: 1.5; text-align: center;">Let it continue.&nbsp; <em>The end</em>.</p>';

	echo '
		</div>
		<div style="margin-top: 2ex; font-size: 2em; text-align: right;">';

	if ($_GET['verse'] == '2:18')
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', $scripturl, '?action=about:unknown;verse=4:16" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 2:18</span>';
	elseif ($_GET['verse'] == '4:16')
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', $scripturl, '?action=about:unknown;verse=22:1-2" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 4:16</span>';
	elseif ($_GET['verse'] == '22:1-2')
		echo '
			from <span style="font-family: Georgia, serif;"><strong>The Book of Unknown</strong>, 22:1-2</span>';

	echo '
		</div>
	</body>
</html>';

	obExit(false);
}

?>