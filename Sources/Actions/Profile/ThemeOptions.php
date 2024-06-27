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