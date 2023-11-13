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

namespace SMF\Actions;

use SMF\BackwardCompatibility;

use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * This action prepares credit and copyright information for the credits page
 * and the admin page.
 */
class Credits implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'call' => 'Credits',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * If true, will not load the sub-template nor the template file.
	 */
	public bool $in_admin = false;

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
		// Discourage robots from indexing this page.
		Utils::$context['robot_no_index'] = true;

		if ($this->in_admin)
		{
			Menu::$loaded['admin']->tab_data = array(
				'title' => Lang::$txt['support_credits_title'],
				'help' => '',
				'description' => '',
			);
		}

		Utils::$context['credits'] = array(
			array(
				'pretext' => Lang::$txt['credits_intro'],
				'title' => Lang::$txt['credits_team'],
				'groups' => array(
					array(
						'title' => Lang::$txt['credits_groups_pm'],
						'members' => array(
							'Aleksi "Lex" Kilpinen',
							// Former Project Managers
							'Michele "Illori" Davis',
							'Jessica "Suki" González',
							'Will "Kindred" Wagner',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_dev'],
						'members' => array(
							// Lead Developer
							'Shawn Bulen',
							// Developers
							'John "live627" Rayes',
							'Oscar "Ozp" Rydhé',

							// Former Developers
							'Aaron van Geffen',
							'Antechinus',
							'Bjoern "Bloc" Kristiansen',
							'Brad "IchBin™" Grow',
							'Colin Schoen',
							'emanuele',
							'Hendrik Jan "Compuart" Visser',
							'Jessica "Suki" González',
							'Jon "Sesquipedalian" Stovell',
							'Juan "JayBachatero" Hernandez',
							'Karl "RegularExpression" Benson',
							'Matthew "Labradoodle-360" Kerle',
							User::$me->is_admin ? 'Matt "Grudge" Wolf' : 'Grudge',
							'Michael "Oldiesmann" Eshom',
							'Michael "Thantos" Miller',
							'Norv',
							'Peter "Arantor" Spicer',
							'Selman "[SiNaN]" Eser',
							'Shitiz "Dragooon" Garg',
							// 'Spuds', // Doesn't want to be listed here
							// 'Steven "Fustrate" Hoffman',
							'Theodore "Orstio" Hildebrandt',
							'Thorsten "TE" Eurich',
							'winrules',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_support'],
						'members' => array(
							// Lead Support Specialist
							'Will "Kindred" Wagner',
							// Support Specialists
							'Doug Heffernan',
							'lurkalot',
							'Steve',

							// Former Support Specialists
							'Aleksi "Lex" Kilpinen',
							'br360',
							'GigaWatt',
							'ziycon',
							'Adam Tallon',
							'Bigguy',
							'Bruno "margarett" Alves',
							'CapadY',
							'ChalkCat',
							'Chas Large',
							'Duncan85',
							'gbsothere',
							'JimM',
							'Justyne',
							'Kat',
							'Kevin "greyknight17" Hou',
							'Krash',
							'Mashby',
							'Michael Colin Blaber',
							'Old Fossil',
							'S-Ace',
							'shadav',
							'Storman™',
							'Wade "sησω" Poulsen',
							'xenovanis',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_customize'],
						'members' => array(
							// Lead Customizer
							'Diego Andrés',
							// Customizers
							'GL700Wing',
							'Johnnie "TwitchisMental" Ballew',
							'Jonathan "vbgamer45" Valentin',

							// Former Customizers
							'Sami "SychO" Mazouz',
							'Brannon "B" Hall',
							'Gary M. Gadsdon',
							'Jack "akabugeyes" Thorsen',
							'Jason "JBlaze" Clemons',
							'Joey "Tyrsson" Smith',
							'Kays',
							'Michael "Mick." Gomez',
							'NanoSector',
							'Ricky.',
							'Russell "NEND" Najar',
							'SA™',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_docs'],
						'members' => array(
							// Doc Coordinator
							'Michele "Illori" Davis',
							// Doc Writers
							'Irisado',

							// Former Doc Writers
							'AngelinaBelle',
							'Chainy',
							'Graeme Spence',
							'Joshua "groundup" Dickerson',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_internationalizers'],
						'members' => array(
							// Lead Localizer
							'Nikola "Dzonny" Novaković',
							// Localizers
							'm4z',
							// Former Localizers
							'Francisco "d3vcho" Domínguez',
							'Robert Monden',
							'Relyana',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_marketing'],
						'members' => array(
							// Marketing Coordinator

							// Marketing

							// Former Marketing
							'Adish "(F.L.A.M.E.R)" Patel',
							'Bryan "Runic" Deakin',
							'Marcus "cσσкιє мσηѕтєя" Forsberg',
							'Ralph "[n3rve]" Otowo',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_site'],
						'members' => array(
							'Jeremy "SleePy" Darwood',
						),
					),
					array(
						'title' => Lang::$txt['credits_groups_servers'],
						'members' => array(
							'Derek Schwab',
							'Michael Johnson',
							'Liroy van Hoewijk',
						),
					),
				),
			),
		);

		// Give the translators some credit for their hard work.
		if (!is_array(Lang::$txt['translation_credits']))
		{
			Lang::$txt['translation_credits'] = array_filter(array_map('trim', explode(',', Lang::$txt['translation_credits'])));
		}

		if (!empty(Lang::$txt['translation_credits']))
		{
			Utils::$context['credits'][] = array(
				'title' => Lang::$txt['credits_groups_translation'],
				'groups' => array(
					array(
						'title' => Lang::$txt['credits_groups_translation'],
						'members' => Lang::$txt['translation_credits'],
					),
				),
			);
		}

		Utils::$context['credits'][] = array(
			'title' => Lang::$txt['credits_special'],
			'posttext' => Lang::$txt['credits_anyone'],
			'groups' => array(
				array(
					'title' => Lang::$txt['credits_groups_consultants'],
					'members' => array(
						'albertlast',
						'Brett Flannigan',
						'Mark Rose',
						'René-Gilles "Nao 尚" Deberdt',
						'tinoest',
						Lang::$txt['credits_code_contributors'],
					),
				),
				array(
					'title' => Lang::$txt['credits_groups_beta'],
					'members' => array(
						Lang::$txt['credits_beta_message'],
					),
				),
				array(
					'title' => Lang::$txt['credits_groups_translators'],
					'members' => array(
						Lang::$txt['credits_translators_message'],
					),
				),
				array(
					'title' => Lang::$txt['credits_groups_founder'],
					'members' => array(
						'Unknown W. "[Unknown]" Brackets',
					),
				),
				array(
					'title' => Lang::$txt['credits_groups_orignal_pm'],
					'members' => array(
						'Jeff Lewis',
						'Joseph Fung',
						'David Recordon',
					),
				),
				array(
					'title' => Lang::$txt['credits_in_memoriam'],
					'members' => array(
						'Crip',
						'K@',
						'metallica48423',
						'Paul_Pauline',
					),
				),
			),
		);

		// Give credit to any graphic library's, software library's, plugins etc
		Utils::$context['credits_software_graphics'] = array(
			'graphics' => array(
				'<a href="http://p.yusukekamiyamane.com/">Fugue Icons</a> | © 2012 Yusuke Kamiyamane | These icons are licensed under a Creative Commons Attribution 3.0 License',
				'<a href="https://techbase.kde.org/Projects/Oxygen/Licensing#Use_on_Websites">Oxygen Icons</a> | These icons are licensed under <a href="http://www.gnu.org/copyleft/lesser.html">GNU LGPLv3</a>',
			),
			'software' => array(
				'<a href="https://jquery.org/">JQuery</a> | © John Resig | Licensed under <a href="https://github.com/jquery/jquery/blob/master/LICENSE.txt">The MIT License (MIT)</a>',
				'<a href="https://briancherne.github.io/jquery-hoverIntent/">hoverIntent</a> | © Brian Cherne | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://www.sceditor.com/">SCEditor</a> | © Sam Clarke | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="http://wayfarerweb.com/jquery/plugins/animadrag/">animaDrag</a> | © Abel Mohler | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/mzubala/jquery-custom-scrollbar">jQuery Custom Scrollbar</a> | © Maciej Zubala | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="http://slippry.com/">jQuery Responsive Slider</a> | © booncon ROCKETS | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/ichord/At.js">At.js</a> | © chord.luo@gmail.com | Licensed under <a href="https://github.com/ichord/At.js/blob/master/LICENSE-MIT">The MIT License (MIT)</a>',
				'<a href="https://github.com/ttsvetko/HTML5-Desktop-Notifications">HTML5 Desktop Notifications</a> | © Tsvetan Tsvetkov | Licensed under <a href="https://github.com/ttsvetko/HTML5-Desktop-Notifications/blob/master/License.txt">The Apache License Version 2.0</a>',
				'<a href="https://github.com/enygma/gauth">GAuth Code Generator/Validator</a> | © Chris Cornutt | Licensed under <a href="https://github.com/enygma/gauth/blob/master/LICENSE">The MIT License (MIT)</a>',
				'<a href="https://github.com/enyo/dropzone">Dropzone.js</a> | © Matias Meno | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/matthiasmullie/minify">Minify</a> | © Matthias Mullie | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/true/php-punycode">PHP-Punycode</a> | © True B.V. | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
			),
			'fonts' => array(
				'<a href="https://fontlibrary.org/en/font/anonymous-pro"> Anonymous Pro</a> | © 2009 | This font is licensed under the SIL Open Font License, Version 1.1',
				'<a href="https://fontlibrary.org/en/font/consolamono"> ConsolaMono</a> | © 2012 | This font is licensed under the SIL Open Font License, Version 1.1',
				'<a href="https://fontlibrary.org/en/font/phennig"> Phennig</a> | © 2009-2012 | This font is licensed under the SIL Open Font License, Version 1.1',
			),
		);

		// Support for mods that use the <credits> tag via the package manager
		Utils::$context['credits_modifications'] = array();

		if (($mods = CacheApi::get('mods_credits', 86400)) === null)
		{
			$mods = array();

			$request = Db::$db->query('substring', '
				SELECT version, name, credits
				FROM {db_prefix}log_packages
				WHERE install_state = {int:installed_mods}
					AND credits != {string:empty}
					AND SUBSTRING(filename, 1, 9) != {string:patch_name}',
				array(
					'installed_mods' => 1,
					'patch_name' => 'smf_patch',
					'empty' => '',
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$credit_info = Utils::jsonDecode($row['credits'], true);

				$copyright = empty($credit_info['copyright']) ? '' : Lang::$txt['credits_copyright'] . ' © ' . Utils::htmlspecialchars($credit_info['copyright']);
				$license = empty($credit_info['license']) ? '' : Lang::$txt['credits_license'] . ': ' . (!empty($credit_info['licenseurl']) ? '<a href="' . Utils::htmlspecialchars($credit_info['licenseurl']) . '">' . Utils::htmlspecialchars($credit_info['license']) . '</a>' : Utils::htmlspecialchars($credit_info['license']));

				$version = Lang::$txt['credits_version'] . ' ' . $row['version'];

				$title = (empty($credit_info['title']) ? $row['name'] : Utils::htmlspecialchars($credit_info['title'])) . ': ' . $version;

				// Build this one out and stash it away.
				$mod_name = empty($credit_info['url']) ? $title : '<a href="' . $credit_info['url'] . '">' . $title . '</a>';

				$mods[] = $mod_name . (!empty($license) ? ' | ' . $license : '') . (!empty($copyright) ? ' | ' . $copyright : '');
			}

			CacheApi::put('mods_credits', $mods, 86400);
		}

		Utils::$context['credits_modifications'] = $mods;

		Utils::$context['copyrights'] = array(
			'smf' => sprintf(Lang::$forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl),
			/* Modification Authors:  You may add a copyright statement to this array for your mods.
				Copyright statements should be in the form of a value only without a array key.  I.E.:
					'Some Mod by Thantos © 2010',
					Lang::$txt['some_mod_copyright'],
			*/
			'mods' => array(
			),
		);

		// Support for those that want to use a hook as well
		IntegrationHook::call('integrate_credits');

		if (!$this->in_admin)
		{
			Theme::loadTemplate('Who');
			Utils::$context['sub_template'] = 'credits';
			Utils::$context['robot_no_index'] = true;
			Utils::$context['page_title'] = Lang::$txt['credits'];
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
	 *
	 * @param bool $in_admin Whether this is being called from the admin area.
	 */
	public static function call(bool $in_admin = false): void
	{
		self::load();
		self::$obj->in_admin = $in_admin;
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
		// Don't blink. Don't even blink. Blink and you're dead.
		Lang::load('Who');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Credits::exportStatic'))
	Credits::exportStatic();

?>