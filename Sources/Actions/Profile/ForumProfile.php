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

use SMF\Lang;
use SMF\Profile;
use SMF\Utils;

/**
 * Handles the main "Forum Profile" section of the profile.
 */
class ForumProfile implements ActionInterface
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
			'call' => 'forumProfile',
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
		Profile::$member->loadThemeOptions();

		if (allowedTo(array('profile_forum_own', 'profile_forum_any')))
		{
			Profile::$member->loadCustomFields('forumprofile');
		}

		Utils::$context['page_desc'] = sprintf(Lang::$txt['forumProfile_info'], Utils::$context['forum_name_html_safe']);

		Utils::$context['show_preview_button'] = true;

		Profile::$member->setupContext(
			array(
				'avatar_choice', 
			'hr', 
				'personal_text', 
			'hr',
				'bday1', 
				'usertitle', 
				'signature', 
			'hr',
				'website_title', 
				'website_url',
			),
		);
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
		if (!isset(Profile::$member))
			Profile::load();
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\ForumProfile::exportStatic'))
	ForumProfile::exportStatic();

?>