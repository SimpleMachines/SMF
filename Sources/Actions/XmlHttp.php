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

namespace SMF\Actions;

use SMF\Actions\Admin\News;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Editor;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Handles XML-based interaction (mainly XMLhttp)
 */
class XmlHttp implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'XMLhttpMain',
			'GetJumpTo' => 'GetJumpTo',
			'ListMessageIcons' => 'ListMessageIcons',
			'RetrievePreview' => 'RetrievePreview',
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
	public string $subaction;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'jumpto' => 'jumpTo',
		'messageicons' => 'messageIcons',
		'previews' => 'previews',
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
	 * The main handler and designator for AJAX stuff - jumpto, message icons and previews
	 */
	public function execute(): void
	{
		if (!isset($this->subaction)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Get a list of boards and categories used for the jumpto dropdown.
	 */
	public function jumpTo()
	{
		// Find the boards/categories they can see.
		$boardListOptions = [
			'use_permissions' => true,
			'selected_board' => Utils::$context['current_board'] ?? 0,
		];
		Utils::$context['jump_to'] = MessageIndex::getBoardList($boardListOptions);

		// Make the board safe for display.
		foreach (Utils::$context['jump_to'] as $id_cat => $cat) {
			Utils::$context['jump_to'][$id_cat]['name'] = Utils::htmlspecialcharsDecode(strip_tags($cat['name']));

			foreach ($cat['boards'] as $id_board => $board) {
				Utils::$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = Utils::htmlspecialcharsDecode(strip_tags($board['name']));
			}
		}

		Utils::$context['sub_template'] = 'jump_to';
	}

	/**
	 * Gets a list of available message icons and sends the info to the template for display
	 */
	public function messageIcons()
	{
		Utils::$context['icons'] = Editor::getMessageIcons(Board::$info->id);
		Utils::$context['sub_template'] = 'message_icons';
	}

	/**
	 * Handles retrieving previews of news items, newsletters, signatures and warnings.
	 * Calls the appropriate function based on $_POST['item']
	 *
	 * @return void|bool Returns false if $_POST['item'] isn't set or isn't valid
	 */
	public function previews()
	{
		$items = [
			'newspreview',
			'newsletterpreview',
			'sig_preview',
			'warning_preview',
		];

		Utils::$context['sub_template'] = 'generic_xml';

		if (!isset($_POST['item']) || !in_array($_POST['item'], $items)) {
			return false;
		}

		call_user_func([$this, $_POST['item']]);
	}

	/**
	 * Handles previewing news items
	 */
	public function newspreview()
	{
		$errors = [];

		$news = !isset($_POST['news']) ? '' : Utils::htmlspecialchars($_POST['news'], ENT_QUOTES);

		if (empty($news)) {
			$errors[] = ['value' => 'no_news'];
		} else {
			Msg::preparsecode($news);
		}

		Utils::$context['xml_data'] = [
			'news' => [
				'identifier' => 'parsedNews',
				'children' => [
					[
						'value' => BBCodeParser::load()->parse($news),
					],
				],
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Handles previewing newsletters
	 */
	public function newsletterpreview()
	{
		Lang::load('Errors');

		Utils::$context['post_error']['messages'] = [];
		Utils::$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
		Utils::$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

		if (empty($_POST['subject'])) {
			Utils::$context['post_error']['messages'][] = Lang::$txt['error_no_subject'];
		}

		if (empty($_POST['message'])) {
			Utils::$context['post_error']['messages'][] = Lang::$txt['error_no_message'];
		}

		News::prepareMailingForPreview();

		Utils::$context['sub_template'] = 'pm';
	}

	/**
	 * Handles previewing signatures
	 */
	public function sig_preview()
	{
		require_once Config::$sourcedir . '/Profile-Modify.php';

		Lang::load('Profile');
		Lang::load('Errors');

		$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
		$is_owner = $user == User::$me->id;

		// @todo Temporary
		// Borrowed from loadAttachmentContext in Display.php
		$can_change = $is_owner ? User::$me->allowedTo(['profile_extra_any', 'profile_extra_own']) : User::$me->allowedTo('profile_extra_any');

		$errors = [];

		if (!empty($user) && $can_change) {
			$request = Db::$db->query(
				'',
				'SELECT signature
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				[
					'id_member' => $user,
				],
			);
			list($current_signature) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Lang::censorText($current_signature);

			$allowedTags = BBCodeParser::getSigTags();

			$current_signature = !empty($current_signature) ? BBCodeParser::load()->parse($current_signature, true, 'sig' . $user, $allowedTags) : Lang::$txt['no_signature_set'];

			$preview_signature = !empty($_POST['signature']) ? Utils::htmlspecialchars($_POST['signature']) : Lang::$txt['no_signature_preview'];

			$validation = Profile::validateSignature($preview_signature);

			if ($validation !== true && $validation !== false) {
				$errors[] = ['value' => Lang::$txt['profile_error_' . $validation], 'attributes' => ['type' => 'error']];
			}

			Lang::censorText($preview_signature);

			$preview_signature = BBCodeParser::load()->parse($preview_signature, true, 'sig' . $user, $allowedTags);
		} elseif (!$can_change) {
			if ($is_owner) {
				$errors[] = ['value' => Lang::$txt['cannot_profile_extra_own'], 'attributes' => ['type' => 'error']];
			} else {
				$errors[] = ['value' => Lang::$txt['cannot_profile_extra_any'], 'attributes' => ['type' => 'error']];
			}
		} else {
			$errors[] = ['value' => Lang::$txt['no_user_selected'], 'attributes' => ['type' => 'error']];
		}

		Utils::$context['xml_data']['signatures'] = [
			'identifier' => 'signature',
			'children' => [],
		];

		if (isset($current_signature)) {
			Utils::$context['xml_data']['signatures']['children'][] = [
				'value' => $current_signature,
				'attributes' => ['type' => 'current'],
			];
		}

		if (isset($preview_signature)) {
			Utils::$context['xml_data']['signatures']['children'][] = [
				'value' => $preview_signature,
				'attributes' => ['type' => 'preview'],
			];
		}

		if (!empty($errors)) {
			Utils::$context['xml_data']['errors'] = [
				'identifier' => 'error',
				'children' => array_merge(
					[
						[
							'value' => Lang::$txt['profile_errors_occurred'],
							'attributes' => ['type' => 'errors_occurred'],
						],
					],
					$errors,
				),
			];
		}
	}

	/**
	 * Handles previewing user warnings
	 */
	public function warning_preview()
	{
		Lang::load('Errors');
		Lang::load('ModerationCenter');

		Utils::$context['post_error']['messages'] = [];

		if (User::$me->allowedTo('issue_warning')) {
			$warning_body = !empty($_POST['body']) ? trim(Lang::censorText($_POST['body'])) : '';

			Utils::$context['preview_subject'] = !empty($_POST['title']) ? trim(Utils::htmlspecialchars($_POST['title'])) : '';

			if (isset($_POST['issuing'])) {
				if (empty($_POST['title']) || empty($_POST['body'])) {
					Utils::$context['post_error']['messages'][] = Lang::$txt['warning_notify_blank'];
				}
			} else {
				if (empty($_POST['title'])) {
					Utils::$context['post_error']['messages'][] = Lang::$txt['mc_warning_template_error_no_title'];
				}

				if (empty($_POST['body'])) {
					Utils::$context['post_error']['messages'][] = Lang::$txt['mc_warning_template_error_no_body'];
				}

				// Add in few replacements.
				/**
				 * These are the defaults:
				 * - {MEMBER} - Member Name. => current user for review
				 * - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
				 * - {FORUMNAME} - Forum Name.
				 * - {SCRIPTURL} - Web address of forum.
				 * - {REGARDS} - Standard email sign-off.
				 */
				$find = [
					'{MEMBER}',
					'{FORUMNAME}',
					'{SCRIPTURL}',
					'{REGARDS}',
				];

				$replace = [
					User::$me->name,
					Config::$mbname,
					Config::$scripturl,
					sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']),
				];

				$warning_body = str_replace($find, $replace, $warning_body);
			}

			if (!empty($_POST['body'])) {
				Msg::preparsecode($warning_body);

				$warning_body = BBCodeParser::load()->parse($warning_body);
			}

			Utils::$context['preview_message'] = $warning_body;
		} else {
			Utils::$context['post_error']['messages'][] = ['value' => Lang::$txt['cannot_issue_warning'], 'attributes' => ['type' => 'error']];
		}

		Utils::$context['sub_template'] = 'warning';
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
	 * Backward compatibility wrapper for the jumpto sub-action.
	 */
	public static function GetJumpTo(): void
	{
		self::load();
		self::$obj->subaction = 'jumpto';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the messageicons sub-action.
	 */
	public static function ListMessageIcons(): void
	{
		self::load();
		self::$obj->subaction = 'messageicons';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the previews sub-action.
	 */
	public static function RetrievePreview(): void
	{
		self::load();
		self::$obj->subaction = 'previews';
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
		Theme::loadTemplate('Xml');

		// Easy adding of sub actions.
		IntegrationHook::call('integrate_XMLhttpMain_subActions', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\XmlHttp::exportStatic')) {
	XmlHttp::exportStatic();
}

?>