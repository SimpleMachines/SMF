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

declare(strict_types=1);

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Creates the editor input box so that people can write messages to post.
 */
class Editor implements \ArrayAccess
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
		$this->setSCEditorOptions($options);

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

		Theme::loadJavaScriptFile('jquery.sceditor.bbcode.min.js', [], 'smf_sceditor_bbcode');
		Theme::loadJavaScriptFile('jquery.sceditor.smf.js', ['minimize' => true], 'smf_sceditor_smf');

		$scExtraLangs = '
		sceditor.locale["' . Lang::$txt['lang_dictionary'] . '"] = {
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
		if (self::$bbc_tags != []) {
			return;
		}

		Utils::$context['bbc_tags'] = &self::$bbc_tags;
		Utils::$context['disabled_tags'] = &self::$disabled_tags;
		Utils::$context['bbc_toolbar'] = &self::$bbc_toolbar;
		Utils::$context['bbcodes_handlers'] = &self::$bbc_handlers;

		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		/*
			array(
				'code' => 'b', // Required
				'description' => Lang::$editortxt['bold'], // Required
				'image' => 'bold', // Optional
				'before' => '[b]', // Optional
				'after' => '[/b]', // Optional
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
			[],
			[
				'code' => 'removeformat',
				'description' => Lang::$editortxt['remove_formatting'],
			],
		];

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
			[
				'code' => 'source',
				'description' => Lang::$editortxt['view_source'],
			],
		];

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

		foreach (self::$bbc_tags as $row => $tagRow) {
			if (!isset(self::$bbc_toolbar[$row])) {
				self::$bbc_toolbar[$row] = [];
			}

			foreach ($tagRow as $tag) {
				if (isset($tag['code']) && !isset(self::$disabled_tags[$tag['code']])) {
					$thisTag = $editor_tag_map[$tag['code']] ?? $tag['code'];
					self::$bbc_toolbar[$row][$group][] = $thisTag;

					if (isset($tag['before']) || isset($tag['image'])) {
						self::$bbc_handlers[$thisTag] = $tag;
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
	protected function implodeRecursive(array $glue, array $pieces, int $counter = 0): string {
		return implode(
			$glue[$counter++],
			array_map(
				fn($v) => is_array($v) ? $this->implodeRecursive($glue, $v, $counter) : $v,
				$pieces
			)
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
	 * Initialize the smiley toolbar, if enabled and not already loaded.
	 *
	 * @param array $editorOptions Various options for the editor.
	 */
	protected function setSCEditorOptions($editorOptions)
	{
		$this->sce_options = [
			'width' => $this->width ?? '100%',
			'height' => $this->height ?? '175px',
			'style' => Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/css/jquery.sceditor.default.css') ? 'theme_url' : 'default_theme_url'] . '/css/jquery.sceditor.default.css' . Utils::$context['browser_cache'],
			'autoUpdate' => true,
			'emoticonsCompat' => true,
			'emoticons' => [],
			'emoticonsEnabled' => !$this->disable_smiley_box,
			'emoticonsRoot' => Theme::$current->settings['smileys_url'] . '/',
			'colors' => [
				['black', Lang::$editortxt['black']],
				['red', Lang::$editortxt['red']],
				['yellow', Lang::$editortxt['yellow']],
				['pink', Lang::$editortxt['pink']],
				['green', Lang::$editortxt['green']],
				['orange', Lang::$editortxt['orange']],
				['purple', Lang::$editortxt['purple']],
				['blue', Lang::$editortxt['blue']],
				['beige', Lang::$editortxt['beige']],
				['brown', Lang::$editortxt['brown']],
				['teal', Lang::$editortxt['teal']],
				['navy', Lang::$editortxt['navy']],
				['maroon', Lang::$editortxt['maroon']],
				['limegreen', Lang::$editortxt['lime_green']],
				['white', Lang::$editortxt['white']],
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
			'autofocus' => $this->id != 'quickReply',
			'rtl' => !empty(Utils::$context['right_to_left']),
			'parserOptions' => [
				'txtVars' => [
					'code' => Lang::$txt['code'],
				],
			],
		] + ($editorOptions['options'] ?? []);

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
		// Usful if, e.g., a mod wants to add an SCEditor plugin.
		IntegrationHook::call('integrate_sceditor_options', [&$this->sce_options]);
	}
}

?>