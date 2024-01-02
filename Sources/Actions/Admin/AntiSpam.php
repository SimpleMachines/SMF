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
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Handles anti-spam settings.
 */
class AntiSpam implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'modifyAntispamSettings' => 'ModifyAntispamSettings',
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
		$config_vars = self::getConfigVars();

		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		// Generate a sample registration image.
		Utils::$context['verification_image_href'] = Config::$scripturl . '?action=verificationcode;rand=' . bin2hex(random_bytes(16));

		// Firstly, figure out what languages we're dealing with, and do a little processing for the form's benefit.
		Lang::get();
		Utils::$context['qa_languages'] = [];

		foreach (Utils::$context['languages'] as $lang_id => $lang) {
			$lang_id = strtr($lang_id, ['-utf8' => '']);
			$lang['name'] = strtr($lang['name'], ['-utf8' => '']);
			Utils::$context['qa_languages'][$lang_id] = $lang;
		}

		// Secondly, load any questions we currently have.
		Utils::$context['question_answers'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_question, lngfile, question, answers
			FROM {db_prefix}qanda',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$lang = strtr($row['lngfile'], ['-utf8' => '']);

			Utils::$context['question_answers'][$row['id_question']] = [
				'lngfile' => $lang,
				'question' => $row['question'],
				'answers' => (array) Utils::jsonDecode($row['answers'], true),
			];

			Utils::$context['qa_by_lang'][$lang][] = $row['id_question'];
		}
		Db::$db->free_result($request);

		if (empty(Utils::$context['qa_by_lang'][strtr(Lang::$default, ['-utf8' => ''])]) && !empty(Utils::$context['question_answers'])) {
			if (empty(Utils::$context['settings_insert_above'])) {
				Utils::$context['settings_insert_above'] = '';
			}

			Utils::$context['settings_insert_above'] .= '<div class="noticebox">' . sprintf(Lang::$txt['question_not_defined'], Utils::$context['languages'][Lang::$default]['name']) . '</div>';
		}

		// Thirdly, push some JavaScript for the form to make it work.
		$nextrow = !empty(Utils::$context['question_answers']) ? max(array_keys(Utils::$context['question_answers'])) + 1 : 1;
		$setup_verification_add_answer = Utils::JavaScriptEscape(Lang::$txt['setup_verification_add_answer']);
		$default_lang = strtr(Lang::$default, ['-utf8' => '']);

		Theme::addInlineJavaScript(<<<END
				var nextrow = {$nextrow};
				$(".qa_link a").click(function() {
					var id = $(this).parent().attr("id").substring(6);
					$("#qa_fs_" + id).show();
					$(this).parent().hide();
				});
				$(".qa_fieldset legend a").click(function() {
					var id = $(this).closest("fieldset").attr("id").substring(6);
					$("#qa_dt_" + id).show();
					$(this).closest("fieldset").hide();
				});
				$(".qa_add_question a").click(function() {
					var id = $(this).closest("fieldset").attr("id").substring(6);
					$('<dt><input type="text" name="question[' + id + '][' + nextrow + ']" value="" size="50" class="verification_question"></dt><dd><input type="text" name="answer[' + id + '][' + nextrow + '][]" value="" size="50" class="verification_answer" / ><div class="qa_add_answer"><a href="javascript:void(0);">[ ' + {$setup_verification_add_answer} + ' ]</a></div></dd>').insertBefore($(this).parent());
					nextrow++;
				});
				$(".qa_fieldset ").on("click", ".qa_add_answer a", function() {
					var attr = $(this).closest("dd").find(".verification_answer:last").attr("name");
					$('<input type="text" name="' + attr + '" value="" size="50" class="verification_answer">').insertBefore($(this).closest("div"));
					return false;
				});
				$("#qa_dt_{$default_lang} a").click();
			END, true);

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

			// Handle verification questions.
			$changes = [
				'insert' => [],
				'replace' => [],
				'delete' => [],
			];

			$qs_per_lang = [];

			foreach (Utils::$context['qa_languages'] as $lang_id => $dummy) {
				// If we had some questions for this language before, but don't now, delete everything from that language.
				if ((!isset($_POST['question'][$lang_id]) || !is_array($_POST['question'][$lang_id])) && !empty(Utils::$context['qa_by_lang'][$lang_id])) {
					$changes['delete'] = array_merge($changes['delete'], Utils::$context['qa_by_lang'][$lang_id]);
				}

				// Now step through and see if any existing questions no longer exist.
				if (!empty(Utils::$context['qa_by_lang'][$lang_id])) {
					foreach (Utils::$context['qa_by_lang'][$lang_id] as $q_id) {
						if (empty($_POST['question'][$lang_id][$q_id])) {
							$changes['delete'][] = $q_id;
						}
					}
				}

				// Now let's see if there are new questions or ones that need updating.
				if (isset($_POST['question'][$lang_id])) {
					foreach ($_POST['question'][$lang_id] as $q_id => $question) {
						// Ignore junky ids.
						$q_id = (int) $q_id;

						if ($q_id <= 0) {
							continue;
						}

						// Check the question isn't empty (because they want to delete it?)
						if (empty($question) || trim($question) == '') {
							if (isset(Utils::$context['question_answers'][$q_id])) {
								$changes['delete'][] = $q_id;
							}

							continue;
						}

						$question = Utils::htmlspecialchars(trim($question));

						// Get the answers. Firstly check there actually might be some.
						if (!isset($_POST['answer'][$lang_id][$q_id]) || !is_array($_POST['answer'][$lang_id][$q_id])) {
							if (isset(Utils::$context['question_answers'][$q_id])) {
								$changes['delete'][] = $q_id;
							}

							continue;
						}

						// Now get them and check that they might be viable.
						$answers = [];

						foreach ($_POST['answer'][$lang_id][$q_id] as $answer) {
							if (!empty($answer) && trim($answer) !== '') {
								$answers[] = Utils::htmlspecialchars(trim($answer));
							}
						}

						if (empty($answers)) {
							if (isset(Utils::$context['question_answers'][$q_id])) {
								$changes['delete'][] = $q_id;
							}

							continue;
						}

						$answers = Utils::jsonEncode($answers);

						// At this point we know we have a question and some answers. What are we doing with it?
						if (!isset(Utils::$context['question_answers'][$q_id])) {
							// New question. Now, we don't want to randomly consume ids, so we'll set those, rather than trusting the browser's supplied ids.
							$changes['insert'][] = [$lang_id, $question, $answers];
						} else {
							// It's an existing question. Let's see what's changed, if anything.
							if ($lang_id != Utils::$context['question_answers'][$q_id]['lngfile'] || $question != Utils::$context['question_answers'][$q_id]['question'] || $answers != Utils::$context['question_answers'][$q_id]['answers']) {
								$changes['replace'][$q_id] = ['lngfile' => $lang_id, 'question' => $question, 'answers' => $answers];
							}
						}

						if (!isset($qs_per_lang[$lang_id])) {
							$qs_per_lang[$lang_id] = 0;
						}
						$qs_per_lang[$lang_id]++;
					}
				}
			}

			// OK, so changes?
			if (!empty($changes['delete'])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}qanda
					WHERE id_question IN ({array_int:questions})',
					[
						'questions' => $changes['delete'],
					],
				);
			}

			if (!empty($changes['replace'])) {
				foreach ($changes['replace'] as $q_id => $question) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}qanda
						SET lngfile = {string:lngfile},
							question = {string:question},
							answers = {string:answers}
						WHERE id_question = {int:id_question}',
						[
							'id_question' => $q_id,
							'lngfile' => $question['lngfile'],
							'question' => $question['question'],
							'answers' => $question['answers'],
						],
					);
				}
			}

			if (!empty($changes['insert'])) {
				Db::$db->insert(
					'insert',
					'{db_prefix}qanda',
					['lngfile' => 'string-50', 'question' => 'string-255', 'answers' => 'string-65534'],
					$changes['insert'],
					['id_question'],
				);
			}

			// Lastly, the count of messages needs to be no more than the lowest number of questions for any one language.
			$count_questions = empty($qs_per_lang) ? 0 : min($qs_per_lang);

			if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions) {
				$_POST['qa_verification_number'] = $count_questions;
			}

			IntegrationHook::call('integrate_save_spam_settings', [&$save_vars]);

			// Now save.
			ACP::saveDBSettings($save_vars);
			$_SESSION['adm-save'] = true;

			CacheApi::put('verificationQuestions', null, 300);

			Utils::redirectexit('action=admin;area=antispam');
		}

		$character_range = array_merge(range('A', 'H'), ['K', 'M', 'N', 'P', 'R'], range('T', 'Y'));
		$_SESSION['visual_verification_code'] = '';

		for ($i = 0; $i < 6; $i++) {
			$_SESSION['visual_verification_code'] .= $character_range[array_rand($character_range)];
		}

		// Some javascript for CAPTCHA.
		Utils::$context['settings_post_javascript'] = '';

		if (Utils::$context['use_graphic_library']) {
			Utils::$context['settings_post_javascript'] .= '
			function refreshImages()
			{
				var imageType = document.getElementById(\'visual_verification_type\').value;
				document.getElementById(\'verification_image\').src = \'' . Utils::$context['verification_image_href'] . ';type=\' + imageType;
			}';
		}

		// Show the image itself, or text saying we can't.
		if (Utils::$context['use_graphic_library']) {
			$config_vars['vv']['postinput'] = '<br><img src="' . Utils::$context['verification_image_href'] . ';type=' . (empty(Config::$modSettings['visual_verification_type']) ? 0 : Config::$modSettings['visual_verification_type']) . '" alt="' . Lang::$txt['setting_image_verification_sample'] . '" id="verification_image"><br>';
		} else {
			$config_vars['vv']['postinput'] = '<br><span class="smalltext">' . Lang::$txt['setting_image_verification_nogd'] . '</span>';
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
		Utils::$context['settings_title'] = Lang::$txt['antispam_Settings'];
		Utils::$context['page_title'] = Lang::$txt['antispam_title'];
		Utils::$context['sub_template'] = 'show_settings';

		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['antispam_title'],
			'description' => Lang::$txt['antispam_Settings_desc'],
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
	 * Gets the configuration variables for the anti-spam area.
	 *
	 * @return array $config_vars for the anti-spam area.
	 */
	public static function getConfigVars(): array
	{
		Lang::load('Help');
		Lang::load('ManageSettings');

		// Generate a sample registration image.
		Utils::$context['use_graphic_library'] = in_array('gd', get_loaded_extensions());

		$config_vars = [
			['check', 'reg_verification'],
			['check', 'search_enable_captcha'],
			// This, my friend, is a cheat :p
			'guest_verify' => [
				'check',
				'guests_require_captcha',
				'subtext' => Lang::$txt['setting_guests_require_captcha_desc'],
			],
			[
				'int',
				'posts_require_captcha',
				'subtext' => Lang::$txt['posts_require_captcha_desc'],
				'min' => -1,
				'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}',
			],
			'',

			// PM Settings
			'pm1' => ['int', 'max_pm_recipients', 'subtext' => Lang::$txt['max_pm_recipients_note']],
			'pm2' => ['int', 'pm_posts_verification', 'subtext' => Lang::$txt['pm_posts_verification_note']],
			'pm3' => ['int', 'pm_posts_per_hour', 'subtext' => Lang::$txt['pm_posts_per_hour_note']],
			// Visual verification.
			['title', 'configure_verification_means'],
			['desc', 'configure_verification_means_desc'],
			'vv' => [
				'select',
				'visual_verification_type',
				[
					Lang::$txt['setting_image_verification_off'],
					Lang::$txt['setting_image_verification_vsimple'],
					Lang::$txt['setting_image_verification_simple'],
					Lang::$txt['setting_image_verification_medium'],
					Lang::$txt['setting_image_verification_high'],
					Lang::$txt['setting_image_verification_extreme'],
				],
				'subtext' => Lang::$txt['setting_visual_verification_type_desc'],
				'onchange' => Utils::$context['use_graphic_library'] ? 'refreshImages();' : '',
			],
			// reCAPTCHA
			['title', 'recaptcha_configure'],
			['desc', 'recaptcha_configure_desc', 'class' => 'windowbg'],
			['check', 'recaptcha_enabled', 'subtext' => Lang::$txt['recaptcha_enable_desc']],
			['text', 'recaptcha_site_key', 'subtext' => Lang::$txt['recaptcha_site_key_desc']],
			['text', 'recaptcha_secret_key', 'subtext' => Lang::$txt['recaptcha_secret_key_desc']],
			['select', 'recaptcha_theme', ['light' => Lang::$txt['recaptcha_theme_light'], 'dark' => Lang::$txt['recaptcha_theme_dark']]],
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			['title', 'setup_verification_questions'],
			['desc', 'setup_verification_questions_desc'],
			['int', 'qa_verification_number', 'subtext' => Lang::$txt['setting_qa_verification_number_desc']],
			['callback', 'question_answer_list'],
		];

		IntegrationHook::call('integrate_spam_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyAntispamSettings($return_config = false)
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
if (is_callable(__NAMESPACE__ . '\\AntiSpam::exportStatic')) {
	AntiSpam::exportStatic();
}

?>