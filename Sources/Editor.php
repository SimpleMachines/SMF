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
 * Creates the editor input box so that people can write messages to post.
 */
class Editor implements \ArrayAccess
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
			'load' => 'create_control_richedit',
			'getMessageIcons' => 'getMessageIcons',
		],
	];

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
	 * @var string
	 *
	 * Whether WYSIWYG mode is initially on or off.
	 */
	public bool $rich_active;

	/**
	 * @var string
	 *
	 * Whether to show the smiley box.
	 */
	public bool $disable_smiley_box;

	/**
	 * @var string
	 *
	 * Column width of the editor's input area.
	 */
	public int $columns;

	/**
	 * @var string
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
	 * @var string
	 *
	 *
	 */
	public static string $bbc_handlers = '';

	/**
	 * @var array
	 *
	 *
	 */
	public static array $smileys_toolbar = [
		'postform' => [],
		'popup' => [],
	];

	/****************************
	 * Internal static properties
	 ****************************/

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
	 * Constructor.
	 *
	 * @param array $options Various options for the editor.
	 */
	public function __construct(array $options)
	{
		$this->init();
		$this->buildButtons();

		// Every control must have a ID!
		$this->id = (string) ($options['id'] ?? 'message');

		$this->value = strtr((string) ($options['value'] ?? ''), [
			// Tabs are not shown in SCEditor; replace with spaces.
			"\t" => '    ',
			// The [#] item code for creating list items causes issues with
			// SCEditor, but [+] is a safe equivalent.
			'[#]' => '[+]',
		]);

		$this->disable_smiley_box = !empty($options['disable_smiley_box']);
		$this->columns = (int) ($options['columns'] ?? 60);
		$this->rows = (int) ($options['rows'] ?? 18);
		$this->width = (string) ($options['width'] ?? '70%');
		$this->height = (string) ($options['height'] ?? '175px');
		$this->form = (string) ($options['form'] ?? 'postmodify');
		$this->preview_type = (int) ($options['preview_type'] ?? self::PREVIEW_HTML);
		$this->labels = (array) ($options['labels'] ?? []);
		$this->required = !empty($options['required']);

		$this->locale = !empty(Lang::$txt['lang_dictionary']) && Lang::$txt['lang_dictionary'] != 'en' ? Lang::$txt['lang_dictionary'] : '';

		$this->rich_active = empty(Config::$modSettings['disable_wysiwyg']) && (!empty(Theme::$current->options['wysiwyg_default']) || !empty($options['force_rich']) || !empty($_REQUEST[$this->id . '_mode']));

		$this->buildBbcToolbar();
		$this->buildSmileysToolbar();
		$this->setSCEditorOptions();

		self::$loaded[$this->id] = $this;

		// Backward compatibility.
		Utils::$context['post_box_name'] = $this->id;
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
			Lang::load('Post');

			$icons = [
				['value' => 'xx', 'name' => Lang::$txt['standard']],
				['value' => 'thumbup', 'name' => Lang::$txt['thumbs_up']],
				['value' => 'thumbdown', 'name' => Lang::$txt['thumbs_down']],
				['value' => 'exclamation', 'name' => Lang::$txt['exclamation_point']],
				['value' => 'question', 'name' => Lang::$txt['question_mark']],
				['value' => 'lamp', 'name' => Lang::$txt['lamp']],
				['value' => 'smiley', 'name' => Lang::$txt['icon_smiley']],
				['value' => 'angry', 'name' => Lang::$txt['icon_angry']],
				['value' => 'cheesy', 'name' => Lang::$txt['icon_cheesy']],
				['value' => 'grin', 'name' => Lang::$txt['icon_grin']],
				['value' => 'sad', 'name' => Lang::$txt['icon_sad']],
				['value' => 'wink', 'name' => Lang::$txt['icon_wink']],
				['value' => 'poll', 'name' => Lang::$txt['icon_poll']],
			];

			foreach ($icons as $k => $dummy) {
				$icons[$k]['url'] = Theme::$current->settings['images_url'] . '/post/' . $dummy['value'] . '.png';

				$icons[$k]['is_last'] = false;
			}
		}
		// Otherwise load the icons, and check we give the right image too...
		else {
			$icons = CacheApi::get('posting_icons-' . $board_id, 480);

			if ($icons == null) {
				$icons = [];

				$request = Db::$db->query(
					'',
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
						'is_last' => false,
					];
				}
				Db::$db->free_result($request);

				CacheApi::put('posting_icons-' . $board_id, $icons, 480);
			}
		}

		IntegrationHook::call('integrate_load_message_icons', [&$icons]);

		return array_values($icons);
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

		Lang::load('Post');
		Lang::load('Editor');
		Lang::load('Drafts');

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

		// JS makes the editor go round
		Theme::loadJavaScriptFile('editor.js', ['minimize' => true], 'smf_editor');
		Theme::loadJavaScriptFile('jquery.sceditor.bbcode.min.js', [], 'smf_sceditor_bbcode');
		Theme::loadJavaScriptFile('jquery.sceditor.smf.js', ['minimize' => true], 'smf_sceditor_smf');

		$scExtraLangs = '
		$.sceditor.locale["' . Lang::$txt['lang_dictionary'] . '"] = {
			"Width (optional):": "' . Lang::$editortxt['width'] . '",
			"Height (optional):": "' . Lang::$editortxt['height'] . '",
			"Insert": "' . Lang::$editortxt['insert'] . '",
			"Description (optional):": "' . Lang::$editortxt['description'] . '",
			"Rows:": "' . Lang::$editortxt['rows'] . '",
			"Cols:": "' . Lang::$editortxt['cols'] . '",
			"URL:": "' . Lang::$editortxt['url'] . '",
			"E-mail:": "' . Lang::$editortxt['email'] . '",
			"Video URL:": "' . Lang::$editortxt['video_url'] . '",
			"More": "' . Lang::$editortxt['more'] . '",
			"Close": "' . Lang::$editortxt['close'] . '",
			dateFormat: "' . Lang::$editortxt['dateformat'] . '"
		};';

		Theme::addInlineJavaScript($scExtraLangs, true);

		Theme::addInlineJavaScript('
		var smf_smileys_url = \'' . Theme::$current->settings['smileys_url'] . '\';
		var bbc_quote_from = \'' . addcslashes(Lang::$txt['quote_from'], "'") . '\';
		var bbc_quote = \'' . addcslashes(Lang::$txt['quote'], "'") . '\';
		var bbc_search_on = \'' . addcslashes(Lang::$txt['search_on'], "'") . '\';');

		Utils::$context['shortcuts_text'] = Lang::$txt['shortcuts' . (!empty(Utils::$context['drafts_save']) ? '_drafts' : '') . (stripos($_SERVER['HTTP_USER_AGENT'], 'Macintosh') !== false ? '_mac' : (BrowserDetector::isBrowser('is_firefox') ? '_firefox' : ''))];

		if (Utils::$context['show_spellchecking']) {
			Theme::loadJavaScriptFile('spellcheck.js', ['minimize' => true], 'smf_spellcheck');

			// Some hidden information is needed in order to make the spell checking work.
			if (!isset($_REQUEST['xml'])) {
				Utils::$context['insert_after_template'] .= '
				<form name="spell_form" id="spell_form" method="post" accept-charset="' . Utils::$context['character_set'] . '" target="spellWindow" action="' . Config::$scripturl . '?action=spellcheck">
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
				'onclick' => !empty(Utils::$context['drafts_save']) ? 'submitThisOnce(this);' : (!empty(Utils::$context['drafts_save']) ? 'return confirm(' . Utils::JavaScriptEscape(Lang::$txt['draft_save_note']) . ') && submitThisOnce(this);' : ''),
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
				'value' => Lang::$txt['spell_check'],
				'show' => !empty(Utils::$context['show_spellchecking']),
			],
			'preview' => [
				'type' => 'submit',
				'value' => Lang::$txt['preview'],
				'accessKey' => 'p',
			],
		];
	}

	/**
	 * Initialize the BBC button toolbar, if not already loaded.
	 */
	protected function buildBbcToolbar(): void
	{
		if (!empty(self::$bbc_tags)) {
			return;
		}

		Utils::$context['bbc_tags'] = &self::$bbc_tags;
		Utils::$context['disabled_tags'] = &self::$disabled_tags;
		Utils::$context['bbc_toolbar'] = &self::$bbc_toolbar;
		Utils::$context['bbcodes_handlers'] = &self::$bbc_handlers;

		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		// Note: 'before' and 'after' are deprecated as of SMF 2.1. Instead, use a separate JS file to configure the functionality of your toolbar buttons.
		/*
			array(
				'code' => 'b', // Required
				'description' => Lang::$editortxt['bold'], // Required
				'image' => 'bold', // Optional
				'before' => '[b]', // Deprecated
				'after' => '[/b]', // Deprecated
			),
		*/
		self::$bbc_tags[] = [
			[
				'code' => 'bold',
				'description' => Lang::$editortxt['bold'],
			],
			[
				'code' => 'italic',
				'description' => Lang::$editortxt['italic'],
			],
			[
				'code' => 'underline',
				'description' => Lang::$editortxt['underline'],
			],
			[
				'code' => 'strike',
				'description' => Lang::$editortxt['strikethrough'],
			],
			[
				'code' => 'superscript',
				'description' => Lang::$editortxt['superscript'],
			],
			[
				'code' => 'subscript',
				'description' => Lang::$editortxt['subscript'],
			],
			[],
			[
				'code' => 'pre',
				'description' => Lang::$editortxt['preformatted_text'],
			],
			[
				'code' => 'left',
				'description' => Lang::$editortxt['align_left'],
			],
			[
				'code' => 'center',
				'description' => Lang::$editortxt['center'],
			],
			[
				'code' => 'right',
				'description' => Lang::$editortxt['align_right'],
			],
			[
				'code' => 'justify',
				'description' => Lang::$editortxt['justify'],
			],
			[],
			[
				'code' => 'font',
				'description' => Lang::$editortxt['font_name'],
			],
			[
				'code' => 'size',
				'description' => Lang::$editortxt['font_size'],
			],
			[
				'code' => 'color',
				'description' => Lang::$editortxt['font_color'],
			],
		];

		if (empty(Config::$modSettings['disable_wysiwyg'])) {
			self::$bbc_tags[count(self::$bbc_tags) - 1][] = [
				'code' => 'removeformat',
				'description' => Lang::$editortxt['remove_formatting'],
			];
		}

		self::$bbc_tags[] = [
			[
				'code' => 'floatleft',
				'description' => Lang::$editortxt['float_left'],
			],
			[
				'code' => 'floatright',
				'description' => Lang::$editortxt['float_right'],
			],
			[],
			[
				'code' => 'youtube',
				'description' => Lang::$editortxt['insert_youtube_video'],
			],
			[
				'code' => 'image',
				'description' => Lang::$editortxt['insert_image'],
			],
			[
				'code' => 'link',
				'description' => Lang::$editortxt['insert_link'],
			],
			[
				'code' => 'email',
				'description' => Lang::$editortxt['insert_email'],
			],
			[],
			[
				'code' => 'table',
				'description' => Lang::$editortxt['insert_table'],
			],
			[
				'code' => 'code',
				'description' => Lang::$editortxt['code'],
			],
			[
				'code' => 'quote',
				'description' => Lang::$editortxt['insert_quote'],
			],
			[],
			[
				'code' => 'bulletlist',
				'description' => Lang::$editortxt['bullet_list'],
			],
			[
				'code' => 'orderedlist',
				'description' => Lang::$editortxt['numbered_list'],
			],
			[
				'code' => 'horizontalrule',
				'description' => Lang::$editortxt['insert_horizontal_rule'],
			],
			[],
			[
				'code' => 'maximize',
				'description' => Lang::$editortxt['maximize'],
			],
		];

		if (empty(Config::$modSettings['disable_wysiwyg'])) {
			self::$bbc_tags[count(self::$bbc_tags) - 1][] = [
				'code' => 'source',
				'description' => Lang::$editortxt['view_source'],
			];
		}

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

		// Allow mods to modify BBC buttons.
		IntegrationHook::call('integrate_bbc_buttons', [&self::$bbc_tags, &$editor_tag_map, &self::$disabled_tags]);

		// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
		$disabled_bbc = !empty(Config::$modSettings['disabledBBC']) ? explode(',', Config::$modSettings['disabledBBC']) : [];

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

			foreach ($editor_tag_map as $tag_name => $tag_alias) {
				if ($tag === $tag_name) {
					self::$disabled_tags[$tag_alias] = true;
				}
			}

			self::$disabled_tags[$tag] = true;
		}

		$bbcodes_styles = '';

		foreach (self::$bbc_tags as $row => $tag_row) {
			if (!isset(self::$bbc_toolbar[$row])) {
				self::$bbc_toolbar[$row] = [];
			}

			$tags_row = [];

			foreach ($tag_row as $tag) {
				if (empty($tag['code'])) {
					self::$bbc_toolbar[$row][] = implode(',', $tags_row);
					$tags_row = [];
				} elseif (empty(self::$disabled_tags[$tag['code']])) {
					$tags_row[] = $tag['code'];

					// If we have a custom button image, set it now.
					if (isset($tag['image'])) {
						$bbcodes_styles .= '
						.sceditor-button-' . $tag['code'] . ' div {
							background: url(\'' . Theme::$current->settings['default_theme_url'] . '/images/bbc/' . $tag['image'] . '.png\');
						}';
					}

					// Set the tooltip and possibly the command info
					self::$bbc_handlers .= '
						sceditor.command.set(' . Utils::JavaScriptEscape($tag['code']) . ', {
							tooltip: ' . Utils::JavaScriptEscape($tag['description'] ?? $tag['code']);

					// Legacy support for 2.0 BBC mods
					if (isset($tag['before'])) {
						self::$bbc_handlers .= ',
							exec: function () {
								this.insertText(' . Utils::JavaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . Utils::JavaScriptEscape($tag['after']) : '') . ');
							},
							txtExec: [' . Utils::JavaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . Utils::JavaScriptEscape($tag['after']) : '') . ']';
					}

					self::$bbc_handlers .= '
						});';
				}
			}

			if (!empty($tags_row)) {
				self::$bbc_toolbar[$row][] = implode(',', $tags_row);
			}
		}

		if (!empty($bbcodes_styles)) {
			Theme::addInlineCss($bbcodes_styles);
		}
	}

	/**
	 * Initialize the smiley toolbar, if enabled and not already loaded.
	 */
	protected function buildSmileysToolbar(): void
	{
		if ($this->disable_smiley_box || !empty(self::$smileys_toolbar['postform']) || !empty(self::$smileys_toolbar['popup'])) {
			return;
		}

		Utils::$context['smileys'] = self::$smileys_toolbar;

		if (User::$me->smiley_set != 'none') {
			// Cache for longer when customized smiley codes aren't enabled
			$cache_time = empty(Config::$modSettings['smiley_enable']) ? 7200 : 480;

			if (($temp = CacheApi::get('posting_smileys_' . User::$me->smiley_set, $cache_time)) == null) {
				$request = Db::$db->query(
					'',
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
					$row['description'] = !empty(Lang::$txt['icon_' . strtolower($row['description'])]) ? Utils::htmlspecialchars(Lang::$txt['icon_' . strtolower($row['description'])]) : Utils::htmlspecialchars($row['description']);

					self::$smileys_toolbar[empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				Db::$db->free_result($request);

				foreach (self::$smileys_toolbar as $section => $smiley_rows) {
					foreach ($smiley_rows as $rowIndex => $smileys) {
						self::$smileys_toolbar[$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;
					}

					if (!empty($smiley_rows)) {
						self::$smileys_toolbar[$section][count($smiley_rows) - 1]['isLast'] = true;
					}
				}

				CacheApi::put('posting_smileys_' . User::$me->smiley_set, self::$smileys_toolbar, $cache_time);
			} else {
				self::$smileys_toolbar = $temp;
			}
		}
	}

	/**
	 * Initialize the smiley toolbar, if enabled and not already loaded.
	 */
	protected function setSCEditorOptions()
	{
		// Set up the SCEditor options
		$this->sce_options = [
			'width' => $this->width ?? '100%',
			'height' => $this->height ?? '175px',
			'style' => Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/css/jquery.sceditor.default.css') ? 'theme_url' : 'default_theme_url'] . '/css/jquery.sceditor.default.css' . Utils::$context['browser_cache'],
			'emoticonsCompat' => true,
			'colors' => 'black,maroon,brown,green,navy,grey,red,orange,teal,blue,white,hotpink,yellow,limegreen,purple',
			'format' => 'bbcode',
			'plugins' => '',
			'bbcodeTrim' => false,
		];

		if (!empty($this->locale)) {
			$this->sce_options['locale'] = $this->locale;
		}

		if (!empty(Utils::$context['right_to_left'])) {
			$this->sce_options['rtl'] = true;
		}

		if ($this->id != 'quickReply') {
			$this->sce_options['autofocus'] = true;
		}

		$this->sce_options['emoticons'] = [];
		$this->sce_options['emoticonsDescriptions'] = [];
		$this->sce_options['emoticonsEnabled'] = false;

		if ((!empty(self::$smileys_toolbar['postform']) || !empty(self::$smileys_toolbar['popup'])) && !$this->disable_smiley_box) {
			$this->sce_options['emoticonsEnabled'] = true;
			$this->sce_options['emoticons']['dropdown'] = [];
			$this->sce_options['emoticons']['popup'] = [];

			$count_locations = count(self::$smileys_toolbar);

			foreach (self::$smileys_toolbar as $location => $smiley_rows) {
				$count_locations--;

				unset($smiley_location);

				if ($location == 'postform') {
					$smiley_location = &$this->sce_options['emoticons']['dropdown'];
				} elseif ($location == 'popup') {
					$smiley_location = &$this->sce_options['emoticons']['popup'];
				}

				$num_rows = count($smiley_rows);

				// This is needed because otherwise the editor will remove all the duplicate (empty) keys and leave only 1 additional line
				$empty_placeholder = 0;

				foreach ($smiley_rows as $smiley_row) {
					foreach ($smiley_row['smileys'] as $smiley) {
						$smiley_location[$smiley['code']] = Theme::$current->settings['smileys_url'] . '/' . $smiley['filename'];

						$this->sce_options['emoticonsDescriptions'][$smiley['code']] = $smiley['description'];
					}

					if (empty($smiley_row['isLast']) && $num_rows != 1) {
						$smiley_location['-' . $empty_placeholder++] = '';
					}
				}
			}
		}

		$this->sce_options['parserOptions']['txtVars'] = [
			'code' => Lang::$txt['code'],
		];

		$this->sce_options['toolbar'] = '';

		if (!empty(Config::$modSettings['enableBBC'])) {
			$count_tags = count(self::$bbc_tags);

			foreach (self::$bbc_toolbar as $i => $buttonRow) {
				$this->sce_options['toolbar'] .= implode('|', $buttonRow);

				$count_tags--;

				if (!empty($count_tags)) {
					$this->sce_options['toolbar'] .= '||';
				}
			}
		}

		// Allow mods to change $this->sce_options.
		// Usful if, e.g., a mod wants to add an SCEditor plugin.
		IntegrationHook::call('integrate_sceditor_options', [&$this->sce_options]);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Editor::exportStatic')) {
	Editor::exportStatic();
}

?>