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

declare(strict_types=1);

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Creates the editor input box so that people can write messages to post.
 */
class Editor implements \ArrayAccess, \Stringable
{
	use ArrayAccessHelper;

	/*****************
	 * Class constants
	 *****************/

	public const PREVIEW_HTML = 1;
	public const PREVIEW_XML = 2;

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
	 * @var string
	 *
	 * This editor's value.
	 */
	public string $value;

	/**
	 * Determines whether the editor starts in rich text (WYSIWYG) mode.
	 *
	 * This property is initialized based on several factors:
	 * - If the global setting `disable_wysiwyg` is enabled;
	 * - If the user's theme preference or the provided option `force_rich` is true;
	 * - If a request explicitly sets the editor mode for the instance (e.g., `$_REQUEST[$this->id . '_mode']`); it overrides other settings.
	 *
	 * @var bool True if the editor starts in WYSIWYG mode, false otherwise.
	 */
	public bool $rich_active;

	/**
	 * @var bool
	 *
	 * Whether to show the smiley box.
	 */
	public bool $disable_smiley_box;

	/**
	 * @var int
	 *
	 * Column width of the editor's input area.
	 */
	public int $columns;

	/**
	 * @var int
	 *
	 * Row height of the editor's input area.
	 */
	public int $rows;

	/**
	 * @var string
	 *
	 * CSS width of the editor's input area.
	 */
	public string $width;

	/**
	 * @var string
	 *
	 * CSS height of the editor's input area.
	 */
	public string $height;

	/**
	 * @var string
	 *
	 * ID of the HTML form for this editor.
	 */
	public string $form;

	/**
	 * @var int
	 *
	 * Which type of previews we want.
	 *
	 * Value must be one of this class's PREVIEW_* constants.
	 */
	public int $preview_type;

	/**
	 * @var array
	 *
	 * Labels for the form's main buttons.
	 */
	public array $labels;

	/**
	 * @var string
	 *
	 * The locale to use for the input form.
	 */
	public string $locale;

	/**
	 * @var bool
	 *
	 * Whether input is required (i.e. submitted value cannot be empty).
	 */
	public bool $required;

	/**
	 * @var array
	 *
	 * Options to pass to SCEditor.
	 */
	public array $sce_options = [];

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
	 * @var array
	 *
	 *
	 */
	public static array $bbc_tags = [];

	/**
	 * @var array
	 *
	 *
	 */
	public static array $disabled_tags = [];

	/**
	 * @var array
	 *
	 *
	 */
	public static array $bbc_toolbar = [];

	/**
	 * @var array
	 *
	 *
	 */
	public static array $bbc_handlers = [];

	/**
	 * @var array
	 *
	 *
	 */
	public static array $smileys_toolbar = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'rich_value' => 'value',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Initializes a new instance of the editor class and configures its options and behavior.
	 *
	 * This constructor prepares the editor with default or user-specified options, including its
	 * dimensions, behavior, and visual features. It also sets up toolbars, smileys, and WYSIWYG
	 * capabilities if enabled.
	 *
	 * Behavior:
	 * 1. Initializes the editor with a unique ID and sets default options for its dimensions and behavior.
	 * 2. Configures the smiley and BBC toolbars, applying any necessary translations or replacements.
	 * 3. Enables WYSIWYG mode based on global settings, user preferences, or provided options.
	 * 4. Sets the SCEditor options using the provided `$options` array.
	 * 5. Adds backward compatibility support by storing the editor ID in the global context.
	 *
	 * Supported options:
	 *    - `id` (string): The unique identifier for the editor instance. Defaults to 'message'.
	 *    - `value` (string): The initial value of the editor, with certain replacements for compatibility.
	 *    - `disable_smiley_box` (bool): Whether to disable the smiley selection box. Default is false.
	 *    - `columns` (int): Number of columns for the editor text area. Default is 60.
	 *    - `rows` (int): Number of rows for the editor text area. Default is 18.
	 *    - `width` (string): Width of the editor. Default is '100%'.
	 *    - `height` (string): Height of the editor. Default is '250px'.
	 *    - `form` (string): The form name associated with the editor. Default is 'postmodify'.
	 *    - `preview_type` (int): The type of preview for the editor. Default is `self::PREVIEW_HTML`.
	 *    - `labels` (array): Additional labels for customization.
	 *    - `required` (bool): Indicates whether the editor input is required. Default is false.
	 *    - `force_rich` (bool): Force the editor to start in rich text mode. Default is false.
	 *       This option directly influences `$this->rich_active`, which determines if WYSIWYG mode is enabled.
	 *    - `plugins` (array): List of additional plugins to be loaded. Defaults to an empty array if not set.
	 *    - `disable_url_autolinking` (bool): If set, disables the autolinker plugin for URLs.
	 *    - `options` (array): Additional SCEditor configuration options to be merged with default settings.
	 *
	 * Custom SCEditor options:
	 *    - `commandsWithDropdown`: Identifies buttons that use dropdown menus.
	 *    - `textOnlyCommands`: Configures buttons to display text without icons.
	 *    - `commandsWithText`: Configures buttons to show text alongside icons.
	 *
	 * Hooks:
	 * - Hook: `integrate_sceditor_options`
	 * - Parameters:
	 *   - `array &$this->sce_options`: Reference to the array of SCEditor options.
	 *
	 * - Hook: `integrate_bbc_buttons`
	 * - Parameters:
	 *   - `array &$bbc_tags`: Reference to the array of BBC tags.
	 *   - `array &$editor_tag_map`: Reference to the mapping of BBC tags to SCEditor commands.
	 *   - `array &$disabled_tags`: Reference to the array of disabled BBC tags.
	 *
	 * Example bbc tag array:
	 * ```php
	 * [
	 *     'code' => 'b',
	 *     'description' => Lang::getTxt('bold', var: 'editortxt'), // Optional
	 *     'image' => 'bold', // Optional
	 *     'before' => '[b]', // Optional
	 *     'after' => '[/b]', // Optional
	 * ]
	 * ```
	 *
	 * Example editor tag map:
	 * ```php
	 * [
	 *     'bbcode' => 'sceditorCommand',
	 * ]
	 * ```
	 *
	 * Notes:
	 * - A blank array (`[]`) in the `bbc_tags` represents a separator between groups of buttons in the toolbar.
	 * - The `editor_tag_map` is only used when the BBC tag and the SCEditor command differ.
	 *
	 * @param array $options An associative array of configuration options for the editor.
	 */
	public function __construct(array $options)
	{
		$this->init();
		$this->buildButtons();

		// Every control must have a ID!
		$this->id = (string) ($options['id'] ?? 'message');

		$this->value = strtr((string) ($options['value'] ?? ''), [
			// The [#] item code for creating list items causes issues with
			// SCEditor, but [+] is a safe equivalent.
			'[#]' => '[+]',
		]);

		$this->disable_smiley_box = !empty($options['disable_smiley_box']);
		$this->columns = (int) ($options['columns'] ?? 60);
		$this->rows = (int) ($options['rows'] ?? 18);
		$this->width = (string) ($options['width'] ?? '100%');
		$this->height = (string) ($options['height'] ?? '250px');
		$this->form = (string) ($options['form'] ?? 'postmodify');
		$this->preview_type = (int) ($options['preview_type'] ?? self::PREVIEW_HTML);
		$this->labels = (array) ($options['labels'] ?? []);
		$this->required = !empty($options['required']);

		$this->locale = !empty(Lang::getTxt('lang_dictionary', file: 'General')) && Lang::getTxt('lang_dictionary', file: 'General') != 'en' ? Lang::getTxt('lang_dictionary', file: 'General') : '';

		$this->rich_active = empty(Config::$modSettings['disable_wysiwyg']) && (!empty(Theme::$current->options['wysiwyg_default']) || !empty($options['force_rich']) || !empty($_REQUEST[$this->id . '_mode']));

		$this->buildBbcToolbar();
		$this->buildSmileysToolbar();
		$this->setSCEditorOptions($options);

		self::$loaded[$this->id] = $this;

		// Backward compatibility.
		Utils::$context['post_box_name'] = $this->id;
	}

	/**
	 * Allows this object to be handled like a string.
	 */
	public function __toString(): string
	{
		return json_encode($this->sce_options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @param array $options Various options for the editor.
	 * @return object An instance of this class.
	 */
	public static function load(array $options): object
	{
		return new self($options);
	}

	/**
	 * Adds a new BBC tag to the toolbar before or after a specified tag.
	 *
	 * @param array $new_tag The new tag to add.
	 * @param string $reference_tag The tag code to reference.
	 * @param bool $before True to add the new tag before the reference tag, false to add it after.
	 */
	public static function addBbcTag(array $new_tag, string $reference_tag, bool $before = true): void
	{
		if (self::$bbc_tags == []) {
			self::initBbcTags();
		}

		foreach (self::$bbc_tags as &$row) {
			foreach ($row as $index => $tag) {
				if (isset($tag['code']) && $tag['code'] === $reference_tag) {
					if ($before) {
						array_splice($row, $index, 0, [$new_tag]);
					} else {
						array_splice($row, $index + 1, 0, [$new_tag]);
					}

					return;
				}
			}
		}
	}

	/**
	 * Removes a BBC tag from the toolbar.
	 *
	 * @param string $tag_code The tag code to remove.
	 */
	public static function removeBbcTag(string $tag_code): void
	{
		if (self::$bbc_tags == []) {
			self::initBbcTags();
		}

		foreach (self::$bbc_tags as &$row) {
			foreach ($row as $index => $tag) {
				if (isset($tag['code']) && $tag['code'] === $tag_code) {
					array_splice($row, $index, 1);

					return;
				}
			}
		}
	}

	/**
	 * Retrieves a list of message icons.
	 *
	 * Based on the settings, the array will either contain a list of default
	 * message icons or a list of custom message icons retrieved from the
	 * database.
	 *
	 * The board_id is needed for the custom message icons (which can be set for
	 * each board individually).
	 *
	 * @param int $board_id The ID of the board
	 * @return array An array of info about available icons
	 */
	public static function getMessageIcons(int $board_id): array
	{
		if (empty(Config::$modSettings['messageIcons_enable'])) {
			$icons = [
				[
					'value' => 'xx',
					'name' => Lang::getTxt('standard', file: 'Post'),
				],
				[
					'value' => 'thumbup',
					'name' => Lang::getTxt('thumbs_up', file: 'Post'),
				],
				[
					'value' => 'thumbdown',
					'name' => Lang::getTxt('thumbs_down', file: 'Post'),
				],
				[
					'value' => 'exclamation',
					'name' => Lang::getTxt('exclamation_point', file: 'Post'),
				],
				[
					'value' => 'question',
					'name' => Lang::getTxt('question_mark', file: 'Post'),
				],
				[
					'value' => 'lamp',
					'name' => Lang::getTxt('lamp', file: 'Post'),
				],
				[
					'value' => 'smiley',
					'name' => Lang::getTxt('icon_smiley', file: 'General'),
				],
				[
					'value' => 'angry',
					'name' => Lang::getTxt('icon_angry', file: 'General'),
				],
				[
					'value' => 'cheesy',
					'name' => Lang::getTxt('icon_cheesy', file: 'General'),
				],
				[
					'value' => 'grin',
					'name' => Lang::getTxt('icon_grin', file: 'General'),
				],
				[
					'value' => 'sad',
					'name' => Lang::getTxt('icon_sad', file: 'General'),
				],
				[
					'value' => 'wink',
					'name' => Lang::getTxt('icon_wink', file: 'General'),
				],
				[
					'value' => 'poll',
					'name' => Lang::getTxt('icon_poll', file: 'Post'),
				],
			];

			foreach ($icons as $k => $dummy) {
				$icons[$k]['url'] = Theme::$current->settings['images_url'] . '/post/' . $dummy['value'] . '.png';
			}
		}
		// Otherwise load the icons, and check we give the right image too...
		else {
			$icons = CacheApi::get('posting_icons-' . $board_id, 480);

			if ($icons == null) {
				$icons = [];

				$request = Db::$db->query(
					'SELECT title, filename
					FROM {db_prefix}message_icons
					WHERE id_board IN (0, {int:board_id})
					ORDER BY icon_order',
					[
						'board_id' => $board_id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$icons[$row['filename']] = [
						'value' => $row['filename'],
						'name' => $row['title'],
						'url' => Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['filename'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . $row['filename'] . '.png',
					];
				}
				Db::$db->free_result($request);

				CacheApi::put('posting_icons-' . $board_id, $icons, 480);
			}
		}

		IntegrationHook::call('integrate_load_message_icons', [&$icons]);

		return array_values($icons);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Initializes BBC tags for the toolbar.
	 */
	protected static function initBbcTags(): void
	{
		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		/*
			array(
				'code' => 'b', // Required
				'description' => Lang::getTxt('bold', var: 'editortxt'), // Required
				'image' => 'bold', // Optional
				'before' => '[b]', // Optional
				'after' => '[/b]', // Optional
			),
		*/
		self::$bbc_tags = [
			[
				[
					'code' => 'bold',
					'description' => Lang::getTxt('bold', var: 'editortxt'),
				],
				[
					'code' => 'italic',
					'description' => Lang::getTxt('italic', var: 'editortxt'),
				],
				[
					'code' => 'underline',
					'description' => Lang::getTxt('underline', var: 'editortxt'),
				],
				[
					'code' => 'strike',
					'description' => Lang::getTxt('strikethrough', var: 'editortxt'),
				],
				[
					'code' => 'superscript',
					'description' => Lang::getTxt('superscript', var: 'editortxt'),
				],
				[
					'code' => 'subscript',
					'description' => Lang::getTxt('subscript', var: 'editortxt'),
				],
				[
					'image' => 'tt',
					'code' => 'tt',
					'description' => Lang::getTxt('tt', var: 'editortxt'),
				],
				[],
				[
					'code' => 'pre',
					'description' => Lang::getTxt('preformatted_text', var: 'editortxt'),
				],
				[
					'code' => 'left',
					'description' => Lang::getTxt('align_left', var: 'editortxt'),
				],
				[
					'code' => 'center',
					'description' => Lang::getTxt('center', var: 'editortxt'),
				],
				[
					'code' => 'right',
					'description' => Lang::getTxt('align_right', var: 'editortxt'),
				],
				[
					'code' => 'justify',
					'description' => Lang::getTxt('justify', var: 'editortxt'),
				],
				[],
				[
					'code' => 'font',
					'description' => Lang::getTxt('font_name', var: 'editortxt'),
				],
				[
					'code' => 'size',
					'description' => Lang::getTxt('font_size', var: 'editortxt'),
				],
				[
					'code' => 'color',
					'description' => Lang::getTxt('font_color', var: 'editortxt'),
				],
				[],
				[
					'code' => 'removeformat',
					'description' => Lang::getTxt('remove_formatting', var: 'editortxt'),
				],
			],
			[
				[
					'code' => 'floatleft',
					'description' => Lang::getTxt('float_left', var: 'editortxt'),
				],
				[
					'code' => 'floatright',
					'description' => Lang::getTxt('float_right', var: 'editortxt'),
				],
				[],
				[
					'code' => 'youtube',
					'description' => Lang::getTxt('insert_youtube_video', var: 'editortxt'),
				],
				[
					'code' => 'image',
					'description' => Lang::getTxt('insert_image', var: 'editortxt'),
				],
				[
					'code' => 'email',
					'description' => Lang::getTxt('insert_email', var: 'editortxt'),
				],
				[
					'code' => 'link',
					'description' => Lang::getTxt('insert_link', var: 'editortxt'),
				],
				[
					'code' => 'unlink',
					'description' => Lang::getTxt('unlink', var: 'editortxt'),
				],
				[],
				[
					'code' => 'table',
					'description' => Lang::getTxt('insert_table', var: 'editortxt'),
				],
				[
					'code' => 'code',
					'description' => Lang::getTxt('code', var: 'editortxt'),
				],
				[
					'code' => 'quote',
					'description' => Lang::getTxt('insert_quote', var: 'editortxt'),
				],
				[
					'image' => 'heading',
					'code' => 'heading',
					'description' => Lang::getTxt('heading', var: 'editortxt'),
				],
				[],
				[
					'code' => 'bulletlist',
					'description' => Lang::getTxt('bullet_list', var: 'editortxt'),
				],
				[
					'code' => 'orderedlist',
					'description' => Lang::getTxt('numbered_list', var: 'editortxt'),
				],
				[
					'code' => 'horizontalrule',
					'description' => Lang::getTxt('insert_horizontal_rule', var: 'editortxt'),
				],
				[],
				[
					'code' => 'maximize',
					'description' => Lang::getTxt('maximize', var: 'editortxt'),
				],
				[
					'code' => 'source',
					'description' => Lang::getTxt('view_source', var: 'editortxt'),
				],
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Initializes some required template stuff.
	 *
	 * Only acts the first time an instance of this class is created.
	 */
	protected function init(): void
	{
		// Don't do this twice.
		if (!empty(self::$loaded)) {
			return;
		}

		// Some general stuff.
		Theme::$current->settings['smileys_url'] = Config::$modSettings['smileys_url'] . '/' . User::$me->smiley_set;

		if (!empty(Utils::$context['drafts_autosave'])) {
			Utils::$context['drafts_autosave_frequency'] = empty(Config::$modSettings['drafts_autosave_frequency']) ? 60000 : Config::$modSettings['drafts_autosave_frequency'] * 1000;
		}

		// This really has some WYSIWYG stuff.
		Theme::loadCSSFile('jquery.sceditor.css', ['default_theme' => true, 'validate' => true], 'smf_jquery_sceditor');

		Theme::loadTemplate('GenericControls');

		/*
			THEME AUTHORS:
			If you want to change or tweak the CSS for the editor,
			include a file named 'jquery.sceditor.theme.css' in your theme.
		 */
		Theme::loadCSSFile('jquery.sceditor.theme.css', ['force_current' => true, 'validate' => true], 'smf_jquery_sceditor_theme');

		Theme::loadJavaScriptFile('jquery.sceditor.bbcode.min.js', [], 'smf_sceditor_bbcode');
		Theme::loadJavaScriptFile('sceditor.plugins.smf.js', ['minimize' => true], 'smf_sceditor_smf_plugin');

		$locale_key = Lang::getTxt('lang_dictionary', file: 'General');

		$translation_map = [
			'Width (optional):' => Lang::getTxt('width', var: 'editortxt'),
			'Height (optional):' => Lang::getTxt('height', var: 'editortxt'),
			'Insert' => Lang::getTxt('insert', var: 'editortxt'),
			'Description (optional):' => Lang::getTxt('description', var: 'editortxt'),
			'Rows:' => Lang::getTxt('rows', var: 'editortxt'),
			'Cols:' => Lang::getTxt('cols', var: 'editortxt'),
			'URL:' => Lang::getTxt('url', var: 'editortxt'),
			'E-mail:' => Lang::getTxt('email', var: 'editortxt'),
			'Video URL:' => Lang::getTxt('video_url', var: 'editortxt'),
			'More' => Lang::getTxt('more', var: 'editortxt'),
			'Close' => Lang::getTxt('close', var: 'editortxt'),
			'dateFormat' => Lang::getTxt('dateformat', var: 'editortxt'),
			'details' => Lang::getTxt('details', var: 'editortxt'),
			'spoiler' => Lang::getTxt('spoiler', var: 'editortxt'),
			'summaryPrompt' => Lang::getTxt('summary_prompt', var: 'editortxt'),
		];
		IntegrationHook::call('integrate_sceditor_locale', [&$translation_map]);

		$sc_extra_langs = 'sceditor.locale["' . $locale_key . '"] = ' . json_encode($translation_map, JSON_UNESCAPED_UNICODE) . ';';

		Theme::addInlineJavaScript($sc_extra_langs, true);
		Theme::addInlineJavaScript('
		var smf_smileys_url = \'' . Theme::$current->settings['smileys_url'] . '\';
		var bbc_quote_from = \'' . addcslashes(Lang::getTxt('quote_from', file: 'General'), "'") . '\';
		var bbc_quote = \'' . addcslashes(Lang::getTxt('quote', file: 'General'), "'") . '\';
		var bbc_search_on = \'' . addcslashes(Lang::getTxt('search_on', file: 'General'), "'") . '\';');

		Utils::$context['shortcuts_text'] = Lang::getTxt('shortcuts' . (!empty(Utils::$context['drafts_save']) ? '_drafts' : '') . (stripos($_SERVER['HTTP_USER_AGENT'], 'Macintosh') !== false ? '_mac' : (BrowserDetector::isBrowser('is_firefox') ? '_firefox' : '')), file: 'Post');

		if (Utils::$context['show_spellchecking']) {
			Theme::loadJavaScriptFile('spellcheck.js', ['minimize' => true], 'smf_spellcheck');

			// Some hidden information is needed in order to make the spell checking work.
			if (!isset($_REQUEST['xml'])) {
				Utils::$context['insert_after_template'] .= '
				<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="' . Config::$scripturl . '?action=spellcheck">
					<input type="hidden" name="spellstring" value="">
				</form>';
			}
		}

		// Backward compatibility.
		Utils::$context['controls']['richedit'] = &self::$loaded;
	}

	/**
	 * Builds the main editor form buttons (submit, preview, etc.)
	 */
	protected function buildButtons(): void
	{
		Utils::$context['richedit_buttons'] = [
			'save_draft' => [
				'type' => 'submit',
				'value' => Lang::$txt['draft_save'],
				'onclick' => !empty(Utils::$context['drafts_save']) ? 'return confirm(' . Utils::escapeJavaScript(Lang::$txt['draft_save_note']) . ');' : '',
				'accessKey' => 'd',
				'show' => !empty(Utils::$context['drafts_save']),
			],
			'id_draft' => [
				'type' => 'hidden',
				'value' => empty(Utils::$context['id_draft']) ? 0 : Utils::$context['id_draft'],
				'show' => !empty(Utils::$context['drafts_save']),
			],
			'spell_check' => [
				'type' => 'submit',
				'value' => Lang::getTxt('spell_check', file: 'General'),
				'show' => !empty(Utils::$context['show_spellchecking']),
			],
			'preview' => [
				'type' => 'submit',
				'value' => Lang::getTxt('preview', file: 'General'),
				'accessKey' => 'p',
			],
		];
	}

	/**
	 * Initializes and constructs the BBC (Bulletin Board Code) button toolbar for the editor.
	 *
	 * This method sets up the available BBC tags and their corresponding actions for the editor.
	 * It manages which tags are enabled, disabled, and how they appear in the toolbar. The method
	 * also allows integrations or modifications via hooks for custom functionality.
	 *
	 * Behavior:
	 * 1. Links key context variables (e.g., `bbc_tags`, `disabled_tags`, `bbc_toolbar`) for use in the editor.
	 * 2. Initializes the BBC tags with predefined options, such as the tag's code, description, and icon.
	 * 3. Maps specific BBC tags to SCEditor commands for seamless functionality.
	 * 4. Dynamically generates a list of disabled buttons based on configuration settings.
	 * 5. Applies integration hooks (`integrate_bbc_buttons`) to allow modifications to BBC buttons.
	 * 6. Assembles the toolbar structure based on the active and disabled tags.
	 */
	protected function buildBbcToolbar(): void
	{
		if (self::$bbc_tags == []) {
			self::initBbcTags();
		}

		Utils::$context['bbc_tags'] = &self::$bbc_tags;
		Utils::$context['disabled_tags'] = &self::$disabled_tags;
		Utils::$context['bbc_toolbar'] = &self::$bbc_toolbar;
		Utils::$context['bbcodes_handlers'] = &self::$bbc_handlers;

		// Map BBC tags to SCEditor commands.
		$editor_tag_map = [
			'b' => 'bold',
			'i' => 'italic',
			'u' => 'underline',
			's' => 'strike',
			'img' => 'image',
			'url' => 'link',
			'sup' => 'superscript',
			'sub' => 'subscript',
			'hr' => 'horizontalrule',
		];

		// Disable the buttons for any BBC that this user is not allowed to use.
		foreach (Utils::$context['restricted_bbc'] as $tag) {
			if (!User::$me->allowedTo('bbc_' . $tag)) {
				if ($tag === 'list') {
					$context['disabled_tags']['bulletlist'] = true;
					$context['disabled_tags']['orderedlist'] = true;
				} elseif ($tag === 'float') {
					$context['disabled_tags']['floatleft'] = true;
					$context['disabled_tags']['floatright'] = true;
				} elseif (isset($editor_tag_map[$tag])) {
					Utils::$context['disabled_tags'][$editor_tag_map[$tag]] = true;
				}

				Utils::$context['disabled_tags'][$tag] = true;
			}
		}

		// Allow mods to modify BBC buttons.
		IntegrationHook::call('integrate_bbc_buttons', [&self::$bbc_tags, &$editor_tag_map, &self::$disabled_tags]);

		// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
		$disabled_bbc = !empty(Config::$modSettings['disabledBBC']) ? explode(',', Config::$modSettings['disabledBBC']) : [];

		if (empty(Config::$modSettings['disable_wysiwyg'])) {
			self::$disabled_tags['removeformat'] = true;
			self::$disabled_tags['orderedlist'] = true;
		}

		foreach ($disabled_bbc as $tag) {
			$tag = trim($tag);

			if ($tag === 'list') {
				self::$disabled_tags['bulletlist'] = true;
				self::$disabled_tags['orderedlist'] = true;
			}

			if ($tag === 'float') {
				self::$disabled_tags['floatleft'] = true;
				self::$disabled_tags['floatright'] = true;
			}

			self::$disabled_tags[$editor_tag_map[$tag] ?? $tag] = true;
		}

		// Allow mods to modify BBC buttons.
		IntegrationHook::call('integrate_bbc_buttons', [&self::$bbc_tags, &$editor_tag_map, &self::$disabled_tags]);

		$group = 0;

		foreach (self::$bbc_tags as $row => $tag_row) {
			if (!isset(self::$bbc_toolbar[$row])) {
				self::$bbc_toolbar[$row] = [];
			}

			foreach ($tag_row as $tag) {
				if (isset($tag['code']) && !isset(self::$disabled_tags[$tag['code']])) {
					$this_tag = $editor_tag_map[$tag['code']] ?? $tag['code'];
					self::$bbc_toolbar[$row][$group][] = $this_tag;

					if (isset($tag['before']) || isset($tag['image'])) {
						self::$bbc_handlers[$this_tag] = $tag;
					}
				} else {
					$group++;
				}
			}
		}
	}

	/**
	 * Recursively implodes an array
	 *
	 * @param string[] $glue    list of values that glue elements together
	 * @param array    $pieces  multi-dimensional array to recursively implode
	 * @param int      $counter internal
	 *
	 * @return string imploded array
	 */
	protected function implodeRecursive(array $glue, array $pieces, int $counter = 0): string
	{
		return implode(
			$glue[$counter++],
			array_map(
				fn($v) => is_array($v) ? $this->implodeRecursive($glue, $v, $counter) : $v,
				$pieces,
			),
		);
	}

	/**
	 * Initialize the smiley toolbar, if enabled and not already loaded.
	 */
	protected function buildSmileysToolbar(): void
	{
		if ($this->disable_smiley_box || self::$smileys_toolbar != []) {
			return;
		}

		Utils::$context['smileys'] = &self::$smileys_toolbar;

		if (User::$me->smiley_set != 'none') {
			// Cache for longer when customized smiley codes aren't enabled
			$cache_time = empty(Config::$modSettings['smiley_enable']) ? 7200 : 480;

			if (($temp = CacheApi::get('posting_smileys_' . User::$me->smiley_set, $cache_time)) == null) {
				$request = Db::$db->query(
					'SELECT s.code, f.filename, s.description, s.smiley_row, s.hidden
					FROM {db_prefix}smileys AS s
						JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
					WHERE s.hidden IN (0, 2)
						AND f.smiley_set = {string:smiley_set}' . (empty(Config::$modSettings['smiley_enable']) ? '
						AND s.code IN ({array_string:default_codes})' : '') . '
					ORDER BY s.smiley_row, s.smiley_order',
					[
						'default_codes' => ['>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)'],
						'smiley_set' => User::$me->smiley_set,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					self::$smileys_toolbar[] = $row;
				}
				Db::$db->free_result($request);
				CacheApi::put('posting_smileys_' . User::$me->smiley_set, self::$smileys_toolbar, $cache_time);
			} else {
				self::$smileys_toolbar = $temp;
			}
		}
	}

	/**
	 * Configures the options for the SCEditor instance and applies
	 * necessary plugins, styles, and other customizations.
	 *
	 * This method sets default options for the SCEditor, including dimensions,
	 * style paths, plugins, toolbar configuration, emoticons, and localization
	 * settings.  Additionally, it allows for customization via integration hooks
	 * and external editor options provided as arguments.
	 *
	 * @param array $editorOptions An associative array of editor options provided externally.
	 *
	 * Behavior:
	 * 1. Initializes default plugins, enabling `autolinker` if URL auto-linking is enabled.
	 * 2. Configures SCEditor options such as dimensions, toolbar, colors, fonts, and parsing behavior.
	 * 3. Sets emoticons and their display behavior based on smiley toolbar configurations.
	 * 4. Provides integration hooks to allow further modification by mods.
	 */
	protected function setSCEditorOptions(array $editorOptions)
	{
		if (!isset($editorOptions['plugins'])) {
			$editorOptions['plugins'] = [];
		}

		if ($this->preview_type == self::PREVIEW_XML) {
			$editorOptions['plugins'][] = 'xmlPreview';
			Theme::loadJavaScriptFile('sceditor.plugins.xml-preview.js', ['minimize' => true], 'smf_xml_preview');
		}

		if (!empty(Config::$modSettings['autoLinkUrls']) && empty($editorOptions['disable_url_autolinking']) && User::$me->allowedTo('bbc_url')) {
			$editorOptions['plugins'][] = 'autolinker';
			Autolinker::createJavaScriptFile();
			Theme::loadJavaScriptFile('sceditor.plugins.autolinker.js', ['minimize' => true], 'smf_autolinker');
		}

		$this->sce_options = [
			'width' => $this->width ?? '100%',
			'height' => $this->height ?? '250px',
			'style' => Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/css/jquery.sceditor.default.css') ? 'theme_url' : 'default_theme_url'] . '/css/jquery.sceditor.default.css' . Utils::$context['browser_cache'],
			'autoUpdate' => true,
			'emoticonsCompat' => true,
			'emoticons' => [],
			'emoticonsEnabled' => !$this->disable_smiley_box,
			'emoticonsRoot' => Theme::$current->settings['smileys_url'] . '/',
			'colors' => [
				['black', Lang::getTxt('black', var: 'editortxt')],
				['red', Lang::getTxt('red', var: 'editortxt')],
				['yellow', Lang::getTxt('yellow', var: 'editortxt')],
				['pink', Lang::getTxt('pink', var: 'editortxt')],
				['green', Lang::getTxt('green', var: 'editortxt')],
				['orange', Lang::getTxt('orange', var: 'editortxt')],
				['purple', Lang::getTxt('purple', var: 'editortxt')],
				['blue', Lang::getTxt('blue', var: 'editortxt')],
				['beige', Lang::getTxt('beige', var: 'editortxt')],
				['brown', Lang::getTxt('brown', var: 'editortxt')],
				['teal', Lang::getTxt('teal', var: 'editortxt')],
				['navy', Lang::getTxt('navy', var: 'editortxt')],
				['maroon', Lang::getTxt('maroon', var: 'editortxt')],
				['limegreen', Lang::getTxt('lime_green', var: 'editortxt')],
				['white', Lang::getTxt('white', var: 'editortxt')],
			],
			'fonts' => 'Arial,Arial Black,Comic Sans MS,Courier New,Georgia,Impact,Sans-serif,Serif,Times New Roman,Trebuchet MS,Verdana',
			'icons' => 'monocons',
			'format' => 'bbcode',
			'plugins' => 'smf,' . implode(',', $editorOptions['plugins'] ?? []),
			'toolbar' => $this->implodeRecursive(['||', '|', ','], self::$bbc_toolbar),
			'customTextualCommands' => self::$bbc_handlers,
			'startInSourceMode' => !$this->rich_active,
			'bbcodeTrim' => false,
			'resizeWidth' => false,
			'resizeMaxHeight' => -1,
			'locale' => $this->locale ?? 'en',
			'rtl' => !empty(Utils::$context['right_to_left']),
			'commandsWithDropdown' => [
				'color' => true,
				'heading' => true,
				'font' => true,
				'size' => true,
			],
			'textOnlyCommands' => [],
			'commandsWithText' => [],
			'parserOptions' => [
				'txtVars' => [
					'code' => Lang::$txt['code'],
				],
			],
		];

		if (isset($editorOptions['options'])) {
			$this->sce_options = array_merge_recursive($this->sce_options, $editorOptions['options']);
		}

		if ($this->sce_options['emoticonsEnabled']) {
			$translations = [
				0 => 'dropdown',
				2 => 'more',
			];
			$prevRowIndex = 0;

			foreach (self::$smileys_toolbar as $smiley) {
				$this->sce_options['emoticons'][$translations[$smiley['hidden']]][$smiley['code']] = [
					'newRow' => $smiley['smiley_row'] != $prevRowIndex,
					'url' => $smiley['filename'],
					'tooltip' => Utils::htmlspecialchars(Lang::$txt['icon_' . strtolower($smiley['description'])] ?? $smiley['description']),
				];
				$prevRowIndex = $smiley['smiley_row'];
			}
		}

		// Allow mods to change $this->sce_options.
		// Useful if, e.g., a mod wants to add an SCEditor plugin.
		IntegrationHook::call('integrate_sceditor_options', [&$this->sce_options]);
	}
}
