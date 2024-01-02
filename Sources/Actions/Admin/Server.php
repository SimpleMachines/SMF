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
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Url;
use SMF\User;
use SMF\Utils;

/**
 * Contains all the functionality required to be able to edit the core server
 * settings. This includes anything from which an error may result in the forum
 * destroying itself in a firey fury.
 *
 * Adding options to one of the setting screens isn't hard. Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 * 		array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 * 		array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In these cases, it will look for Lang::$txt['nameInModSettingsAndSQL'] as the description,
 * and Lang::$helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 * 		array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 * 		array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 * 		array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 * 		array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 * 		array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 * 		array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => Lang::$txt['displayedValue'])),
 * 		Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 * 		array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 * 		array('permissions', 'manage_groups'),
 * - A BBC selection box.
 * 		array('bbc', 'sig_bbc'),
 * - A list of boards to choose from
 *  	array('boards', 'likes_boards'),
 *  	Note that the storage in the database is as 1,2,3,4
 *
 * For each option:
 * 	- type (see above), variable name, size/possible values.
 * 	  OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *  	array('text', 'invalidlabel', 3, 'label' => 'Actual Label')
 */
class Server implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ModifySettings',
			'getLoadAverageDisabled' => 'getLoadAverageDisabled',
			'prepareServerSettingsContext' => 'prepareServerSettingsContext',
			'checkSettingsFileWriteSafe' => 'checkSettingsFileWriteSafe',
			'modifyGeneralSettings' => 'ModifyGeneralSettings',
			'modifyDatabaseSettings' => 'ModifyDatabaseSettings',
			'modifyCookieSettings' => 'ModifyCookieSettings',
			'modifyGeneralSecuritySettings' => 'ModifyGeneralSecuritySettings',
			'modifyCacheSettings' => 'ModifyCacheSettings',
			'modifyExportSettings' => 'ModifyExportSettings',
			'modifyLoadBalancingSettings' => 'ModifyLoadBalancingSettings',
			'showPHPinfoSettings' => 'ShowPHPinfoSettings',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Default values for load balancing options.
	 */
	public const LOADAVG_DEFAULT_VALUES = [
		'loadavg_auto_opt' => 1.0,
		'loadavg_search' => 2.5,
		'loadavg_allunread' => 2.0,
		'loadavg_unreadreplies' => 3.5,
		'loadavg_show_posts' => 2.0,
		'loadavg_userstats' => 10.0,
		'loadavg_bbc' => 30.0,
		'loadavg_forum' => 40.0,
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
		'database' => 'database',
		'cookie' => 'cookie',
		'security' => 'security',
		'cache' => 'cache',
		'export' => 'export',
		'loads' => 'loadBalancing',
		'phpinfo' => 'phpinfo',
	];

	/**
	 * @var bool
	 *
	 * True if Settings.php is not writable.
	 */
	public static $settings_not_writable;

	/**
	 * @var bool
	 *
	 * True if we are unable to back up Settings.php.
	 */
	public static $settings_backup_fail;

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

	/**
	 * @var bool
	 *
	 * Whether load averaging is disabled on this server.
	 */
	protected static bool $loadAverageDisabled;

	/****************
	 * Public methods
	 ****************/

	/**
	 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
	 * the appropriate one based on the sub-action.
	 *
	 * Requires the admin_forum permission.
	 * Redirects to the appropriate function based on the sub-action.
	 *
	 * Uses edit_settings adminIndex.
	 */
	public function execute(): void
	{
		// This is just to keep the database password more secure.
		User::$me->isAllowedTo('admin_forum');

		User::$me->checkSession('request');

		Utils::$context['page_title'] = Lang::$txt['admin_server_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Warn the user if there's any relevant information regarding Settings.php.
		self::checkSettingsFileWriteSafe();

		if (self::$settings_backup_fail) {
			Utils::$context['settings_message'] = [
				'label' => Lang::$txt['admin_backup_fail'],
				'tag' => 'div',
				'class' => 'centertext strong',
			];
		}

		Utils::$context['settings_not_writable'] = self::$settings_not_writable;
		Utils::$context['sub_action'] = $this->subaction;

		// Call the right method for this sub-action.
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * General forum settings - forum name, maintenance mode, etc.
	 * Practically, this shows an interface for the settings in Settings.php to be changed.
	 *
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Contains the actual array of settings to show from Settings.php.
	 * - Accessed from ?action=admin;area=serversettings;sa=general.
	 */
	public function general(): void
	{
		$config_vars = self::generalConfigVars();

		// If no cert, force_ssl must remain 0 (The admin search doesn't require this)
		$config_vars['force_ssl']['disabled'] = empty(Config::$modSettings['force_ssl']) && !Url::create(Config::$boardurl)->hasSSL();

		// Setup the template stuff.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=general;save';
		Utils::$context['settings_title'] = Lang::$txt['general_settings'];
		Utils::$context['save_disabled'] = Utils::$context['settings_not_writable'];

		// Saving settings?
		if (isset($_REQUEST['save'])) {
			IntegrationHook::call('integrate_save_general_settings');

			foreach ($config_vars as $config_var) {
				if (is_array($config_var) && isset($config_var[3]) && $config_var[3] == 'text' && !empty($_POST[$config_var[0]])) {
					$_POST[$config_var[0]] = Utils::normalize($_POST[$config_var[0]]);
				}
			}

			// Are we saving the stat collection?
			if (!empty($_POST['enable_sm_stats']) && empty(Config::$modSettings['sm_stats_key'])) {
				$registerSMStats = $this->registerSMStats();

				// Failed to register, disable it again.
				if (empty($registerSMStats)) {
					$_POST['enable_sm_stats'] = 0;
				}
			}

			// Ensure all URLs are aligned with the new force_ssl setting
			// Treat unset like 0
			$this->alignURLsWithSSLSetting($_POST['force_ssl'] ?? 0);

			ACP::saveSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=serversettings;sa=general;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Fill the config array.
		self::prepareServerSettingsContext($config_vars);

		// Some javascript for SSL
		if (empty(Utils::$context['settings_not_writable'])) {
			Theme::addInlineJavaScript(<<<'END'
				$(function()
				{
					$("#force_ssl").change(function()
					{
						var mode = $(this).val() == 1 ? false : true;
						$("#image_proxy_enabled").prop("disabled", mode);
						$("#image_proxy_secret").prop("disabled", mode);
						$("#image_proxy_maxsize").prop("disabled", mode);
					}).change();
				});
				END, true);
		}
	}

	/**
	 * Basic database and paths settings - database name, host, etc.
	 *
	 * - It shows an interface for the settings in Settings.php to be changed.
	 * - It contains the actual array of settings to show from Settings.php.
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Accessed from ?action=admin;area=serversettings;sa=database.
	 */
	public function database(): void
	{
		$config_vars = self::databaseConfigVars();

		// Setup the template stuff.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=database;save';
		Utils::$context['settings_title'] = Lang::$txt['database_settings'];
		Utils::$context['save_disabled'] = Utils::$context['settings_not_writable'];

		if (!Db::$db->allow_persistent()) {
			Theme::addInlineJavaScript(<<<'END'
				$(function()
				{
					$("#db_persist").prop("disabled", true);
				});
				END, true);
		}

		// Saving settings?
		if (isset($_REQUEST['save'])) {
			IntegrationHook::call('integrate_save_database_settings');

			ACP::saveSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=serversettings;sa=database;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Fill the config array.
		self::prepareServerSettingsContext($config_vars);
	}

	/**
	 * This function handles cookies settings modifications.
	 */
	public function cookie(): void
	{
		$config_vars = self::cookieConfigVars();

		Theme::addInlineJavaScript(<<<'END'
			function hideGlobalCookies()
			{
				var usingLocal = $("#localCookies").prop("checked");
				$("#setting_globalCookies").closest("dt").toggle(!usingLocal);
				$("#globalCookies").closest("dd").toggle(!usingLocal);

				var usingGlobal = !usingLocal && $("#globalCookies").prop("checked");
				$("#setting_globalCookiesDomain").closest("dt").toggle(usingGlobal);
				$("#globalCookiesDomain").closest("dd").toggle(usingGlobal);
			};
			hideGlobalCookies();

			$("#localCookies, #globalCookies").click(function() {
				hideGlobalCookies();
			});
			END, true);

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=cookie;save';
		Utils::$context['settings_title'] = Lang::$txt['cookies_sessions_settings'];
		Utils::$context['save_disabled'] = Utils::$context['settings_not_writable'];

		// Saving settings?
		if (isset($_REQUEST['save'])) {
			IntegrationHook::call('integrate_save_cookie_settings');

			$_POST['cookiename'] = Utils::normalize($_POST['cookiename']);

			// Local and global do not play nicely together.
			if (!empty($_POST['localCookies']) && empty($_POST['globalCookies'])) {
				unset($_POST['globalCookies']);
			}

			if (empty(Config::$modSettings['localCookies']) != empty($_POST['localCookies']) || empty(Config::$modSettings['globalCookies']) != empty($_POST['globalCookies'])) {
				$scope_changed = true;
			}

			if (!empty($_POST['globalCookiesDomain'])) {
				$_POST['globalCookiesDomain'] = Url::create((strpos($_POST['globalCookiesDomain'], '//') === false ? 'http://' : '') . ltrim($_POST['globalCookiesDomain'], '.'), true)->host;

				if (!preg_match('/(?:^|\.)' . preg_quote($_POST['globalCookiesDomain'], '/') . '$/u', Url::create(Config::$boardurl))->host) {
					ErrorHandler::fatalLang('invalid_cookie_domain', false);
				}
			}

			// Per spec, if samesite setting is 'none', cookies MUST be secure. Thems the rules. Else you lock everyone out...
			if (!empty($_POST['samesiteCookies']) && ($_POST['samesiteCookies'] === 'none') && empty($_POST['secureCookies'])) {
				ErrorHandler::fatalLang('samesiteSecureRequired', false);
			}

			ACP::saveSettings($config_vars);

			// If the cookie name or scope were changed, reset the cookie.
			if (Config::$cookiename != $_POST['cookiename'] || !empty($scope_changed)) {
				$original_session_id = Utils::$context['session_id'];

				// Remove the old cookie.
				Cookie::setLoginCookie(-3600, 0);

				// Set the new one.
				Config::$cookiename = !empty($_POST['cookiename']) ? $_POST['cookiename'] : Config::$cookiename;

				Cookie::setLoginCookie(60 * Config::$modSettings['cookieTime'], User::$me->id, Cookie::encrypt(User::$me->passwd, User::$me->password_salt));

				Utils::redirectexit('action=admin;area=serversettings;sa=cookie;' . Utils::$context['session_var'] . '=' . $original_session_id, Utils::$context['server']['needs_login_fix']);
			}

			// If we disabled 2FA, reset all members and membergroups settings.
			if (isset($_POST['tfa_mode']) && empty($_POST['tfa_mode'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}membergroups
					SET tfa_required = {int:zero}',
					[
						'zero' => 0,
					],
				);

				Db::$db->query(
					'',
					'UPDATE {db_prefix}members
					SET tfa_secret = {string:empty}, tfa_backup = {string:empty}',
					[
						'empty' => '',
					],
				);
			}

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=serversettings;sa=cookie;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Fill the config array.
		self::prepareServerSettingsContext($config_vars);
	}

	/**
	 * Settings really associated with general security aspects.
	 */
	public function security(): void
	{
		$config_vars = self::securityConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			if (!empty($_POST['cors_domains'])) {
				$cors_domains = explode(',', $_POST['cors_domains']);

				foreach ($cors_domains as &$cors_domain) {
					if (strpos($cors_domain, '//') === false) {
						$cors_domain = '//' . $cors_domain;
					}

					$temp = new Url($cors_domain, true);

					if (strpos($temp->host, '*') !== false) {
						$temp->host = substr($temp->host, strrpos($temp->host, '*'));
					}

					$cors_domain = (!empty($temp->scheme) ? $temp->scheme . '://' : '') . $temp->host . (!empty($temp->port) ? ':' . $temp->port : '');
				}

				$_POST['cors_domains'] = implode(',', $cors_domains);
			}

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;

			IntegrationHook::call('integrate_save_general_security_settings');

			User::$me->logOnline();
			Utils::redirectexit('action=admin;area=serversettings;sa=security;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;save;sa=security';
		Utils::$context['settings_title'] = Lang::$txt['security_settings'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Simply modifying cache functions
	 */
	public function cache(): void
	{
		$config_vars = self::cacheConfigVars();

		// Saving again?
		if (isset($_GET['save'])) {
			IntegrationHook::call('integrate_save_cache_settings');

			if (is_callable([CacheApi::$loadedApi, 'cleanCache']) && ((int) $_POST['cache_enable'] < CacheApi::$enable || $_POST['cache_accelerator'] != CacheApi::$accelerator)) {
				CacheApi::clean();
			}

			ACP::saveSettings($config_vars);
			$_SESSION['adm-save'] = true;

			// We need to save the CacheApi::$enable to Config::$modSettings as well
			Config::updateModSettings(['cache_enable' => (int) $_POST['cache_enable']]);

			// exit so we reload our new settings on the page
			Utils::redirectexit('action=admin;area=serversettings;sa=cache;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		Lang::load('ManageMaintenance');
		SecurityToken::create('admin-maint');
		Utils::$context['template_layers'][] = 'clean_cache_button';

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=cache;save';
		Utils::$context['settings_title'] = Lang::$txt['caching_settings'];

		// Changing cache settings won't have any effect if Settings.php is not writable.
		Utils::$context['save_disabled'] = Utils::$context['settings_not_writable'];

		// Decide what message to show.
		if (!Utils::$context['save_disabled']) {
			Utils::$context['settings_message'] = Lang::$txt['caching_information'];
		}

		// Prepare the template.
		self::prepareServerSettingsContext($config_vars);
	}

	/**
	 * Controls settings for data export functionality
	 */
	public function export(): void
	{
		Utils::$context['settings_message'] = Lang::$txt['export_settings_description'];

		$config_vars = self::exportConfigVars();

		if (isset($_REQUEST['save'])) {
			$prev_export_dir = is_dir(Config::$modSettings['export_dir']) ? rtrim(Config::$modSettings['export_dir'], '/\\') : '';

			if (!empty($_POST['export_dir'])) {
				$_POST['export_dir'] = rtrim($_POST['export_dir'], '/\\');
			}

			if ($diskspace_disabled) {
				$_POST['export_min_diskspace_pct'] = 0;
			}

			$_POST['export_rate'] = max(5, min($_POST['export_rate'], 500));

			ACP::saveDBSettings($config_vars);

			// Create the new directory, but revert to the previous one if anything goes wrong.
			require_once Config::$sourcedir . '/Actions/Profile/Export.php';
			create_export_dir($prev_export_dir);

			// Ensure we don't lose track of any existing export files.
			if (!empty($prev_export_dir) && $prev_export_dir != Config::$modSettings['export_dir']) {
				$export_files = glob($prev_export_dir . DIRECTORY_SEPARATOR . '*');

				foreach ($export_files as $export_file) {
					if (!in_array(basename($export_file), ['index.php', '.htaccess'])) {
						rename($export_file, Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR . basename($export_file));
					}
				}
			}

			IntegrationHook::call('integrate_save_export_settings');

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=serversettings;sa=export;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=export;save';
		Utils::$context['settings_title'] = Lang::$txt['export_settings'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Allows to edit load balancing settings.
	 */
	public function loadBalancing(): void
	{
		// Setup a warning message, but disabled by default.
		self::getLoadAverageDisabled();

		Utils::$context['settings_message'] = ['label' => Lang::$txt['loadavg_disabled_conf'], 'class' => 'error'];

		if (self::$loadAverageDisabled && DIRECTORY_SEPARATOR === '\\') {
			Utils::$context['settings_message']['label'] = Lang::$txt['loadavg_disabled_windows'];

			if (isset($_GET['save'])) {
				$_SESSION['adm-save'] = Utils::$context['settings_message']['label'];
			}
		} elseif (self::$loadAverageDisabled && stripos(PHP_OS, 'darwin') === 0) {
			Utils::$context['settings_message']['label'] = Lang::$txt['loadavg_disabled_osx'];

			if (isset($_GET['save'])) {
				$_SESSION['adm-save'] = Utils::$context['settings_message']['label'];
			}
		} elseif (!self::$loadAverageDisabled) {
			Utils::$context['settings_message']['label'] = sprintf(Lang::$txt['loadavg_warning'], Config::$modSettings['load_average']);
		}

		$config_vars = self::loadBalancingConfigVars();

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=serversettings;sa=loads;save';
		Utils::$context['settings_title'] = Lang::$txt['load_balancing_settings'];

		// Saving?
		if (isset($_GET['save'])) {
			// Stupidity is not allowed.
			foreach ($_POST as $key => $value) {
				if (!isset(self::LOADAVG_DEFAULT_VALUES[$key])) {
					continue;
				}

				switch ($key) {
					case 'loadavg_auto_opt':
						$min_value = 1.0;
						break;

					case 'loadavg_forum':
						$min_value = 10.0;
						break;

					default:
						$min_value = 2.0;
						break;
				}

				$_POST[$key] = max((float) $value, $min_value);
			}

			IntegrationHook::call('integrate_save_loadavg_settings');

			ACP::saveDBSettings($config_vars);

			if (!isset($_SESSION['adm-save'])) {
				$_SESSION['adm-save'] = true;
			}

			Utils::redirectexit('action=admin;area=serversettings;sa=loads;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Allows us to see the server's PHP settings
	 *
	 * - loads the settings into an array for display in a template
	 * - drops cookie values just in case
	 */
	public function phpinfo(): void
	{
		$category = Lang::$txt['phpinfo_settings'];

		// get the data
		ob_start();
		phpinfo();

		// We only want it for its body, pigs that we are.
		$info_lines = preg_replace('~^.*<body>(.*)</body>.*$~', '$1', ob_get_contents());
		$info_lines = explode("\n", strip_tags($info_lines, '<tr><td><h2>'));
		ob_end_clean();

		// Remove things that could be considered sensitive.
		$remove = '_COOKIE|Cookie|_GET|_REQUEST|REQUEST_URI|QUERY_STRING|REQUEST_URL|HTTP_REFERER';

		// Put all of it into an array.
		foreach ($info_lines as $line) {
			if (preg_match('~(' . $remove . ')~', $line)) {
				continue;
			}

			// New category?
			if (strpos($line, '<h2>') !== false) {
				$category = preg_match('~<h2>(.*)</h2>~', $line, $title) ? $category = $title[1] : $category;
			}

			// Load it as setting => value or the old setting local master.
			if (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val)) {
				$pinfo[$category][$val[1]] = $val[2];
			} elseif (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val)) {
				$pinfo[$category][$val[1]] = [Lang::$txt['phpinfo_localsettings'] => $val[2], Lang::$txt['phpinfo_defaultsettings'] => $val[3]];
			}
		}

		// load it in to context and display it
		Utils::$context['pinfo'] = $pinfo;
		Utils::$context['page_title'] = Lang::$txt['admin_server_settings'];
		Utils::$context['sub_template'] = 'php_info';
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
	 * Gets configuration variables for the general sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function generalConfigVars(): array
	{
		/*
			If you're writing a mod, it's a bad idea to add things here...

			For each option:
				variable name, description, type (constant), size/possible values, helptext, optional 'min' (minimum value for float/int, defaults to 0), optional 'max' (maximum value for float/int), optional 'step' (amount to increment/decrement value for float/int)
			OR	an empty string for a horizontal rule.
			OR	a string for a titled section.
		 */
		$config_vars = [
			['mbname', Lang::$txt['admin_title'], 'file', 'text', 30],
			'',
			['maintenance', Lang::$txt['admin_maintain'], 'file', 'check'],
			['mtitle', Lang::$txt['maintenance_subject'], 'file', 'text', 36],
			['mmessage', Lang::$txt['maintenance_message'], 'file', 'text', 36],
			'',
			['webmaster_email', Lang::$txt['admin_webmaster_email'], 'file', 'text', 30],
			'',
			['enableCompressedOutput', Lang::$txt['enableCompressedOutput'], 'db', 'check', null, 'enableCompressedOutput'],
			['disableHostnameLookup', Lang::$txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'],
			'',
			'force_ssl' => ['force_ssl', Lang::$txt['force_ssl'], 'db', 'select', [Lang::$txt['force_ssl_off'], Lang::$txt['force_ssl_complete']], 'force_ssl'],
			['image_proxy_enabled', Lang::$txt['image_proxy_enabled'], 'file', 'check', null, 'image_proxy_enabled'],
			['image_proxy_secret', Lang::$txt['image_proxy_secret'], 'file', 'text', 30, 'image_proxy_secret'],
			['image_proxy_maxsize', Lang::$txt['image_proxy_maxsize'], 'file', 'int', null, 'image_proxy_maxsize'],
			'',
			['enable_sm_stats', Lang::$txt['enable_sm_stats'], 'db', 'check', null, 'enable_sm_stats'],
		];

		IntegrationHook::call('integrate_general_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the database sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function databaseConfigVars(): array
	{
		/*
			If you're writing a mod, it's a bad idea to add things here....

			For each option:
				variable name, description, type (constant), size/possible values, helptext, optional 'min' (minimum value for float/int, defaults to 0), optional 'max' (maximum value for float/int), optional 'step' (amount to increment/decrement value for float/int)
			OR an empty string for a horizontal rule.
			OR a string for a titled section.
		 */
		$config_vars = [
			['db_persist', Lang::$txt['db_persist'], 'file', 'check', null, 'db_persist'],
			['db_error_send', Lang::$txt['db_error_send'], 'file', 'check'],
			['ssi_db_user', Lang::$txt['ssi_db_user'], 'file', 'text', null, 'ssi_db_user'],
			['ssi_db_passwd', Lang::$txt['ssi_db_passwd'], 'file', 'password'],
			'',
			['autoFixDatabase', Lang::$txt['autoFixDatabase'], 'db', 'check', false, 'autoFixDatabase'],
		];

		// Add PG Stuff
		if (Db::$db->title === POSTGRE_TITLE) {
			$fts_language = [];

			$request = Db::$db->query(
				'',
				'SELECT cfgname FROM pg_ts_config',
				[],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$fts_language[$row['cfgname']] = $row['cfgname'];
			}
			Db::$db->free_result($request);

			$config_vars = array_merge(
				$config_vars,
				[
					'',
					['search_language', Lang::$txt['search_language'], 'db', 'select', $fts_language, 'pgFulltextSearch'],
				],
			);
		}

		IntegrationHook::call('integrate_database_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the cookie sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function cookieConfigVars(): array
	{
		$config_vars = [
			// Cookies...
			['cookiename', Lang::$txt['cookie_name'], 'file', 'text', 20],
			['cookieTime', Lang::$txt['cookieTime'], 'db', 'select', array_filter(array_map(
				function ($str) {
					return Lang::$txt[$str] ?? '';
				},
				Utils::$context['login_cookie_times'],
			))],
			['localCookies', Lang::$txt['localCookies'], 'db', 'check', false, 'localCookies'],
			['globalCookies', Lang::$txt['globalCookies'], 'db', 'check', false, 'globalCookies'],
			['globalCookiesDomain', Lang::$txt['globalCookiesDomain'], 'db', 'text', false, 'globalCookiesDomain'],
			['secureCookies', Lang::$txt['secureCookies'], 'db', 'check', false, 'secureCookies', 'disabled' => !Config::httpsOn()],
			['httponlyCookies', Lang::$txt['httponlyCookies'], 'db', 'check', false, 'httponlyCookies'],
			['samesiteCookies', Lang::$txt['samesiteCookies'], 'db', 'select', [
				'none' 		=> Lang::$txt['samesiteNone'],
				'lax' 		=> Lang::$txt['samesiteLax'],
				'strict' 	=> Lang::$txt['samesiteStrict'],
			],
				'samesiteCookies'],
			'',

			// Sessions
			['databaseSession_enable', Lang::$txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'],
			['databaseSession_loose', Lang::$txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'],
			['databaseSession_lifetime', Lang::$txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime', 'postinput' => Lang::$txt['seconds']],
			'',

			// 2FA
			['tfa_mode', Lang::$txt['tfa_mode'], 'db', 'select', [
				0 => Lang::$txt['tfa_mode_disabled'],
				1 => Lang::$txt['tfa_mode_enabled'],
			] + (empty(User::$me->tfa_secret) ? [] : [
				2 => Lang::$txt['tfa_mode_forced'],
			]) + (empty(User::$me->tfa_secret) ? [] : [
				3 => Lang::$txt['tfa_mode_forcedall'],
			]), 'subtext' => Lang::$txt['tfa_mode_subtext'] . (empty(User::$me->tfa_secret) ? '<br><strong>' . Lang::$txt['tfa_mode_forced_help'] . '</strong>' : ''), 'tfa_mode'],
		];

		IntegrationHook::call('integrate_cookie_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the security sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function securityConfigVars(): array
	{
		$config_vars = [
			['int', 'failed_login_threshold'],
			['int', 'loginHistoryDays', 'subtext' => Lang::$txt['zero_to_disable']],
			'',

			['check', 'securityDisable'],
			['check', 'securityDisable_moderate'],
			'',

			// Reactive on email, and approve on delete
			['check', 'send_validation_onChange'],
			['check', 'approveAccountDeletion'],
			'',

			// Password strength.
			[
				'select',
				'password_strength',
				[
					Lang::$txt['setting_password_strength_low'],
					Lang::$txt['setting_password_strength_medium'],
					Lang::$txt['setting_password_strength_high'],
				],
			],
			['check', 'enable_password_conversion'],
			'',

			// Reporting of personal messages?
			['check', 'enableReportPM'],
			'',

			['check', 'allow_cors'],
			['check', 'allow_cors_credentials'],
			['text', 'cors_domains'],
			['text', 'cors_headers'],
			'',

			[
				'select',
				'frame_security',
				[
					'SAMEORIGIN' => Lang::$txt['setting_frame_security_SAMEORIGIN'],
					'DENY' => Lang::$txt['setting_frame_security_DENY'],
					'DISABLE' => Lang::$txt['setting_frame_security_DISABLE'],
				],
			],
			'',

			[
				'select',
				'proxy_ip_header',
				[
					'disabled' => Lang::$txt['setting_proxy_ip_header_disabled'],
					'autodetect' => Lang::$txt['setting_proxy_ip_header_autodetect'],
					'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For',
					'HTTP_CLIENT_IP' => 'Client-IP',
					'HTTP_X_REAL_IP' => 'X-Real-IP',
					'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP',
				],
			],
			['text', 'proxy_ip_servers'],
		];

		IntegrationHook::call('integrate_general_security_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the cache sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function cacheConfigVars(): array
	{
		// Detect all available optimizers
		$detectedCacheApis = CacheApi::detect();
		$apis_names = [];

		foreach ($detectedCacheApis as $class_name => $cache_api) {
			$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

			$apis_names[$class_name] = Lang::$txt[$class_name_txt_key . '_cache'] ?? $class_name;
		}

		// Set our values to show what, if anything, we found.
		if (empty($detectedCacheApis)) {
			Lang::$txt['cache_settings_message'] = '<strong class="alert">' . Lang::$txt['detected_no_caching'] . '</strong>';

			$cache_level = [Lang::$txt['cache_off']];
			$apis_names['none'] = Lang::$txt['cache_off'];
		} else {
			Lang::$txt['cache_settings_message'] = '<strong class="success">' . sprintf(Lang::$txt['detected_accelerators'], implode(', ', $apis_names)) . '</strong>';

			$cache_level = [Lang::$txt['cache_off'], Lang::$txt['cache_level1'], Lang::$txt['cache_level2'], Lang::$txt['cache_level3']];
		}

		// Define the variables we want to edit.
		$config_vars = [
			['', Lang::$txt['cache_settings_message'], '', 'desc'],
			['cache_enable', Lang::$txt['cache_enable'], 'file', 'select', $cache_level, 'cache_enable'],
			['cache_accelerator', Lang::$txt['cache_accelerator'], 'file', 'select', $apis_names],
		];

		// Some javascript to enable/disable certain settings if the option is not selected.
		Utils::$context['settings_post_javascript'] = '
			$(document).ready(function() {
				$("#cache_accelerator").change();
			});';

		IntegrationHook::call('integrate_modify_cache_settings', [&$config_vars]);

		// Maybe we have some additional settings from the selected accelerator.
		if (!empty($detectedCacheApis)) {
			foreach ($detectedCacheApis as $class_name_txt_key => $cache_api) {
				if (is_callable([$cache_api, 'cacheSettings'])) {
					$cache_api->cacheSettings($config_vars);
				}
			}
		}

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the export sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function exportConfigVars(): array
	{
		// Fill in a default value for this if it is missing.
		if (empty(Config::$modSettings['export_dir'])) {
			Config::$modSettings['export_dir'] = Config::$boarddir . DIRECTORY_SEPARATOR . 'exports';
		}

		/*
			Some paranoid hosts worry that the disk space functions pose a security
			risk. Usually these hosts just disable the functions and move on, which
			is fine. A rare few, however, are not only paranoid, but also think it'd
			be a "clever" security move to overload the disk space functions with
			custom code that intentionally delivers false information, which is
			idiotic and evil. At any rate, if the functions are unavailable or if
			they report obviously insane values, it's not possible to track disk
			usage correctly.
		 */
		$diskspace_disabled = (!function_exists('disk_free_space') || !function_exists('disk_total_space') || intval(@disk_total_space(file_exists(Config::$modSettings['export_dir']) ? Config::$modSettings['export_dir'] : Config::$boarddir)) < 1440);

		$config_vars = [
			['text', 'export_dir', 40],
			['int', 'export_expiry', 'subtext' => Lang::$txt['zero_to_disable'], 'postinput' => Lang::$txt['days_word']],
			['int', 'export_min_diskspace_pct', 'postinput' => '%', 'max' => 80, 'disabled' => $diskspace_disabled],
			['int', 'export_rate', 'min' => 5, 'max' => 500, 'step' => 5, 'subtext' => Lang::$txt['export_rate_desc']],
		];

		IntegrationHook::call('integrate_export_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the loads sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function loadBalancingConfigVars(): array
	{
		// Start with a simple checkbox.
		$config_vars = [
			['check', 'loadavg_enable', 'disabled' => self::getLoadAverageDisabled()],
		];

		// Loop through the settings.
		foreach (self::LOADAVG_DEFAULT_VALUES as $name => $value) {
			// Use the default value if the setting isn't set yet.
			$value = !isset(Config::$modSettings[$name]) ? $value : Config::$modSettings[$name];

			$config_vars[] = ['float', $name, 'value' => $value, 'disabled' => self::getLoadAverageDisabled()];
		}

		IntegrationHook::call('integrate_loadavg_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Figures out whether we can calculate load averages on this server.
	 *
	 * @return bool If true, we can't calculate load averages.
	 */
	public static function getLoadAverageDisabled(): bool
	{
		if (isset(self::$loadAverageDisabled)) {
			return self::$loadAverageDisabled;
		}

		// Assume we can't until proven otherwise.
		self::$loadAverageDisabled = true;

		// Windows, so we can't.
		if (DIRECTORY_SEPARATOR === '\\') {
			return self::$loadAverageDisabled;
		}

		// Most Linux distros offer a nice file that we can read.
		Config::$modSettings['load_average'] = @file_get_contents('/proc/loadavg');

		if (!empty(Config::$modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', Config::$modSettings['load_average'], $matches) !== 0) {
			Config::$modSettings['load_average'] = (float) $matches[1];
		}
		// On both Linux and Unix (e.g. macOS), we can we can check shell_exec('uptime').
		elseif ((Config::$modSettings['load_average'] = @shell_exec('uptime')) !== null && preg_match('~load averages?: (\d+\.\d+)~i', Config::$modSettings['load_average'], $matches) !== 0) {
			Config::$modSettings['load_average'] = (float) $matches[1];
		}
		// No shell_exec('uptime') and no /proc/loadavg, so we can't check.
		else {
			unset(Config::$modSettings['load_average']);
		}

		if (!empty(Config::$modSettings['load_average']) || (isset(Config::$modSettings['load_average']) && Config::$modSettings['load_average'] === 0.0)) {
			self::$loadAverageDisabled = false;
		}

		return self::$loadAverageDisabled;
	}

	/**
	 * Helper function, it sets up the context for the manage server settings.
	 * - The basic usage of the six numbered key fields are
	 * - array (0 ,1, 2, 3, 4, 5
	 *		0 variable name - the name of the saved variable
	 *		1 label - the text to show on the settings page
	 *		2 saveto - file or db, where to save the variable name - value pair
	 *		3 type - type of data to save, int, float, text, check
	 *		4 size - false or field size
	 *		5 help - '' or helptxt variable name
	 *	)
	 *
	 * the following named keys are also permitted
	 * 'disabled' => A string of code that will determine whether or not the setting should be disabled
	 * 'postinput' => Text to display after the input field
	 * 'preinput' => Text to display before the input field
	 * 'subtext' => Additional descriptive text to display under the field's label
	 * 'min' => minimum allowed value (for int/float). Defaults to 0 if not set.
	 * 'max' => maximum allowed value (for int/float)
	 * 'step' => how much to increment/decrement the value by (only for int/float - mostly used for float values).
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	public static function prepareServerSettingsContext(&$config_vars)
	{
		if (!empty(Utils::$context['settings_not_writable'])) {
			Utils::$context['settings_message'] = [
				'label' => Lang::$txt['settings_not_writable'],
				'tag' => 'div',
				'class' => 'centertext strong',
			];
		}

		if (isset($_SESSION['adm-save'])) {
			if ($_SESSION['adm-save'] === true) {
				Utils::$context['saved_successful'] = true;
			} else {
				Utils::$context['saved_failed'] = $_SESSION['adm-save'];
			}

			unset($_SESSION['adm-save']);
		}

		Utils::$context['config_vars'] = [];

		foreach ($config_vars as $identifier => $config_var) {
			if (!is_array($config_var) || !isset($config_var[1])) {
				Utils::$context['config_vars'][] = $config_var;
			} else {
				$varname = $config_var[0];
				global $$varname;

				// Set the subtext in case it's part of the label.
				// @todo Temporary. Preventing divs inside label tags.
				$divPos = strpos($config_var[1], '<div');
				$subtext = '';

				if ($divPos !== false) {
					$subtext = preg_replace('~</?div[^>]*>~', '', substr($config_var[1], $divPos));
					$config_var[1] = substr($config_var[1], 0, $divPos);
				}

				Utils::$context['config_vars'][$config_var[0]] = [
					'label' => $config_var[1],
					'help' => $config_var[5] ?? '',
					'type' => $config_var[3],
					'size' => !empty($config_var[4]) && !is_array($config_var[4]) ? $config_var[4] : 0,
					'data' => isset($config_var[4]) && is_array($config_var[4]) && $config_var[3] != 'select' ? $config_var[4] : [],
					'name' => $config_var[0],
					'value' => $config_var[2] == 'file' ? Utils::htmlspecialchars($$varname) : (isset(Config::$modSettings[$config_var[0]]) ? Utils::htmlspecialchars(Config::$modSettings[$config_var[0]]) : (in_array($config_var[3], ['int', 'float']) ? 0 : '')),
					'disabled' => !empty(Utils::$context['settings_not_writable']) || !empty($config_var['disabled']),
					'invalid' => false,
					'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : $subtext,
					'javascript' => '',
					'preinput' => !empty($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => !empty($config_var['postinput']) ? $config_var['postinput'] : '',
				];

				// Handle min/max/step if necessary
				if ($config_var[3] == 'int' || $config_var[3] == 'float') {
					// Default to a min of 0 if one isn't set
					if (isset($config_var['min'])) {
						Utils::$context['config_vars'][$config_var[0]]['min'] = $config_var['min'];
					} else {
						Utils::$context['config_vars'][$config_var[0]]['min'] = 0;
					}

					if (isset($config_var['max'])) {
						Utils::$context['config_vars'][$config_var[0]]['max'] = $config_var['max'];
					}

					if (isset($config_var['step'])) {
						Utils::$context['config_vars'][$config_var[0]]['step'] = $config_var['step'];
					}
				}

				// If this is a select box handle any data.
				if (!empty($config_var[4]) && is_array($config_var[4])) {
					// If it's associative
					$config_values = array_values($config_var[4]);

					if (isset($config_values[0]) && is_array($config_values[0])) {
						Utils::$context['config_vars'][$config_var[0]]['data'] = $config_var[4];
					} else {
						foreach ($config_var[4] as $key => $item) {
							Utils::$context['config_vars'][$config_var[0]]['data'][] = [$key, $item];
						}
					}
				}
			}
		}

		// Two tokens because saving these settings requires both ACP::saveSettings() and ACP::saveDBSettings()
		SecurityToken::create('admin-ssc');
		SecurityToken::create('admin-dbsc');
	}

	/**
	 * Checks whether it is safe to write to Settings.php.
	 *
	 * Sets self::$settings_not_writable and self::$settings_backup_fail.
	 *
	 * @return bool True if Settings.php and Settings_bak.php are both writable.
	 */
	public static function checkSettingsFileWriteSafe(): bool
	{
		self::$settings_not_writable = !is_writable(SMF_SETTINGS_FILE);

		if (self::$settings_not_writable) {
			return false;
		}

		if (!file_exists(SMF_SETTINGS_BACKUP_FILE)) {
			@touch(SMF_SETTINGS_BACKUP_FILE);
		}

		self::$settings_backup_fail = !is_writable(SMF_SETTINGS_BACKUP_FILE);

		return !self::$settings_backup_fail;
	}

	/**
	 * Backward compatibility wrapper for the general sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyGeneralSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::generalConfigVars();
		}

		self::load();
		self::$obj->subaction = 'general';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the database sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyDatabaseSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::databaseConfigVars();
		}

		self::load();
		self::$obj->subaction = 'database';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the cookie sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyCookieSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::cookieConfigVars();
		}

		self::load();
		self::$obj->subaction = 'cookie';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the security sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyGeneralSecuritySettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::securityConfigVars();
		}

		self::load();
		self::$obj->subaction = 'security';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the cache sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyCacheSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::cacheConfigVars();
		}

		self::load();
		self::$obj->subaction = 'cache';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the export sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyExportSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::exportConfigVars();
		}

		self::load();
		self::$obj->subaction = 'export';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the loads sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyLoadBalancingSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::loadBalancingConfigVars();
		}

		self::load();
		self::$obj->subaction = 'loads';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the phpinfo sub-action.
	 */
	public static function showPHPinfoSettings(): void
	{
		self::load();
		self::$obj->subaction = 'phpinfo';
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
		Lang::load('ManageSettings');

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['admin_server_settings'],
			'help' => 'serversettings',
			'description' => Lang::$txt['admin_basic_settings'],
		];

		IntegrationHook::call('integrate_server_settings', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Align URLs with SSL Setting.
	 *
	 * If force_ssl has changed, ensure all URLs are aligned with the new setting.
	 * This includes:
	 *     - Config::$boardurl
	 *     - Config::$modSettings['smileys_url']
	 *     - Config::$modSettings['avatar_url']
	 *     - Config::$modSettings['custom_avatar_url'] - if found
	 *     - theme_url - all entries in the themes table
	 *     - images_url - all entries in the themes table
	 *
	 * This function will NOT overwrite URLs that are not subfolders of $boardurl.
	 * The admin must have pointed those somewhere else on purpose, so they must be
	 * updated manually.
	 *
	 * A word of caution: You can't trust the http/https scheme reflected for these
	 * URLs in Config::* or in Config::$modSettings. This is because SMF may change
	 * them in memory to comply with the force_ssl setting - a soft redirect may be
	 * in effect. Thus, conditional updates to these values do not work. You gotta
	 * just brute force overwrite them based on force_ssl.
	 *
	 * @param int $new_force_ssl is the current force_ssl setting.
	 */
	protected function alignURLsWithSSLSetting($new_force_ssl = 0)
	{
		// Check Config::$boardurl
		if (!empty($new_force_ssl)) {
			$newval = strtr(Config::$boardurl, ['http://' => 'https://']);
		} else {
			$newval = strtr(Config::$boardurl, ['https://' => 'http://']);
		}

		Config::updateSettingsFile(['boardurl' => $newval]);

		$new_settings = [];

		// Check $smileys_url, but only if it points to a subfolder of Config::$boardurl
		if ($this->boardurlMatch(Config::$modSettings['smileys_url'])) {
			if (!empty($new_force_ssl)) {
				$newval = strtr(Config::$modSettings['smileys_url'], ['http://' => 'https://']);
			} else {
				$newval = strtr(Config::$modSettings['smileys_url'], ['https://' => 'http://']);
			}

			$new_settings['smileys_url'] = $newval;
		}

		// Check $avatar_url, but only if it points to a subfolder of Config::$boardurl
		if ($this->boardurlMatch(Config::$modSettings['avatar_url'])) {
			if (!empty($new_force_ssl)) {
				$newval = strtr(Config::$modSettings['avatar_url'], ['http://' => 'https://']);
			} else {
				$newval = strtr(Config::$modSettings['avatar_url'], ['https://' => 'http://']);
			}

			$new_settings['avatar_url'] = $newval;
		}

		// Check $custom_avatar_url, but only if it points to a subfolder of Config::$boardurl
		// This one had been optional in the past, make sure it is set first
		if (isset(Config::$modSettings['custom_avatar_url']) && $this->boardurlMatch(Config::$modSettings['custom_avatar_url'])) {
			if (!empty($new_force_ssl)) {
				$newval = strtr(Config::$modSettings['custom_avatar_url'], ['http://' => 'https://']);
			} else {
				$newval = strtr(Config::$modSettings['custom_avatar_url'], ['https://' => 'http://']);
			}

			$new_settings['custom_avatar_url'] = $newval;
		}

		// Save updates to the settings table
		if (!empty($new_settings)) {
			Config::updateModSettings($new_settings, true);
		}

		// Now we move onto the themes.
		// First, get a list of theme URLs...
		$request = Db::$db->query(
			'',
			'SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable in ({string:themeurl}, {string:imagesurl})
				AND id_member = {int:zero}',
			[
				'themeurl' => 'theme_url',
				'imagesurl' => 'images_url',
				'zero' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// First check to see if it points to a subfolder of Config::$boardurl
			if ($this->boardurlMatch($row['value'])) {
				if (!empty($new_force_ssl)) {
					$newval = strtr($row['value'], ['http://' => 'https://']);
				} else {
					$newval = strtr($row['value'], ['https://' => 'http://']);
				}

				Db::$db->query(
					'',
					'UPDATE {db_prefix}themes
					SET value = {string:theme_val}
					WHERE variable = {string:theme_var}
						AND id_theme = {string:theme_id}
						AND id_member = {int:zero}',
					[
						'theme_val' => $newval,
						'theme_var' => $row['variable'],
						'theme_id' => $row['id_theme'],
						'zero' => 0,
					],
				);
			}
		}
		Db::$db->free_result($request);
	}

	/**
	 * Config::$boardurl Match.
	 *
	 * Helper function to see if the url being checked is based off of Config::$boardurl.
	 * If not, it was overridden by the admin to some other value on purpose, and should not
	 * be stepped on by SMF when aligning URLs with the force_ssl setting.
	 * The site admin must change URLs that are not aligned with Config::$boardurl manually.
	 *
	 * @param string $url is the url to check.
	 * @return bool Returns true if the url is based off of Config::$boardurl (without the scheme), false if not
	 */
	protected function boardurlMatch($url = ''): bool
	{
		// Strip the schemes
		$urlpath = strtr($url, ['http://' => '', 'https://' => '']);
		$boardurlpath = strtr(Config::$boardurl, ['http://' => '', 'https://' => '']);

		// If leftmost portion of path matches boardurl, return true
		$result = strpos($urlpath, $boardurlpath);

		return $result === false || $result != 0 ? false : true;
	}

	/**
	 * Registers the site with the Simple Machines Stat collection. This function
	 * purposely does not use Config::updateModSettings() as it will be called shortly after
	 * this process completes by the saveSettings() function.
	 *
	 * @see SMStats() for more information.
	 * @link https://www.simplemachines.org/about/stats.php for more info.
	 *
	 */
	protected function registerSMStats()
	{
		// Already have a key?  Can't register again.
		if (!empty(Config::$modSettings['sm_stats_key'])) {
			return true;
		}

		$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);

		if (!$fp) {
			$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
		}

		if ($fp) {
			$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode(Config::$boardurl) . ' HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'Connection: Close' . "\r\n\r\n";
			fwrite($fp, $out);

			$return_data = '';

			while (!feof($fp)) {
				$return_data .= fgets($fp, 128);
			}

			fclose($fp);

			// Get the unique site ID.
			preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

			if (!empty($ID[1])) {
				Db::$db->insert(
					'replace',
					'{db_prefix}settings',
					['variable' => 'string', 'value' => 'string'],
					['sm_stats_key', $ID[1]],
					['variable'],
				);

				return true;
			}
		}

		return false;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Server::exportStatic')) {
	Server::exportStatic();
}

?>