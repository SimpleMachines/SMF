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
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Parser;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Uuid;

/**
 * Sends mail via SendMail
 */
class VerificationQuestions extends AntiSpamAgent implements AntiSpamInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 *
	 */
	public int $number_questions;

	/**
	 * @var array
	 *
	 *
	 */
	public array $questions;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 *
	 */
	protected array $question_ids;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		return $this->number_questions > 0;
	}

	/**
	 *
	 */
	public function create(?array $options = []): bool
	{
		$this->question_ids = !empty($_SESSION[$this->sessionID()]['q']) ? $_SESSION[$this->sessionID()]['q'] : [];

		// We already have some.
		if (!empty($this->question_ids)) {
			return true;
		}

		// Attempt to try the current page's language, followed by the user's preference, followed by the site default.
		$possible_langs = [];

		if (isset($_SESSION['language'])) {
			$possible_langs[] = strtr($_SESSION['language'], ['-utf8' => '']);
		}

		if (!empty(User::$me->language)) {
			$possible_langs[] = User::$me->language;
		}

		$possible_langs[] = Lang::$default;

		$this->question_ids = [];

		foreach ($possible_langs as $lang) {
			$lang = strtr($lang, ['-utf8' => '']);

			if (isset(Config::$modSettings['question_id_cache']['langs'][$lang])) {
				// If we find questions for this, grab the ids from this language's ones, randomize the array and take just the number we need.
				$this->question_ids = Config::$modSettings['question_id_cache']['langs'][$lang];

				shuffle($this->question_ids);

				$this->question_ids = \array_slice($this->question_ids, 0, $this->number_questions);

				break;
			}
		}

		$this->setQuestions();

		return true;
	}

	public function html(): void
	{
		// Where in the question array is this question?
		foreach ($this->questions as $qIndex => $q) {
			echo '
				<div class="smalltext">
					' . $q['q'] . ':<br>
					<input type="text" name="' . $this->form_id . '_vv[q][' . $q['id'] . ']" size="30" value="' . $q['a'] . '" ' . ($q['is_error'] ? 'style="border: 1px red solid;"' : '') . ' tabindex="' . Utils::$context['tabindex']++ . '" required>
				</div>';
		}
	}

	/**
	 *
	 */
	public function validate(?array $options = []): array|bool
	{
		$incorrectQuestions = [];

		foreach ($_SESSION[$this->sessionID()]['q'] as $q) {
			// We don't have this question any more, thus no answers.
			if (!isset(Config::$modSettings['question_id_cache']['questions'][$q])) {
				continue;
			}

			// We have our question but it might have multiple answers.
			// First, did they actually answer this question?
			if (!isset($_REQUEST[$this->sessionID()]['q'][$q]) || trim($_REQUEST[$this->sessionID()]['q'][$q]) == '') {
				$incorrectQuestions[] = $q;

				continue;
			}

			// Second, is their answer in the list of possible answers?
			$given_answer = trim(Utils::htmlspecialchars(Utils::convertCase($_REQUEST[$this->sessionID()]['q'][$q], 'fold')));

			if (!\in_array($given_answer, Config::$modSettings['question_id_cache']['questions'][$q]['answers'])) {
				$incorrectQuestions[] = $q;
			}
		}

		if (!empty($incorrectQuestions)) {
			return ['wrong_verification_answer'];
		}

		return true;
	}

	public function refresh(bool $only_if_necessary, bool $do_test): bool
	{
		$should_refresh = parent::shouldRefresh($only_if_necessary, $do_test);

		// This can also force a fresh, although unlikely.
		if ($this->number_questions > 0 && empty($_SESSION[$this->sessionID()]['q'])) {
			$should_refresh = true;
		}

		if ($should_refresh) {
			$this->setQuestions();
		}

		return $should_refresh;
	}

	public function __construct(string $form_id, Uuid $agent_id)
	{
		parent::__construct($form_id, $agent_id);

		$this->number_questions = $options['override_qs'] ?? (!empty(Config::$modSettings['qa_verification_number']) ? (int) Config::$modSettings['qa_verification_number'] : 0);
		$this->questions = [];

		$this->loadQuestionCache();
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function getConfigVars(array &$config_vars): void
	{
		$config_vars = array_merge($config_vars, [
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			['title', 'setup_verification_questions'],
			['desc', 'setup_verification_questions_desc'],
			[
				'int',
				'qa_verification_number',
				'subtext' => Lang::getTxt('setting_qa_verification_number_desc', file: 'ManageSettings'),
			],
			['callback', 'question_answer_list'],
		]);

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

			Utils::$context['settings_insert_above'] .= '<div class="noticebox">' . Lang::getTxt('question_not_defined', Utils::$context['languages'][Lang::$default], file: 'ManageSettings') . '</div>';
		}

		// Thirdly, push some JavaScript for the form to make it work.
		$nextrow = !empty(Utils::$context['question_answers']) ? max(array_keys(Utils::$context['question_answers'])) + 1 : 1;
		$setup_verification_add_answer = Utils::escapeJavaScript(Lang::getTxt('setup_verification_add_answer', file: 'ManageSettings'));
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

		if (isset($_GET['save'])) {
		}
	}

	public static function saveConfigVars(): void
	{
		// Handle verification questions.
		$changes = [
			'insert' => [],
			'replace' => [],
			'delete' => [],
		];

		$qs_per_lang = [];

		foreach (Utils::$context['qa_languages'] as $lang_id => $dummy) {
			// If we had some questions for this language before, but don't now, delete everything from that language.
			if ((!isset($_POST['question'][$lang_id]) || !\is_array($_POST['question'][$lang_id])) && !empty(Utils::$context['qa_by_lang'][$lang_id])) {
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
					if (!isset($_POST['answer'][$lang_id][$q_id]) || !\is_array($_POST['answer'][$lang_id][$q_id])) {
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

		CacheApi::put('verificationQuestions', null, 300);

	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets the verification questions and answers for this instance.
	 */
	protected function setQuestions(): void
	{
		// Have we got some questions to load?
		if (empty($this->question_ids)) {
			return;
		}

		$_SESSION[$this->sessionID()]['q'] = [];

		foreach ($this->question_ids as $q) {
			// Bit of a shortcut this.
			$row = &Config::$modSettings['question_id_cache']['questions'][$q];

			if (empty($row['question'])) {
				continue;
			}

			$this->questions[] = [
				'id' => $q,
				'q' => Utils::adjustHeadingLevels(Parser::transform($row['question'], options: ['no_paragraphs' => true]), null),
				'is_error' => !empty($incorrectQuestions) && \in_array($q, $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->sessionID()], $_REQUEST[$this->sessionID()]['q'], $_REQUEST[$this->sessionID()]['q'][$q]) ? Utils::htmlspecialchars($_REQUEST[$this->sessionID()]['q'][$q]) : '',
			];

			$_SESSION[$this->sessionID()]['q'][] = $q;
		}
	}

	/**
	 * Loads the cache of verification questions and answers.
	 */
	protected function loadQuestionCache(): void
	{
		if (empty($this->number_questions) || !empty(Config::$modSettings['question_id_cache'])) {
			return;
		}

		if ((Config::$modSettings['question_id_cache'] = CacheApi::get('verificationQuestions', 300)) == null) {
			Config::$modSettings['question_id_cache'] = [
				'questions' => [],
				'langs' => [],
			];

			$request = Db::$db->query(
				'SELECT id_question, lngfile, question, answers
				FROM {db_prefix}qanda',
				[],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$id_question = $row['id_question'];

				unset($row['id_question']);

				$row['answers'] = (array) Utils::jsonDecode($row['answers'], true);

				foreach ($row['answers'] as $k => $v) {
					$row['answers'][$k] = Utils::convertCase($v, 'fold');
				}

				Config::$modSettings['question_id_cache']['questions'][$id_question] = $row;
				Config::$modSettings['question_id_cache']['langs'][$row['lngfile']][] = $id_question;
			}
			Db::$db->free_result($request);

			CacheApi::put('verificationQuestions', Config::$modSettings['question_id_cache'], 300);
		}
	}
}
