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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Utils;

/**
 * Shows the popup menu for the current user's profile.
 */
class Popup implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'profile_popup',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * A list of menu items to pull from the main profile menu.
	 * 
	 * The values of all 'title' elements are Lang::$txt keys, and will be 
	 * replaced at runtime with the values of those Lang::$txt strings.
	 * 
	 * Occurrences of '{scripturl}' in value strings will be replaced at runtime 
	 * with the value of Config::$scripturl.
	 */
	public array $profile_items = array(
		array(
			'menu' => 'edit_profile',
			'area' => 'account',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'forumprofile',
			'title' => 'popup_forumprofile',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'theme',
			'title' => 'theme',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'notification',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'ignoreboards',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'lists',
			'url' => '{scripturl}?action=profile;area=lists;sa=ignore',
			'title' => 'popup_ignore',
		),
		array(
			'menu' => 'info',
			'area' => 'showposts',
			'title' => 'popup_showposts',
		),
		array(
			'menu' => 'info',
			'area' => 'showdrafts',
			'title' => 'popup_showdrafts',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'groupmembership',
			'title' => 'popup_groupmembership',
		),
		array(
			'menu' => 'profile_action',
			'area' => 'subscriptions',
			'title' => 'popup_subscriptions',
		),
		array(
			'menu' => 'profile_action',
			'area' => 'logout',
		),
	);


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
		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = array();

		IntegrationHook::call('integrate_profile_popup', array(&$this->profile_items));

		// Now check if these items are available
		Utils::$context['profile_items'] = array();
				
		foreach ($this->profile_items as $item)
		{
			if (isset(Menu::$loaded['profile']['sections'][$item['menu']]['areas'][$item['area']]))
			{
				Utils::$context['profile_items'][] = $item;
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Finalize various string values.
		array_walk_recursive(
			$this->profile_items,
			function(&$value, $key)
			{
				if ($key === 'title')
					$value = Lang::$txt[$value] ?? $value;

				$value = strtr($value, array('{scripturl}' => Config::$scripturl));
			}
		);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Popup::exportStatic'))
	Popup::exportStatic();

?>