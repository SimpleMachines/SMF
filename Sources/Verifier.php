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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Sets up the anti-spam control that tries to verify the user's humanity.
 *
 * Supports old-fashioned CAPTCHA, reCAPTCHA, and verification questions.
 */
class Verifier implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'create' => 'create_control_verification',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// Force a refresh after this many failed attempts.
	// This helps prevent bots from solving via brute force attacks.
	public const MAX_ATTEMPTS = 3;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * This editor's ID string.
	 */
	public string $id;

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $show_visual;

	/**
	 * @var string
	 *
	 *
	 */
	public string $image_href;

	/**
	 * @var string
	 *
	 *
	 */
	public string $text_value;

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

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $can_recaptcha;

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

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $empty_field;

	/**
	 * @var int
	 *
	 *
	 */
	public int $max_errors;

	/**
	 * @var array
	 *
	 * Error messages about any problems encountered during setup.
	 */
	public array $errors = [];

	/**
	 * @var bool
	 *
	 * Whether there is anything to show.
	 * Assume true until proven otherwise.
	 */
	public bool $result = true;

	/**
	 * @var int
	 *
	 *
	 */
	public int $tracking = 0;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/**
	 * @var string
	 *
	 * ID of the most recently loaded instance of this class.
	 *
	 * Normally this is the one that we want to show.
	 */
	public static string $vid = '';

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
	 * Constructor.
	 *
	 * @todo BEFORE COMMITTING: Can't return errors from constructor.
	 *
	 * @param array $options Options for the verification control.
	 * @param bool $do_test Whether to check to see if the user entered the code correctly.
	 * @return bool|array False if there's nothing to show, true if everything went well or an array containing error indicators if the test failed.
	 */
	public function __construct(array $options, bool $do_test = false)
	{
		// Add a verification hook, pre-setup.
		IntegrationHook::call('integrate_create_control_verification_pre', [&$options, $do_test]);

		// Always need an ID. If someone forgot to provide it, fall back to the
		// current action (but trim off any trailing '2' in the action name).
		$this->id = $options['id'] ?? rtrim(($_REQUEST['action'] ?? 'post'), '2');

		self::$vid = $this->id;

		// Make sure that we don't overwrite previous values.
		if (isset(self::$loaded[$this->id])) {
			$this->show_visual = self::$loaded[$this->id]->show_visual;
			$this->image_href = self::$loaded[$this->id]->image_href;
			$this->text_value = self::$loaded[$this->id]->text_value;
			$this->number_questions = self::$loaded[$this->id]->number_questions;
			$this->questions = self::$loaded[$this->id]->questions;
			$this->can_recaptcha = self::$loaded[$this->id]->can_recaptcha;
			$this->empty_field = self::$loaded[$this->id]->empty_field;
			$this->max_errors = self::$loaded[$this->id]->max_errors;
		} else {
			$this->show_visual = !empty($options['override_visual']) || (!empty(Config::$modSettings['visual_verification_type']) && !isset($options['override_visual']));
			$this->image_href = Config::$scripturl . '?action=verificationcode;vid=' . $this->id . ';rand=' . bin2hex(random_bytes(16));
			$this->text_value = '';
			$this->number_questions = $options['override_qs'] ?? (!empty(Config::$modSettings['qa_verification_number']) ? Config::$modSettings['qa_verification_number'] : 0);
			$this->questions = [];
			$this->can_recaptcha = !empty(Config::$modSettings['recaptcha_enabled']) && !empty(Config::$modSettings['recaptcha_site_key']) && !empty(Config::$modSettings['recaptcha_secret_key']);
			$this->empty_field = empty($options['no_empty_field']);
			$this->max_errors = $options['max_errors'] ?? 3;
		}

		$this->init($this->show_visual);

		// Is there actually going to be anything?
		if (empty($this->show_visual) && empty($this->number_questions) && empty($this->can_recaptcha)) {
			$this->result = false;
		}
		// Already loaded and not checking answers, so we are done.
		elseif (isset(self::$loaded[$this->id]) && !$this->do_test) {
			$this->result = true;
		}
		// This is new one, or we are updating it, or we are checking answers.
		else {
			self::$loaded[$this->id] = $this;

			$this->sanitizeReCaptcha();
			$this->addCaptchaJavaScript();
			$this->loadQuestionCache();

			if (!isset($_SESSION[$this->id . '_vv'])) {
				$_SESSION[$this->id . '_vv'] = [];
			}

			// Start with any testing.
			if ($do_test) {
				$this->test();
			}

			// Are we refreshing then?
			// (We might need to even if 'dont_refresh' was requested.)
			if ($this->shouldRefresh(!empty($options['dont_refresh']), $do_test)) {
				$this->refresh();
			} else {
				// Same questions as before.
				$this->question_ids = !empty($_SESSION[$this->id . '_vv']['q']) ? $_SESSION[$this->id . '_vv']['q'] : [];

				$this->text_value = !empty($_REQUEST[$this->id . '_vv']['code']) ? Utils::htmlspecialchars($_REQUEST[$this->id . '_vv']['code']) : '';
			}

			// If we do have an empty field, it would be nice to hide it from
			// legitimate users who shouldn't be populating it anyway.
			if (!empty($_SESSION[$this->id . '_vv']['empty_field'])) {
				Theme::addInlineCss('.vv_special { display: none; }');
			}

			// Do we have some questions to load?
			$this->setQuestions();

			$_SESSION[$this->id . '_vv']['count'] = (int) ($_SESSION[$this->id . '_vv']['count'] ?? 0);
			$_SESSION[$this->id . '_vv']['count']++;

			// Let our hooks know that we are done with the verification process.
			IntegrationHook::call('integrate_create_control_verification_post', [&$this->errors, $do_test]);

			// Return errors if we have them.
			if (!empty($this->errors)) {
				// Backward compatibility.
				Utils::$context['require_verification'] = $this->errors;
				Utils::$context['visual_verification'] = $this->result;
				Utils::$context['visual_verification_id'] = $this->id;

				$this->result = true;

				return;
			}

			// If they passed the test, make a note.
			if ($do_test) {
				$_SESSION[$this->id . '_vv']['did_pass'] = true;
			}

			// Say that everything went well, chaps.
			$this->result = true;
		}

		Utils::$context['require_verification'] = $this->result;
		Utils::$context['visual_verification'] = $this->result;
		Utils::$context['visual_verification_id'] = $this->id;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor that returns result (or error indicators).
	 *
	 * @param array &$options Options for the verification control.
	 * @param bool $do_test Whether to check to see if the user entered the code correctly.
	 * @return bool|array False if there's nothing to show, true if everything went well, or an array containing error indicators if the test failed.
	 */
	public static function create(&$options, $do_test = false)
	{
		$obj = new self($options, $do_test);

		foreach ($options as $key => $value) {
			$options[$key] = $obj->$key;
		}

		return !empty($obj->errors) ? $obj->errors : $obj->result;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Initializes some required template stuff.
	 */
	protected function init(): void
	{
		// The template
		Theme::loadTemplate('GenericControls');

		// Some javascript ma'am?
		if ($this->show_visual && !in_array('smf_captcha', Utils::$context['javascript_files'])) {
			Theme::loadJavaScriptFile('captcha.js', ['minimize' => true], 'smf_captcha');
		}

		Utils::$context['use_graphic_library'] = extension_loaded('gd');

		// Skip I, J, L, O, Q, S and Z.
		Utils::$context['standard_captcha_range'] = array_merge(range('A', 'H'), ['K', 'M', 'N', 'P', 'R'], range('T', 'Y'));

		// Backward compatibility.
		Utils::$context['controls']['verification'] = &self::$loaded;
	}

	/**
	 * Sanitizes ReCaptcha fields.
	 */
	protected function sanitizeReCaptcha(): void
	{
		if (!$this->can_recaptcha) {
			return;
		}

		// Only allow 40 alphanumeric, underscore, and dash characters.
		$this->recaptcha_site_key = substr(preg_replace('/\W/', '', Config::$modSettings['recaptcha_site_key']), 0, 40);

		// Light or dark theme...
		$this->recaptcha_theme = Config::$modSettings['recaptcha_theme'] == 'dark' ? 'dark' : 'light';
	}

	/**
	 * Adds JavaScript for the visual captcha.
	 */
	protected function addCaptchaJavaScript(): void
	{
		if (!$this->show_visual) {
			return;
		}

		Utils::$context['insert_after_template'] .= '
		<script>
			var verification' . $this->id . 'Handle = new smfCaptcha("' . $this->image_href . '", "' . $this->id . '", ' . (Utils::$context['use_graphic_library'] ? 1 : 0) . ');
		</script>';
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
				'',
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

	/**
	 *
	 */
	protected function test(): void
	{
		// This cannot happen!
		if (!isset($_SESSION[$this->id . '_vv']['count'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Hmm, it's requested but not actually declared. This shouldn't happen.
		if ($this->empty_field && empty($_SESSION[$this->id . '_vv']['empty_field'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// While we're here, did the user do something bad?
		if ($this->empty_field && !empty($_SESSION[$this->id . '_vv']['empty_field']) && !empty($_REQUEST[$_SESSION[$this->id . '_vv']['empty_field']])) {
			$this->errors[] = 'wrong_verification_answer';
		}

		if ($this->can_recaptcha) {
			$reCaptcha = new \ReCaptcha\ReCaptcha(Config::$modSettings['recaptcha_secret_key'], new \ReCaptcha\RequestMethod\SocketPost());

			// Was there a reCAPTCHA response?
			if (isset($_POST['g-recaptcha-response'])) {
				$resp = $reCaptcha->verify($_POST['g-recaptcha-response'], User::$me->ip);

				if (!$resp->isSuccess()) {
					$this->errors[] = 'wrong_verification_recaptcha';
				}
			} else {
				$this->errors[] = 'wrong_verification_code';
			}
		}

		if ($this->show_visual && (empty($_REQUEST[$this->id . '_vv']['code']) || empty($_SESSION[$this->id . '_vv']['code']) || strtoupper($_REQUEST[$this->id . '_vv']['code']) !== $_SESSION[$this->id . '_vv']['code'])) {
			$this->errors[] = 'wrong_verification_code';
		}

		if ($this->number_questions > 0) {
			$incorrectQuestions = [];

			foreach ($_SESSION[$this->id . '_vv']['q'] as $q) {
				// We don't have this question any more, thus no answers.
				if (!isset(Config::$modSettings['question_id_cache']['questions'][$q])) {
					continue;
				}

				// We have our question but it might have multiple answers.
				// First, did they actually answer this question?
				if (!isset($_REQUEST[$this->id . '_vv']['q'][$q]) || trim($_REQUEST[$this->id . '_vv']['q'][$q]) == '') {
					$incorrectQuestions[] = $q;

					continue;
				}

				// Second, is their answer in the list of possible answers?
				$given_answer = trim(Utils::htmlspecialchars(Utils::convertCase($_REQUEST[$this->id . '_vv']['q'][$q], 'fold')));

				if (!in_array($given_answer, Config::$modSettings['question_id_cache']['questions'][$q]['answers'])) {
					$incorrectQuestions[] = $q;
				}
			}

			if (!empty($incorrectQuestions)) {
				$this->errors[] = 'wrong_verification_answer';
			}
		}

		// Do ay hooks have something to say about this verification?
		IntegrationHook::call('integrate_create_control_verification_test', [$this, &$this->errors]);
	}

	/**
	 * Do we need to refresh this verification?
	 *
	 * @param bool $only_if_necessary If true, only refresh if we absolutely must.
	 * @param bool $do_test Whether we are checking their answers.
	 * @return bool Whether we should refresh this verification.
	 */
	protected function shouldRefresh(bool $only_if_necessary, bool $do_test): bool
	{
		// This means:
		// 1. If we weren't asked to avoid refreshing, refresh.
		// 2. If we didn't check their answers, refresh.
		// 3. If they previously passed a verification in this session (which
		//    means they need a new one), or haven't tried yet, or tried too
		//    many times, refresh.
		$should_refresh = !$only_if_necessary && !$do_test && (!empty($_SESSION[$this->id . '_vv']['did_pass']) || empty($_SESSION[$this->id . '_vv']['count']) || $_SESSION[$this->id . '_vv']['count'] > self::MAX_ATTEMPTS);

		// This can also force a fresh, although unlikely.
		if (($this->show_visual && empty($_SESSION[$this->id . '_vv']['code'])) || ($this->number_questions > 0 && empty($_SESSION[$this->id . '_vv']['q']))) {
			$should_refresh = true;
		}

		// Any errors means we refresh potentially.
		if (!empty($this->errors)) {
			if (empty($_SESSION[$this->id . '_vv']['errors'])) {
				$_SESSION[$this->id . '_vv']['errors'] = 0;
			}
			// Too many errors?
			elseif ($_SESSION[$this->id . '_vv']['errors'] > $this->max_errors) {
				$should_refresh = true;
			}

			// Keep track of these.
			$_SESSION[$this->id . '_vv']['errors']++;
		}

		return $should_refresh;
	}

	/**
	 * Refresh this verification.
	 */
	protected function refresh(): void
	{
		// Assume nothing went before.
		$_SESSION[$this->id . '_vv']['count'] = 0;
		$_SESSION[$this->id . '_vv']['errors'] = 0;
		$_SESSION[$this->id . '_vv']['did_pass'] = false;
		$_SESSION[$this->id . '_vv']['q'] = [];
		$_SESSION[$this->id . '_vv']['code'] = '';

		// Make our magic empty field.
		if ($this->empty_field) {
			// We're building a field that lives in the template, that we hope to be empty later. But at least we give it a believable name.
			$terms = ['gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier'];

			$second_terms = ['hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value'];

			$start = random_int(0, 27);

			$hash = bin2hex(random_bytes(2));

			$_SESSION[$this->id . '_vv']['empty_field'] = $terms[array_rand($terms)] . '-' . $second_terms[array_rand($second_terms)] . '-' . $hash;
		}

		// Generating a new image.
		if ($this->show_visual) {
			// Are we overriding the range?
			$character_range = !empty($options['override_range']) ? $options['override_range'] : Utils::$context['standard_captcha_range'];

			for ($i = 0; $i < 6; $i++) {
				$_SESSION[$this->id . '_vv']['code'] .= $character_range[array_rand($character_range)];
			}
		}

		// Getting some new questions?
		if ($this->number_questions) {
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

					$this->question_ids = array_slice($this->question_ids, 0, $this->number_questions);

					break;
				}
			}
		}

		// Hooks may need to know about this.
		IntegrationHook::call('integrate_create_control_verification_refresh', [$this]);
	}

	/**
	 * Sets the verification questions and answers for this instance.
	 */
	protected function setQuestions(): void
	{
		// Have we got some questions to load?
		if (empty($this->question_ids)) {
			return;
		}

		$_SESSION[$this->id . '_vv']['q'] = [];

		foreach ($this->question_ids as $q) {
			// Bit of a shortcut this.
			$row = &Config::$modSettings['question_id_cache']['questions'][$q];

			$this->questions[] = [
				'id' => $q,
				'q' => BBCodeParser::load()->parse($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($q, $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->id . '_vv'], $_REQUEST[$this->id . '_vv']['q'], $_REQUEST[$this->id . '_vv']['q'][$q]) ? Utils::htmlspecialchars($_REQUEST[$this->id . '_vv']['q'][$q]) : '',
			];

			$_SESSION[$this->id . '_vv']['q'][] = $q;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Verifier::exportStatic')) {
	Verifier::exportStatic();
}

?>