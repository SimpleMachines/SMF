<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

namespace SMF\AntiSpam\APIs;

use SMF\AntiSpam\AntiSpamAgent;
use SMF\AntiSpam\AntiSpamInterface;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Uuid;

/**
 * Sends mail via SendMail
 */
class reCAPTCHA extends AntiSpamAgent implements AntiSpamInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $can_recaptcha = false;

	/**
	 * @var string
	 *
	 *
	 */
	public string $recaptcha_site_key = '';

	/**
	 * @var string
	 *
	 *
	 */
	public string $recaptcha_theme = 'light';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		return !empty($this->recaptcha_site_key) && $this->can_recaptcha;
	}

	/**
	 *
	 */
	public function create(?array $options = []): bool
	{
		// The HTML registers it.
		return true;
	}

	public function html(): void
	{
		$lang = Lang::getTxt(Lang::txtExists('lang_recaptcha', file: 'General') ? 'lang_recaptcha' : 'lang_dictionary', file: 'General');

		Theme::loadJavaScriptFile('https://www.google.com/recaptcha/api.js?hl=' . $lang, ['external' => true]);

		echo '
				<div class="g-recaptcha centertext" data-sitekey="', $this->recaptcha_site_key, '" data-theme="', $this->recaptcha_theme, '"></div>';
	}

	/**
	 *
	 */
	public function validate(?array $options = []): array|bool
	{
		$reCaptcha = new \ReCaptcha\ReCaptcha(Config::$modSettings['recaptcha_secret_key'], new \ReCaptcha\RequestMethod\SocketPost());

		// Was there a reCAPTCHA response?
		if (isset($_POST['g-recaptcha-response'])) {
			$resp = $reCaptcha->verify($_POST['g-recaptcha-response'], User::$me->ip);

			if (!$resp->isSuccess()) {
				return ['wrong_verification_recaptcha'];
			}
		} else {
			return ['wrong_verification_code'];
		}

		return true;
	}

	public function refresh(bool $only_if_necessary, bool $do_test): bool
	{
		return false;
	}

	public function __construct(string $form_id, Uuid $agent_id)
	{
		parent::__construct($form_id, $agent_id);

		if (empty(Config::$modSettings['recaptcha_site_key'])) {
			return;
		}

		// Only allow 40 alphanumeric, underscore, and dash characters.
		$this->recaptcha_site_key = substr(preg_replace('/\W/', '', Config::$modSettings['recaptcha_site_key']), 0, 40);

		// Light or dark theme...
		$this->recaptcha_theme = Config::$modSettings['recaptcha_theme'] == 'dark' ? 'dark' : 'light';

		$this->can_recaptcha = !empty(Config::$modSettings['recaptcha_enabled']) && !empty(Config::$modSettings['recaptcha_site_key']) && !empty(Config::$modSettings['recaptcha_secret_key']);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function getConfigVars(array &$config_vars): void
	{
		$config_vars = array_merge($config_vars, [
			// reCAPTCHA
			['title', 'recaptcha_configure'],
			['desc', 'recaptcha_configure_desc', 'class' => 'windowbg'],
			[
				'check',
				'recaptcha_enabled',
				'subtext' => Lang::getTxt('recaptcha_enable_desc', file: 'ManageSettings'),
			],
			[
				'text',
				'recaptcha_site_key',
				'subtext' => Lang::getTxt('recaptcha_site_key_desc', file: 'ManageSettings'),
			],
			[
				'text',
				'recaptcha_secret_key',
				'subtext' => Lang::getTxt('recaptcha_secret_key_desc', file: 'ManageSettings'),
			],
			[
				'select',
				'recaptcha_theme',
				[
					'light' => Lang::getTxt('recaptcha_theme_light', file: 'ManageSettings'),
					'dark' => Lang::getTxt('recaptcha_theme_dark', file: 'ManageSettings'),
				],
			],
		]);
	}
}
