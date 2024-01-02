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
 * Moderation type settings - although there are fewer than we have you believe ;)
 */
class Warnings implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'modifyWarningSettings' => 'ModifyWarningSettings',
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
		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		$config_vars = self::getConfigVars();

		// Cannot use moderation if post moderation is not enabled.
		if (!Config::$modSettings['postmod_active']) {
			unset($config_vars['moderate']);
		}

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Make sure these don't have an effect.
			if (!$currently_enabled && empty($_POST['warning_enable'])) {
				$_POST['warning_watch'] = 0;
				$_POST['warning_moderate'] = 0;
				$_POST['warning_mute'] = 0;
			}
			// If it was disabled and we're enabling it now, set some sane defaults.
			elseif (!$currently_enabled && !empty($_POST['warning_enable'])) {
				// Need to add these, these weren't there before...
				$vars = [
					'warning_watch' => 10,
					'warning_mute' => 60,
				];

				if (Config::$modSettings['postmod_active']) {
					$vars['warning_moderate'] = 35;
				}

				foreach ($vars as $var => $value) {
					$config_vars[] = ['int', $var];
					$_POST[$var] = $value;
				}
			} else {
				$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
				$_POST['warning_moderate'] = Config::$modSettings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
				$_POST['warning_mute'] = min($_POST['warning_mute'], 100);
			}

			// We might not have these already depending on how we got here.
			$_POST['user_limit'] = isset($_POST['user_limit']) ? (int) $_POST['user_limit'] : Config::$modSettings['user_limit'];
			$_POST['warning_decrement'] = isset($_POST['warning_decrement']) ? (int) $_POST['warning_decrement'] : Config::$modSettings['warning_decrement'];

			// Fix the warning setting array!
			$_POST['warning_settings'] = (!empty($_POST['warning_enable']) ? 1 : 0) . ',' . min(100, $_POST['user_limit']) . ',' . min(100, $_POST['warning_decrement']);
			$save_vars = $config_vars;
			$save_vars[] = ['text', 'warning_settings'];
			unset($save_vars['enable'], $save_vars['rem1'], $save_vars['rem2']);

			IntegrationHook::call('integrate_save_warning_settings', [&$save_vars]);

			ACP::saveDBSettings($save_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=warnings');
		}

		// We actually store lots of these together - for efficiency.
		list(Config::$modSettings['warning_enable'], Config::$modSettings['user_limit'], Config::$modSettings['warning_decrement']) = explode(',', Config::$modSettings['warning_settings']);

		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=warnings;save';
		Utils::$context['settings_title'] = Lang::$txt['warnings'];
		Utils::$context['page_title'] = Lang::$txt['warnings'];

		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['warnings'],
			'help' => '',
			'description' => Lang::$txt['warnings_desc'],
		];

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
	 * Gets the configuration variables for the warnings area.
	 *
	 * @return array $config_vars for the warnings area.
	 */
	public static function getConfigVars(): array
	{
		Lang::load('Help');
		Lang::load('ManageSettings');

		// We need the existing ones for this
		list($currently_enabled, Config::$modSettings['user_limit'], Config::$modSettings['warning_decrement']) = explode(',', Config::$modSettings['warning_settings']);

		$config_vars = [
			// Warning system?
			'enable' => ['check', 'warning_enable'],
		];

		if (!empty(Config::$modSettings['warning_settings']) && $currently_enabled) {
			$config_vars += [
				'',
				[
					'int',
					'warning_watch',
					'subtext' => Lang::$txt['setting_warning_watch_note'] . ' ' . Lang::$txt['zero_to_disable'],
				],
				'moderate' => [
					'int',
					'warning_moderate',
					'subtext' => Lang::$txt['setting_warning_moderate_note'] . ' ' . Lang::$txt['zero_to_disable'],
				],
				[
					'int',
					'warning_mute',
					'subtext' => Lang::$txt['setting_warning_mute_note'] . ' ' . Lang::$txt['zero_to_disable'],
				],
				'rem1' => [
					'int',
					'user_limit',
					'subtext' => Lang::$txt['setting_user_limit_note'],
				],
				'rem2' => [
					'int',
					'warning_decrement',
					'subtext' => Lang::$txt['setting_warning_decrement_note'] . ' ' . Lang::$txt['zero_to_disable'],
				],
				['permissions', 'view_warning_any'],
				['permissions', 'view_warning_own'],
			];
		}

		IntegrationHook::call('integrate_warning_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyWarningSettings($return_config = false)
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Warnings::exportStatic')) {
	Warnings::exportStatic();
}

?>