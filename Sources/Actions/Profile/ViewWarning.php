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
use SMF\Sources\ErrorHandler;
use SMF\Sources\ItemList;
use SMF\Sources\Lang;
use SMF\Sources\Profile;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class ViewWarning implements ActionInterface
{
	use ActionTrait;

	use BackwardCompatibility;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// Firstly, can we actually even be here?
		if (
			!(User::$me->is_owner && User::$me->allowedTo('view_warning_own'))
			&& !User::$me->allowedTo('view_warning_any')
			&& !User::$me->allowedTo('issue_warning')
			&& !User::$me->allowedTo('moderate_forum')
		) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Let's use a generic list to get all the current warnings, and use the issue warnings grab-a-granny thing.
		$list_options = [
			'id' => 'view_warnings',
			'title' => Lang::$txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['profile_viewwarning_no_warnings'],
			'base_href' => Config::$scripturl . '?action=profile;area=viewwarning;sa=user;u=' . Profile::$member->id,
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => __NAMESPACE__ . '\\IssueWarning::list_getUserWarnings',
				'params' => [],
			],
			'get_count' => [
				'function' => __NAMESPACE__ . '\\IssueWarning::list_getUserWarningCount',
				'params' => [],
			],
			'columns' => [
				'log_time' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_time'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					],
				],
				'reason' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_reason'],
						'style' => 'width: 50%;',
					],
					'data' => [
						'db' => 'reason',
					],
				],
				'level' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_level'],
					],
					'data' => [
						'db' => 'counter',
					],
					'sort' => [
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => Lang::$txt['profile_viewwarning_desc'],
					'class' => 'smalltext',
					'style' => 'padding: 2ex;',
				],
			],
		];

		// Create the list for viewing.
		new ItemList($list_options);

		// Create some common text bits for the template.
		Utils::$context['level_effects'] = [
			0 => '',
			Config::$modSettings['warning_watch'] => Lang::$txt['profile_warning_effect_own_watched'],
			Config::$modSettings['warning_moderate'] => Lang::$txt['profile_warning_effect_own_moderated'],
			Config::$modSettings['warning_mute'] => Lang::$txt['profile_warning_effect_own_muted'],
		];

		// Figure out which warning level this member is at.
		Utils::$context['current_level'] = 0;

		foreach (Utils::$context['level_effects'] as $limit => $dummy) {
			if (Utils::$context['member']['warning'] >= $limit) {
				Utils::$context['current_level'] = $limit;
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
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		// Make sure things which are disabled stay disabled.
		Config::$modSettings['warning_watch'] = !empty(Config::$modSettings['warning_watch']) ? Config::$modSettings['warning_watch'] : 110;

		Config::$modSettings['warning_moderate'] = !empty(Config::$modSettings['warning_moderate']) && !empty(Config::$modSettings['postmod_active']) ? Config::$modSettings['warning_moderate'] : 110;

		Config::$modSettings['warning_mute'] = !empty(Config::$modSettings['warning_mute']) ? Config::$modSettings['warning_mute'] : 110;
	}
}

?>