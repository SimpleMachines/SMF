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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\Actions\Who;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IP;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows a summary of a member's profile.
 */
class Summary implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'summary' => 'summary',
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
		// Menu tab
		Menu::$loaded['profile']->tab_data = [
			'title' => Lang::$txt['summary'],
			'icon_class' => 'main_icons profile_hd',
		];

		// Set up the stuff and load the user.
		Utils::$context += [
			'page_title' => sprintf(Lang::$txt['profile_of_username'], Profile::$member->formatted['name']),
			'can_send_pm' => User::$me->allowedTo('pm_send'),
			'can_have_buddy' => User::$me->allowedTo('profile_extra_own') && !empty(Config::$modSettings['enable_buddylist']),
			'can_issue_warning' => User::$me->allowedTo('issue_warning') && Config::$modSettings['warning_settings'][0] == 1,
			'can_view_warning' => (User::$me->allowedTo('moderate_forum') || User::$me->allowedTo('issue_warning') || User::$me->allowedTo('view_warning_any') || (User::$me->is_owner && User::$me->allowedTo('view_warning_own'))) && Config::$modSettings['warning_settings'][0] === '1',
		];

		// Set a canonical URL for this page.
		Utils::$context['canonical_url'] = Config::$scripturl . '?action=profile;u=' . Profile::$member->id;

		// See if they have broken any warning levels...
		if (!empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= Profile::$member->formatted['warning']) {
			Utils::$context['warning_status'] = Lang::$txt['profile_warning_is_muted'];
		} elseif (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= Profile::$member->formatted['warning']) {
			Utils::$context['warning_status'] = Lang::$txt['profile_warning_is_moderation'];
		} elseif (!empty(Config::$modSettings['warning_watch']) && Config::$modSettings['warning_watch'] <= Profile::$member->formatted['warning']) {
			Utils::$context['warning_status'] = Lang::$txt['profile_warning_is_watch'];
		}

		// They haven't even been registered for a full day!?
		$days_registered = (int) ((time() - Profile::$member->date_registered) / (3600 * 24));

		if (empty(Profile::$member->date_registered) || $days_registered < 1) {
			Profile::$member->formatted['posts_per_day'] = Lang::$txt['not_applicable'];
		} else {
			Profile::$member->formatted['posts_per_day'] = Lang::numberFormat(Profile::$member->formatted['real_posts'] / $days_registered, 3);
		}

		// Set the age...
		if (empty(Profile::$member->formatted['birth_date']) || substr(Profile::$member->formatted['birth_date'], 0, 4) < 1002) {
			Profile::$member->formatted += [
				'age' => Lang::$txt['not_applicable'],
				'today_is_birthday' => false,
			];
		} else {
			list($birth_year, $birth_month, $birth_day) = sscanf(Profile::$member->formatted['birth_date'], '%d-%d-%d');

			$datearray = getdate(time());

			Profile::$member->formatted += [
				'age' => $birth_year <= 1004 ? Lang::$txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1),
				'today_is_birthday' => $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day && $birth_year > 1004,
			];
		}

		if (Utils::$context['can_see_ip'] && empty(Config::$modSettings['disableHostnameLookup']) && filter_var(Profile::$member->formatted['ip'], FILTER_VALIDATE_IP) !== false) {
			$ip = new IP(Profile::$member->formatted['ip']);
			Profile::$member->formatted['hostname'] = $ip->getHost();
		}

		// Are they hidden?
		Profile::$member->formatted['is_hidden'] = empty(Profile::$member->show_online);
		Profile::$member->formatted['show_last_login'] = User::$me->allowedTo('admin_forum') || !Profile::$member->formatted['is_hidden'];

		if (!empty(Config::$modSettings['who_enabled']) && Profile::$member->formatted['show_last_login']) {
			$action = Who::determineActions(Profile::$member->url);

			if ($action !== false) {
				Profile::$member->formatted['action'] = $action;
			}
		}

		// If the user is awaiting activation, and the viewer has permission, set up some activation context messages.
		if (Profile::$member->formatted['is_activated'] % 10 != 1 && User::$me->allowedTo('moderate_forum')) {
			Utils::$context['activate_type'] = Profile::$member->formatted['is_activated'];

			// What should the link text be?
			Utils::$context['activate_link_text'] = in_array(Profile::$member->formatted['is_activated'], [3, 4, 5, 13, 14, 15]) ? Lang::$txt['account_approve'] : Lang::$txt['account_activate'];

			// Should we show a custom message?
			Utils::$context['activate_message'] = Lang::$txt['account_activate_method_' . Profile::$member->formatted['is_activated'] % 10] ?? Lang::$txt['account_not_activated'];

			// If they can be approved, we need to set up a token for them.
			Utils::$context['token_check'] = 'profile-aa' . Profile::$member->id;
			SecurityToken::create(Utils::$context['token_check'], 'get');

			// Puerile comment
			$type = in_array(Profile::$member->formatted['is_activated'], [3, 4, 5, 13, 14, 15]) ? 'approve' : 'activate';

			Utils::$context['activate_link'] = Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=' . $type . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context[Utils::$context['token_check'] . '_token_var'] . '=' . Utils::$context[Utils::$context['token_check'] . '_token'];
		}

		// Prevent signature images from going outside the box.
		if (Utils::$context['signature_enabled']) {
			list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
			$sig_limits = explode(',', $sig_limits);

			if (!empty($sig_limits[5]) || !empty($sig_limits[6])) {
				Theme::addInlineCss("\n\t" . '.signature img { ' . (!empty($sig_limits[5]) ? 'max-width: ' . (int) $sig_limits[5] . 'px; ' : '') . (!empty($sig_limits[6]) ? 'max-height: ' . (int) $sig_limits[6] . 'px; ' : '') . '}');
			}
		}

		// How about, are they banned?
		Profile::$member->formatted['bans'] = [];

		if (User::$me->allowedTo('moderate_forum')) {
			// Can they edit the ban?
			Utils::$context['can_edit_ban'] = User::$me->allowedTo('manage_bans');

			$ban_query = [];
			$ban_query_vars = [
				'time' => time(),
			];
			$ban_query[] = 'id_member = ' . Profile::$member->formatted['id'];
			$ban_query[] = ' {inet:ip} BETWEEN bi.ip_low and bi.ip_high';
			$ban_query_vars['ip'] = Profile::$member->formatted['ip'];

			// Do we have a hostname already?
			if (!empty(Profile::$member->formatted['hostname'])) {
				$ban_query[] = '({string:hostname} LIKE hostname)';
				$ban_query_vars['hostname'] = Profile::$member->formatted['hostname'];
			}

			// Check their email as well...
			if (strlen(Profile::$member->formatted['email']) != 0) {
				$ban_query[] = '({string:email} LIKE bi.email_address)';
				$ban_query_vars['email'] = Profile::$member->formatted['email'];
			}

			// So... are they banned?  Dying to know!
			$request = Db::$db->query(
				'',
				'SELECT bg.id_ban_group, bg.name, bg.cannot_access, bg.cannot_post,
					bg.cannot_login, bg.reason
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:time}))
				WHERE (' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars,
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// Work out what restrictions we actually have.
				$ban_restrictions = [];

				foreach (['access', 'login', 'post'] as $type) {
					if ($row['cannot_' . $type]) {
						$ban_restrictions[] = Lang::$txt['ban_type_' . $type];
					}
				}

				// No actual ban in place?
				if (empty($ban_restrictions)) {
					continue;
				}

				// Prepare the link for context.
				$ban_explanation = sprintf(Lang::$txt['user_cannot_due_to'], implode(', ', $ban_restrictions), '<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>');

				Profile::$member->formatted['bans'][$row['id_ban_group']] = [
					'reason' => empty($row['reason']) ? '' : '<br><br><strong>' . Lang::$txt['ban_reason'] . ':</strong> ' . $row['reason'],
					'cannot' => [
						'access' => !empty($row['cannot_access']),
						'post' => !empty($row['cannot_post']),
						'login' => !empty($row['cannot_login']),
					],
					'explanation' => $ban_explanation,
				];
			}
			Db::$db->free_result($request);
		}

		Profile::$member->loadCustomFields();

		Utils::$context['print_custom_fields'] = [];

		// Any custom profile fields?
		if (!empty(Utils::$context['custom_fields'])) {
			foreach (Utils::$context['custom_fields'] as $custom) {
				Utils::$context['print_custom_fields'][Utils::$context['cust_profile_fields_placement'][$custom['placement']]][] = $custom;
			}
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

	/**
	 * Backward compatibility wrapper.
	 */
	public static function summary(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

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
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		// Are there things we don't show?
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];

		// Is the signature even enabled on this forum?
		Utils::$context['signature_enabled'] = substr(Config::$modSettings['signature_settings'], 0, 1) == 1;

		// Expand the warning settings.
		list(Config::$modSettings['warning_enable'], Config::$modSettings['user_limit']) = explode(',', Config::$modSettings['warning_settings']);

		// Can the viewer see this member's IP address?
		Utils::$context['can_see_ip'] = User::$me->allowedTo('moderate_forum');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Summary::exportStatic')) {
	Summary::exportStatic();
}

?>