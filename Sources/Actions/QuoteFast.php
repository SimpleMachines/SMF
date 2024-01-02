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

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class handles quoting posts via JavaScript.
 *
 * Loads a post an inserts it into the current editing text box.
 * - uses the Post language file.
 * - uses special (sadly browser dependent) javascript to parse entities for
 *   internationalization reasons.
 * - accessed with ?action=quotefast.
 */
class QuoteFast implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'QuoteFast',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		$moderate_boards = User::$me->boardsAllowedTo('moderate_board');

		$request = Db::$db->query(
			'',
			'SELECT COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body, m.id_topic, m.subject,
				m.id_board, m.id_member, m.approved, m.modified_time, m.modified_name, m.modified_reason
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE {query_see_message_board}
				AND m.id_msg = {int:id_msg}' . (isset($_REQUEST['modify']) || (!empty($moderate_boards) && $moderate_boards[0] == 0) ? '' : '
				AND (t.locked = {int:not_locked}' . (empty($moderate_boards) ? '' : ' OR m.id_board IN ({array_int:moderation_board_list})') . ')') . '
			LIMIT 1',
			[
				'current_member' => User::$me->id,
				'moderation_board_list' => $moderate_boards,
				'id_msg' => (int) $_REQUEST['quote'],
				'not_locked' => 0,
			],
		);
		Utils::$context['close_window'] = Db::$db->num_rows($request) == 0;
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		Utils::$context['sub_template'] = 'quotefast';

		if (!empty($row)) {
			$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == User::$me->id) || User::$me->allowedTo('approve_posts', $row['id_board']);
		}

		if (!empty($can_view_post)) {
			// Remove special formatting we don't want anymore.
			$row['body'] = Msg::un_preparsecode($row['body']);

			// Censor the message!
			Lang::censorText($row['body']);

			// Want to modify a single message by double clicking it?
			if (isset($_REQUEST['modify'])) {
				Lang::censorText($row['subject']);

				Utils::$context['sub_template'] = 'modifyfast';
				Utils::$context['message'] = [
					'id' => $_REQUEST['quote'],
					'body' => $row['body'],
					'subject' => addcslashes($row['subject'], '"'),
					'reason' => [
						'name' => $row['modified_name'],
						'text' => $row['modified_reason'],
						'time' => $row['modified_time'],
					],
				];

				return;
			}

			// Remove any nested quotes.
			if (!empty(Config::$modSettings['removeNestedQuotes'])) {
				$row['body'] = preg_replace(['~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'], '', $row['body']);
			}

			$lb = "\n";

			// Add a quote string on the front and end.
			Utils::$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . ']' . $lb . $row['body'] . $lb . '[/quote]';
			Utils::$context['quote']['text'] = strtr(Utils::htmlspecialcharsDecode(Utils::$context['quote']['xml']), ['\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>']);
			Utils::$context['quote']['xml'] = strtr(Utils::$context['quote']['xml'], ['&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;']);

			Utils::$context['quote']['mozilla'] = strtr(Utils::htmlspecialchars(Utils::$context['quote']['text']), ['&quot;' => '"']);
		}
		// @todo Needs a nicer interface.
		// In case our message has been removed in the meantime.
		elseif (isset($_REQUEST['modify'])) {
			Utils::$context['sub_template'] = 'modifyfast';
			Utils::$context['message'] = [
				'id' => 0,
				'body' => '',
				'subject' => '',
				'reason' => [
					'name' => '',
					'text' => '',
					'time' => '',
				],
			];
		} else {
			Utils::$context['quote'] = [
				'xml' => '',
				'mozilla' => '',
				'text' => '',
			];
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
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
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Lang::load('Post');

		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Post');
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\QuoteFast::exportStatic')) {
	QuoteFast::exportStatic();
}

?>