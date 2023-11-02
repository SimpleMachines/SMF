<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Admin;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Menu;
use SMF\Utils;

/**
 * Provides the search functionality inside the admin control panel.
 */
class Find implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'AdminSearch',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'internal';

	/**
	 * @var array
	 *
	 * This is a special array of functions that contain setting data.
	 * We query all these simply to find all the settings.
	 *
	 * MOD AUTHORS: If you want to be "real freaking good" then add any settings
	 * pages for your mod to this array via the integrate_admin_search hook!
	 *
	 * Format: array('function_to_get_config_vars', 'url_for_admin_area_and_sa')
	 */
	public array $settings_search = array(
		array(__NAMESPACE__ . '\\Features::basicConfigVars', 'area=featuresettings;sa=basic'),
		array(__NAMESPACE__ . '\\Features::bbcConfigVars', 'area=featuresettings;sa=bbc'),
		array(__NAMESPACE__ . '\\Features::layoutConfigVars', 'area=featuresettings;sa=layout'),
		array(__NAMESPACE__ . '\\Features::likesConfigVars', 'area=featuresettings;sa=likes'),
		array(__NAMESPACE__ . '\\Features::mentionsConfigVars', 'area=featuresettings;sa=mentions'),
		array(__NAMESPACE__ . '\\Features::sigConfigVars', 'area=featuresettings;sa=sig'),
		array(__NAMESPACE__ . '\\AntiSpam::getConfigVars', 'area=antispam'),
		array(__NAMESPACE__ . '\\Warnings::getConfigVars', 'area=warnings'),
		array(__NAMESPACE__ . '\\Mods::getConfigVars', 'area=modsettings;sa=general'),
		array(__NAMESPACE__ . '\\Attachments::attachConfigVars', 'area=manageattachments;sa=attachments'),
		array(__NAMESPACE__ . '\\Attachments::avatarConfigVars', 'area=manageattachments;sa=avatars'),
		array(__NAMESPACE__ . '\\Calendar::getConfigVars', 'area=managecalendar;sa=settings'),
		array(__NAMESPACE__ . '\\Boards::getConfigVars', 'area=manageboards;sa=settings'),
		array(__NAMESPACE__ . '\\Mail::getConfigVars', 'area=mailqueue;sa=settings'),
		array(__NAMESPACE__ . '\\News::getConfigVars', 'area=news;sa=settings'),
		array(__NAMESPACE__ . '\\Membergroups::getConfigVars', 'area=membergroups;sa=settings'),
		array(__NAMESPACE__ . '\\Permissions::getConfigVars', 'area=permissions;sa=settings'),
		array(__NAMESPACE__ . '\\Posts::postConfigVars', 'area=postsettings;sa=posts'),
		array(__NAMESPACE__ . '\\Posts::topicConfigVars', 'area=postsettings;sa=topics'),
		array(__NAMESPACE__ . '\\Posts::draftConfigVars', 'area=postsettings;sa=drafts'),
		array(__NAMESPACE__ . '\\Search::getConfigVars', 'area=managesearch;sa=settings'),
		array(__NAMESPACE__ . '\\Smileys::getConfigVars', 'area=smileys;sa=settings'),
		array(__NAMESPACE__ . '\\Server::generalConfigVars', 'area=serversettings;sa=general'),
		array(__NAMESPACE__ . '\\Server::databaseConfigVars', 'area=serversettings;sa=database'),
		array(__NAMESPACE__ . '\\Server::cookieConfigVars', 'area=serversettings;sa=cookie'),
		array(__NAMESPACE__ . '\\Server::securityConfigVars', 'area=serversettings;sa=security'),
		array(__NAMESPACE__ . '\\Server::cacheConfigVars', 'area=serversettings;sa=cache'),
		array(__NAMESPACE__ . '\\Server::exportConfigVars', 'area=serversettings;sa=export'),
		array(__NAMESPACE__ . '\\Server::loadBalancingConfigVars', 'area=serversettings;sa=loads'),
		array(__NAMESPACE__ . '\\Languages::getConfigVars', 'area=languages;sa=settings'),
		array(__NAMESPACE__ . '\\Registration::getConfigVars', 'area=regcenter;sa=settings'),
		array(__NAMESPACE__ . '\\SearchEngines::getConfigVars', 'area=sengines;sa=settings'),
		array(__NAMESPACE__ . '\\Subscriptions::getConfigVars', 'area=paidsubscribe;sa=settings'),
		array(__NAMESPACE__ . '\\Tasks::getConfigVars', 'area=scheduledtasks;sa=settings'),
		array(__NAMESPACE__ . '\\Logs::getConfigVars', 'area=logs;sa=settings'),
	);

	/**
	 * @var array
	 *
	 * Load a lot of language files.
	 *
	 * MOD AUTHORS: If your mod uses it's own language file for its settings,
	 * add the language file to this array via the integrate_admin_search hook.
	 */
	public array $language_files = array(
		'Drafts',
		'Help',
		'Login',
		'ManageBoards',
		'ManageCalendar',
		'ManageMail',
		'ManagePaid',
		'ManagePermissions',
		'ManageSettings',
		'ManageSmileys',
		'Search',
	);

	/**
	 * @var array
	 *
	 * Any extra files we ought to include.
	 *
	 * MOD AUTHORS: If your mod uses autoloading classes, you don't need to
	 * worry about this array. Otherwise, you can add your file to this array
	 * via the integrate_admin_search hook.
	 */
	public array $include_files = array();

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = array(
		'internal' => 'internal',
		'online' => 'online',
		'member' => 'member',
	);

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
	 * This function allocates out all the search stuff.
	 */
	public function execute(): void
	{
		isAllowedTo('admin_forum');

		Utils::$context['search_type'] = $this->subaction;
		Utils::$context['search_term'] = isset($_REQUEST['search_term']) ? Utils::htmlspecialchars($_REQUEST['search_term'], ENT_QUOTES) : '';

		Utils::$context['sub_template'] = 'admin_search_results';
		Utils::$context['page_title'] = Lang::$txt['admin_search_results'];

		// Keep track of what the admin wants.
		if (empty(Utils::$context['admin_preferences']['sb']) || Utils::$context['admin_preferences']['sb'] != $this->subaction)
		{
			Utils::$context['admin_preferences']['sb'] = $this->subaction;

			// Update the preferences.
			ACP::updateAdminPreferences();
		}

		if (trim(Utils::$context['search_term']) == '')
			Utils::$context['search_results'] = array();
		else
			call_helper(array($this, self::$subactions[$this->subaction]));
	}


	/**
	 * A complicated but relatively quick internal search.
	 */
	public function internal()
	{
		// Try to get some more memory.
		setMemoryLimit('128M');

		call_integration_hook('integrate_admin_search', array(&$this->language_files, &$this->include_files, &$this->settings_search));

		Lang::load(implode('+', $this->language_files));

		foreach ($this->include_files as $file)
			require_once(Config::$sourcedir . '/' . $file . '.php');

		/* This is the huge array that defines everything... it's a huge array of items formatted as follows:
			0 = Language index (Can be array of indexes) to search through for this setting.
			1 = URL for this indexes page.
			2 = Help index for help associated with this item (If different from 0)
		*/

		$search_data = array(
			// All the major sections of the forum.
			'sections' => array(
			),
			'settings' => array(
				array('COPPA', 'area=regcenter;sa=settings'),
				array('CAPTCHA', 'area=antispam'),
			),
		);

		// Go through the admin menu structure trying to find suitably named areas!
		foreach (Menu::$loaded['admin']['sections'] as $section)
		{
			foreach ($section['areas'] as $menu_key => $menu_item)
			{
				$search_data['sections'][] = array($menu_item['label'], 'area=' . $menu_key);

				if (!empty($menu_item['subsections']))
				{
					foreach ($menu_item['subsections'] as $key => $sublabel)
					{
						if (isset($sublabel['label']))
						{
							$search_data['sections'][] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
						}
					}
				}
			}
		}

		foreach ($this->settings_search as $setting_area)
		{
			// Get a list of their variables.
			$config_vars = call_user_func($setting_area[0], true);

			foreach ($config_vars as $var)
			{
				if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch', 'desc')))
				{
					$search_data['settings'][] = array($var[(isset($var[2]) && in_array($var[2], array('file', 'db'))) ? 0 : 1], $setting_area[1], 'alttxt' => (isset($var[2]) && in_array($var[2], array('file', 'db'))) || isset($var[3]) ? (in_array($var[2], array('file', 'db')) ? $var[1] : $var[3]) : '');
				}
			}
		}

		Utils::$context['page_title'] = Lang::$txt['admin_search_results'];
		Utils::$context['search_results'] = array();

		$search_term = strtolower(un_htmlspecialchars(Utils::$context['search_term']));

		// Go through all the search data trying to find this text!
		foreach ($search_data as $section => $data)
		{
			foreach ($data as $item)
			{
				$found = false;

				if (!is_array($item[0]))
					$item[0] = array($item[0]);

				foreach ($item[0] as $term)
				{
					if (
						stripos($term, $search_term) !== false
						|| (
							isset(Lang::$txt[$term])
							&& stripos(Lang::$txt[$term], $search_term) !== false
						)
						|| (
							isset(Lang::$txt['setting_' . $term])
							&& stripos(Lang::$txt['setting_' . $term], $search_term) !== false
						)
					)
					{
						$found = $term;
						break;
					}
				}

				if ($found)
				{
					// Format the name - and remove any descriptions the entry may have.
					$name = isset(Lang::$txt[$found]) ? Lang::$txt[$found] : (isset(Lang::$txt['setting_' . $found]) ? Lang::$txt['setting_' . $found] : (!empty($item['alttxt']) ? $item['alttxt'] : $found));

					$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $name);

					Utils::$context['search_results'][] = array(
						'url' => (substr($item[1], 0, 4) == 'area' ? Config::$scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ((substr($item[1], 0, 4) == 'area' && $section == 'settings' ? '#' . $item[0][0] : '')),
						'name' => $name,
						'type' => $section,
						'help' => shorten_subject(isset($item[2]) ? strip_tags(Lang::$helptxt[$item[2]]) : (isset(Lang::$helptxt[$found]) ? strip_tags(Lang::$helptxt[$found]) : ''), 255),
					);
				}
			}
		}
	}

	/**
	 * All this does is pass through to manage members.
	 * {@see ViewMembers()}
	 */
	public function member()
	{
		$_REQUEST['sa'] = 'query';

		$_POST['membername'] = un_htmlspecialchars(Utils::$context['search_term']);
		$_POST['types'] = '';

		Members::call();
	}

	/**
	 * This file allows the user to search the SM online manual for a little of help.
	 */
	public function online()
	{
		Utils::$context['doc_apiurl'] = 'https://wiki.simplemachines.org/api.php';
		Utils::$context['doc_scripturl'] = 'https://wiki.simplemachines.org/smf/';

		// Set all the parameters search might expect.
		$postVars = explode(' ', Utils::$context['search_term']);

		// Encode the search data.
		foreach ($postVars as $k => $v)
			$postVars[$k] = urlencode($v);

		// This is what we will send.
		$postVars = implode('+', $postVars);

		// Get the results from the doc site.
		// Demo URL:
		// https://wiki.simplemachines.org/api.php?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=template+eval
		$search_results = fetch_web_data(Utils::$context['doc_apiurl'] . '?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=' . $postVars);

		// If we didn't get any xml back we are in trouble - perhaps the doc site is overloaded?
		if (!$search_results || preg_match('~<' . '\?xml\sversion="\d+\.\d+"\?' . '>\s*(<api\b[^>]*>.+?</api>)~is', $search_results, $matches) != true)
			ErrorHandler::fatalLang('cannot_connect_doc_site');

		$search_results = $matches[1];

		// Otherwise we simply walk through the XML and stick it in context for display.
		Utils::$context['search_results'] = array();

		// Get the results loaded into an array for processing!
		$results = new XmlArray($search_results, false);

		// Move through the api layer.
		if (!$results->exists('api'))
			ErrorHandler::fatalLang('cannot_connect_doc_site');

		// Are there actually some results?
		if ($results->exists('api/query/search/p'))
		{
			$relevance = 0;
			foreach ($results->set('api/query/search/p') as $result)
			{
				Utils::$context['search_results'][$result->fetch('@title')] = array(
					'title' => $result->fetch('@title'),
					'relevance' => $relevance++,
					'snippet' => str_replace('class=\'searchmatch\'', 'class="highlight"', un_htmlspecialchars($result->fetch('@snippet'))),
				);
			}
		}
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
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->subaction = !isset($_REQUEST['search_type']) || !isset(self::$subactions[$_REQUEST['search_type']]) ? 'internal' : $_REQUEST['search_type'];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Find::exportStatic'))
	Find::exportStatic();

?>