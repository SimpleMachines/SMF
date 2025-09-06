<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\User;
use SMF\Utils;

/**
 * Handles anti-spam settings.
 */
class AntiSpam implements ActionInterface
{
	use ActionTrait;
	use BackwardCompatibility;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		$config_vars = self::getConfigVars();

		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Fix PM settings.
			$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

			// Hack in guest requiring verification!
			if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha'])) {
				$_POST['posts_require_captcha'] = -1;
			}

			$save_vars = $config_vars;

			unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3'], $save_vars['guest_verify']);

			$save_vars[] = ['text', 'pm_spam_settings'];

			// Process all of our config vars from various agents.
			\SMF\AntiSpam\AntiSpam::saveConfigVars();

			IntegrationHook::call('integrate_save_spam_settings', [&$save_vars]);

			// Now save.
			ACP::saveDBSettings($save_vars);
			$_SESSION['adm-save'] = true;

			Utils::redirectexit('action=admin;area=antispam');
		}

		// Hack for PM spam settings.
		list(Config::$modSettings['max_pm_recipients'], Config::$modSettings['pm_posts_verification'], Config::$modSettings['pm_posts_per_hour']) = explode(',', Config::$modSettings['pm_spam_settings']);

		// Hack for guests requiring verification.
		Config::$modSettings['guests_require_captcha'] = !empty(Config::$modSettings['posts_require_captcha']);
		Config::$modSettings['posts_require_captcha'] = !isset(Config::$modSettings['posts_require_captcha']) || Config::$modSettings['posts_require_captcha'] == -1 ? 0 : Config::$modSettings['posts_require_captcha'];

		// Some minor javascript for the guest post setting.
		if (Config::$modSettings['posts_require_captcha']) {
			Utils::$context['settings_post_javascript'] .= '
			document.getElementById(\'guests_require_captcha\').disabled = true;';
		}

		// And everything else.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=antispam;save';
		Utils::$context['settings_title'] = Lang::getTxt('antispam_Settings', file: 'ManageSettings');
		Utils::$context['page_title'] = Lang::getTxt('antispam_title', file: 'Admin');
		Utils::$context['sub_template'] = 'show_settings';

		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::getTxt('antispam_title', file: 'Admin'),
			'description' => Lang::getTxt('antispam_Settings_desc', file: 'ManageSettings'),
		];

		ACP::prepareDBSettingContext($config_vars);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the configuration variables for the anti-spam area.
	 *
	 * @return array $config_vars for the anti-spam area.
	 */
	public static function getConfigVars(): array
	{
		$agents = [];
		$config_vars = [
			['check', 'reg_verification'],
			['check', 'search_enable_captcha'],
			// This, my friend, is a cheat :p
			'guest_verify' => [
				'check',
				'guests_require_captcha',
				'subtext' => Lang::getTxt('setting_guests_require_captcha_desc', file: 'ManageSettings'),
			],
			[
				'int',
				'posts_require_captcha',
				'subtext' => Lang::getTxt('posts_require_captcha_desc', file: 'ManageSettings'),
				'min' => -1,
				'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}',
			],
			'',

			// PM Settings
			'pm1' => [
				'int',
				'max_pm_recipients',
				'subtext' => Lang::getTxt('max_pm_recipients_note', file: 'ManageSettings'),
			],
			'pm2' => [
				'int',
				'pm_posts_verification',
				'subtext' => Lang::getTxt('pm_posts_verification_note', file: 'ManageSettings'),
			],
			'pm3' => [
				'int',
				'pm_posts_per_hour',
				'subtext' => Lang::getTxt('pm_posts_per_hour_note', file: 'ManageSettings'),
			],
			'antispamagents' => ['select', 'antispam_agents', &$agents, 'multiple' => true],
		];

		// Process all of our config vars from various agents.
		\SMF\AntiSpam\AntiSpam::getConfigVars($config_vars, $agents);

		IntegrationHook::call('integrate_spam_settings', [&$config_vars]);

		return $config_vars;
	}
}
