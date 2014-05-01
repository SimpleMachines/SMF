<?php

/**
 * This file contains liking posts and displaying the list of who liked a post.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

class Likes
{
	/**
	 *@var boolean Know if a request comes from an ajax call or not, depends on $_GET['js'] been set.
	 */
	protected $_js = false;

	/**
	 *@var string If filled, its value will contain a string matching a key on a language var $txt[$this->_error]
	 */
	protected $_error = false;

	/**
	 *@var string The unique type to like, needs to be unique and it needs to be no longer than 6 characters, only numbers and letters are allowed.
	 */
	protected $_type = '';

	/**
	 *@var integer a valid ID to identify your like content.
	 */
	protected $_content = 0;
	protected $_response = array();

	/**
	 *@var boolean Boolean value to know if the request is for handling likes or returning a list of users who liked your content.
	 */
	protected $_view = false;

	/**
	 *@var integer The number of times your content has been liked.
	 */
	protected $_numLikes = 0;

	/**
	 * @var array $_validLikes mostly used for external integration, needs to be filled as an array with the following keys:
	 * => 'can_see' boolean|string whether or not the current user can see the like.
	 * => 'can_like' boolean|string whether or not the current user can actually like your content.
	 * for both can_like and can_see: Return a boolean true if the user can, otherwise return a string, the string will be used as key in a regular $txt language error var. The code assumes you already loaded your language file. If no value is returned or the $txt var isn't set, the code will use a generic error message.
	 * => 'redirect' string To add support for non JS users, It is highly encouraged to set a valid url to redirect the user to, if you don't provide any, the code will redirect the user to the main page. The code only performs a light check to see if the redirect is valid so be extra careful while building it.
	 * => 'type' string 6 letters or numbers. The unique identifier for your content, the code doesn't check for duplicate entries, if there are 2 or more exact hook calls, the code will take the first registered one so make sure you provide a unique identifier. Must match with what you sent in $_GET['ltype'].
	 * => 'flush_cache' boolean this is optional, it tells the code to reset your like content's cache entry after a new entry has been inserted.
	 */
	protected $_validLikes = array(
		'can_see' => false,
		'can_like' => false,
		'redirect' => '',
		'type' => '',
		'flush_cache' => '',$smcFunc
	);

	/**
	 * @var array The current user.
	 */
	protected $_user;

	/**
	 * @var boolean to know if response(); will be executed as normal. If this is set to false it indicates the method already solved its own way to send back a response.
	 */
	protected $_setResponse = true;

	public function __construct()
	{
		$this->_type = isset($_GET['ltype']) ? $_GET['ltype'] : '';
		$this->_content = isset($_GET['like']) ? (int) $_GET['like'] : 0;
		$this->_js = isset($_GET['js']) ? true : false;
		$this->_sa = isset($_GET['sa']) ? $_GET['sa'] : 'like';
	}

	/**
	 * The main handler. Verifies permissions (whether the user can see the content in question)
	 * before either liking/unliking or spitting out the list of likers.
	 * Accessed from index.php?action=likes
	 */
	protected function call()
	{
		global $context, $smcFunc;

		$this->_user = $context['user'];

		// Make sure the user can see and like your content.
		$this->check();

		$subActions = array(
			'like',
			'view',
			'delete',
			'insert',
		);

		// So at this point, whatever type of like the user supplied and the item of content in question,
		// we know it exists, now we need to figure out what we're doing with that.
		if (isset($subActions[$this->_sa]) && empty($this->_error))
		{
			// To avoid ambiguity, turn the property to a normal var.
			$call = $this->_sa;

			// Guest can only view likes.
			if ($call != 'view')
				is_not_guest();

			checkSession('get');

			// Call the appropriate method.
			$this->$call();

			// Send the response back to the browser.
			$this->response();
		}

		// else An error message.
	}

	protected function check()
	{
		global $smcFunc, $context;

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
				SELECT m.id_topic
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE {query_see_board}
					AND m.id_msg = {int:msg}',
				array(
					'msg' => $this->_content,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 1)
				list ($id_topic) = $smcFunc['db_fetch_row']($request);

			$smcFunc['db_free_result']($request);
			if (empty($id_topic))
				return $this->_error = 'cannot_';

			// So we know what topic it's in and more importantly we know the user can see it.
			// If we're not viewing, we need some info set up.
			if (!$this->_view)
			{
				$this->_validLikes['flush_cache'] = 'likes_topic_' . $id_topic . '_' . $this->_user['id'];
				$this->_validLikes['redirect'] = 'topic=' . $id_topic . '.msg' . $this->_content . '#msg' . $this->_content;
				$this->_validLikes['can_see'] = true;
				$this->msgIssueLike();
			}
		}

		else
		{
			// Modders: This will give you whatever the user offers up in terms of liking, e.g. $this->_type=msg, $this->_content=1
			// When you hook this, check $this->_type first. If it is not something your mod worries about, return false.
			// Otherwise, fill an array according to the doc at $this->_validLikes. Determine (however you need to) that the user can see and can_like the relevant liked content (and it exists).
			// If the user cannot see it, return the appropriate key (can_see) as false. If the user can see it and can like it, you MUST return your type in the 'type' key back.
			// See also issueLike() for further notes.
			$can_like = call_integration_hook('integrate_valid_likes', array($this->_type, $this->_content));

			$found = false;
			if (!empty($can_like))
			{
				$can_like = (array) $can_like;
				foreach ($can_like as $result)
				{
					if ($result !== false)
					{
						// Does the user can see this?
						if (isset($result['can_see']) && is_string($result['can_see']))
							return $this->_error = $result['can_see'];

						// Does the user can like this?
						if (isset($result['can_like']) && is_string($result['can_like']))
							return $this->_error = $result['can_like'];

						// Match the type with what we already have.
						if (!isset($result['type']) || $result['type'] != $this->_type)
							return $this->_error = 'not_valid_like_type';

						// Fill out the rest.
						$this->_type = $result['type'];
						$this->_validLikes = $result;
						$found = true;
						break;
					}
				}
			}

			if (!$found)
				return $this->_error = 'cannot_';
		}
	}

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
	}

	protected function insert()
	{
		global $smcFunc;

		// Insert the like.
		$smcFunc['db_insert']('insert',
			'{db_prefix}user_likes',
			array('content_id' => 'int', 'content_type' => 'string-6', 'id_member' => 'int', 'like_time' => 'int'),
			array($this->_content, $this->_type, $this->_user['id'], time()),
			array('content_id', 'content_type', 'id_member')
		);

		// Add a background task to process sending alerts.
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/Likes-Notify.php', 'Likes_Notify_Background', serialize(array(
				'content_id' => $this->_content,
				'content_type' => $this->_type,
				'sender_id' => $this->_user['id'],
				'sender_name' => $this->_user['name'],
				'time' => time(),
			)), 0),
			array('id_task')
		);
	}

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
	}

	protected function like()
	{
		global $context, $smcFunc;

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
		$already_liked = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		if ($already_liked)
			$this->delete();

		else
			$this->insert();

		// Now, how many people like this content now? We *could* just +1 / -1 the relevant container but that has proven to become unstable.
		$this->_count();

		// Sometimes there might be other things that need updating after we do this like.
		call_integration_hook('integrate_issue_like', array($this->_type, $this->_content, $this->_numLikes));

		// Now some clean up. This is provided here for any like handlers that want to do any cache flushing.
		// This way a like handler doesn't need to explicitly declare anything in integrate_issue_like, but do so
		// in integrate_valid_likes where it absolutely has to exist.
		if (!empty($this->_validLikes['flush_cache']))
			cache_put_data($this->_validLikes['flush_cache'], null);
	}

	/**
	 * Callback attached to integrate_issue_like.
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

		// Lastly, setting up for display
		loadTemplate('Likes');
		loadLanguage('Help'); // for the close window button
		$context['template_layers'] = array();
		$context['sub_template'] = 'popup';

		// We already took care of our response so there is no need to bother with respond();
		$this->_setResponse = false;
	}

	protected function response()
	{
		global $context;

		// Don't do anything if someone else has already take care of the response.
		if (!$this->_setResponse)
			return;

		// Set everything up for display.
		loadTemplate('Likes');
		$context['template_layers'] = array();

		// If there are any errors, process them first.
		if ($this->_error)
		{
			// If this is a generic error, set it up good.
			if ($this->_error == 'cannot_';)
				$this->_error = $this->_view ? 'cannot_view_likes' : 'cannot_like_content';

			// Is this request coming from an ajax call?
			if ($this->_js)
			{
				$context['sub_template'] = 'error';
				$context['error'] = $this->_error;
			}

			// Nope?  then just do a redirect to whatever url was provided. add the error string only if they provided a valid url.
			else
				redirect(!empty($this->_validLikes['redirect']) ? $this->_validLikes['redirect'] .';error='. $this->_error : '');
		}

		// A like operation.
		else
		{
			// Not an ajax request so send the user back to the previous location or the main page.
			if (!$this->_js)
				redirect(!empty($this->_validLikes['redirect']) ? $this->_validLikes['redirect'] : '');
		}
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
		<style type="text/css">
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