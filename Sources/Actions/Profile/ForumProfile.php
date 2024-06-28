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
use SMF\Sources\Lang;
use SMF\Sources\Profile;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * Handles the main "Forum Profile" section of the profile.
 */
class ForumProfile implements ActionInterface
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
		Profile::$member->loadThemeOptions();

		if (User::$me->allowedTo(['profile_forum_own', 'profile_forum_any'])) {
			Profile::$member->loadCustomFields('forumprofile');
		}

		Utils::$context['page_desc'] = Lang::getTxt('forumProfile_info', ['forum_name' => Utils::$context['forum_name_html_safe']]);

		Utils::$context['show_preview_button'] = true;

		Profile::$member->setupContext(
			[
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