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
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Msg;
use SMF\SecurityToken;
use SMF\TaskRunner;
use SMF\User;
use SMF\Utils;

/**
 * This class contains all the administration settings for topics and posts.
 */
class Posts implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManagePostSettings',
			'modifyPostSettings' => 'ModifyPostSettings',
			'modifyTopicSettings' => 'ModifyTopicSettings',
			'modifyDraftSettings' => 'ModifyDraftSettings',
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
	public string $subaction = 'posts';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'posts' => 'posts',
		'censor' => 'censor',
		'topics' => 'topics',
		'drafts' => 'drafts',
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
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Shows an interface to set and test censored words.
	 * It uses the censor_vulgar, censor_proper, censorWholeWord, and censorIgnoreCase
	 * settings.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=censor.
	 *
	 * @uses template_edit_censored()
	 */
	public function censor(): void
	{
		if (!empty($_POST['save_censor'])) {
			// Make sure censoring is something they can do.
			User::$me->checkSession();
			SecurityToken::validate('admin-censor');

			$censored_vulgar = [];
			$censored_proper = [];

			// Rip it apart, then split it into two arrays.
			if (isset($_POST['censortext'])) {
				$_POST['censortext'] = explode("\n", strtr($_POST['censortext'], ["\r" => '']));

				foreach ($_POST['censortext'] as $c) {
					list($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
				}
			} elseif (isset($_POST['censor_vulgar'], $_POST['censor_proper'])) {
				if (is_array($_POST['censor_vulgar'])) {
					foreach ($_POST['censor_vulgar'] as $i => $value) {
						if (trim(strtr($value, '*', ' ')) == '') {
							unset($_POST['censor_vulgar'][$i], $_POST['censor_proper'][$i]);
						}
					}

					$censored_vulgar = $_POST['censor_vulgar'];
					$censored_proper = $_POST['censor_proper'];
				} else {
					$censored_vulgar = explode("\n", strtr($_POST['censor_vulgar'], ["\r" => '']));
					$censored_proper = explode("\n", strtr($_POST['censor_proper'], ["\r" => '']));
				}
			}

			// Set the new arrays and settings in the database.
			$updates = [
				'censor_vulgar' => Utils::normalize(implode("\n", $censored_vulgar)),
				'censor_proper' => Utils::normalize(implode("\n", $censored_proper)),
				'allow_no_censored' => empty($_POST['allow_no_censored']) ? '0' : '1',
				'censorWholeWord' => empty($_POST['censorWholeWord']) ? '0' : '1',
				'censorIgnoreCase' => empty($_POST['censorIgnoreCase']) ? '0' : '1',
			];

			IntegrationHook::call('integrate_save_censors', [&$updates]);

			Utils::$context['saved_successful'] = true;
			Config::updateModSettings($updates);
		}

		if (isset($_POST['censortest'])) {
			$censorText = Utils::htmlspecialchars($_POST['censortest'], ENT_QUOTES);
			Msg::preparsecode($censorText);
			Utils::$context['censor_test'] = strtr(Lang::censorText($censorText), ['"' => '&quot;']);
		}

		// Set everything up for the template to do its thang.
		$censor_vulgar = explode("\n", Config::$modSettings['censor_vulgar']);
		$censor_proper = explode("\n", Config::$modSettings['censor_proper']);

		Utils::$context['censored_words'] = [];

		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++) {
			if (empty($censor_vulgar[$i])) {
				continue;
			}

			// Skip it, it's either spaces or stars only.
			if (trim(strtr($censor_vulgar[$i], '*', ' ')) == '') {
				continue;
			}

			Utils::$context['censored_words'][Utils::htmlspecialchars(trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? Utils::htmlspecialchars($censor_proper[$i]) : '';
		}

		IntegrationHook::call('integrate_censors');

		// Since the "Allow users to disable the word censor" stuff was moved from a theme setting to a global one, we need this...
		Lang::load('Themes');

		Utils::$context['sub_template'] = 'edit_censored';
		Utils::$context['page_title'] = Lang::$txt['admin_censored_words'];

		SecurityToken::create('admin-censor');
	}

	/**
	 * Modify any setting related to posts and posting.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=posts.
	 */
	public function posts($return_config = false)
	{
		$config_vars = self::postConfigVars();

		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['manageposts_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// If we're changing the message length (and we are using MySQL) let's check the column is big enough.
			if (isset($_POST['max_messageLength']) && $_POST['max_messageLength'] != Config::$modSettings['max_messageLength'] && (Config::$db_type == 'mysql')) {
				$colData = Db::$db->list_columns('{db_prefix}messages', true);

				foreach ($colData as $column) {
					if ($column['name'] == 'body') {
						$body_type = $column['type'];
					}
				}

				if (isset($body_type) && ($_POST['max_messageLength'] > 65535 || $_POST['max_messageLength'] == 0) && $body_type == 'text') {
					ErrorHandler::fatalLang('convert_to_mediumtext', false, [Config::$scripturl . '?action=admin;area=maintain;sa=database']);
				}
			}

			// If we're changing the post preview length let's check its valid
			if (!empty($_POST['preview_characters'])) {
				$_POST['preview_characters'] = (int) min(max(0, $_POST['preview_characters']), 512);
			}

			IntegrationHook::call('integrate_save_post_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=postsettings;sa=posts');
		}

		// Final settings...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=postsettings;save;sa=posts';
		Utils::$context['settings_title'] = Lang::$txt['manageposts_settings'];

		// Prepare the settings...
		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Modify any setting related to topics.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=topics.
	 */
	public function topics($return_config = false)
	{
		$config_vars = self::topicConfigVars();

		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['manageposts_topic_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save'])) {
			User::$me->checkSession();
			IntegrationHook::call('integrate_save_topic_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=postsettings;sa=topics');
		}

		// Final settings...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=postsettings;save;sa=topics';
		Utils::$context['settings_title'] = Lang::$txt['manageposts_topic_settings'];

		// Prepare the settings...
		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Modify any setting related to drafts.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=drafts
	 */
	public function drafts($return_config = false)
	{
		$config_vars = self::draftConfigVars();

		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['managedrafts_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Saving them ?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Protect them from themselves.
			$_POST['drafts_autosave_frequency'] = !isset($_POST['drafts_autosave_frequency']) || $_POST['drafts_autosave_frequency'] < 30 ? 30 : $_POST['drafts_autosave_frequency'];

			// Also disable the scheduled task if we're not using it.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}scheduled_tasks
				SET disabled = {int:disabled}
				WHERE task = {string:task}',
				[
					'disabled' => !empty($_POST['drafts_keep_days']) ? 0 : 1,
					'task' => 'remove_old_drafts',
				],
			);

			TaskRunner::calculateNextTrigger();

			// Save everything else and leave.
			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=postsettings;sa=drafts');
		}

		// Some JavaScript to enable / disable the frequency input box.
		Utils::$context['settings_post_javascript'] = '
			function toggle()
			{
				$("#drafts_autosave_frequency").prop("disabled", !($("#drafts_autosave_enabled").prop("checked")));
			};
			toggle();

			$("#drafts_autosave_enabled").click(function() { toggle(); });
		';

		// Final settings...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=postsettings;sa=drafts;save';
		Utils::$context['settings_title'] = Lang::$txt['managedrafts_settings'];

		// Prepare the settings...
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
	 * Gets configuration variables for the posts sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function postConfigVars(): array
	{
		// All the settings...
		$config_vars = [
			// Simple post options...
			['check', 'removeNestedQuotes'],
			['check', 'disable_wysiwyg'],
			['check', 'additional_options_collapsable'],
			['check', 'guest_post_no_email'],
			'',

			// Posting limits...
			['int', 'max_messageLength', 'subtext' => Lang::$txt['max_messageLength_zero'], 'postinput' => Lang::$txt['manageposts_characters']],
			['int', 'topicSummaryPosts', 'postinput' => Lang::$txt['manageposts_posts']],
			'',

			// Posting time limits...
			['int', 'spamWaitTime', 'postinput' => Lang::$txt['manageposts_seconds']],
			['int', 'edit_wait_time', 'postinput' => Lang::$txt['manageposts_seconds']],
			['int', 'edit_disable_time', 'subtext' => Lang::$txt['zero_to_disable'], 'postinput' => Lang::$txt['manageposts_minutes']],
			'',

			// Automagic image resizing.
			['int', 'max_image_width', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => Lang::$txt['zero_for_no_limit']],
			'',

			// First & Last message preview lengths
			['int', 'preview_characters', 'subtext' => Lang::$txt['zero_to_disable'], 'postinput' => Lang::$txt['preview_characters_units']],

			// Quote expand
			['int', 'quote_expand', 'subtext' => Lang::$txt['zero_to_disable'], 'postinput' => Lang::$txt['quote_expand_pixels_units']],
		];

		IntegrationHook::call('integrate_modify_post_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the topics sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function topicConfigVars(): array
	{
		// Here are all the topic settings.
		$config_vars = [
			// Some simple bools...
			['check', 'enableParticipation'],
			'',

			// Pagination etc...
			['int', 'oldTopicDays', 'postinput' => Lang::$txt['manageposts_days'], 'subtext' => Lang::$txt['zero_to_disable']],
			['int', 'defaultMaxTopics', 'postinput' => Lang::$txt['manageposts_topics']],
			['int', 'defaultMaxMessages', 'postinput' => Lang::$txt['manageposts_posts']],
			['check', 'disable_print_topic'],
			'',

			// All, next/prev...
			['int', 'enableAllMessages', 'postinput' => Lang::$txt['manageposts_posts'], 'subtext' => Lang::$txt['enableAllMessages_zero']],
			['check', 'disableCustomPerPage'],
			['check', 'enablePreviousNext'],
			'',

			// Topic related settings (show gender icon/avatars etc...)
			['check', 'subject_toggle'],
			['check', 'show_modify'],
			['check', 'show_profile_buttons'],
			['check', 'show_user_images'],
			['check', 'show_blurb'],
			['check', 'hide_post_group', 'subtext' => Lang::$txt['hide_post_group_desc']],
			'',

			// First & Last message preview lengths
			['int', 'preview_characters', 'subtext' => Lang::$txt['zero_to_disable'], 'postinput' => Lang::$txt['preview_characters_units']],
			['check', 'message_index_preview_first', 'subtext' => Lang::$txt['message_index_preview_first_desc']],
		];

		IntegrationHook::call('integrate_modify_topic_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets configuration variables for the drafts sub-action.
	 *
	 * @return array $config_vars for the sub-action.
	 */
	public static function draftConfigVars(): array
	{
		// Here are all the draft settings. A bit light for now, but we can add more :P
		$config_vars = [
			// Draft settings ...
			['check', 'drafts_post_enabled'],
			['check', 'drafts_pm_enabled'],
			['check', 'drafts_show_saved_enabled', 'subtext' => Lang::$txt['drafts_show_saved_enabled_subnote']],
			['int', 'drafts_keep_days', 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['drafts_keep_days_subnote']],
			'',
			['check', 'drafts_autosave_enabled', 'subtext' => Lang::$txt['drafts_autosave_enabled_subnote']],
			['int', 'drafts_autosave_frequency', 'postinput' => Lang::$txt['manageposts_seconds'], 'subtext' => Lang::$txt['drafts_autosave_frequency_subnote']],
		];

		IntegrationHook::call('integrate_modify_draft_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the posts sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyPostSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::postConfigVars();
		}

		self::load();
		self::$obj->subaction = 'posts';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the topics sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyTopicSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::topicConfigVars();
		}

		self::load();
		self::$obj->subaction = 'topics';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the drafts sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyDraftSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::draftConfigVars();
		}

		self::load();
		self::$obj->subaction = 'drafts';
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
		// Make sure you can be here.
		User::$me->isAllowedTo('admin_forum');
		Lang::load('Drafts');

		Utils::$context['page_title'] = Lang::$txt['manageposts_title'];

		// Tabs for browsing the different post functions.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['manageposts_title'],
			'help' => 'posts_and_topics',
			'description' => Lang::$txt['manageposts_description'],
			'tabs' => [
				'posts' => [
					'description' => Lang::$txt['manageposts_settings_description'],
				],
				'censor' => [
					'description' => Lang::$txt['admin_censored_desc'],
				],
				'topics' => [
					'description' => Lang::$txt['manageposts_topic_settings_description'],
				],
				'drafts' => [
					'description' => Lang::$txt['managedrafts_settings_description'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_posts', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Posts::exportStatic')) {
	Posts::exportStatic();
}

?>