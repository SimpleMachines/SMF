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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class ViewWarning implements ActionInterface
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
			'call' => false,
		),
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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Firstly, can we actually even be here?
		if (
			!(User::$me->is_owner && allowedTo('view_warning_own'))
			&& !allowedTo('view_warning_any')
			&& !allowedTo('issue_warning')
			&& !allowedTo('moderate_forum')
		)
		{
			fatal_lang_error('no_access', false);
		}

		// Let's use a generic list to get all the current warnings, and use the issue warnings grab-a-granny thing.
		$list_options = array(
			'id' => 'view_warnings',
			'title' => Lang::$txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['profile_viewwarning_no_warnings'],
			'base_href' => Config::$scripturl . '?action=profile;area=viewwarning;sa=user;u=' . Profile::$member->id,
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => __NAMESPACE__ . '\\IssueWarning::list_getUserWarnings',
				'params' => array(),
			),
			'get_count' => array(
				'function' => __NAMESPACE__ . '\\IssueWarning::list_getUserWarningCount',
				'params' => array(),
			),
			'columns' => array(
				'log_time' => array(
					'header' => array(
						'value' => Lang::$txt['profile_warning_previous_time'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => Lang::$txt['profile_warning_previous_reason'],
						'style' => 'width: 50%;',
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'level' => array(
					'header' => array(
						'value' => Lang::$txt['profile_warning_previous_level'],
					),
					'data' => array(
						'db' => 'counter',
					),
					'sort' => array(
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => Lang::$txt['profile_viewwarning_desc'],
					'class' => 'smalltext',
					'style' => 'padding: 2ex;',
				),
			),
		);

		// Create the list for viewing.
		new ItemList($list_options);

		// Create some common text bits for the template.
		Utils::$context['level_effects'] = array(
			0 => '',
			Config::$modSettings['warning_watch'] => Lang::$txt['profile_warning_effect_own_watched'],
			Config::$modSettings['warning_moderate'] => Lang::$txt['profile_warning_effect_own_moderated'],
			Config::$modSettings['warning_mute'] => Lang::$txt['profile_warning_effect_own_muted'],
		);

		// Figure out which warning level this member is at.
		Utils::$context['current_level'] = 0;

		foreach (Utils::$context['level_effects'] as $limit => $dummy)
		{
			if (Utils::$context['member']['warning'] >= $limit)
				Utils::$context['current_level'] = $limit;
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

	/**
	 * Backward compatibility wrapper.
	 */
	public static function viewWarning(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

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
		if (!isset(Profile::$member))
			Profile::load();

		// Make sure things which are disabled stay disabled.
		Config::$modSettings['warning_watch'] = !empty(Config::$modSettings['warning_watch']) ? Config::$modSettings['warning_watch'] : 110;

		Config::$modSettings['warning_moderate'] = !empty(Config::$modSettings['warning_moderate']) && !empty(Config::$modSettings['postmod_active']) ? Config::$modSettings['warning_moderate'] : 110;

		Config::$modSettings['warning_mute'] = !empty(Config::$modSettings['warning_mute']) ? Config::$modSettings['warning_mute'] : 110;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\ViewWarning::exportStatic'))
	ViewWarning::exportStatic();

?>