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

use SMF\Actions\Agreement;
use SMF\Actions\Notify;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\WebFetch\WebFetchApi;

/**
 * Represents a loaded theme. Also provides many theme-related static methods.
 *
 * The most recently loaded theme is always available as SMF\Theme::$current.
 * All loaded themes are available as SMF\Theme::$loaded[$id], where $id is the
 * ID number of a theme.
 *
 * The data previously available via the deprecated global $settings array is
 * now available via SMF\Theme::$current->settings.
 *
 * The data previously available via the deprecated global $options array is
 * now available via SMF\Theme::$current->options.
 */
class Theme
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		// Public static methods not listed here will keep the same name when
		// exported to global namespace.
		'func_names' => [
			'load' => 'loadTheme',
			'loadEssential' => 'loadEssentialThemeData',
			'loadTemplate' => 'loadTemplate',
			'loadSubTemplate' => 'loadSubTemplate',
			'loadCSSFile' => 'loadCSSFile',
			'addInlineCss' => 'addInlineCss',
			'loadJavaScriptFile' => 'loadJavaScriptFile',
			'addJavaScriptVar' => 'addJavaScriptVar',
			'addInlineJavaScript' => 'addInlineJavaScript',
			'setupContext' => 'setupThemeContext',
			'setupMenuContext' => 'setupMenuContext',
			'template_header' => 'template_header',
			'copyright' => 'theme_copyright',
			'template_footer' => 'template_footer',
			'template_javascript' => 'template_javascript',
			'template_css' => 'template_css',
			'custMinify' => 'custMinify',
			'deleteAllMinified' => 'deleteAllMinified',
			'setJavaScript' => 'SetJavaScript',
			'wrapAction' => 'WrapAction',
			'dispatch' => 'dispatch',
			'pickTheme' => 'PickTheme',
			'createButton' => 'create_button',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This theme's ID number.
	 */
	public int $id;

	/**
	 * @var array
	 *
	 * This theme's admin-configurable settings.
	 */
	public array $settings = [];

	/**
	 * @var array
	 *
	 * This theme's user-configurable settings.
	 */
	public array $options = [];

	/**
	 * @var array
	 *
	 * Actions that can be accessed without accepting to the registration
	 * agreement and privacy policy.
	 */
	public array $agreement_actions = [
		'agreement' => true,
		'acceptagreement' => true,
		'login2' => true,
		'logintfa' => true,
		'logout' => true,
		'pm' => ['sa' => ['popup']],
		'profile' => ['area' => ['popup', 'alerts_popup']],
		'xmlhttp' => true,
		'.xml' => true,
	];

	/**
	 * @var array
	 *
	 * Actions that do not require loading the index template.
	 */
	public array $simpleActions = [
		'findmember',
		'helpadmin',
		'printpage',
	];

	/**
	 * @var array
	 *
	 * Areas that do not require loading the index template.
	 * Parent action => array of areas
	 */
	public array $simpleAreas = [
		'profile' => ['popup', 'alerts_popup'],
	];

	/**
	 * @var array
	 *
	 * Subactions that do not require loading the index template.
	 * Parent action => array of subactions
	 */
	public array $simpleSubActions = [
		'pm' => ['popup'],
		'signup' => ['usernamecheck'],
	];

	/**
	 * @var array
	 *
	 * Extra URL params that ask for XML output instead of HTML.
	 */
	public array $extraParams = [
		'preview',
		'splitjs',
	];

	/**
	 * @var array
	 *
	 * Actions that specifically use XML output.
	 */
	public array $xmlActions = [
		'quotefast',
		'jsmodify',
		'xmlhttp',
		'post2',
		'suggest',
		'stats',
		'notifytopic',
		'notifyboard',
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static $loaded = [];

	/**
	 * @var object
	 *
	 * Instance of this class for the current theme.
	 */
	public static $current;

	/**
	 * @var array
	 *
	 * Theme variables that cannot be changed by users.
	 */
	public static array $reservedVars = [
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
		'name',
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Load a theme, by ID.
	 *
	 * @param int $id The ID of the theme to load.
	 * @param bool $initialize Whether or not to initialize a bunch of theme-related variables/settings.
	 */
	public static function load($id = 0, $initialize = true)
	{
		if (empty($id)) {
			// The theme was specified by the board.
			if (!empty(Board::$info->theme)) {
				$id = Board::$info->theme;
			}
			// The theme is the forum's default.
			else {
				$id = Config::$modSettings['theme_guests'];
			}

			// Sometimes the user can choose their own theme.
			if (!empty(Config::$modSettings['theme_allow']) || User::$me->allowedTo('admin_forum')) {
				// The theme was specified by REQUEST.
				if (!empty($_REQUEST['theme']) && (User::$me->allowedTo('admin_forum') || in_array($_REQUEST['theme'], explode(',', Config::$modSettings['knownThemes'])))) {
					$id = (int) $_REQUEST['theme'];
					$_SESSION['id_theme'] = $id;
				}
				// The theme was specified by REQUEST... previously.
				elseif (!empty($_SESSION['id_theme'])) {
					$id = (int) $_SESSION['id_theme'];
				}
				// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
				elseif (!empty(User::$me->theme)) {
					$id = User::$me->theme;
				}
			}

			// Verify the id_theme... no foul play.
			// Always allow the board specific theme, if they are overriding.
			if (!empty(Board::$info->theme) && Board::$info->override_theme) {
				$id = Board::$info->theme;
			} elseif (!empty(Config::$modSettings['enableThemes'])) {
				$themes = explode(',', Config::$modSettings['enableThemes']);

				if (!in_array($id, $themes)) {
					$id = Config::$modSettings['theme_guests'];
				} else {
					$id = (int) $id;
				}
			}
		}

		// Allow mod authors the option to override the theme id for custom page themes
		IntegrationHook::call('integrate_pre_load_theme', [&$id]);

		// If not already loaded, load it now.
		if (!isset(self::$loaded[$id])) {
			self::$loaded[$id] = new self($id, empty(User::$me->id) ? -1 : User::$me->id);
		}

		// Set the current theme to the requested one.
		self::$current = self::$loaded[$id];

		// For backward compatibility.
		if (!empty(Config::$backward_compatibility)) {
			$GLOBALS['settings'] = &self::$current->settings;
			$GLOBALS['options']  = &self::$current->options;
		}

		// Ensure that SMF\Lang knows that it should use this theme's langauge files.
		Lang::addDirs();

		// Initializing sets up a bunch more stuff.
		if (!empty($initialize)) {
			self::$current->initialize();
		}

		// Return the requested theme.
		return self::$loaded[$id];
	}

	/**
	 * This loads the bare minimum data to allow us to load language files!
	 */
	public static function loadEssential()
	{
		// Load the theme used for guests.
		$id = !empty(Config::$modSettings['theme_guests']) ? Config::$modSettings['theme_guests'] : 1;

		self::$current = self::$loaded[$id] = new self($id, 0);

		// Check we have some directories set up.
		if (empty(self::$current->settings['template_dirs'])) {
			self::$current->settings['template_dirs'] = [self::$current->settings['theme_dir']];

			// Based on theme (if there is one).
			if (!empty(self::$current->settings['base_theme_dir'])) {
				self::$current->settings['template_dirs'][] = self::$current->settings['base_theme_dir'];
			}

			// Lastly the default theme.
			if (self::$current->settings['theme_dir'] != self::$current->settings['default_theme_dir']) {
				self::$current->settings['template_dirs'][] = self::$current->settings['default_theme_dir'];
			}
		}

		// For backward compatibility.
		$GLOBALS['settings'] = &self::$current->settings;
		$GLOBALS['options']  = &self::$current->options;

		// Assume we want this.
		Utils::$context['forum_name'] = Config::$mbname;
		Utils::$context['forum_name_html_safe'] = Utils::htmlspecialchars(Utils::$context['forum_name']);

		Lang::load('index+Modifications');

		// Just in case it wasn't already set elsewhere.
		Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? Lang::$txt['lang_character_set'] : Config::$modSettings['global_character_set'];
		Utils::$context['utf8'] = Utils::$context['character_set'] === 'UTF-8';
		Utils::$context['right_to_left'] = !empty(Lang::$txt['lang_rtl']);

		// Tell ErrorHandler::fatalLang() to not reload the theme.
		Utils::$context['theme_loaded'] = true;
	}

	/**
	 * Loads a template - if the theme doesn't include it, uses the default.
	 *
	 *  - Loads a template file with the name template_name from the current,
	 *    default, or base theme.
	 *
	 *  - Detects a wrong default theme directory and tries to work around it.
	 *
	 * @uses self::templateInclude() to include the file.
	 * @param string $template_name The name of the template to load
	 * @param array|string $style_sheets The name of a single stylesheet or an array of names of stylesheets to load
	 * @param bool $fatal If true, dies with an error message if the template cannot be found
	 * @return bool Whether or not the template was loaded
	 */
	public static function loadTemplate($template_name, $style_sheets = [], $fatal = true)
	{
		// Do any style sheets first, cause we're easy with those.
		if (!empty($style_sheets)) {
			if (!is_array($style_sheets)) {
				$style_sheets = [$style_sheets];
			}

			foreach ($style_sheets as $sheet) {
				self::loadCSSFile($sheet . '.css', [], $sheet);
			}
		}

		// No template to load?
		if ($template_name === false) {
			return true;
		}

		$loaded = false;

		foreach (self::$current->settings['template_dirs'] as $template_dir) {
			if (file_exists($template_dir . '/' . $template_name . '.template.php')) {
				$loaded = true;
				self::templateInclude($template_dir . '/' . $template_name . '.template.php', true);
				break;
			}
		}

		if ($loaded) {
			if (Config::$db_show_debug === true) {
				Utils::$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';
			}

			// If they have specified an initialization function for this template, go ahead and call it now.
			if (function_exists('template_' . $template_name . '_init')) {
				call_user_func('template_' . $template_name . '_init');
			}
		}
		// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
		elseif (!file_exists(self::$current->settings['default_theme_dir']) && file_exists(Config::$boarddir . '/Themes/default')) {
			self::$current->settings['default_theme_dir'] = Config::$boarddir . '/Themes/default';
			self::$current->settings['template_dirs'][] = self::$current->settings['default_theme_dir'];

			if (!empty(User::$me->is_admin) && !isset($_GET['th'])) {
				Lang::load('Errors');
				echo '
	<div class="alert errorbox">
		<a href="', Config::$scripturl . '?action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], '" class="alert">', Lang::$txt['theme_dir_wrong'], '</a>
	</div>';
			}

			self::loadTemplate($template_name);
		}
		// Cause an error otherwise.
		elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal) {
			ErrorHandler::fatalLang('theme_template_error', 'template', [(string) $template_name]);
		} elseif ($fatal) {
			die(ErrorHandler::log(sprintf(Lang::$txt['theme_template_error'] ?? 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
		} else {
			return false;
		}
	}

	/**
	 * Loads a sub-template.
	 *
	 * 	- Loads the sub template specified by sub_template_name, which must be
	 *    in an already-loaded template.
	 *
	 *  - If ?debug is in the query string, shows administrators a marker after
	 *    every sub template for debugging purposes.
	 *
	 * @todo get rid of reading $_REQUEST directly
	 *
	 * @param string $sub_template_name The name of the sub-template to load
	 * @param bool $fatal Whether to die with an error if the sub-template can't be loaded
	 */
	public static function loadSubTemplate($sub_template_name, $fatal = false)
	{
		if (Config::$db_show_debug === true) {
			Utils::$context['debug']['sub_templates'][] = $sub_template_name;
		}

		// Figure out what the template function is named.
		$theme_function = 'template_' . $sub_template_name;

		if (function_exists($theme_function)) {
			$theme_function();
		} elseif ($fatal === false) {
			ErrorHandler::fatalLang('theme_template_error', 'template', [(string) $sub_template_name]);
		} elseif ($fatal !== 'ignore') {
			die(ErrorHandler::log(sprintf(Lang::$txt['theme_template_error'] ?? 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));
		}

		// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
		if (User::$me->allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, ['init', 'main_below']) && ob_get_length() > 0 && !isset($_REQUEST['xml'])) {
			echo "\n" . '<div class="noticebox">---- ', $sub_template_name, ' ends ----</div>';
		}
	}

	/**
	 * Adds a CSS file for output later.
	 *
	 * @param string $fileName The name of the file to load.
	 * @param array $params An array of parameters. Keys are the following:
	 *
	 * 	- ['external'] (true/false): Whether the file is a externally located
	 *       file. Needs to be set to true if you are loading an external file.
	 *
	 * 	- ['default_theme'] (true/false): Force use of default theme URL.
	 *
	 * 	- ['force_current'] (true/false): If this is false, we will attempt to
	 *       load the file from the default theme if not found in the current
	 *       theme.
	 *
	 *  - ['validate'] (true/false): If true, we will validate that the local
	 *       file exists.
	 *
	 *  - ['rtl'] (string): Additional file to load in RTL mode.
	 *
	 *  - ['seed'] (true/false/string): If true or null, use cache stale, false
	 *       do not, or used a supplied string.
	 *
	 *  - ['minimize'] (true/false): Whether to add your file to the main
	 *       minimized file. Useful when you have a file that is loaded
	 *       everywhere and for everyone.
	 *
	 *  - ['order_pos'] (int): Defines the relative load order of this file.
	 *       Default value: 3000. FYI, the positions of some of SMF's standard
	 *       CSS files are as follows: index.css = 1, attachments.css = 450,
	 *       rtl.css = 4000, responsive.css = 9000.
	 *
	 *  - ['attributes']: An array extra attributes to add to the element.
	 *
	 * @param string $id An ID to stick on the end of the filename for caching purposes
	 */
	public static function loadCSSFile($fileName, $params = [], $id = '')
	{
		if (empty(Utils::$context['css_files_order'])) {
			Utils::$context['css_files_order'] = [];
		}

		$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ?
			(array_key_exists('browser_cache', Utils::$context) ? Utils::$context['browser_cache'] : '') :
			(is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
		$params['force_current'] = $params['force_current'] ?? false;
		$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
		$params['minimize'] = $params['minimize'] ?? true;
		$params['external'] = $params['external'] ?? false;
		$params['validate'] = $params['validate'] ?? true;
		$params['order_pos'] = isset($params['order_pos']) ? (int) $params['order_pos'] : 3000;
		$params['attributes'] = $params['attributes'] ?? [];

		// Account for shorthand like admin.css?alp21 filenames
		$id = (empty($id) ? strtr(str_replace('.css', '', basename($fileName)), '?', '_') : $id) . '_css';

		$fileName = str_replace(pathinfo($fileName, PATHINFO_EXTENSION), strtok(pathinfo($fileName, PATHINFO_EXTENSION), '?'), $fileName);

		// Is this a local file?
		if (empty($params['external'])) {
			// Are we validating the the file exists?
			if (!empty($params['validate']) && ($mtime = @filemtime(self::$current->settings[$themeRef . '_dir'] . '/css/' . $fileName)) === false) {
				// Maybe the default theme has it?
				if ($themeRef === 'theme' && !$params['force_current'] && ($mtime = @filemtime(self::$current->settings['default_theme_dir'] . '/css/' . $fileName) !== false)) {
					$fileUrl = self::$current->settings['default_theme_url'] . '/css/' . $fileName;
					$filePath = self::$current->settings['default_theme_dir'] . '/css/' . $fileName;
				} else {
					$fileUrl = false;
					$filePath = false;
				}
			} else {
				$fileUrl = self::$current->settings[$themeRef . '_url'] . '/css/' . $fileName;
				$filePath = self::$current->settings[$themeRef . '_dir'] . '/css/' . $fileName;
				$mtime = @filemtime($filePath);
			}
		}
		// An external file doesn't have a filepath. Mock one for simplicity.
		else {
			$fileUrl = $fileName;
			$filePath = $fileName;

			// Always turn these off for external files.
			$params['minimize'] = false;
			$params['seed'] = false;
		}

		$mtime = empty($mtime) ? 0 : $mtime;

		// Add it to the array for use in the template
		if (!empty($fileName) && !empty($fileUrl)) {
			// Find a free number/position
			while (isset(Utils::$context['css_files_order'][$params['order_pos']])) {
				$params['order_pos']++;
			}

			Utils::$context['css_files_order'][$params['order_pos']] = $id;

			Utils::$context['css_files'][$id] = ['fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime];
		}

		if (!empty(Utils::$context['right_to_left']) && !empty($params['rtl'])) {
			self::loadCSSFile($params['rtl'], array_diff_key($params, ['rtl' => 0]));
		}

		if ($mtime > Config::$modSettings['browser_cache']) {
			Config::updateModSettings(['browser_cache' => $mtime]);
		}
	}

	/**
	 * Adds a block of inline CSS code to be executed later
	 *
	 * - Only use this if you have to. Generally external CSS files are better,
	 *   but for very small changes or for scripts that require help from
	 *   PHP/whatever, this can be useful.
	 *
	 * - All code added with this function is added to the same <style> tag,
	 *   so do make sure your css is valid!
	 *
	 * @param string $css Some css code
	 * @return void|bool Adds the CSS to the Utils::$context['css_header'] array or returns if no CSS is specified
	 */
	public static function addInlineCss($css)
	{
		// Gotta add something...
		if (empty($css)) {
			return false;
		}

		Utils::$context['css_header'][] = $css;
	}

	/**
	 * Adds a JavaScript file for output later.
	 *
	 * @param string $fileName The name of the file to load
	 * @param array $params An array of parameters. Keys are the following:
	 *
	 * 	- ['external'] (true/false): Whether the file is a externally located
	 *       file. Needs to be set to true if you are loading an external file.
	 *
	 * 	- ['default_theme'] (true/false): Force use of default theme URL.
	 *
	 * 	- ['defer'] (true/false): Whether the file should load in <head> or
	 *       before the closing <html> tag.
	 *
	 * 	- ['force_current'] (true/false): If this is false, we will attempt to
	 *       load the file from the default theme if not found in the current
	 *       theme.
	 *
	 * 	- ['async'] (true/false): Whether this script should be loaded
	 *       asynchronously.
	 *
	 *  - ['validate'] (true/false): If true, we will validate that the local
	 *       file exists.
	 *
	 *  - ['seed'] (true/false/string): If true or null, use cache stale, false
	 *       do not, or used a supplied string.
	 *
	 *  - ['minimize'] (true/false): Whether to add your file to the main
	 *       minimized file. Useful when you have a file that is loaded
	 *       everywhere and for everyone.
	 *
	 *  - ['attributes']: An array of extra attributes to add to the element.
	 *
	 * @param string $id An ID to append to the filename.
	 */
	public static function loadJavaScriptFile($fileName, $params = [], $id = '')
	{
		$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ?
			(array_key_exists('browser_cache', Utils::$context) ? Utils::$context['browser_cache'] : '') :
			(is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
		$params['force_current'] = $params['force_current'] ?? false;
		$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
		$params['async'] = $params['async'] ?? false;
		$params['defer'] = $params['defer'] ?? false;
		$params['minimize'] = $params['minimize'] ?? false;
		$params['external'] = $params['external'] ?? false;
		$params['validate'] = $params['validate'] ?? true;
		$params['attributes'] = $params['attributes'] ?? [];

		// Account for shorthand like admin.js?alp21 filenames
		$id = (empty($id) ? strtr(str_replace('.js', '', basename($fileName)), '?', '_') : $id) . '_js';
		$fileName = str_replace(pathinfo($fileName, PATHINFO_EXTENSION), strtok(pathinfo($fileName, PATHINFO_EXTENSION), '?'), $fileName);

		// Is this a local file?
		if (empty($params['external'])) {
			// Are we validating it exists on disk?
			if (!empty($params['validate']) && ($mtime = @filemtime(self::$current->settings[$themeRef . '_dir'] . '/scripts/' . $fileName)) === false) {
				// Can't find it in this theme, how about the default?
				if ($themeRef === 'theme' && !$params['force_current'] && ($mtime = @filemtime(self::$current->settings['default_theme_dir'] . '/scripts/' . $fileName)) !== false) {
					$fileUrl = self::$current->settings['default_theme_url'] . '/scripts/' . $fileName;
					$filePath = self::$current->settings['default_theme_dir'] . '/scripts/' . $fileName;
				} else {
					$fileUrl = false;
					$filePath = false;
				}
			} else {
				$fileUrl = self::$current->settings[$themeRef . '_url'] . '/scripts/' . $fileName;
				$filePath = self::$current->settings[$themeRef . '_dir'] . '/scripts/' . $fileName;
				$mtime = @filemtime($filePath);
			}
		}
		// An external file doesn't have a filepath. Mock one for simplicity.
		else {
			$fileUrl = $fileName;
			$filePath = $fileName;

			// Always turn these off for external files.
			$params['minimize'] = false;
			$params['seed'] = false;
		}

		$mtime = empty($mtime) ? 0 : $mtime;

		// Add it to the array for use in the template
		if (!empty($fileName) && !empty($fileUrl)) {
			Utils::$context['javascript_files'][$id] = ['fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime];
		}

		if ($mtime > Config::$modSettings['browser_cache']) {
			Config::updateModSettings(['browser_cache' => $mtime]);
		}
	}

	/**
	 * Add a JavaScript variable for output later (for feeding text strings and
	 * the like to JavaScript).
	 *
	 * This is cleaner and easier for modders than Theme::addInlineJavaScript().
	 *
	 * @param string $key The key for this variable
	 * @param string $value The value
	 * @param bool $escape Whether or not to escape the value
	 */
	public static function addJavaScriptVar($key, $value, $escape = false)
	{
		// Variable name must be a valid string.
		if (!is_string($key) || $key === '' || is_numeric($key)) {
			return;
		}

		// Take care of escaping the value for JavaScript?
		if (!empty($escape)) {
			switch (gettype($value)) {
				// Illegal.
				case 'resource':
					break;

				// Convert PHP objects to arrays before processing.
				case 'object':
					$value = (array) $value;
					// no break

				// Apply Utils::JavaScriptEscape() to any strings in the array.
				case 'array':
					$replacements = [];
					array_walk_recursive(
						$value,
						function ($v, $k) use (&$replacements) {
							if (is_string($v)) {
								$replacements[json_encode($v)] = Utils::JavaScriptEscape($v, true);
							}
						},
					);
					$value = strtr(json_encode($value), $replacements);
					break;

				case 'string':
					$value = Utils::JavaScriptEscape($value);
					break;

				default:
					$value = json_encode($value);
					break;
			}
		}

		// At this point, value should contain suitably escaped JavaScript code.
		// If it obviously doesn't, declare the var with an undefined value.
		if (!is_string($value) && !is_numeric($value)) {
			$value = null;
		}

		Utils::$context['javascript_vars'][$key] = $value;
	}

	/**
	 * Add a block of inline JavaScript code to be executed later.
	 *
	 * - Only use this if you have to. Generally external JS files are better,
	 *   but for very small scripts or for scripts that require help from
	 *   PHP/whatever, this can be useful.
	 *
	 * - All code added with this function is added to the same <script> tag,
	 *   so do make sure your JS is clean!
	 *
	 * @param string $javascript Some JS code
	 * @param bool $defer Whether the script should load in <head> or before the
	 *    closing <html> tag.
	 * @return void|bool Adds the code to one of the Utils::$context['javascript_inline']
	 *    arrays, or returns false if no JS was specified.
	 */
	public static function addInlineJavaScript($javascript, $defer = false)
	{
		if (empty($javascript)) {
			return false;
		}

		Utils::$context['javascript_inline'][($defer === true ? 'defer' : 'standard')][] = $javascript;
	}

	/**
	 * Sets up the basic theme context stuff.
	 *
	 * @param bool $forceload Whether to load the theme even if it's already loaded
	 */
	public static function setupContext($forceload = false)
	{
		static $loaded = false;

		// Under SSI this function can be called more then once.  That can cause some problems.
		// So only run the function once unless we are forced to run it again.
		if ($loaded && !$forceload) {
			return;
		}

		$loaded = true;

		Utils::$context['in_maintenance'] = !empty(Config::$maintenance);
		Utils::$context['current_time'] = Time::create('now')->format(null, false);
		Utils::$context['current_action'] = isset($_GET['action']) ? Utils::htmlspecialchars($_GET['action']) : '';
		Utils::$context['random_news_line'] = [];

		// Get some news...
		Utils::$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes(Config::$modSettings['news'])))));

		for ($i = 0, $n = count(Utils::$context['news_lines']); $i < $n; $i++) {
			if (trim(Utils::$context['news_lines'][$i]) == '') {
				continue;
			}

			// Clean it up for presentation ;).
			Utils::$context['news_lines'][$i] = BBCodeParser::load()->parse(stripslashes(trim(Utils::$context['news_lines'][$i])), true, 'news' . $i);
		}

		if (!empty(Utils::$context['news_lines']) && (!empty(Config::$modSettings['allow_guestAccess']) || User::$me->is_logged)) {
			Utils::$context['random_news_line'] = Utils::$context['news_lines'][mt_rand(0, count(Utils::$context['news_lines']) - 1)];
		}

		if (!User::$me->is_guest) {
			// Personal message popup...
			if (User::$me->unread_messages > ($_SESSION['unread_messages'] ?? 0)) {
				User::$me->popup_messages = true;
			} else {
				User::$me->popup_messages = false;
			}

			$_SESSION['unread_messages'] = User::$me->unread_messages;

			if (User::$me->allowedTo('moderate_forum')) {
				Utils::$context['unapproved_members'] = !empty(Config::$modSettings['unapprovedMembers']) ? Config::$modSettings['unapprovedMembers'] : 0;
			}
		} else {
			User::$me->popup_messages = false;

			// If we've upgraded recently, go easy on the passwords.
			if (!empty(Config::$modSettings['disableHashTime']) && (Config::$modSettings['disableHashTime'] == 1 || time() < Config::$modSettings['disableHashTime'])) {
				Utils::$context['disable_login_hashing'] = true;
			}
		}

		// Setup the main menu items.
		self::setupMenuContext();

		// This is here because old index templates might still use it.
		Utils::$context['show_news'] = !empty(self::$current->settings['enable_news']);

		// This is done to allow theme authors to customize it as they want.
		Utils::$context['show_pm_popup'] = User::$me->popup_messages && !empty(self::$current->options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'pm');

		// 2.1+: Add the PM popup here instead. Theme authors can still override it simply by editing/removing the 'fPmPopup' in the array.
		if (Utils::$context['show_pm_popup']) {
			self::addInlineJavaScript('
			jQuery(document).ready(function($) {
				new smc_Popup({
					heading: ' . Utils::JavaScriptEscape(Lang::$txt['show_personal_messages_heading']) . ',
					content: ' . Utils::JavaScriptEscape(sprintf(Lang::$txt['show_personal_messages'], User::$me->unread_messages, Config::$scripturl . '?action=pm')) . ',
					icon_class: \'main_icons mail_new\'
				});
			});');
		}

		// Add a generic "Are you sure?" confirmation message.
		self::addInlineJavaScript('
		var smf_you_sure =' . Utils::JavaScriptEscape(Lang::$txt['quickmod_confirm']) . ';');

		// Now add the capping code for avatars.
		if (!empty(Config::$modSettings['avatar_max_width_external']) && !empty(Config::$modSettings['avatar_max_height_external']) && !empty(Config::$modSettings['avatar_action_too_large']) && Config::$modSettings['avatar_action_too_large'] == 'option_css_resize') {
			self::addInlineCss("\n\t" . 'img.avatar { max-width: ' . Config::$modSettings['avatar_max_width_external'] . 'px !important; max-height: ' . Config::$modSettings['avatar_max_height_external'] . 'px !important; }');
		}

		// Add max image limits
		if (!empty(Config::$modSettings['max_image_width'])) {
			self::addInlineCss("\n\t" . '.postarea .bbc_img, .list_posts .bbc_img, .post .inner .bbc_img, form#reported_posts .bbc_img, #preview_body .bbc_img { max-width: min(100%,' . Config::$modSettings['max_image_width'] . 'px); }');
		}

		if (!empty(Config::$modSettings['max_image_height'])) {
			self::addInlineCss("\n\t" . '.postarea .bbc_img, .list_posts .bbc_img, .post .inner .bbc_img, form#reported_posts .bbc_img, #preview_body .bbc_img { max-height: ' . Config::$modSettings['max_image_height'] . 'px; }');
		}

		Utils::$context['common_stats'] = [
			'total_posts' => Lang::numberFormat(Config::$modSettings['totalMessages']),
			'total_topics' => Lang::numberFormat(Config::$modSettings['totalTopics']),
			'total_members' => Lang::numberFormat(Config::$modSettings['totalMembers']),
			'latest_member' => [
				'id' => Config::$modSettings['latestMember'],
				'name' => Config::$modSettings['latestRealName'],
				'href' => Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'] . '">' . Config::$modSettings['latestRealName'] . '</a>',
			],
		];

		Utils::$context['common_stats']['boardindex_total_posts'] = sprintf(Lang::$txt['boardindex_total_posts'], Utils::$context['common_stats']['total_posts'], Utils::$context['common_stats']['total_topics'], Utils::$context['common_stats']['total_members']);

		if (empty(self::$current->settings['theme_version'])) {
			self::addJavaScriptVar('smf_scripturl', '"' . Config::$scripturl . '"');
		}

		if (!isset(Utils::$context['page_title'])) {
			Utils::$context['page_title'] = '';
		}

		// Set some specific vars.
		Utils::$context['page_title_html_safe'] = Utils::htmlspecialchars(html_entity_decode(Utils::$context['page_title'])) . (!empty(Utils::$context['current_page']) ? ' - ' . Lang::$txt['page'] . ' ' . (Utils::$context['current_page'] + 1) : '');
		Utils::$context['meta_keywords'] = !empty(Config::$modSettings['meta_keywords']) ? Utils::htmlspecialchars(Config::$modSettings['meta_keywords']) : '';

		// Content related meta tags, including Open Graph
		Utils::$context['meta_tags'][] = ['property' => 'og:site_name', 'content' => Utils::$context['forum_name']];
		Utils::$context['meta_tags'][] = ['property' => 'og:title', 'content' => Utils::$context['page_title_html_safe']];

		if (!empty(Utils::$context['meta_keywords'])) {
			Utils::$context['meta_tags'][] = ['name' => 'keywords', 'content' => Utils::$context['meta_keywords']];
		}

		if (!empty(Utils::$context['canonical_url'])) {
			Utils::$context['meta_tags'][] = ['property' => 'og:url', 'content' => Utils::$context['canonical_url']];
		}

		if (!empty(self::$current->settings['og_image'])) {
			Utils::$context['meta_tags'][] = ['property' => 'og:image', 'content' => self::$current->settings['og_image']];
		}

		if (!empty(Utils::$context['meta_description'])) {
			Utils::$context['meta_tags'][] = ['property' => 'og:description', 'content' => Utils::$context['meta_description']];
			Utils::$context['meta_tags'][] = ['name' => 'description', 'content' => Utils::$context['meta_description']];
		} else {
			Utils::$context['meta_tags'][] = ['property' => 'og:description', 'content' => Utils::$context['page_title_html_safe']];
			Utils::$context['meta_tags'][] = ['name' => 'description', 'content' => Utils::$context['page_title_html_safe']];
		}

		IntegrationHook::call('integrate_theme_context');
	}

	/**
	 * Sets up all of the top menu buttons
	 * Saves them in the cache if it is available and on
	 * Places the results in Utils::$context
	 */
	public static function setupMenuContext()
	{
		// Set up the menu privileges.
		Utils::$context['allow_search'] = !empty(Config::$modSettings['allow_guestAccess']) ? User::$me->allowedTo('search_posts') : (!User::$me->is_guest && User::$me->allowedTo('search_posts'));
		Utils::$context['allow_admin'] = User::$me->allowedTo(['admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys']);

		Utils::$context['allow_memberlist'] = User::$me->allowedTo('view_mlist');
		Utils::$context['allow_calendar'] = User::$me->allowedTo('calendar_view') && !empty(Config::$modSettings['cal_enabled']);
		Utils::$context['allow_moderation_center'] = User::$me->can_mod;
		Utils::$context['allow_pm'] = User::$me->allowedTo('pm_read');

		$cacheTime = Config::$modSettings['lastActive'] * 60;

		// Initial "can you post an event in the calendar" option - but this might have been set in the calendar already.
		if (!isset(Utils::$context['allow_calendar_event'])) {
			Utils::$context['allow_calendar_event'] = Utils::$context['allow_calendar'] && User::$me->allowedTo('calendar_post');

			// If you don't allow events not linked to posts and you're not an admin, we have more work to do...
			if (Utils::$context['allow_calendar'] && Utils::$context['allow_calendar_event'] && empty(Config::$modSettings['cal_allow_unlinked']) && !User::$me->is_admin) {
				$boards_can_post = User::$me->boardsAllowedTo('post_new');
				Utils::$context['allow_calendar_event'] &= !empty($boards_can_post);
			}
		}

		// There is some menu stuff we need to do if we're coming at this from a non-guest perspective.
		if (!User::$me->is_guest) {
			self::addInlineJavaScript("\n\t" . 'var user_menus = new smc_PopupMenu();' . "\n\t" . 'user_menus.add("profile", "' . Config::$scripturl . '?action=profile;area=popup");' . "\n\t" . 'user_menus.add("alerts", "' . Config::$scripturl . '?action=profile;area=alerts_popup;u=' . User::$me->id . '");', true);

			if (Utils::$context['allow_pm']) {
				self::addInlineJavaScript("\n\t" . 'user_menus.add("pm", "' . Config::$scripturl . '?action=pm;sa=popup");', true);
			}

			if (!empty(Config::$modSettings['enable_ajax_alerts'])) {
				$timeout = Notify::getNotifyPrefs(User::$me->id, 'alert_timeout', true);
				$timeout = empty($timeout) ? 10000 : $timeout[User::$me->id]['alert_timeout'] * 1000;

				self::addInlineJavaScript("\n\t" . 'var new_alert_title = "' . Utils::$context['forum_name_html_safe'] . '";' . "\n\t" . 'var alert_timeout = ' . $timeout . ';');

				self::loadJavaScriptFile('alerts.js', ['minimize' => true], 'smf_alerts');
			}
		}

		// All the buttons we can possibly want and then some.
		// Try pulling the final list of buttons from cache first.
		if (($menu_buttons = CacheApi::get('menu_buttons-' . implode('_', User::$me->groups) . '-' . User::$me->language, $cacheTime)) === null || time() - $cacheTime <= Config::$modSettings['settings_updated']) {
			$buttons = [
				'home' => [
					'title' => Lang::$txt['home'],
					'href' => Config::$scripturl,
					'show' => true,
					'sub_buttons' => [
					],
					'is_last' => Utils::$context['right_to_left'],
				],
				'search' => [
					'title' => Lang::$txt['search'],
					'href' => Config::$scripturl . '?action=search',
					'show' => Utils::$context['allow_search'],
					'sub_buttons' => [
					],
				],
				'admin' => [
					'title' => Lang::$txt['admin'],
					'href' => Config::$scripturl . '?action=admin',
					'show' => Utils::$context['allow_admin'],
					'sub_buttons' => [
						'featuresettings' => [
							'title' => Lang::$txt['modSettings_title'],
							'href' => Config::$scripturl . '?action=admin;area=featuresettings',
							'show' => User::$me->allowedTo('admin_forum'),
						],
						'packages' => [
							'title' => Lang::$txt['package'],
							'href' => Config::$scripturl . '?action=admin;area=packages',
							'show' => User::$me->allowedTo('admin_forum'),
						],
						'errorlog' => [
							'title' => Lang::$txt['errorlog'],
							'href' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc',
							'show' => User::$me->allowedTo('admin_forum') && !empty(Config::$modSettings['enableErrorLogging']),
						],
						'permissions' => [
							'title' => Lang::$txt['edit_permissions'],
							'href' => Config::$scripturl . '?action=admin;area=permissions',
							'show' => User::$me->allowedTo('manage_permissions'),
						],
						'memberapprove' => [
							'title' => Lang::$txt['approve_members_waiting'],
							'href' => Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve',
							'show' => !empty(Utils::$context['unapproved_members']),
							'is_last' => true,
						],
					],
				],
				'moderate' => [
					'title' => Lang::$txt['moderate'],
					'href' => Config::$scripturl . '?action=moderate',
					'show' => Utils::$context['allow_moderation_center'],
					'sub_buttons' => [
						'modlog' => [
							'title' => Lang::$txt['modlog_view'],
							'href' => Config::$scripturl . '?action=moderate;area=modlog',
							'show' => !empty(Config::$modSettings['modlog_enabled']) && !empty(User::$me->mod_cache) && User::$me->mod_cache['bq'] != '0=1',
						],
						'poststopics' => [
							'title' => Lang::$txt['mc_unapproved_poststopics'],
							'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=posts',
							'show' => Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']),
						],
						'attachments' => [
							'title' => Lang::$txt['mc_unapproved_attachments'],
							'href' => Config::$scripturl . '?action=moderate;area=attachmod;sa=attachments',
							'show' => Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']),
						],
						'reports' => [
							'title' => Lang::$txt['mc_reported_posts'],
							'href' => Config::$scripturl . '?action=moderate;area=reportedposts',
							'show' => !empty(User::$me->mod_cache) && User::$me->mod_cache['bq'] != '0=1',
						],
						'reported_members' => [
							'title' => Lang::$txt['mc_reported_members'],
							'href' => Config::$scripturl . '?action=moderate;area=reportedmembers',
							'show' => User::$me->allowedTo('moderate_forum'),
							'is_last' => true,
						],
					],
				],
				'calendar' => [
					'title' => Lang::$txt['calendar'],
					'href' => Config::$scripturl . '?action=calendar',
					'show' => Utils::$context['allow_calendar'],
					'sub_buttons' => [
						'view' => [
							'title' => Lang::$txt['calendar_menu'],
							'href' => Config::$scripturl . '?action=calendar',
							'show' => Utils::$context['allow_calendar_event'],
						],
						'post' => [
							'title' => Lang::$txt['calendar_post_event'],
							'href' => Config::$scripturl . '?action=calendar;sa=post',
							'show' => Utils::$context['allow_calendar_event'],
							'is_last' => true,
						],
					],
				],
				'mlist' => [
					'title' => Lang::$txt['members_title'],
					'href' => Config::$scripturl . '?action=mlist',
					'show' => Utils::$context['allow_memberlist'],
					'sub_buttons' => [
						'mlist_view' => [
							'title' => Lang::$txt['mlist_menu_view'],
							'href' => Config::$scripturl . '?action=mlist',
							'show' => true,
						],
						'mlist_search' => [
							'title' => Lang::$txt['mlist_search'],
							'href' => Config::$scripturl . '?action=mlist;sa=search',
							'show' => true,
							'is_last' => true,
						],
					],
					'is_last' => !Utils::$context['right_to_left'] && empty(self::$current->settings['login_main_menu']),
				],
				// Theme authors: If you want the login and register buttons to appear in
				// the main forum menu on your theme, set Theme::$current->settings['login_main_menu'] to
				// true in your theme's template_init() function in index.template.php.
				'login' => [
					'title' => Lang::$txt['login'],
					'href' => Config::$scripturl . '?action=login',
					'onclick' => 'return reqOverlayDiv(this.href, ' . Utils::JavaScriptEscape(Lang::$txt['login']) . ', \'login\');',
					'show' => User::$me->is_guest && !empty(self::$current->settings['login_main_menu']),
					'sub_buttons' => [
					],
					'is_last' => !Utils::$context['right_to_left'],
				],
				'logout' => [
					'title' => Lang::$txt['logout'],
					'href' => Config::$scripturl . '?action=logout;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'show' => !User::$me->is_guest && !empty(self::$current->settings['login_main_menu']),
					'sub_buttons' => [
					],
					'is_last' => !Utils::$context['right_to_left'],
				],
				'signup' => [
					'title' => Lang::$txt['register'],
					'href' => Config::$scripturl . '?action=signup',
					'icon' => 'regcenter',
					'show' => User::$me->is_guest && Utils::$context['can_register'] && !empty(self::$current->settings['login_main_menu']),
					'sub_buttons' => [
					],
					'is_last' => !Utils::$context['right_to_left'],
				],
			];

			// Allow editing menu buttons easily.
			IntegrationHook::call('integrate_menu_buttons', [&$buttons]);

			// Now we put the buttons in the context so the theme can use them.
			$menu_buttons = [];

			foreach ($buttons as $act => $button) {
				if (!empty($button['show'])) {
					$button['active_button'] = false;

					// Make sure the last button truly is the last button.
					if (!empty($button['is_last'])) {
						if (isset($last_button)) {
							unset($menu_buttons[$last_button]['is_last']);
						}

						$last_button = $act;
					}

					// Go through the sub buttons if there are any.
					if (!empty($button['sub_buttons'])) {
						foreach ($button['sub_buttons'] as $key => $subbutton) {
							if (empty($subbutton['show'])) {
								unset($button['sub_buttons'][$key]);
							}

							// 2nd level sub buttons next...
							if (!empty($subbutton['sub_buttons'])) {
								foreach ($subbutton['sub_buttons'] as $key2 => $sub_button2) {
									if (empty($sub_button2['show'])) {
										unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
									}
								}
							}
						}
					}

					// Does this button have its own icon?
					if (isset($button['icon']) && file_exists(self::$current->settings['theme_dir'] . '/images/' . $button['icon'])) {
						$button['icon'] = '<img src="' . self::$current->settings['images_url'] . '/' . $button['icon'] . '" alt="">';
					} elseif (isset($button['icon']) && file_exists(self::$current->settings['default_theme_dir'] . '/images/' . $button['icon'])) {
						$button['icon'] = '<img src="' . self::$current->settings['default_images_url'] . '/' . $button['icon'] . '" alt="">';
					} elseif (isset($button['icon'])) {
						$button['icon'] = '<span class="main_icons ' . $button['icon'] . '"></span>';
					} else {
						$button['icon'] = '<span class="main_icons ' . $act . '"></span>';
					}

					$menu_buttons[$act] = $button;
				}
			}

			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('menu_buttons-' . implode('_', User::$me->groups) . '-' . User::$me->language, $menu_buttons, $cacheTime);
			}
		}

		Utils::$context['menu_buttons'] = $menu_buttons;

		// Logging out requires the session id in the url.
		if (isset(Utils::$context['menu_buttons']['logout'])) {
			Utils::$context['menu_buttons']['logout']['href'] = sprintf(Utils::$context['menu_buttons']['logout']['href'], Utils::$context['session_var'], Utils::$context['session_id']);
		}

		// Figure out which action we are doing so we can set the active tab.
		// Default to home.
		$current_action = 'home';

		if (isset(Utils::$context['menu_buttons'][Utils::$context['current_action']])) {
			$current_action = Utils::$context['current_action'];
		} elseif (Utils::$context['current_action'] == 'search2') {
			$current_action = 'search';
		} elseif (Utils::$context['current_action'] == 'theme') {
			$current_action = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? 'profile' : 'admin';
		} elseif (Utils::$context['current_action'] == 'signup2') {
			$current_action = 'signup';
		} elseif (Utils::$context['current_action'] == 'login2' || (User::$me->is_guest && Utils::$context['current_action'] == 'reminder')) {
			$current_action = 'login';
		} elseif (Utils::$context['current_action'] == 'groups' && Utils::$context['allow_moderation_center'] && User::$me->mod_cache['gq'] != '0=1') {
			$current_action = 'moderate';
		}

		// There are certain exceptions to the above where we don't want anything on the menu highlighted.
		if (Utils::$context['current_action'] == 'profile' && !empty(User::$me->is_owner)) {
			$current_action = !empty($_GET['area']) && $_GET['area'] == 'showalerts' ? 'self_alerts' : 'self_profile';

			Utils::$context[$current_action] = true;
		} elseif (Utils::$context['current_action'] == 'pm') {
			$current_action = 'self_pm';
			Utils::$context['self_pm'] = true;
		}

		Utils::$context['total_mod_reports'] = 0;
		Utils::$context['total_admin_reports'] = 0;

		if (!empty(User::$me->mod_cache) && User::$me->mod_cache['bq'] != '0=1' && !empty(Utils::$context['open_mod_reports']) && !empty(Utils::$context['menu_buttons']['moderate']['sub_buttons']['reports'])) {
			Utils::$context['total_mod_reports'] = Utils::$context['open_mod_reports'];
			Utils::$context['menu_buttons']['moderate']['sub_buttons']['reports']['amt'] = Utils::$context['open_mod_reports'];
		}

		// Show how many errors there are
		if (!empty(Utils::$context['menu_buttons']['admin']['sub_buttons']['errorlog'])) {
			// Get an error count, if necessary
			if (!isset(Utils::$context['num_errors'])) {
				$query = Db::$db->query(
					'',
					'SELECT COUNT(*)
					FROM {db_prefix}log_errors',
					[],
				);
				list(Utils::$context['num_errors']) = Db::$db->fetch_row($query);
				Db::$db->free_result($query);
			}

			if (!empty(Utils::$context['num_errors'])) {
				Utils::$context['total_admin_reports'] += Utils::$context['num_errors'];
				Utils::$context['menu_buttons']['admin']['sub_buttons']['errorlog']['amt'] = Utils::$context['num_errors'];
			}
		}

		// Show number of reported members
		if (!empty(Utils::$context['open_member_reports']) && !empty(Utils::$context['menu_buttons']['moderate']['sub_buttons']['reported_members'])) {
			Utils::$context['total_mod_reports'] += Utils::$context['open_member_reports'];
			Utils::$context['menu_buttons']['moderate']['sub_buttons']['reported_members']['amt'] = Utils::$context['open_member_reports'];
		}

		if (!empty(Utils::$context['unapproved_members']) && !empty(Utils::$context['menu_buttons']['admin'])) {
			Utils::$context['menu_buttons']['admin']['sub_buttons']['memberapprove']['amt'] = Utils::$context['unapproved_members'];
			Utils::$context['total_admin_reports'] += Utils::$context['unapproved_members'];
		}

		if (Utils::$context['total_admin_reports'] > 0 && !empty(Utils::$context['menu_buttons']['admin'])) {
			Utils::$context['menu_buttons']['admin']['amt'] = Utils::$context['total_admin_reports'];
		}

		// Do we have any open reports?
		if (Utils::$context['total_mod_reports'] > 0 && !empty(Utils::$context['menu_buttons']['moderate'])) {
			Utils::$context['menu_buttons']['moderate']['amt'] = Utils::$context['total_mod_reports'];
		}

		// Not all actions are simple.
		IntegrationHook::call('integrate_current_action', [&$current_action]);

		if (isset(Utils::$context['menu_buttons'][$current_action])) {
			Utils::$context['menu_buttons'][$current_action]['active_button'] = true;
		}
	}

	/**
	 * The header template.
	 */
	public static function template_header()
	{
		self::setupContext();

		// Print stuff to prevent caching of pages (except on attachment errors, etc.)
		if (empty(Utils::$context['no_last_modified'])) {
			header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

			// Are we debugging the template/html content?
			if (!isset($_REQUEST['xml']) && isset($_GET['debug']) && !BrowserDetector::isBrowser('ie')) {
				header('content-type: application/xhtml+xml');
			} elseif (!isset($_REQUEST['xml'])) {
				header('content-type: text/html; charset=' . (empty(Utils::$context['character_set']) ? 'ISO-8859-1' : Utils::$context['character_set']));
			}
		}

		header('content-type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=' . (empty(Utils::$context['character_set']) ? 'ISO-8859-1' : Utils::$context['character_set']));

		// We need to splice this in after the body layer, or after the main layer for older stuff.
		if (Utils::$context['in_maintenance'] && User::$me->is_admin) {
			$position = array_search('body', Utils::$context['template_layers']);

			if ($position === false) {
				$position = array_search('main', Utils::$context['template_layers']);
			}

			if ($position !== false) {
				$before = array_slice(Utils::$context['template_layers'], 0, $position + 1);
				$after = array_slice(Utils::$context['template_layers'], $position + 1);
				Utils::$context['template_layers'] = array_merge($before, ['maint_warning'], $after);
			}
		}

		$checked_securityFiles = false;
		$showed_banned = false;

		foreach (Utils::$context['template_layers'] as $layer) {
			self::loadSubTemplate($layer . '_above', true);

			// May seem contrived, but this is done in case the body and main layer aren't there...
			if (in_array($layer, ['body', 'main']) && User::$me->allowedTo('admin_forum') && !User::$me->is_guest && !$checked_securityFiles) {
				$checked_securityFiles = true;

				$securityFiles = ['install.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~'];

				// Add your own files.
				IntegrationHook::call('integrate_security_files', [&$securityFiles]);

				foreach ($securityFiles as $i => $securityFile) {
					if (!file_exists(Config::$boarddir . '/' . $securityFile)) {
						unset($securityFiles[$i]);
					}
				}

				// We are already checking so many files...just few more doesn't make any difference! :P
				if (!empty(Config::$modSettings['currentAttachmentUploadDir'])) {
					$path = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];
				} else {
					$path = Config::$modSettings['attachmentUploadDir'];
				}

				Security::secureDirectory($path, true);
				Security::secureDirectory(Config::$cachedir);

				// If agreement is enabled, at least the english version shall exist
				if (!empty(Config::$modSettings['requireAgreement'])) {
					$agreement = !file_exists(Config::$boarddir . '/agreement.txt');
				}

				// If privacy policy is enabled, at least the default language version shall exist
				if (!empty(Config::$modSettings['requirePolicyAgreement'])) {
					$policy_agreement = empty(Config::$modSettings['policy_' . Lang::$default]);
				}

				if (
					!empty($securityFiles)
					|| (
						!empty(CacheApi::$enable)
						&& !is_writable(Config::$cachedir)
					)
					|| !empty($agreement)
					|| !empty($policy_agreement)
					|| !empty(Utils::$context['auth_secret_missing'])) {
					echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', empty($securityFiles) && empty(Utils::$context['auth_secret_missing']) ? Lang::$txt['generic_warning'] : Lang::$txt['security_risk'], '</h3>
				<p>';

					foreach ($securityFiles as $securityFile) {
						echo '
					', Lang::$txt['not_removed'], '<strong>', $securityFile, '</strong>!<br>';

						if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~') {
							echo '
					', sprintf(Lang::$txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br>';
						}
					}

					if (!empty(CacheApi::$enable) && !is_writable(Config::$cachedir)) {
						echo '
					<strong>', Lang::$txt['cache_writable'], '</strong><br>';
					}

					if (!empty($agreement)) {
						echo '
					<strong>', Lang::$txt['agreement_missing'], '</strong><br>';
					}

					if (!empty($policy_agreement)) {
						echo '
					<strong>', Lang::$txt['policy_agreement_missing'], '</strong><br>';
					}

					if (!empty(Utils::$context['auth_secret_missing'])) {
						echo '
					<strong>', Lang::$txt['auth_secret_missing'], '</strong><br>';
					}

					echo '
				</p>
			</div>';
				}
			}
			// If the user is banned from posting inform them of it.
			elseif (in_array($layer, ['main', 'body']) && isset($_SESSION['ban']['cannot_post']) && !$showed_banned) {
				$showed_banned = true;
				echo '
					<div class="windowbg alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red;">
						', sprintf(Lang::$txt['you_are_post_banned'], User::$me->is_guest ? Lang::$txt['guest_title'] : User::$me->name);

				if (!empty($_SESSION['ban']['cannot_post']['reason'])) {
					echo '
						<div style="padding-left: 4ex; padding-top: 1ex;">', $_SESSION['ban']['cannot_post']['reason'], '</div>';
				}

				if (!empty($_SESSION['ban']['expire_time'])) {
					echo '
						<div>', sprintf(Lang::$txt['your_ban_expires'], Time::create('@' . $_SESSION['ban']['expire_time'])->format(null, false)), '</div>';
				} else {
					echo '
						<div>', Lang::$txt['your_ban_expires_never'], '</div>';
				}

				echo '
					</div>';
			}
		}
	}

	/**
	 * Show the copyright.
	 */
	public static function copyright()
	{
		// Don't display copyright for things like SSI.
		if (SMF !== 1) {
			return;
		}

		// Put in the version...
		printf(Lang::$forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl);
	}

	/**
	 * The template footer.
	 */
	public static function template_footer()
	{
		// Show the load time?  (only makes sense for the footer.)
		Utils::$context['show_load_time'] = !empty(Config::$modSettings['timeLoadPageEnable']);
		Utils::$context['load_time'] = round(microtime(true) - TIME_START, 3);
		Utils::$context['load_queries'] = Db::$count;

		if (!empty(Utils::$context['template_layers']) && is_array(Utils::$context['template_layers'])) {
			foreach (array_reverse(Utils::$context['template_layers']) as $layer) {
				self::loadSubTemplate($layer . '_below', true);
			}
		}
	}

	/**
	 * Output the inline JavaScript and JavaScript files.
	 *
	 * @param bool $do_deferred If true will only output the deferred JS
	 *             (the stuff that goes right before the closing body tag)
	 */
	public static function template_javascript($do_deferred = false)
	{
		// Use this hook to minify/optimize Javascript files and vars
		IntegrationHook::call('integrate_pre_javascript_output', [&$do_deferred]);

		$toMinify = [
			'standard' => [],
			'defer' => [],
			'async' => [],
		];

		// Output the declared Javascript variables.
		if (!empty(Utils::$context['javascript_vars']) && !$do_deferred) {
			echo "\n\t<script>";

			foreach (Utils::$context['javascript_vars'] as $key => $value) {
				if (!is_string($key) || is_numeric($key)) {
					continue;
				}

				if (!is_string($value) && !is_numeric($value)) {
					$value = null;
				}

				echo "\n\t\t", 'var ', $key, isset($value) ? ' = ' . $value : '', ';';
			}

			echo "\n\t</script>";
		}

		// In the dark days before HTML5, deferred JS files needed to be loaded at the end of the body.
		// Now we load them in the head and use 'async' and/or 'defer' attributes. Much better performance.
		if (!$do_deferred) {
			// While we have JavaScript files to place in the template.
			foreach (Utils::$context['javascript_files'] as $id => $js_file) {
				// Last minute call! allow theme authors to disable single files.
				if (!empty(self::$current->settings['disable_files']) && in_array($id, self::$current->settings['disable_files'])) {
					continue;
				}

				// By default files don't get minimized unless the file explicitly says so!
				if (!empty($js_file['options']['minimize']) && !empty(Config::$modSettings['minimize_files'])) {
					if (!empty($js_file['options']['async'])) {
						$toMinify['async'][] = $js_file;
					} elseif (!empty($js_file['options']['defer'])) {
						$toMinify['defer'][] = $js_file;
					} else {
						$toMinify['standard'][] = $js_file;
					}

					// Grab a random seed.
					if (!isset($minSeed) && isset($js_file['options']['seed'])) {
						$minSeed = $js_file['options']['seed'];
					}
				} else {
					echo "\n\t" . '<script src="', $js_file['fileUrl'], $js_file['options']['seed'] ?? '', '"', !empty($js_file['options']['async']) ? ' async' : '', !empty($js_file['options']['defer']) ? ' defer' : '';

					if (!empty($js_file['options']['attributes'])) {
						foreach ($js_file['options']['attributes'] as $key => $value) {
							if (is_bool($value)) {
								echo !empty($value) ? ' ' . $key : '';
							} else {
								echo ' ', $key, '="', $value, '"';
							}
						}
					}

					echo '></script>';
				}
			}

			foreach ($toMinify as $js_files) {
				if (!empty($js_files)) {
					$result = self::custMinify($js_files, 'js');

					$minSuccessful = array_keys($result) === ['smf_minified'];

					foreach ($result as $minFile) {
						echo "\n\t" . '<script src="', $minFile['fileUrl'], $minSuccessful && isset($minSeed) ? $minSeed : '', '"', !empty($minFile['options']['async']) ? ' async' : '', !empty($minFile['options']['defer']) ? ' defer' : '', '></script>';
					}
				}
			}
		}

		// Inline JavaScript - Actually useful some times!
		if (!empty(Utils::$context['javascript_inline'])) {
			if (!empty(Utils::$context['javascript_inline']['defer']) && $do_deferred) {
				echo "\n" . '<script>';
				echo "\n" . 'window.addEventListener("DOMContentLoaded", function() {';

				foreach (Utils::$context['javascript_inline']['defer'] as $js_code) {
					echo "\n\t" . trim($js_code);
				}

				echo "\n" . '});';
				echo "\n" . '</script>';
			}

			if (!empty(Utils::$context['javascript_inline']['standard']) && !$do_deferred) {
				echo "\n\t" . '<script>';

				foreach (Utils::$context['javascript_inline']['standard'] as $js_code) {
					echo "\n\t\t" . trim($js_code);
				}

				echo "\n\t" . '</script>';
			}
		}
	}

	/**
	 * Output the CSS files
	 */
	public static function template_css()
	{
		// Use this hook to minify/optimize CSS files
		IntegrationHook::call('integrate_pre_css_output');

		$toMinify = [];
		$normal = [];

		uasort(
			Utils::$context['css_files'],
			function ($a, $b) {
				return $a['options']['order_pos'] < $b['options']['order_pos'] ? -1 : ($a['options']['order_pos'] > $b['options']['order_pos'] ? 1 : 0);
			},
		);

		foreach (Utils::$context['css_files'] as $id => $file) {
			// Last minute call! allow theme authors to disable single files.
			if (!empty(self::$current->settings['disable_files']) && in_array($id, self::$current->settings['disable_files'])) {
				continue;
			}

			// Files are minimized unless they explicitly opt out.
			if (!isset($file['options']['minimize'])) {
				$file['options']['minimize'] = true;
			}

			if (!empty($file['options']['minimize']) && !empty(Config::$modSettings['minimize_files']) && !isset($_REQUEST['normalcss'])) {
				$toMinify[] = $file;

				// Grab a random seed.
				if (!isset($minSeed) && isset($file['options']['seed'])) {
					$minSeed = $file['options']['seed'];
				}
			} else {
				$normal[] = [
					'url' => $file['fileUrl'] . ($file['options']['seed'] ?? ''),
					'attributes' => !empty($file['options']['attributes']) ? $file['options']['attributes'] : [],
				];
			}
		}

		if (!empty($toMinify)) {
			$result = self::custMinify($toMinify, 'css');

			$minSuccessful = array_keys($result) === ['smf_minified'];

			foreach ($result as $minFile) {
				echo "\n\t" . '<link rel="stylesheet" href="', $minFile['fileUrl'], $minSuccessful && isset($minSeed) ? $minSeed : '', '">';
			}
		}

		// Print the rest after the minified files.
		if (!empty($normal)) {
			foreach ($normal as $nf) {
				echo "\n\t" . '<link rel="stylesheet" href="', $nf['url'], '"';

				if (!empty($nf['attributes'])) {
					foreach ($nf['attributes'] as $key => $value) {
						if (is_bool($value)) {
							echo !empty($value) ? ' ' . $key : '';
						} else {
							echo ' ', $key, '="', $value, '"';
						}
					}
				}

				echo '>';
			}
		}

		if (Config::$db_show_debug === true) {
			// Try to keep only what's useful.
			$repl = [Config::$boardurl . '/Themes/' => '', Config::$boardurl . '/' => ''];

			foreach (Utils::$context['css_files'] as $file) {
				Utils::$context['debug']['sheets'][] = strtr($file['fileName'], $repl);
			}
		}

		if (!empty(Utils::$context['css_header'])) {
			echo "\n\t" . '<style>';

			foreach (Utils::$context['css_header'] as $css) {
				echo "\n\t" . trim($css);
			}

			echo "\n\t" . '</style>';
		}
	}

	/**
	 * Get an array of previously defined files and adds them to our main minified files.
	 * Sets a one day cache to avoid re-creating a file on every request.
	 *
	 * @param array $data The files to minify.
	 * @param string $type either css or js.
	 * @return array Info about the minified file, or about the original files if the minify process failed.
	 */
	public static function custMinify($data, $type)
	{
		$types = ['css', 'js'];
		$type = !empty($type) && in_array($type, $types) ? $type : false;
		$data = is_array($data) ? $data : [];

		if (empty($type) || empty($data)) {
			return $data;
		}

		// Different pages include different files, so we use a hash to label the different combinations
		$hash = md5(implode(' ', array_map(
			function ($file) {
				return $file['filePath'] . '-' . $file['mtime'];
			},
			$data,
		)));

		// Is this a deferred or asynchronous JavaScript file?
		$async = $type === 'js';
		$defer = $type === 'js';

		if ($type === 'js') {
			foreach ($data as $id => $file) {
				// A minified script should only be loaded asynchronously if all its components wanted to be.
				if (empty($file['options']['async'])) {
					$async = false;
				}

				// A minified script should only be deferred if all its components wanted to be.
				if (empty($file['options']['defer'])) {
					$defer = false;
				}
			}
		}

		// Did we already do this?
		$minified_file = self::$current->settings['theme_dir'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/minified_' . $hash . '.' . $type;
		$already_exists = file_exists($minified_file);

		// Already done?
		if ($already_exists) {
			return ['smf_minified' => [
				'fileUrl' => self::$current->settings['theme_url'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/' . basename($minified_file),
				'filePath' => $minified_file,
				'fileName' => basename($minified_file),
				'options' => ['async' => !empty($async), 'defer' => !empty($defer)],
			]];
		}

		// File has to exist. If it doesn't, try to create it.
		if (@fopen($minified_file, 'w') === false || !Utils::makeWritable($minified_file)) {
			Lang::load('Errors');
			ErrorHandler::log(sprintf(Lang::$txt['file_not_created'], $minified_file), 'general');

			// The process failed, so roll back to print each individual file.
			return $data;
		}

		// No namespaces, sorry!
		$classType = 'MatthiasMullie\\Minify\\' . strtoupper($type);

		$minifier = new $classType();

		foreach ($data as $id => $file) {
			$toAdd = !empty($file['filePath']) && file_exists($file['filePath']) ? $file['filePath'] : false;

			// The file couldn't be located so it won't be added. Log this error.
			if (empty($toAdd)) {
				Lang::load('Errors');
				ErrorHandler::log(sprintf(Lang::$txt['file_minimize_fail'], !empty($file['fileName']) ? $file['fileName'] : $id), 'general');

				continue;
			}

			// Add this file to the list.
			$minifier->add($toAdd);
		}

		// Create the file.
		$minifier->minify($minified_file);
		unset($minifier);
		clearstatcache();

		// Minify process failed.
		if (!filesize($minified_file)) {
			Lang::load('Errors');
			ErrorHandler::log(sprintf(Lang::$txt['file_not_created'], $minified_file), 'general');

			// The process failed so roll back to print each individual file.
			return $data;
		}

		return ['smf_minified' => [
			'fileUrl' => self::$current->settings['theme_url'] . '/' . ($type == 'css' ? 'css' : 'scripts') . '/' . basename($minified_file),
			'filePath' => $minified_file,
			'fileName' => basename($minified_file),
			'options' => ['async' => $async, 'defer' => $defer],
		]];
	}

	/**
	 * Clears out old minimized CSS and JavaScript files and ensures Config::$modSettings['browser_cache'] is up to date
	 */
	public static function deleteAllMinified()
	{
		$not_deleted = [];
		$most_recent = 0;

		// Kinda sucks that we need to do another query to get all the theme dirs, but c'est la vie.
		$request = Db::$db->query(
			'',
			'SELECT id_theme AS id, value AS dir
			FROM {db_prefix}themes
			WHERE variable = {string:var}',
			[
				'var' => 'theme_dir',
			],
		);

		while ($theme = Db::$db->fetch_assoc($request)) {
			foreach (['css', 'js'] as $type) {
				foreach (glob(rtrim($theme['dir'], '/') . '/' . ($type == 'css' ? 'css' : 'scripts') . '/*.' . $type) as $filename) {
					// We want to find the most recent mtime of non-minified files
					if (strpos(pathinfo($filename, PATHINFO_BASENAME), 'minified') === false) {
						$most_recent = max($most_recent, (int) @filemtime($filename));
					}
					// Try to delete minified files. Add them to our error list if that fails.
					elseif (!@unlink($filename)) {
						$not_deleted[] = $filename;
					}
				}
			}
		}
		Db::$db->free_result($request);

		// This setting tracks the most recent modification time of any of our CSS and JS files
		if ($most_recent != Config::$modSettings['browser_cache']) {
			Config::updateModSettings(['browser_cache' => $most_recent]);
		}

		// If any of the files could not be deleted, log an error about it.
		if (!empty($not_deleted)) {
			Lang::load('Errors');
			ErrorHandler::log(sprintf(Lang::$txt['unlink_minimized_fail'], implode('<br>', $not_deleted)), 'general');
		}
	}

	/**
	 * Sets an option via JavaScript.
	 * - sets a theme option without outputting anything.
	 * - can be used with JavaScript, via a dummy image... (which doesn't require
	 * the page to reload.)
	 * - requires someone who is logged in.
	 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
	 * - does not log access to the Who's Online log. (in index.php..)
	 */
	public static function setJavaScript()
	{
		// Check the session id.
		User::$me->checkSession('get');

		if (!isset(self::$current)) {
			self::load();
		}

		// This good-for-nothing pixel is being used to keep the session alive.
		if (empty($_GET['var']) || !isset($_GET['val'])) {
			Utils::redirectexit(self::$current->settings['images_url'] . '/blank.png');
		}

		// Sorry, guests can't go any further than this.
		if (User::$me->is_guest || User::$me->id == 0) {
			Utils::obExit(false);
		}

		// Can't change reserved vars.
		if (in_array(strtolower($_GET['var']), self::$reservedVars)) {
			Utils::redirectexit(self::$current->settings['images_url'] . '/blank.png');
		}

		// Use a specific theme?
		if (isset($_GET['th']) || isset($_GET['id'])) {
			// Invalidate the current themes cache too.
			CacheApi::put('theme_settings-' . self::$current->settings['theme_id'] . ':' . User::$me->id, null, 60);

			self::$current->settings['theme_id'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];
		}

		// If this is the admin preferences the passed value will just be an element of it.
		if ($_GET['var'] == 'admin_preferences') {
			self::$current->options['admin_preferences'] = !empty(self::$current->options['admin_preferences']) ? Utils::jsonDecode(self::$current->options['admin_preferences'], true) : [];

			// New thingy...
			if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5) {
				self::$current->options['admin_preferences'][$_GET['admin_key']] = $_GET['val'];
			}

			// Change the value to be something nice,
			$_GET['val'] = Utils::jsonEncode(self::$current->options['admin_preferences']);
		}

		// Update the option.
		Db::$db->insert(
			'replace',
			'{db_prefix}themes',
			['id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
			[self::$current->settings['theme_id'], User::$me->id, $_GET['var'], is_array($_GET['val']) ? implode(',', $_GET['val']) : $_GET['val']],
			['id_theme', 'id_member', 'variable'],
		);

		CacheApi::put('theme_settings-' . self::$current->settings['theme_id'] . ':' . User::$me->id, null, 60);

		// Don't output anything...
		Utils::redirectexit(self::$current->settings['images_url'] . '/blank.png');
	}

	/**
	 * Possibly the simplest and best example of how to use the template system.
	 *
	 *  - Allows the theme to take care of actions.
	 *
	 *  - Called if Theme::$current->settings['catch_action'] is set and action
	 *    isn't found in the action array.
	 *
	 *  - Can use a template, layers, sub_template, filename, and/or function.
	 */
	public static function wrapAction()
	{
		// Load any necessary template(s)?
		if (isset(self::$current->settings['catch_action']['template'])) {
			// Load both the template and language file. (but don't fret if the language file isn't there...)
			self::loadTemplate(self::$current->settings['catch_action']['template']);
			Lang::load(self::$current->settings['catch_action']['template'], '', false);
		}

		// Any special layers?
		if (isset(self::$current->settings['catch_action']['layers'])) {
			Utils::$context['template_layers'] = self::$current->settings['catch_action']['layers'];
		}

		// Any function to call?
		if (isset(self::$current->settings['catch_action']['function'])) {
			$hook = self::$current->settings['catch_action']['function'];

			if (!isset(self::$current->settings['catch_action']['filename'])) {
				self::$current->settings['catch_action']['filename'] = '';
			}

			IntegrationHook::add('integrate_wrap_action', $hook, false, self::$current->settings['catch_action']['filename'], false);

			IntegrationHook::call('integrate_wrap_action');
		}

		// And finally, the main sub template ;).
		if (isset(self::$current->settings['catch_action']['sub_template'])) {
			Utils::$context['sub_template'] = self::$current->settings['catch_action']['sub_template'];
		}
	}

	/**
	 * Redirects ?action=theme to the correct location.
	 */
	public static function dispatch()
	{
		// If any sub-action besides 'pick' was requested, redirect to admin.
		if (!isset($_REQUEST['sa']) || $_REQUEST['sa'] !== 'pick') {
			Utils::redirectexit('action=admin;area=theme' . (isset($_REQUEST['sa']) ? ';sa=' . $_REQUEST['sa'] : '') . (isset($_REQUEST['u']) ? ';u=' . $_REQUEST['u'] : ''));
		}

		self::pickTheme();
	}

	/**
	 * Shows an interface to allow a member to choose a new theme.
	 *
	 * - uses the Themes template. (pick sub template.)
	 * - accessed with ?action=theme;sa=pick.
	 */
	public static function pickTheme()
	{
		User::$me->kickIfGuest();

		$_REQUEST['u'] = !isset($_REQUEST['u']) ? User::$me->id : (int) $_REQUEST['u'];

		// Only admins can change default values.
		if (in_array($_REQUEST['u'], [-1, 0])) {
			User::$me->isAllowedTo('admin_forum');
		}
		// Is the ability to change themes enabled overall?
		elseif (empty(Config::$modSettings['theme_allow'])) {
			Utils::redirectexit('action=profile;area=theme;u=' . $_REQUEST['u']);
		}
		// Does the current user have permission to change themes for the specified user?
		else {
			User::$me->isAllowedTo('profile_extra' . ($_REQUEST['u'] === User::$me->id ? '_own' : '_any'));
		}

		Lang::load('Profile');
		Lang::load('Themes');
		Lang::load('Settings');
		self::loadTemplate('Themes');

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=theme;sa=pick;u=' . $_REQUEST['u'],
			'name' => Lang::$txt['theme_pick'],
		];
		Utils::$context['default_theme_id'] = Config::$modSettings['theme_default'];
		$_SESSION['id_theme'] = 0;

		// Have we made a decision, or are we just browsing?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('pick-th');

			$id_theme = (int) key($_POST['save']);

			if (isset($_POST['vrt'][$id_theme])) {
				$variant = $_POST['vrt'][$id_theme];
			}

			// -1 means we are setting the forum's default theme.
			if ($_REQUEST['u'] === -1) {
				Config::updateModSettings(['theme_guests' => $id_theme]);
				Utils::redirectexit('action=admin;area=theme;sa=admin;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
			}
			// 0 means we are resetting everyone's theme.
			elseif ($_REQUEST['u'] === 0) {
				User::updateMemberData(null, ['id_theme' => $id_theme]);
				Utils::redirectexit('action=admin;area=theme;sa=admin;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
			}
			// Setting a particular user's theme.
			elseif (self::canPickTheme($_REQUEST['u'], $id_theme)) {
				// An identifier of zero means that the user wants the forum default theme.
				User::updateMemberData($_REQUEST['u'], ['id_theme' => $id_theme]);

				if (!empty($variant)) {
					// Set the identifier to the forum default.
					if (isset($id_theme) && $id_theme == 0) {
						$id_theme = Config::$modSettings['theme_guests'];
					}

					Db::$db->insert(
						'replace',
						'{db_prefix}themes',
						['id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
						[$id_theme, $_REQUEST['u'], 'theme_variant', $variant],
						['id_theme', 'id_member', 'variable'],
					);
					CacheApi::put('theme_settings-' . $id_theme . ':' . $_REQUEST['u'], null, 90);

					if (User::$me->id == $_REQUEST['u']) {
						$_SESSION['id_variant'] = 0;
					}
				}

				Utils::redirectexit('action=profile;area=theme;u=' . $_REQUEST['u']);
			}
		}

		// Figure out who the member of the minute is, and what theme they've chosen.
		Utils::$context['current_member'] = $_REQUEST['u'];

		if (Utils::$context['current_member'] === User::$me->id) {
			Utils::$context['current_theme'] = User::$me->theme;
		} else {
			$request = Db::$db->query(
				'',
				'SELECT id_theme
				FROM {db_prefix}members
				WHERE id_member = {int:current_member}
				LIMIT 1',
				[
					'current_member' => Utils::$context['current_member'],
				],
			);
			list(Utils::$context['current_theme']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// Get the theme name and descriptions.
		Utils::$context['available_themes'] = [];

		if (!empty(Config::$modSettings['knownThemes'])) {
			$request = Db::$db->query(
				'',
				'SELECT id_theme, variable, value
				FROM {db_prefix}themes
				WHERE variable IN ({literal:name}, {literal:theme_url}, {literal:theme_dir}, {literal:images_url}, {literal:disable_user_variant})' . (!User::$me->allowedTo('admin_forum') ? '
					AND id_theme IN ({array_int:known_themes})' : '') . '
					AND id_theme != {int:default_theme}
					AND id_member = {int:no_member}
					AND id_theme IN ({array_int:enable_themes})',
				[
					'default_theme' => 0,
					'no_member' => 0,
					'known_themes' => explode(',', Config::$modSettings['knownThemes']),
					'enable_themes' => explode(',', Config::$modSettings['enableThemes']),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!isset(Utils::$context['available_themes'][$row['id_theme']])) {
					Utils::$context['available_themes'][$row['id_theme']] = [
						'id' => $row['id_theme'],
						'selected' => Utils::$context['current_theme'] == $row['id_theme'],
						'num_users' => 0,
					];
				}
				Utils::$context['available_themes'][$row['id_theme']][$row['variable']] = $row['value'];
			}
			Db::$db->free_result($request);
		}

		// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
		if (!isset(Utils::$context['available_themes'][Config::$modSettings['theme_guests']])) {
			Utils::$context['available_themes'][0] = [
				'num_users' => 0,
			];
			$guest_theme = 0;
		} else {
			$guest_theme = Config::$modSettings['theme_guests'];
		}

		$request = Db::$db->query(
			'',
			'SELECT id_theme, COUNT(*) AS the_count
			FROM {db_prefix}members
			GROUP BY id_theme
			ORDER BY id_theme DESC',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Figure out which theme it is they are REALLY using.
			if (!empty(Config::$modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',', Config::$modSettings['knownThemes']))) {
				$row['id_theme'] = $guest_theme;
			} elseif (empty(Config::$modSettings['theme_allow'])) {
				$row['id_theme'] = $guest_theme;
			}

			if (isset(Utils::$context['available_themes'][$row['id_theme']])) {
				Utils::$context['available_themes'][$row['id_theme']]['num_users'] += $row['the_count'];
			} else {
				Utils::$context['available_themes'][$guest_theme]['num_users'] += $row['the_count'];
			}
		}
		Db::$db->free_result($request);

		// Get any member variant preferences.
		$variant_preferences = [];

		if (Utils::$context['current_member'] > 0) {
			$request = Db::$db->query(
				'',
				'SELECT id_theme, value
				FROM {db_prefix}themes
				WHERE variable = {string:theme_variant}
					AND id_member IN ({array_int:id_member})
				ORDER BY id_member ASC',
				[
					'theme_variant' => 'theme_variant',
					'id_member' => isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? [-1, Utils::$context['current_member']] : [-1],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$variant_preferences[$row['id_theme']] = $row['value'];
			}
			Db::$db->free_result($request);
		}

		// Save the setting first.
		$current_images_url = self::$current->settings['images_url'];
		$current_theme_variants = !empty(self::$current->settings['theme_variants']) ? self::$current->settings['theme_variants'] : [];

		$current_lang_dirs = Lang::$dirs;
		$current_thumbnail = Lang::$txt['theme_thumbnail_href'];
		$current_description = Lang::$txt['theme_description'];

		foreach (Utils::$context['available_themes'] as $id_theme => $theme_data) {
			// Don't try to load the forum or board default theme's data... it doesn't have any!
			if ($id_theme == 0) {
				continue;
			}

			// The thumbnail needs the correct path.
			self::$current->settings['images_url'] = &$theme_data['images_url'];

			Lang::addDirs([$theme_data['theme_dir'] . '/languages']);
			Lang::load('Settings', '', false, true);

			if (empty(Lang::$txt['theme_thumbnail_href'])) {
				Lang::$txt['theme_thumbnail_href'] = $theme_data['images_url'] . '/thumbnail.png';
			}

			if (empty(Lang::$txt['theme_description'])) {
				Lang::$txt['theme_description'] = '';
			}

			Utils::$context['available_themes'][$id_theme]['thumbnail_href'] = sprintf(Lang::$txt['theme_thumbnail_href'], self::$current->settings['images_url']);

			Utils::$context['available_themes'][$id_theme]['description'] = Lang::$txt['theme_description'];

			// Are there any variants?
			Utils::$context['available_themes'][$id_theme]['variants'] = [];

			if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || User::$me->allowedTo('admin_forum'))) {
				$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));

				if (preg_match('~((?:SMF\\\\)?Theme::\$current(?:->|_)|\$)settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches)) {
					self::$current->settings['theme_variants'] = [];

					// Fill settings up.
					eval(($matches[1] === '$' ? 'global $settings; ' : 'use SMF\\Theme; ') . $matches[0]);

					if (!empty(self::$current->settings['theme_variants'])) {
						foreach (self::$current->settings['theme_variants'] as $variant) {
							Utils::$context['available_themes'][$id_theme]['variants'][$variant] = [
								'label' => Lang::$txt['variant_' . $variant] ?? $variant,
								'thumbnail' => file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : (file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') ? $theme_data['images_url'] . '/thumbnail.png' : ''),
							];
						}

						Utils::$context['available_themes'][$id_theme]['selected_variant'] = $_GET['vrt'] ?? (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty(self::$current->settings['default_variant']) ? self::$current->settings['default_variant'] : self::$current->settings['theme_variants'][0]));

						if (!isset(Utils::$context['available_themes'][$id_theme]['variants'][Utils::$context['available_themes'][$id_theme]['selected_variant']]['thumbnail'])) {
							Utils::$context['available_themes'][$id_theme]['selected_variant'] = self::$current->settings['theme_variants'][0];
						}

						Utils::$context['available_themes'][$id_theme]['thumbnail_href'] = Utils::$context['available_themes'][$id_theme]['variants'][Utils::$context['available_themes'][$id_theme]['selected_variant']]['thumbnail'];

						// Allow themes to override the text.
						Utils::$context['available_themes'][$id_theme]['pick_label'] = Lang::$txt['variant_pick'] ?? Lang::$txt['theme_pick_variant'];
					}
				}
			}

			// Restore language stuff.
			Lang::$dirs = $current_lang_dirs;
			Lang::$txt['theme_thumbnail_href'] = $current_thumbnail;
			Lang::$txt['theme_description'] = $current_description;
		}

		self::addJavaScriptVar(
			'oThemeVariants',
			Utils::jsonEncode(array_map(
				function ($theme) {
					return $theme['variants'];
				},
				Utils::$context['available_themes'],
			)),
		);
		self::loadJavaScriptFile('profile.js', ['defer' => false, 'minimize' => true], 'smf_profile');
		self::$current->settings['images_url'] = $current_images_url;
		self::$current->settings['theme_variants'] = $current_theme_variants;

		// As long as we're not doing the default theme...
		if ($_REQUEST['u'] >= 0) {
			if ($guest_theme != 0) {
				Utils::$context['available_themes'][0] = Utils::$context['available_themes'][$guest_theme];
			}

			Utils::$context['available_themes'][0]['id'] = 0;
			Utils::$context['available_themes'][0]['name'] = Lang::$txt['theme_forum_default'];
			Utils::$context['available_themes'][0]['selected'] = Utils::$context['current_theme'] == 0;
			Utils::$context['available_themes'][0]['description'] = Lang::$txt['theme_global_description'];
		}

		ksort(Utils::$context['available_themes']);

		Utils::$context['page_title'] = Lang::$txt['theme_pick'];
		Utils::$context['sub_template'] = 'pick';
		SecurityToken::create('pick-th');
	}

	/**
	 * Creates an image/text button.
	 *
	 * @deprecated since 2.1
	 *
	 * @param string $name The name of the button. Should be a main_icons class
	 *    or the name of an image.
	 * @param string $alt The alt text.
	 * @param string $label The Lang::$txt string to use as the label.
	 * @param string $custom Custom text/html to add to the img tag. Only when
	 *    using an actual image.
	 * @param bool $force_use Whether to override template_create_button and
	 *    use this instead.
	 * @return string The HTML to display the button.
	 */
	public static function createButton($name, $alt, $label = '', $custom = '', $force_use = false)
	{
		// Does the current loaded theme have this and we are not forcing the usage of this function?
		if (function_exists('template_create_button') && !$force_use) {
			return template_create_button($name, $alt, $label = '', $custom = '');
		}

		if (!Theme::$current->settings['use_image_buttons']) {
			return Lang::$txt[$alt];
		}

		if (!empty(Theme::$current->settings['use_buttons'])) {
			return '<span class="main_icons ' . $name . '" alt="' . Lang::$txt[$alt] . '"></span>' . ($label != '' ? '&nbsp;<strong>' . Lang::$txt[$label] . '</strong>' : '');
		}

		return '<img src="' . Theme::$current->settings['lang_images_url'] . '/' . $name . '" alt="' . Lang::$txt[$alt] . '" ' . $custom . '>';
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected in order to force instantiation via Theme::load().
	 *
	 * @param int $id The ID of the theme to load.
	 * @param int $member The ID of the member whose theme preferences we want.
	 */
	protected function __construct($id = 0, $member = -1)
	{
		$this->id = $id;

		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2 && ($temp = CacheApi::get('theme_settings-' . $this->id . ':' . $member, 60)) != null && time() - 60 > Config::$modSettings['settings_updated']) {
			$themeData = $temp;
			$flag = true;
		} elseif (($temp = CacheApi::get('theme_settings-' . $this->id, 90)) != null && time() - 60 > Config::$modSettings['settings_updated']) {
			$themeData = $temp;

			// If $member is 0 or -1, we might already have everything we need.
			if (array_key_exists($member, $themeData)) {
				$flag = !empty($themeData[0]) && !empty($themeData[-1]);
			}
			// Nothing cached for this member.
			else {
				$themeData[$member] = [];
			}
		} else {
			$themeData = [-1 => [], 0 => []];

			if (!array_key_exists($member, $themeData)) {
				$themeData[$member] = [];
			}
		}

		if (empty($flag)) {
			// Load variables from the current or default theme, global or this user's.
			$result = Db::$db->query(
				'',
				'SELECT variable, value, id_member, id_theme
				FROM {db_prefix}themes
				WHERE id_member' . (empty($themeData[0]) ? ' IN ({array_int:members})' : ' = {int:id_member}') . '
					AND id_theme' . ($this->id == 1 ? ' = {int:id_theme}' : ' IN ({array_int:themes})') . '
				ORDER BY id_theme asc',
				[
					'id_theme' => $this->id,
					'id_member' => $member,
					'themes' => array_unique([1, $this->id]),
					'members' => array_unique([-1, 0, $member]),
				],
			);

			// Pick between $this->settings and $this->options depending on whose data it is.
			foreach (Db::$db->fetch_all($result) as $row) {
				// There are just things we shouldn't be able to change as members.
				if ($row['id_member'] != 0 && in_array($row['variable'], self::$reservedVars)) {
					continue;
				}

				// If this is the theme_dir of the default theme, store it.
				if (in_array($row['variable'], ['theme_dir', 'theme_url', 'images_url']) && $row['id_theme'] == '1' && empty($row['id_member'])) {
					$themeData[0]['default_' . $row['variable']] = $row['value'];
				}

				// If this isn't set yet, is a theme option, or is not the default theme..
				if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1') {
					$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
				}
			}
			Db::$db->free_result($result);

			if (!empty($themeData[-1])) {
				foreach ($themeData[-1] as $k => $v) {
					if (!isset($themeData[$member][$k])) {
						$themeData[$member][$k] = $v;
					}
				}
			}

			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2) {
				CacheApi::put('theme_settings-' . $this->id . ':' . $member, $themeData, 60);
			}
			// Only if we didn't already load that part of the cache...
			elseif (!isset($temp)) {
				CacheApi::put('theme_settings-' . $this->id, [-1 => $themeData[-1], 0 => $themeData[0]], 90);
			}
		}

		$this->settings = $themeData[0];
		$this->options = $themeData[$member];

		$this->settings['theme_id'] = $this->id;

		$this->settings['actual_theme_url'] = $this->settings['theme_url'];
		$this->settings['actual_images_url'] = $this->settings['images_url'];
		$this->settings['actual_theme_dir'] = $this->settings['theme_dir'];

		$this->settings['template_dirs'] = [];

		// This theme first.
		$this->settings['template_dirs'][] = $this->settings['theme_dir'];

		// Based on theme (if there is one).
		if (!empty($this->settings['base_theme_dir'])) {
			$this->settings['template_dirs'][] = $this->settings['base_theme_dir'];
		}

		// Lastly the default theme.
		if ($this->settings['theme_dir'] != $this->settings['default_theme_dir']) {
			$this->settings['template_dirs'][] = $this->settings['default_theme_dir'];
		}
	}

	/**
	 * Sets a bunch of Utils::$context variables, loads templates and language
	 * files, and does other stuff that is required to use the theme for output.
	 */
	protected function initialize()
	{
		$this->requireAgreement();
		$this->sslRedirect();
		$this->fixUrl();

		// Create User::$me if it is missing (e.g., an error very early in the login process).
		if (!isset(User::$me)) {
			User::load();
		}

		$this->fixSmileySet();

		// Some basic information...
		if (!isset(Utils::$context['html_headers'])) {
			Utils::$context['html_headers'] = '';
		}

		if (!isset(Utils::$context['javascript_files'])) {
			Utils::$context['javascript_files'] = [];
		}

		if (!isset(Utils::$context['css_files'])) {
			Utils::$context['css_files'] = [];
		}

		if (!isset(Utils::$context['css_header'])) {
			Utils::$context['css_header'] = [];
		}

		if (!isset(Utils::$context['javascript_inline'])) {
			Utils::$context['javascript_inline'] = ['standard' => [], 'defer' => []];
		}

		if (!isset(Utils::$context['javascript_vars'])) {
			Utils::$context['javascript_vars'] = [];
		}

		Utils::$context['login_url'] = Config::$scripturl . '?action=login2';
		Utils::$context['menu_separator'] = !empty($this->settings['use_image_buttons']) ? ' ' : ' | ';
		Utils::$context['session_var'] = $_SESSION['session_var'];
		Utils::$context['session_id'] = $_SESSION['session_value'];
		Utils::$context['forum_name'] = Config::$mbname;
		Utils::$context['forum_name_html_safe'] = Utils::htmlspecialchars(Utils::$context['forum_name']);
		Utils::$context['header_logo_url_html_safe'] = empty($this->settings['header_logo_url']) ? '' : Utils::htmlspecialchars($this->settings['header_logo_url']);
		Utils::$context['current_action'] = isset($_REQUEST['action']) ? Utils::htmlspecialchars($_REQUEST['action']) : null;
		Utils::$context['current_subaction'] = $_REQUEST['sa'] ?? null;
		Utils::$context['can_register'] = empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] != 3;

		if (isset(Config::$modSettings['load_average'])) {
			Utils::$context['load_average'] = Config::$modSettings['load_average'];
		}

		// Detect the browser. This is separated out because it's also used in attachment downloads
		BrowserDetector::call();

		$this->loadTemplatesAndLangFiles();

		// Allow overriding the forum's default time/number formats.
		if (empty(User::$profiles[User::$me->id]['time_format']) && !empty(Lang::$txt['time_format'])) {
			User::$me->time_format = Lang::$txt['time_format'];
		}

		// Set the character set from the template.
		Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? Lang::$txt['lang_character_set'] : Config::$modSettings['global_character_set'];
		Utils::$context['right_to_left'] = !empty(Lang::$txt['lang_rtl']);

		// Guests may still need a name.
		if (User::$me->is_guest && empty(User::$me->name)) {
			User::$me->name = Lang::$txt['guest_title'];
		}

		// Any theme-related strings that need to be loaded?
		if (!empty($this->settings['require_theme_strings'])) {
			Lang::load('ThemeStrings', '', false);
		}

		// Make a special URL for the language.
		$this->settings['lang_images_url'] = $this->settings['images_url'] . '/' . (!empty(Lang::$txt['image_lang']) ? Lang::$txt['image_lang'] : User::$me->language);

		$this->loadCss();

		$this->loadVariant();

		Utils::$context['tabindex'] = 1;

		$this->loadJavaScript();

		$this->setupLinktree();

		// Any files to include at this point?
		if (!empty(Config::$modSettings['integrate_theme_include'])) {
			$theme_includes = explode(',', Config::$modSettings['integrate_theme_include']);

			foreach ($theme_includes as $include) {
				$include = strtr(trim($include), ['$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => $this->settings['theme_dir']]);

				if (file_exists($include)) {
					require_once $include;
				}
			}
		}

		// Call load theme integration functions.
		IntegrationHook::call('integrate_load_theme');

		// We are ready to go.
		Utils::$context['theme_loaded'] = true;
	}

	/**
	 * If necessary, redirect to the agreement or privacy policy so that we can
	 * force the user to accept the current version.
	 */
	protected function requireAgreement()
	{
		// Perhaps we've changed the agreement or privacy policy? Only redirect if:
		// 1. They're not a guest or admin
		// 2. This isn't called from SSI
		// 3. This isn't an XML request
		// 4. They're not trying to do any of the following actions:
		// 4a. View or accept the agreement and/or policy
		// 4b. Login or logout
		// 4c. Get a feed (RSS, ATOM, etc.)
		if (!empty(User::$me->id) && empty(User::$me->is_admin) && SMF != 'SSI' && !isset($_REQUEST['xml']) && !QueryString::isFilteredRequest($this->agreement_actions, 'action')) {
			$can_accept_agreement = !empty(Config::$modSettings['requireAgreement']) && Agreement::canRequireAgreement();

			$can_accept_privacy_policy = !empty(Config::$modSettings['requirePolicyAgreement']) && Agreement::canRequirePrivacyPolicy();

			if ($can_accept_agreement || $can_accept_privacy_policy) {
				Utils::redirectexit('action=agreement');
			}
		}
	}

	/**
	 * Check to see if we're forcing SSL, and redirect if necessary.
	 */
	protected function sslRedirect()
	{
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance)
			&& !Config::httpsOn() && SMF != 'SSI') {
			if (isset($_GET['sslRedirect'])) {
				Lang::load('Errors');
				ErrorHandler::fatalLang('login_ssl_required', false);
			}

			Utils::redirectexit(strtr($_SERVER['REQUEST_URL'], ['http://' => 'https://']) . (strpos($_SERVER['REQUEST_URL'], '?') > 0 ? ';' : '?') . 'sslRedirect');
		}
	}

	/**
	 * If the user got here using an unexpected URL, fix it.
	 */
	protected function fixUrl()
	{
		// Check to see if they're accessing it from the wrong place.
		if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME'])) {
			$detected_url = Config::httpsOn() ? 'https://' : 'http://';

			$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

			$temp = preg_replace('~/' . basename(Config::$scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));

			if ($temp != '/') {
				$detected_url .= $temp;
			}
		}

		if (isset($detected_url) && $detected_url != Config::$boardurl) {
			// Try #1 - check if it's in a list of alias addresses.
			if (!empty(Config::$modSettings['forum_alias_urls'])) {
				$aliases = explode(',', Config::$modSettings['forum_alias_urls']);

				foreach ($aliases as $alias) {
					// Rip off all the boring parts, spaces, etc.
					if ($detected_url == trim($alias) || strtr($detected_url, ['http://' => '', 'https://' => '']) == trim($alias)) {
						$do_fix = true;
					}
				}
			}

			// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
			if (empty($do_fix) && strtr($detected_url, ['://' => '://www.']) == Config::$boardurl && (empty($_GET) || count($_GET) == 1) && SMF != 'SSI') {
				// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
				if (empty($_GET)) {
					Utils::redirectexit('wwwRedirect');
				} else {
					$k = key($_GET);
					$v = current($_GET);

					if ($k != 'wwwRedirect') {
						Utils::redirectexit('wwwRedirect;' . $k . '=' . $v);
					}
				}
			}

			// #3 is just a check for SSL...
			if (strtr($detected_url, ['https://' => 'http://']) == Config::$boardurl) {
				$do_fix = true;
			}

			// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
			if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1) {
				// Caching is good ;).
				$oldurl = Config::$boardurl;

				// Fix Config::$boardurl and Config::$scripturl.
				Config::$boardurl = $detected_url;
				Config::$scripturl = strtr(Config::$scripturl, [$oldurl => Config::$boardurl]);
				$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], [$oldurl => Config::$boardurl]);

				// Fix the theme urls...
				$this->settings['theme_url'] = strtr($this->settings['theme_url'], [$oldurl => Config::$boardurl]);
				$this->settings['default_theme_url'] = strtr($this->settings['default_theme_url'], [$oldurl => Config::$boardurl]);
				$this->settings['actual_theme_url'] = strtr($this->settings['actual_theme_url'], [$oldurl => Config::$boardurl]);
				$this->settings['images_url'] = strtr($this->settings['images_url'], [$oldurl => Config::$boardurl]);
				$this->settings['default_images_url'] = strtr($this->settings['default_images_url'], [$oldurl => Config::$boardurl]);
				$this->settings['actual_images_url'] = strtr($this->settings['actual_images_url'], [$oldurl => Config::$boardurl]);

				// And just a few mod settings :).
				Config::$modSettings['smileys_url'] = strtr(Config::$modSettings['smileys_url'], [$oldurl => Config::$boardurl]);
				Config::$modSettings['avatar_url'] = strtr(Config::$modSettings['avatar_url'], [$oldurl => Config::$boardurl]);
				Config::$modSettings['custom_avatar_url'] = strtr(Config::$modSettings['custom_avatar_url'], [$oldurl => Config::$boardurl]);

				// Clean up after Board::load().
				if (isset(Board::$info->moderators)) {
					foreach (Board::$info->moderators as $k => $dummy) {
						Board::$info->moderators[$k]['href'] = strtr($dummy['href'], [$oldurl => Config::$boardurl]);
						Board::$info->moderators[$k]['link'] = strtr($dummy['link'], ['"' . $oldurl => '"' . Config::$boardurl]);
					}
				}

				foreach (Utils::$context['linktree'] as $k => $dummy) {
					Utils::$context['linktree'][$k]['url'] = strtr($dummy['url'], [$oldurl => Config::$boardurl]);
				}
			}
		}
	}

	/**
	 * Determine the current smiley set.
	 */
	protected function fixSmileySet()
	{
		$smiley_sets_known = explode(',', Config::$modSettings['smiley_sets_known']);

		if (empty(Config::$modSettings['smiley_sets_enable']) || (User::$me->smiley_set != 'none' && !in_array(User::$me->smiley_set, $smiley_sets_known))) {
			User::$me->smiley_set = !empty($this->settings['smiley_sets_default']) ? $this->settings['smiley_sets_default'] : Config::$modSettings['smiley_sets_default'];
		}
	}

	/**
	 * Figure out which template layers and language files should be loaded.
	 */
	protected function loadTemplatesAndLangFiles()
	{
		// This allows sticking some HTML on the page output - useful for controls.
		Utils::$context['insert_after_template'] = '';

		IntegrationHook::call('integrate_simple_actions', [&$this->simpleActions, &$this->simpleAreas, &$this->simpleSubActions, &$this->extraParams, &$this->xmlActions]);

		Utils::$context['simple_action'] = (
			in_array(Utils::$context['current_action'], $this->simpleActions)
			|| (
				isset($this->simpleAreas[Utils::$context['current_action']], $_REQUEST['area'])

				&& in_array($_REQUEST['area'], $this->simpleAreas[Utils::$context['current_action']])
			)
			|| (
				isset($this->simpleSubActions[Utils::$context['current_action']])
				&& in_array(Utils::$context['current_subaction'], $this->simpleSubActions[Utils::$context['current_action']])
			)
		);

		// See if there is any extra param to check.
		$requiresXML = false;

		foreach ($this->extraParams as $key => $extra) {
			if (isset($_REQUEST[$extra])) {
				$requiresXML = true;
			}
		}

		// Output is fully XML, so no need for the index template.
		if (isset($_REQUEST['xml']) && (in_array(Utils::$context['current_action'], $this->xmlActions) || $requiresXML)) {
			Lang::load('index+Modifications');
			self::loadTemplate('Xml');
			Utils::$context['template_layers'] = [];
		}

		// These actions don't require the index template at all.
		elseif (!empty(Utils::$context['simple_action'])) {
			Lang::load('index+Modifications');
			Utils::$context['template_layers'] = [];
		} else {
			// Custom templates to load, or just default?
			if (isset($this->settings['theme_templates'])) {
				$templates = explode(',', $this->settings['theme_templates']);
			} else {
				$templates = ['index'];
			}

			// Load each template...
			foreach ($templates as $template) {
				self::loadTemplate($template);
			}

			// ...and attempt to load their associated language files.
			$required_files = implode('+', array_merge($templates, ['Modifications']));
			Lang::load($required_files, '', false);

			// Custom template layers?
			if (isset($this->settings['theme_layers'])) {
				Utils::$context['template_layers'] = explode(',', $this->settings['theme_layers']);
			} else {
				Utils::$context['template_layers'] = ['html', 'body'];
			}
		}

		// Initialize the theme.
		self::loadSubTemplate('init', 'ignore');
	}

	/**
	 * Loads the main CSS files for this theme.
	 */
	protected function loadCss()
	{
		// And of course, let's load the default CSS file.
		self::loadCSSFile('index.css', ['minimize' => true, 'order_pos' => 1], 'smf_index');

		// Here is my luvly Responsive CSS
		self::loadCSSFile('responsive.css', ['force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000], 'smf_responsive');

		if (Utils::$context['right_to_left']) {
			self::loadCSSFile('rtl.css', ['order_pos' => 4000], 'smf_rtl');
		}
	}

	/**
	 * Loads the correct theme variant, if applicable.
	 */
	protected function loadVariant()
	{
		// We allow theme variants, because we're cool.
		Utils::$context['theme_variant'] = '';
		Utils::$context['theme_variant_url'] = '';

		if (!empty($this->settings['theme_variants'])) {
			// Overriding - for previews and that ilk.
			if (!empty($_REQUEST['variant'])) {
				$_SESSION['id_variant'] = $_REQUEST['variant'];
			}

			// User selection?
			if (empty($this->settings['disable_user_variant']) || User::$me->allowedTo('admin_forum')) {
				Utils::$context['theme_variant'] = !empty($_SESSION['id_variant']) && in_array($_SESSION['id_variant'], $this->settings['theme_variants']) ? $_SESSION['id_variant'] : (!empty($this->options['theme_variant']) && in_array($this->options['theme_variant'], $this->settings['theme_variants']) ? $this->options['theme_variant'] : '');
			}

			// If not a user variant, select the default.
			if (Utils::$context['theme_variant'] == '' || !in_array(Utils::$context['theme_variant'], $this->settings['theme_variants'])) {
				Utils::$context['theme_variant'] = !empty($this->settings['default_variant']) && in_array($this->settings['default_variant'], $this->settings['theme_variants']) ? $this->settings['default_variant'] : $this->settings['theme_variants'][0];
			}

			// Do this to keep things easier in the templates.
			Utils::$context['theme_variant'] = '_' . Utils::$context['theme_variant'];
			Utils::$context['theme_variant_url'] = Utils::$context['theme_variant'] . '/';

			if (!empty(Utils::$context['theme_variant'])) {
				self::loadCSSFile('index' . Utils::$context['theme_variant'] . '.css', ['order_pos' => 300], 'smf_index' . Utils::$context['theme_variant']);

				if (Utils::$context['right_to_left']) {
					self::loadCSSFile('rtl' . Utils::$context['theme_variant'] . '.css', ['order_pos' => 4200], 'smf_rtl' . Utils::$context['theme_variant']);
				}
			}
		}
	}

	/**
	 * Loads the boilerplate JavaScript variables and files for this theme.
	 */
	protected function loadJavaScript()
	{
		// Default JS variables for use in every theme
		Utils::$context['javascript_vars'] = [
			'smf_theme_url' => '"' . $this->settings['theme_url'] . '"',
			'smf_default_theme_url' => '"' . $this->settings['default_theme_url'] . '"',
			'smf_images_url' => '"' . $this->settings['images_url'] . '"',
			'smf_smileys_url' => '"' . Config::$modSettings['smileys_url'] . '"',
			'smf_smiley_sets' => '"' . Config::$modSettings['smiley_sets_known'] . '"',
			'smf_smiley_sets_default' => '"' . Config::$modSettings['smiley_sets_default'] . '"',
			'smf_avatars_url' => '"' . Config::$modSettings['avatar_url'] . '"',
			'smf_scripturl' => '"' . Config::$scripturl . '"',
			'smf_iso_case_folding' => Utils::$context['server']['iso_case_folding'] ? 'true' : 'false',
			'smf_charset' => '"' . Utils::$context['character_set'] . '"',
			'smf_session_id' => '"' . Utils::$context['session_id'] . '"',
			'smf_session_var' => '"' . Utils::$context['session_var'] . '"',
			'smf_member_id' => User::$me->id,
			'ajax_notification_text' => Utils::JavaScriptEscape(Lang::$txt['ajax_in_progress']),
			'help_popup_heading_text' => Utils::JavaScriptEscape(Lang::$txt['help_popup']),
			'banned_text' => Utils::JavaScriptEscape(sprintf(Lang::$txt['your_ban'], User::$me->name)),
			'smf_txt_expand' => Utils::JavaScriptEscape(Lang::$txt['code_expand']),
			'smf_txt_shrink' => Utils::JavaScriptEscape(Lang::$txt['code_shrink']),
			'smf_collapseAlt' => Utils::JavaScriptEscape(Lang::$txt['hide']),
			'smf_expandAlt' => Utils::JavaScriptEscape(Lang::$txt['show']),
			'smf_quote_expand' => !empty(Config::$modSettings['quote_expand']) ? Config::$modSettings['quote_expand'] : 'false',
			'allow_xhjr_credentials' => !empty(Config::$modSettings['allow_cors_credentials']) ? 'true' : 'false',
		];

		// Add the JQuery library to the list of files to load.
		$jQueryUrls =  [
			'cdn' => 'https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js',
			'jquery_cdn' => 'https://code.jquery.com/jquery-' . JQUERY_VERSION . '.min.js',
			'microsoft_cdn' => 'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-' . JQUERY_VERSION . '.min.js',
		];

		if (isset(Config::$modSettings['jquery_source']) && array_key_exists(Config::$modSettings['jquery_source'], $jQueryUrls)) {
			self::loadJavaScriptFile($jQueryUrls[Config::$modSettings['jquery_source']], ['external' => true, 'seed' => false], 'smf_jquery');
		} elseif (isset(Config::$modSettings['jquery_source']) && Config::$modSettings['jquery_source'] == 'local') {
			self::loadJavaScriptFile('jquery-' . JQUERY_VERSION . '.min.js', ['seed' => false], 'smf_jquery');
		} elseif (isset(Config::$modSettings['jquery_source'], Config::$modSettings['jquery_custom']) && Config::$modSettings['jquery_source'] == 'custom') {
			self::loadJavaScriptFile(Config::$modSettings['jquery_custom'], ['external' => true, 'seed' => false], 'smf_jquery');
		}
		// Fall back to the forum default
		else {
			self::loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js', ['external' => true, 'seed' => false], 'smf_jquery');
		}

		// Queue our JQuery plugins!
		self::loadJavaScriptFile('smf_jquery_plugins.js', ['minimize' => true], 'smf_jquery_plugins');

		if (!User::$me->is_guest) {
			self::loadJavaScriptFile('jquery.custom-scrollbar.js', ['minimize' => true], 'smf_jquery_scrollbar');
			self::loadCSSFile('jquery.custom-scrollbar.css', ['force_current' => false, 'validate' => true], 'smf_scrollbar');
		}

		// script.js and theme.js, always required, so always add them! Makes index.template.php cleaner and all.
		self::loadJavaScriptFile('script.js', ['defer' => false, 'minimize' => true], 'smf_script');
		self::loadJavaScriptFile('theme.js', ['minimize' => true], 'smf_theme');

		// And we should probably trigger the cron too.
		if (empty(Config::$modSettings['cron_is_real_cron'])) {
			$ts = time();
			$ts -= $ts % 15;
			$escaped_boardurl = Utils::JavaScriptEscape(Config::$boardurl);
			self::addInlineJavaScript(<<<END
					function triggerCron()
					{
						$.get({$escaped_boardurl} + "/cron.php?ts={$ts}");
					}
					window.setTimeout(triggerCron, 1);
				END, true);

			// Robots won't normally trigger cron.php, so for them run the tasks directly.
			if (BrowserDetector::isBrowser('possibly_robot')) {
				(new TaskRunner())->runOneTask();
			}
		}
	}

	/**
	 * Sets up the top level linktree.
	 */
	protected function setupLinktree()
	{
		// Note that if we're dealing with certain very early errors (e.g., login) the linktree might not be set yet...
		if (empty(Utils::$context['linktree'])) {
			Utils::$context['linktree'] = [];
		}

		array_unshift(Utils::$context['linktree'], [
			'url' => Config::$scripturl,
			'name' => Utils::$context['forum_name_html_safe'],
		]);

		// Filter out the restricted boards from the linktree.
		if (!User::$me->is_admin && !empty(Board::$info->id)) {
			foreach (Utils::$context['linktree'] as $k => $element) {
				if (
					!empty($element['groups'])
					&& (
						count(array_intersect(User::$me->groups, $element['groups'])) == 0
						|| (
							!empty(Config::$modSettings['deny_boards_access'])
							&& count(array_intersect(User::$me->groups, $element['deny_groups'])) != 0
						)
					)
				) {
					Utils::$context['linktree'][$k]['name'] = Lang::$txt['restricted_board'];
					Utils::$context['linktree'][$k]['extra_before'] = '<i>';
					Utils::$context['linktree'][$k]['extra_after'] = '</i>';
					unset(Utils::$context['linktree'][$k]['url']);
				}
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Determines if a user can change their theme to the one specified.
	 *
	 * @param int $id_member
	 * @param int $id_theme
	 * @return bool
	 */
	protected static function canPickTheme($id_member, $id_theme)
	{
		return
			// The selected theme is enabled.
			(
				in_array($id_theme, explode(',', Config::$modSettings['enableThemes']))
				|| $id_theme == 0
			)
			// And...
			&& (
				// Current user is an admin.
				User::$me->allowedTo('admin_forum')
				// Or...
				|| (
					// The option to choose themes is enabled.
					!empty(Config::$modSettings['theme_allow'])
					// And current user is allowed to change profile extras of the specified user.
					&& User::$me->allowedTo(User::$me->id == $id_member ? 'profile_extra_own' : 'profile_extra_any')
					// And the selected theme is known. (0 means forum default.)
					&& in_array(
						$id_theme,
						array_merge(
							[0],
							explode(',', Config::$modSettings['knownThemes']),
						),
					)
				)
			);
	}

	/**
	 * Load the template/language file using require
	 * 	- loads the template or language file specified by filename.
	 * 	- uses eval unless disableTemplateEval is enabled.
	 * 	- outputs a parse error if the file did not exist or contained errors.
	 * 	- attempts to detect the error and line, and show detailed information.
	 *
	 * @param string $filename The name of the file to include
	 * @param bool $once If true only includes the file once (like include_once)
	 */
	protected static function templateInclude($filename, $once = false)
	{
		static $templates = [];

		// We want to be able to figure out any errors...
		@ini_set('track_errors', '1');

		// Don't include the file more than once, if $once is true.
		if ($once && in_array($filename, $templates)) {
			return;
		}

		// Add this file to the include list, whether $once is true or not.
		$templates[] = $filename;

		$file_found = file_exists($filename);

		if ($once && $file_found) {
			require_once $filename;
		} elseif ($file_found) {
			require $filename;
		}

		if ($file_found !== true) {
			ob_end_clean();

			if (!empty(Config::$modSettings['enableCompressedOutput'])) {
				@ob_start('ob_gzhandler');
			} else {
				ob_start();
			}

			if (isset($_GET['debug'])) {
				header('content-type: application/xhtml+xml; charset=' . (empty(Utils::$context['character_set']) ? 'ISO-8859-1' : Utils::$context['character_set']));
			}

			// Don't cache error pages!!
			header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			header('cache-control: no-cache');

			if (!isset(Lang::$txt['template_parse_error'])) {
				Lang::$txt['template_parse_error'] = 'Template Parse Error!';
				Lang::$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
				Lang::$txt['template_parse_error_details'] = 'There was a problem loading the <pre><strong>%1$s</strong></pre> template or language file.  Please check the syntax and try again - remember, single quotes (<pre>\'</pre>) often have to be escaped with a slash (<pre>\\</pre>).  To see more specific error information from PHP, try <a href="%2$s%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s?theme=1">use the default theme</a>.';
				Lang::$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';
			}

			// First, let's get the doctype and language information out of the way.
			echo '<!DOCTYPE html>' . "\n" . '<html', !empty(Utils::$context['right_to_left']) ? ' dir="rtl"' : '', '>' . "\n\t" . '<head>';

			if (isset(Utils::$context['character_set'])) {
				echo "\n\t\t" . '<meta charset="', Utils::$context['character_set'], '">';
			}

			if (!empty(Config::$maintenance) && !User::$me->allowedTo('admin_forum')) {
				echo "\n\t\t" . '<title>', Config::$mtitle, '</title>' . "\n\t" . '</head>' . "\n\t" . '<body>' . "\n\t\t" . '<h3>', Config::$mtitle, '</h3>' . "\n\t\t", Config::$mmessage, "\n\t" . '</body>' . "\n" . '</html>';
			} elseif (!User::$me->allowedTo('admin_forum')) {
				echo "\n\t" . '<title>', Lang::$txt['template_parse_error'], '</title>' . "\n\t" . '</head>' . "\n\t" . '<body>' . "\n\t\t" . '<h3>', Lang::$txt['template_parse_error'], '</h3>' . "\n\t\t", Lang::$txt['template_parse_error_message'], "\n\t" . '</body>' . "\n" . '</html>';
			} else {
				$error = WebFetchApi::fetch(Config::$boardurl . strtr($filename, [Config::$boarddir => '', strtr(Config::$boarddir, '\\', '/') => '']));

				$error_array = error_get_last();

				if (empty($error) && ini_get('track_errors') && !empty($error_array)) {
					$error = $error_array['message'];
				}

				if (empty($error)) {
					$error = Lang::$txt['template_parse_errmsg'];
				}

				$error = strtr($error, ['<b>' => '<strong>', '</b>' => '</strong>']);

				echo "\n\t\t" . '<title>', Lang::$txt['template_parse_error'], '</title>' . "\n\t" . '</head>';

				echo "\n\t" . '<body>' . "\n\t" . '<h3>', Lang::$txt['template_parse_error'], '</h3>' . "\n\t";

				echo sprintf(Lang::$txt['template_parse_error_details'], strtr($filename, [Config::$boarddir => '', strtr(Config::$boarddir, '\\', '/') => '']), Config::$boardurl, Config::$scripturl);

				if (!empty($error)) {
					echo "\n\t\t" . '<hr>' . "\n\t\t" . '<div style="margin: 0 20px;"><pre>', strtr(strtr($error, ['<strong>' . Config::$boarddir => '<strong>...', '<strong>' . strtr(Config::$boarddir, '\\', '/') => '<strong>...']), '\\', '/'), '</pre></div>';
				}

				// I know, I know... this is VERY COMPLICATED.  Still, it's good.
				if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0) {
					$data = file($filename);
					$data2 = BBCodeParser::highlightPhpCode(implode('', $data));
					$data2 = preg_split('~\\<br( /)?\\>~', $data2);

					// Fix the PHP code stuff...
					if (!BrowserDetector::isBrowser('gecko')) {
						$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
					} else {
						$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);
					}

					// Now we get to work around a bug in PHP where it doesn't escape <br>s!
					$j = -1;

					foreach ($data as $line) {
						$j++;

						if (substr_count($line, '<br>') == 0) {
							continue;
						}

						$n = substr_count($line, '<br>');

						for ($i = 0; $i < $n; $i++) {
							$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
							unset($data2[$j + $i + 1]);
						}
						$j += $n;
					}
					$data2 = array_values($data2);
					array_unshift($data2, '');

					echo "\n\t\t" . '<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

					// Figure out what the color coding was before...
					$line = max($match[1] - 9, 1);
					$last_line = '';

					for ($line2 = $line - 1; $line2 > 1; $line2--) {
						if (strpos($data2[$line2], '<') !== false) {
							if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0) {
								$last_line = $color_match[1];
							}
							break;
						}
					}

					// Show the relevant lines...
					for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++) {
						if ($line == $match[1]) {
							echo '</pre><div style="background-color: #ffb0b5;"><pre style="margin: 0;">';
						}

						echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';

						if (isset($data2[$line]) && $data2[$line] != '') {
							echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];
						}

						if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0) {
							$last_line = $color_match[1];
							echo '</', substr($last_line, 1, 4), '>';
						} elseif ($last_line != '' && strpos($data2[$line], '<') !== false) {
							$last_line = '';
						} elseif ($last_line != '' && $data2[$line] != '') {
							echo '</', substr($last_line, 1, 4), '>';
						}

						if ($line == $match[1]) {
							echo '</pre></div><pre style="margin: 0;">';
						} else {
							echo "\n";
						}
					}

					echo '</pre></div>';
				}

				echo "\n\t" . '</body>' . "\n" . '</html>';
			}

			die;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Theme::exportStatic')) {
	Theme::exportStatic();
}

?>