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
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\User;
use SMF\Utils;

/**
 * This my friend, is for all the mod authors out there.
 */
class Mods implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'modifyModSettings' => 'ModifyModSettings',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'general';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'general' => 'general',
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['sub_action'] = $this->subaction;

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 *
	 */
	public function general(): void
	{
		$config_vars = self::getConfigVars();

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=modsettings;save;sa=general';
		Utils::$context['settings_title'] = Lang::$txt['mods_cat_modifications_misc'];

		// No removing this line, you dirty unwashed mod authors. :p
		if (empty($config_vars)) {
			Utils::$context['settings_save_dont_show'] = true;
			Utils::$context['settings_message'] = [
				'label' => Lang::$txt['modification_no_misc_settings'],
				'tag' => 'div',
				'class' => 'centertext',
			];
		}
		// Saving?
		elseif (isset($_GET['save'])) {
			User::$me->checkSession();

			$save_vars = $config_vars;

			IntegrationHook::call('integrate_save_general_mod_settings', [&$save_vars]);

			// This line is to help mod authors do a search/add after if you want to add something here. Keyword: FOOT TAPPING SUCKS!
			ACP::saveDBSettings($save_vars);

			// This line is to remind mod authors that it's nice to let the users know when something has been saved.
			$_SESSION['adm-save'] = true;

			// This line is to help mod authors do a search/add after if you want to add something here. Keyword: I LOVE TEA!
			Utils::redirectexit('action=admin;area=modsettings;sa=general');
		}

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: RED INK IS FOR TEACHERS AND THOSE WHO LIKE PAIN!
		ACP::prepareDBSettingContext($config_vars);
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
	 * Gets the configuration variables for the general sub-action.
	 *
	 * @return array $config_vars for the general sub-action.
	 */
	public static function getConfigVars(): array
	{
		$config_vars = [
			// MOD AUTHORS: Please use the hook below to add your settings.
			// But if you insist on editing this file, add your new settings
			// UNDER this comment.
		];

		// MOD AUTHORS: This hook is the right way to add new settings.
		IntegrationHook::call('integrate_general_mod_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyModSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
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
		Lang::load('Help');
		Lang::load('ManageSettings');

		Utils::$context['page_title'] = Lang::$txt['admin_modifications'];

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['admin_modifications'],
			'help' => 'modsettings',
			'description' => Lang::$txt['modification_settings_desc'],
			'tabs' => [
				'general' => [
				],
			],
		];

		// Make it easier for mods to add new areas.
		IntegrationHook::call('integrate_modify_modifications', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Mods::exportStatic')) {
	Mods::exportStatic();
}

?>