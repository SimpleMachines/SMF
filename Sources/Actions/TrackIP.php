<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\BackwardCompatibility;

use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\ItemList;
use SMF\Profile;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class TrackIP implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'list_getIPMessages' => 'list_getIPMessages',
			'list_getIPMessageCount' => 'list_getIPMessageCount',
			'trackIP' => 'TrackIP',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int $memID
	 *
	 * ID of the member to track.
	 */
	public int $memID;

	/**
	 * @var bool $standalone
	 *
	 * True if this was called via ?action=trackip.
	 * False if this was called via ?action=profile;area=tracking;sa=ip.
	 */
	public bool $standalone;

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
	 * Do the job.
	 */
	public function execute(): void
	{
		// Can the user do this?
		User::$me->isAllowedTo('moderate_forum');

		if ($this->standalone)
		{
			Theme::loadTemplate('Profile');
			Lang::load('Profile');
			Utils::$context['base_url'] = Config::$scripturl . '?action=trackip';

			Utils::$context['ip'] = IP::ip2range(User::$me->ip);
		}
		else
		{
			Utils::$context['base_url'] = Config::$scripturl . '?action=profile;area=tracking;sa=ip;u=' . $this->memID;

			Utils::$context['ip'] = IP::ip2range(User::$loaded[$this->memID]->ip);
		}

		Utils::$context['sub_template'] = 'trackIP';

		// Searching?
		if (isset($_REQUEST['searchip']))
			Utils::$context['ip'] = IP::ip2range(trim($_REQUEST['searchip']));

		if (count(Utils::$context['ip']) !== 2)
			ErrorHandler::fatalLang('invalid_tracking_ip', false);

		$ip_string = array('{inet:ip_address_low}', '{inet:ip_address_high}');

		$fields = array(
			'ip_address_low' => Utils::$context['ip']['low'],
			'ip_address_high' => Utils::$context['ip']['high'],
		);

		$ip_var = Utils::$context['ip'];

		if (Utils::$context['ip']['low'] !== Utils::$context['ip']['high'])
		{
			Utils::$context['ip'] = Utils::$context['ip']['low'] . '-' . Utils::$context['ip']['high'];
		}
		else
		{
			Utils::$context['ip'] = Utils::$context['ip']['low'];
		}

		if ($this->standalone)
			Utils::$context['page_title'] = Lang::$txt['trackIP'] . ' - ' . Utils::$context['ip'];

		Utils::$context['ips'] = array();

		$request = Db::$db->query('', '
			SELECT id_member, real_name AS display_name, member_ip
			FROM {db_prefix}members
			WHERE member_ip >= ' . $ip_string[0] . ' and member_ip <= ' . $ip_string[1],
			$fields
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['ips'][(string) new IP($row['member_ip'])][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
		}
		Db::$db->free_result($request);

		ksort(Utils::$context['ips']);

		// For messages we use the "messages per page" option
		$max_per_page = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		// Start with the user messages.
		$list_options = array(
			'id' => 'track_message_list',
			'title' => Lang::$txt['messages_from_ip'] . ' ' . Utils::$context['ip'],
			'start_var_name' => 'messageStart',
			'items_per_page' => $max_per_page,
			'no_items_label' => Lang::$txt['no_messages_from_ip'],
			'base_href' => Utils::$context['base_url'] . ';searchip=' . Utils::$context['ip'],
			'default_sort_col' => 'date',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getIPMessages',
				'params' => array(
					'm.poster_ip >= ' . $ip_string[0] . ' and m.poster_ip <= ' . $ip_string[1],
					$fields,
				),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getIPMessageCount',
				'params' => array(
					'm.poster_ip >= ' . $ip_string[0] . ' and m.poster_ip <= ' . $ip_string[1],
					$fields,
				),
			),
			'columns' => array(
				'ip_address' => array(
					'header' => array(
						'value' => Lang::$txt['ip_address'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Utils::$context['base_url'] . ';searchip=%1$s">%1$s</a>',
							'params' => array(
								'ip' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'm.poster_ip',
						'reverse' => 'm.poster_ip DESC',
					),
				),
				'poster' => array(
					'header' => array(
						'value' => Lang::$txt['poster'],
					),
					'data' => array(
						'db' => 'member_link',
					),
				),
				'subject' => array(
					'header' => array(
						'value' => Lang::$txt['subject'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Config::$scripturl . '?topic=%1$s.msg%2$s#msg%2$s" rel="nofollow">%3$s</a>',
							'params' => array(
								'topic' => false,
								'id' => false,
								'subject' => false,
							),
						),
					),
				),
				'date' => array(
					'header' => array(
						'value' => Lang::$txt['date'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'm.id_msg DESC',
						'reverse' => 'm.id_msg',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => Lang::$txt['messages_from_ip_desc'],
				),
			),
		);

		// Create the messages list.
		new ItemList($list_options);

		// Set the options for the error lists.
		$list_options = array(
			'id' => 'track_user_list',
			'title' => Lang::$txt['errors_from_ip'] . ' ' . Utils::$context['ip'],
			'start_var_name' => 'errorStart',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['no_errors_from_ip'],
			'base_href' => Utils::$context['base_url'] . ';searchip=' . Utils::$context['ip'],
			'default_sort_col' => 'date2',
			'get_items' => array(
				'function' => 'list_getUserErrors',
				'params' => array(
					'le.ip >= ' . $ip_string[0] . ' and le.ip <= ' . $ip_string[1],
					$fields,
				),
			),
			'get_count' => array(
				'function' => 'list_getUserErrorCount',
				'params' => array(
					'ip >= ' . $ip_string[0] . ' and ip <= ' . $ip_string[1],
					$fields,
				),
			),
			'columns' => array(
				'ip_address2' => array(
					'header' => array(
						'value' => Lang::$txt['ip_address'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Utils::$context['base_url'] . ';searchip=%1$s">%1$s</a>',
							'params' => array(
								'ip' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'le.ip',
						'reverse' => 'le.ip DESC',
					),
				),
				'display_name' => array(
					'header' => array(
						'value' => Lang::$txt['display_name'],
					),
					'data' => array(
						'db' => 'member_link',
					),
				),
				'message' => array(
					'header' => array(
						'value' => Lang::$txt['message'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '%1$s<br><a href="%2$s">%2$s</a>',
							'params' => array(
								'message' => false,
								'url' => false,
							),
						),
						'class' => 'word_break',
					),
				),
				'date2' => array(
					'header' => array(
						'value' => Lang::$txt['date'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'le.id_error DESC',
						'reverse' => 'le.id_error',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => Lang::$txt['errors_from_ip_desc'],
				),
			),
		);

		// Create the error list.
		new ItemList($list_options);

		// Allow 3rd party integrations to add in their own lists or whatever.
		Utils::$context['additional_track_lists'] = array();
		IntegrationHook::call('integrate_profile_trackip', array($ip_string, $ip_var));

		Utils::$context['single_ip'] = ($ip_var['low'] === $ip_var['high']);

		if (Utils::$context['single_ip'])
		{
			Utils::$context['whois_servers'] = array(
				'apnic' => array(
					'name' => Lang::$txt['whois_apnic'],
					'url' => 'https://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . Utils::$context['ip'],
				),
				'arin' => array(
					'name' => Lang::$txt['whois_arin'],
					'url' => 'https://whois.arin.net/rest/ip/' . Utils::$context['ip'],
				),
				'lacnic' => array(
					'name' => Lang::$txt['whois_lacnic'],
					'url' => 'https://lacnic.net/cgi-bin/lacnic/whois?query=' . Utils::$context['ip'],
				),
				'ripe' => array(
					'name' => Lang::$txt['whois_ripe'],
					'url' => 'https://apps.db.ripe.net/search/query.html?searchtext=' . Utils::$context['ip'],
				),
			);
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
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets all the posts made from a particular IP
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $where A query to filter which posts are returned
	 * @param array $where_vars An array of parameters for $where
	 * @return array An array containing information about the posts
	 */
	public static function list_getIPMessages(int $start, int $items_per_page, string $sort, string $where, array $where_vars = array()): array
	{
		$messages = array();

		// Get all the messages fitting this where clause.
		$request = Db::$db->query('', '
			SELECT
				m.id_msg, m.poster_ip, COALESCE(mem.real_name, m.poster_name) AS display_name, mem.id_member,
				m.subject, m.poster_time, m.id_topic, m.id_board
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE {query_see_message_board} AND ' . $where . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($where_vars, array(
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			))
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$messages[] = array(
				'ip' => new IP($row['poster_ip']),
				'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>',
				'board' => array(
					'id' => $row['id_board'],
					'href' => Config::$scripturl . '?board=' . $row['id_board']
				),
				'topic' => $row['id_topic'],
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time']
			);
		}
		Db::$db->free_result($request);

		return $messages;
	}

	/**
	 * Gets the number of posts made from a particular IP
	 *
	 * @param string $where A query indicating which posts to count
	 * @param array $where_vars The parameters for $where
	 * @return int Count of messages matching the IP
	 */
	public static function list_getIPMessageCount(string $where, array $where_vars = array()): int
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m
			WHERE {query_see_message_board} AND ' . $where,
			$where_vars
		);
		list($count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $count;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param int $memID The ID of a member whose IP we want to track.
	 */
	public static function trackIP(int $memID = 0): void
	{
		self::load();
		self::$obj->memID = $memID;
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// If this was called via the profile action, the profile action class will
		// have already been loaded. If it isn't, this was called directly.
		$this->standalone = !class_exists(__NAMESPACE__ . '\\Profile\\Main', false);

		if ($this->standalone)
		{
			$this->memID = User::$me->id;
		}
		else
		{
			if (!isset(Profile::$member))
				Profile::load();

			$this->memID = Profile::$member->id;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\TrackIP::exportStatic'))
	TrackIP::exportStatic();

?>