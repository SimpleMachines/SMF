<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Routable;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows an interface to allow a member to choose a new theme.
 *
 * - uses the Themes template. (pick sub template.)
 * - accessed with ?action=themechooser.
 */
class ThemeChooser implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
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
		Lang::load('ThemeStrings');
		Theme::loadTemplate('Themes');

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=themechooser;u=' . $_REQUEST['u'],
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
			elseif ($this->canChooseTheme($_REQUEST['u'], $id_theme)) {
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
						[
							'id_theme' => 'int',
							'id_member' => 'int',
							'variable' => 'string-255',
							'value' => 'string-65534',
						],
						[
							[
								$id_theme,
								$_REQUEST['u'],
								'theme_variant',
								$variant,
							],
						],
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
			[],
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
		$current_images_url = Theme::$current->settings['images_url'];
		$current_theme_variants = !empty(Theme::$current->settings['theme_variants']) ? Theme::$current->settings['theme_variants'] : [];

		$current_lang_dirs = Lang::$dirs;
		$current_thumbnail = Lang::$txt['theme_thumbnail_href'];
		$current_description = Lang::$txt['theme_description'];

		foreach (Utils::$context['available_themes'] as $id_theme => $theme_data) {
			// Don't try to load the forum or board default theme's data... it doesn't have any!
			if ($id_theme == 0) {
				continue;
			}

			// The thumbnail needs the correct path.
			Theme::$current->settings['images_url'] = &$theme_data['images_url'];

			Lang::addDirs([$theme_data['theme_dir'] . '/languages']);
			Lang::load('Settings', '', false, true);

			if (empty(Lang::$txt['theme_thumbnail_href'])) {
				Lang::$txt['theme_thumbnail_href'] = $theme_data['images_url'] . '/thumbnail.png';
			}

			if (empty(Lang::$txt['theme_description'])) {
				Lang::$txt['theme_description'] = '';
			}

			Utils::$context['available_themes'][$id_theme]['thumbnail_href'] = Lang::getTxt('theme_thumbnail_href', Theme::$current->settings);

			Utils::$context['available_themes'][$id_theme]['description'] = Lang::$txt['theme_description'];

			// Are there any variants?
			Utils::$context['available_themes'][$id_theme]['variants'] = [];

			if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || User::$me->allowedTo('admin_forum'))) {
				$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));

				if (preg_match('~((?:SMF\\\\)?Theme::\$current(?:->|_)|\$)settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches)) {
					Theme::$current->settings['theme_variants'] = [];

					// Fill settings up.
					eval(($matches[1] === '$' ? 'global $settings; ' : 'use SMF\\Theme; ') . $matches[0]);

					if (!empty(Theme::$current->settings['theme_variants'])) {
						foreach (Theme::$current->settings['theme_variants'] as $variant) {
							Utils::$context['available_themes'][$id_theme]['variants'][$variant] = [
								'label' => Lang::$txt['variant_' . $variant] ?? $variant,
								'thumbnail' => file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : (file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') ? $theme_data['images_url'] . '/thumbnail.png' : ''),
							];
						}

						Utils::$context['available_themes'][$id_theme]['selected_variant'] = $_GET['vrt'] ?? (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty(Theme::$current->settings['default_variant']) ? Theme::$current->settings['default_variant'] : Theme::$current->settings['theme_variants'][0]));

						if (!isset(Utils::$context['available_themes'][$id_theme]['variants'][Utils::$context['available_themes'][$id_theme]['selected_variant']]['thumbnail'])) {
							Utils::$context['available_themes'][$id_theme]['selected_variant'] = Theme::$current->settings['theme_variants'][0];
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

		Theme::addJavaScriptVar(
			'oThemeVariants',
			Utils::jsonEncode(array_map(
				function ($theme) {
					return $theme['variants'];
				},
				Utils::$context['available_themes'],
			)),
		);
		Theme::loadJavaScriptFile('profile.js', ['defer' => false, 'minimize' => true], 'smf_profile');
		Theme::$current->settings['images_url'] = $current_images_url;
		Theme::$current->settings['theme_variants'] = $current_theme_variants;

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

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		$route[] = $params['action'];
		unset($params['action']);

		if (isset($params['u']) && $params['u'] == User::$me->id) {
			unset($params['u']);
		}

		return ['route' => $route, 'params' => $params];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Determines if a user can change their theme to the one specified.
	 *
	 * @param int $id_member
	 * @param int $id_theme
	 * @return bool
	 */
	protected function canChooseTheme(int $id_member, int $id_theme): bool
	{
		return (
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
			)
		);
	}
}

?>