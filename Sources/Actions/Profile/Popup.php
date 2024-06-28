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

namespace SMF\Sources\Actions\Profile;

use SMF\Sources\ActionInterface;
use SMF\Sources\ActionTrait;
use SMF\Sources\Config;
use SMF\Sources\IntegrationHook;
use SMF\Sources\Lang;
use SMF\Sources\Menu;
use SMF\Sources\Utils;

/**
 * Shows the popup menu for the current user's profile.
 */
class Popup implements ActionInterface
{
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * A list of menu items to pull from the main profile menu.
	 *
	 * The values of all 'title' elements are Lang::$txt keys, and will be
	 * replaced at runtime with the values of those Lang::$txt strings.
	 *
	 * Occurrences of '{scripturl}' in value strings will be replaced at runtime
	 * with the value of Config::$scripturl.
	 */
	public array $profile_items = [
		[
			'menu' => 'edit_profile',
			'area' => 'account',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'forumprofile',
			'title' => 'popup_forumprofile',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'theme',
			'title' => 'theme',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'notification',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'ignoreboards',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'lists',
			'url' => '{scripturl}?action=profile;area=lists;sa=ignore',
			'title' => 'popup_ignore',
		],
		[
			'menu' => 'info',
			'area' => 'showposts',
			'title' => 'popup_showposts',
		],
		[
			'menu' => 'info',
			'area' => 'showdrafts',
			'title' => 'popup_showdrafts',
		],
		[
			'menu' => 'edit_profile',
			'area' => 'groupmembership',
			'title' => 'popup_groupmembership',
		],
		[
			'menu' => 'profile_action',
			'area' => 'subscriptions',
			'title' => 'popup_subscriptions',
		],
		[
			'menu' => 'profile_action',
			'area' => 'logout',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];

		IntegrationHook::call('integrate_profile_popup', [&$this->profile_items]);

		// Now check if these items are available
		Utils::$context['profile_items'] = [];

		foreach ($this->profile_items as $item) {
			if (isset(Menu::$loaded['profile']['sections'][$item['menu']]['areas'][$item['area']])) {
				Utils::$context['profile_items'][] = $item;
			}
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Finalize various string values.
		array_walk_recursive(
			$this->profile_items,
			function (&$value, $key) {
				if ($key === 'title') {
					$value = Lang::$txt[$value] ?? $value;
				}

				$value = strtr($value, ['{scripturl}' => Config::$scripturl]);
			},
		);
	}
}

?>