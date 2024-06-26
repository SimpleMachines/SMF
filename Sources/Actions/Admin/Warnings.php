<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\BackwardCompatibility;
use SMF\ActionTrait;
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
	use ActionTrait;

	use BackwardCompatibility;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var bool
	 *
	 * Currently enabled moderation settings.
	 */
	public static bool $currently_enabled;

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
			// todo: fix $currently_enabled
			if (!self::$currently_enabled && empty($_POST['warning_enable'])) {
				$_POST['warning_watch'] = 0;
				$_POST['warning_moderate'] = 0;
				$_POST['warning_mute'] = 0;
			}
			// If it was disabled and we're enabling it now, set some sane defaults.
			elseif (!self::$currently_enabled && !empty($_POST['warning_enable'])) {
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

		self::$currently_enabled = (bool) $currently_enabled;

		$config_vars = [
			// Warning system?
			'enable' => ['check', 'warning_enable'],
		];

		if (!empty(Config::$modSettings['warning_settings']) && self::$currently_enabled) {
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
}

?>