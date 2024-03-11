<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Handles the "Look and Layout" section of the profile
 */
class ThemeOptions implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		Theme::loadTemplate('Settings');
		Theme::loadSubTemplate('options');

		// Check for variants or dark mode
		if (!empty(Theme::$current->settings['theme_variants']) || !empty(Theme::$current->settings['has_dark_mode'])) {

			Lang::load('Themes');
			Utils::$context['additional_options'] = [];

			// Theme Variants
			if (!empty(Theme::$current->settings['theme_variants']) && (empty(Theme::$current->settings['disable_user_variant']) || User::$me->allowedTo('admin_forum'))) {
				$available_variants = [];
				foreach (Theme::$current->settings['theme_variants'] as $variant) {
					$available_variants[$variant] = Lang::$txt['variant_' . $variant] ?? $variant;
				}

				Utils::$context['additional_options'][] = Lang::$txt['theme_opt_variant'];
				Utils::$context['additional_options'][] = [
					'id' => 'theme_variant',
					'label' => Lang::$txt['theme_pick_variant'],
					'options' => $available_variants,
					'default' => isset(Theme::$current->settings['default_variant']) && !empty(Theme::$current->settings['default_variant']) ? Theme::$current->settings['default_variant'] : Theme::$current->settings['theme_variants'][0],
					'enabled' => !empty(Theme::$current->settings['theme_variants']),
				];
			}

			// Theme Color Mode
			if (!empty(Theme::$current->settings['has_dark_mode']) && (empty(Theme::$current->settings['disable_user_mode']) || User::$me->allowedTo('admin_forum'))) {
				$available_modes = [];
				foreach (Theme::$current->settings['theme_colormodes'] as $mode) {
					$available_modes[$mode] = Lang::$txt['colormode_' . $mode] ?? $mode;
				}

				Utils::$context['additional_options'][] = Lang::$txt['theme_opt_colormode'];
				Utils::$context['additional_options'][] = [
					'id' => 'theme_colormode',
					'label' => Lang::$txt['theme_pick_colormode'],
					'options' => $available_modes,
					'default' => isset(Theme::$current->settings['default_colormode']) && !empty(Theme::$current->settings['default_colormode']) ? Theme::$current->settings['default_colormode'] : Theme::$current->settings['theme_colormodes'][0],
					'enabled' => !empty(Theme::$current->settings['has_dark_mode']),
				];
			}

			Utils::$context['theme_options'] = array_merge(Utils::$context['additional_options'], Utils::$context['theme_options']);
		}

		// Let mods hook into the theme options.
		IntegrationHook::call('integrate_theme_options');

		Profile::$member->loadThemeOptions();

		if (User::$me->allowedTo(['profile_extra_own', 'profile_extra_any'])) {
			Profile::$member->loadCustomFields('theme');
		}

		Utils::$context['page_desc'] = Lang::$txt['theme_info'];

		Profile::$member->setupContext(
			[
				'id_theme',
				'smiley_set',
				'hr',
				'time_format',
				'timezone',
				'hr',
				'theme_settings',
			],
		);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

?>