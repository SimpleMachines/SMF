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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
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
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var self
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static self $obj;

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

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return self An instance of this class.
	 */
	public static function load(): self
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

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
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

?>