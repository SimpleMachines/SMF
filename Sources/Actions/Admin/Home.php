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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Credits;
use SMF\Actions\Groups;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * The administration home page.
 *
 * It uses the Admin template along with the admin sub template.
 * It requires at least some sort of administrative permissions.
 * It uses the index administrative area.
 * It can be found by going to ?action=admin.
 */
class Home implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'AdminHome',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * You have to be able to do at least one of these to see this page.
	 */
	public array $permissions = [
		'admin_forum',
		'edit_news',
		'manage_attachments',
		'manage_bans',
		'manage_boards',
		'manage_membergroups',
		'manage_permissions',
		'manage_smileys',
		'moderate_forum',
		'send_mail',
	];

	/**
	 * @var array
	 *
	 * Server software to check the versions of.
	 */
	public array $checkFor = [
		'gd',
		'imagemagick',
		'db_server',
		'apcu',
		'memcacheimplementation',
		'memcachedimplementation',
		'postgres',
		'sqlite',
		'zend',
		'filebased',
		'php',
		'server',
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
	 * Shows the page.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo($this->permissions);

		// Find all of this forum's administrators...
		if (Groups::listMembergroupMembers_Href(Utils::$context['administrators'], 1, 32) && User::$me->allowedTo('manage_membergroups')) {
			// Add a 'more'-link if there are more than 32.
			Utils::$context['more_admins_link'] = '<a href="' . Config::$scripturl . '?action=moderate;area=viewgroups;sa=members;group=1">' . Lang::$txt['more'] . '</a>';
		}

		// Load the credits stuff.
		Credits::call(true);

		// This makes it easier to get the latest news with your time format.
		Utils::$context['time_format'] = urlencode(User::$me->time_format);
		Utils::$context['forum_version'] = SMF_FULL_VERSION;

		// Get a list of current server versions.
		Utils::$context['current_versions'] = ACP::getServerVersions($this->checkFor);

		Utils::$context['can_admin'] = User::$me->allowedTo('admin_forum');

		Utils::$context['sub_template'] = Utils::$context['admin_area'] == 'credits' ? 'credits' : 'admin';

		Utils::$context['page_title'] = Utils::$context['admin_area'] == 'credits' ? Lang::$txt['support_credits_title'] : Lang::$txt['admin_center'];

		if (Utils::$context['admin_area'] != 'credits') {
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::$txt['admin_center'],
				'help' => '',
				'description' => '<strong>' . Lang::$txt['hello_guest'] . ' ' . User::$me->name . '!</strong>
					' . sprintf(Lang::$txt['admin_main_welcome'], Lang::$txt['admin_center'], Lang::$txt['help'], Lang::$txt['help']),
			];
		}

		// Lastly, fill in the blanks in the support resources paragraphs.
		Lang::$txt['support_resources_p1'] = sprintf(
			Lang::$txt['support_resources_p1'],
			'https://wiki.simplemachines.org/',
			'https://wiki.simplemachines.org/smf/features2',
			'https://wiki.simplemachines.org/smf/options2',
			'https://wiki.simplemachines.org/smf/themes2',
			'https://wiki.simplemachines.org/smf/packages2',
		);

		Lang::$txt['support_resources_p2'] = sprintf(
			Lang::$txt['support_resources_p2'],
			'https://www.simplemachines.org/community/',
			'https://www.simplemachines.org/redirect/english_support',
			'https://www.simplemachines.org/redirect/international_support_boards',
			'https://www.simplemachines.org/redirect/smf_support',
			'https://www.simplemachines.org/redirect/customize_support',
		);

		if (Utils::$context['admin_area'] == 'admin') {
			Theme::loadJavaScriptFile('admin.js', ['defer' => false, 'minimize' => true], 'smf_admin');
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Home::exportStatic')) {
	Home::exportStatic();
}

?>