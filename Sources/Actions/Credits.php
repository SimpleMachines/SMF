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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Routable;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This action prepares credit and copyright information for the credits page
 * and the admin page.
 */
class Credits implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * If true, will not load the sub-template nor the template file.
	 */
	public bool $in_admin = false;

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

		if ($this->in_admin) {
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::getTxt('support_credits_title', file: 'Admin'),
				'help' => '',
				'description' => '',
			];
		}

		Utils::$context['credits'] = [
			[
				'pretext' => Lang::getTxt('credits_intro', ['SMF_VERSION' => SMF_VERSION], file: 'Who'),
				'title' => Lang::getTxt('credits_team', file: 'Who'),
				'groups' => [
					[
						'title' => Lang::getTxt('credits_groups_pm', file: 'Who'),
						'members' => [
							'Aleksi "Lex" Kilpinen',
							// Former Project Managers
							'Michele "Illori" Davis',
							'Jessica "Suki" González',
							'Will "Kindred" Wagner',
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_dev', file: 'Who'),
						'members' => [
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
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_support', file: 'Who'),
						'members' => [
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
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_customize', file: 'Who'),
						'members' => [
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
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_docs', file: 'Who'),
						'members' => [
							// Doc Coordinator
							'Michele "Illori" Davis',
							// Doc Writers
							'Irisado',

							// Former Doc Writers
							'AngelinaBelle',
							'Chainy',
							'Graeme Spence',
							'Joshua "groundup" Dickerson',
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_internationalizers', file: 'Who'),
						'members' => [
							// Lead Localizer
							'Nikola "Dzonny" Novaković',
							// Localizers
							'm4z',
							// Former Localizers
							'Francisco "d3vcho" Domínguez',
							'Robert Monden',
							'Relyana',
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_marketing', file: 'Who'),
						'members' => [
							// Marketing Coordinator

							// Marketing

							// Former Marketing
							'Adish "(F.L.A.M.E.R)" Patel',
							'Bryan "Runic" Deakin',
							'Marcus "cσσкιє мσηѕтєя" Forsberg',
							'Ralph "[n3rve]" Otowo',
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_site', file: 'Who'),
						'members' => [
							'Jeremy "SleePy" Darwood',
						],
					],
					[
						'title' => Lang::getTxt('credits_groups_servers', file: 'Who'),
						'members' => [
							'Derek Schwab',
							'Michael Johnson',
							'Liroy van Hoewijk',
						],
					],
				],
			],
		];

		// Give the translators some credit for their hard work.
		if (!is_array(Lang::getTxt('translation_credits', file: 'Who'))) {
			Lang::setTxt(
				'translation_credits',
				array_filter(
					array_map(
						'trim',
						explode(',', Lang::getTxt('translation_credits', file: 'Who')),
					),
				),
			);
		}

		if (!empty(Lang::getTxt('translation_credits', file: 'Who'))) {
			Utils::$context['credits'][] = [
				'title' => Lang::getTxt('credits_groups_translation', file: 'Who'),
				'groups' => [
					[
						'title' => Lang::getTxt('credits_groups_translation', file: 'Who'),
						'members' => Lang::getTxt('translation_credits', file: 'Who'),
					],
				],
			];
		}

		Utils::$context['credits'][] = [
			'title' => Lang::getTxt('credits_special', file: 'Who'),
			'posttext' => Lang::getTxt('credits_anyone', file: 'Who'),
			'groups' => [
				[
					'title' => Lang::getTxt('credits_groups_consultants', file: 'Who'),
					'members' => [
						'albertlast',
						'Brett Flannigan',
						'Mark Rose',
						'René-Gilles "Nao 尚" Deberdt',
						'tinoest',
						Lang::getTxt('credits_code_contributors', file: 'Who'),
					],
				],
				[
					'title' => Lang::getTxt('credits_groups_beta', file: 'Who'),
					'members' => [
						Lang::getTxt('credits_beta_message', file: 'Who'),
					],
				],
				[
					'title' => Lang::getTxt('credits_groups_translators', file: 'Who'),
					'members' => [
						Lang::getTxt('credits_translators_message', file: 'Who'),
					],
				],
				[
					'title' => Lang::getTxt('credits_groups_founder', file: 'Who'),
					'members' => [
						'Unknown W. "[Unknown]" Brackets',
					],
				],
				[
					'title' => Lang::getTxt('credits_groups_orignal_pm', file: 'Who'),
					'members' => [
						'Jeff Lewis',
						'Joseph Fung',
						'David Recordon',
					],
				],
				[
					'title' => Lang::getTxt('credits_in_memoriam', file: 'Who'),
					'members' => [
						'Crip',
						'K@',
						'metallica48423',
						'Paul_Pauline',
						'Rock Lee',
					],
				],
			],
		];

		// Give credit to any graphic library's, software library's, plugins etc
		Utils::$context['credits_software_graphics'] = [
			'graphics' => [
				'<a href="https://p.yusukekamiyamane.com/">Fugue Icons</a> | © 2012 Yusuke Kamiyamane | These icons are licensed under a Creative Commons Attribution 3.0 License',
				'<a href="https://techbase.kde.org/Projects/Oxygen/Licensing#Use_on_Websites">Oxygen Icons</a> | These icons are licensed under <a href="https://www.gnu.org/licenses/lgpl-3.0.html">GNU LGPLv3</a>',
			],
			'software' => [
				'<a href="https://jquery.org/">JQuery</a> | © John Resig | Licensed under <a href="https://github.com/jquery/jquery/blob/master/LICENSE.txt">The MIT License (MIT)</a>',
				'<a href="https://briancherne.github.io/jquery-hoverIntent/">hoverIntent</a> | © Brian Cherne | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://www.sceditor.com/">SCEditor</a> | © Sam Clarke | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/code-of-kpp/jquery-animadrag/blob/master/animadrag.js">animaDrag</a> | © Abel Mohler | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/mzubala/jquery-custom-scrollbar">jQuery Custom Scrollbar</a> | © Maciej Zubala | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://slippry.com/">jQuery Responsive Slider</a> | © booncon ROCKETS | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/ichord/At.js">At.js</a> | © chord.luo@gmail.com | Licensed under <a href="https://github.com/ichord/At.js/blob/master/LICENSE-MIT">The MIT License (MIT)</a>',
				'<a href="https://github.com/ttsvetko/HTML5-Desktop-Notifications">HTML5 Desktop Notifications</a> | © Tsvetan Tsvetkov | Licensed under <a href="https://github.com/ttsvetko/HTML5-Desktop-Notifications/blob/master/License.txt">The Apache License Version 2.0</a>',
				'<a href="https://github.com/enygma/gauth">GAuth Code Generator/Validator</a> | © Chris Cornutt | Licensed under <a href="https://github.com/enygma/gauth/blob/master/LICENSE">The MIT License (MIT)</a>',
				'<a href="https://github.com/enyo/dropzone">Dropzone.js</a> | © Matias Meno | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/matthiasmullie/minify">Minify</a> | © Matthias Mullie | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/true/php-punycode">PHP-Punycode</a> | © True B.V. | Licensed under <a href="https://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
				'<a href="https://github.com/mdbassit/Coloris">Coloris</a> | © Mohammed Bassit | Licensed under <a href="https://github.com/mdbassit/Coloris/blob/main/LICENSE">The MIT License (MIT)</a>',
			],
			'fonts' => [
				'<a href="https://fontlibrary.org/en/font/anonymous-pro"> Anonymous Pro</a> | © 2009 | This font is licensed under the SIL Open Font License, Version 1.1',
				'<a href="https://fontlibrary.org/en/font/consolamono"> ConsolaMono</a> | © 2012 | This font is licensed under the SIL Open Font License, Version 1.1',
				'<a href="https://fontlibrary.org/en/font/phennig"> Phennig</a> | © 2009-2012 | This font is licensed under the SIL Open Font License, Version 1.1',
			],
		];

		// Support for mods that use the <credits> tag via the package manager
		Utils::$context['credits_modifications'] = [];

		if (($mods = CacheApi::get('mods_credits', 86400)) === null) {
			$mods = [];

			$request = Db::$db->query(
				'SELECT version, name, credits
				FROM {db_prefix}log_packages
				WHERE install_state = {int:installed_mods}
					AND credits != {string:empty}
					AND SUBSTRING(filename, 1, 9) != {string:patch_name}',
				[
					'installed_mods' => 1,
					'patch_name' => 'smf_patch',
					'empty' => '',
				],
				identifier: 'substring',
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$credit_info = Utils::jsonDecode($row['credits'], true);

				$copyright = empty($credit_info['copyright']) ? '' : Lang::getTxt('credits_copyright', ['copyright_holder' => Utils::htmlspecialchars($credit_info['copyright'])], file: 'Who');

				$license = empty($credit_info['license']) ? '' : Lang::getTxt('credits_license', ['license' => (!empty($credit_info['licenseurl']) ? '<a href="' . Utils::htmlspecialchars($credit_info['licenseurl']) . '">' . Utils::htmlspecialchars($credit_info['license']) . '</a>' : Utils::htmlspecialchars($credit_info['license']))], file: 'Who');

				$version = Lang::getTxt('credits_version', $row, file: 'Who');

				$title = (empty($credit_info['title']) ? $row['name'] : Utils::htmlspecialchars($credit_info['title'])) . ', ' . $version;

				// Build this one out and stash it away.
				$mod_name = empty($credit_info['url']) ? $title : '<a href="' . $credit_info['url'] . '">' . $title . '</a>';

				$mods[] = $mod_name . (!empty($license) ? ' | ' . $license : '') . (!empty($copyright) ? ' | ' . $copyright : '');
			}

			CacheApi::put('mods_credits', $mods, 86400);
		}

		Utils::$context['credits_modifications'] = $mods;

		Utils::$context['copyrights'] = [
			'smf' => Lang::formatText(Lang::$forum_copyright, ['version' => SMF_FULL_VERSION, 'year' => SMF_SOFTWARE_YEAR, 'scripturl' => Config::$scripturl]),
			/* Modification Authors:  You may add a copyright statement to this array for your mods.
				Copyright statements should be in the form of a value only without an array key.  I.E.:
					'Some Mod by Thantos © 2010',
					Lang::getTxt('some_mod_copyright', file: 'SomeModLanguageFile'),
			*/
			'mods' => [
			],
		];

		// Support for those that want to use a hook as well
		IntegrationHook::call('integrate_credits');

		if (!$this->in_admin) {
			Theme::loadTemplate('Who');
			Utils::$context['sub_template'] = 'credits';
			Utils::$context['robot_no_index'] = true;
			Utils::$context['page_title'] = Lang::getTxt('credits', file: 'Who');
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

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
}
