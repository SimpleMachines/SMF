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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\ProvidesSubActionInterface;
use SMF\ProvidesSubActionTrait;
use SMF\Theme;
use SMF\Utils;

/**
 * This class has the important job of taking care of help messages and the help center.
 */
class Help implements ActionInterface, ProvidesSubActionInterface
{
	use ActionTrait;
	use ProvidesSubActionTrait;
	use BackwardCompatibility;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		IntegrationHook::call('integrate_manage_help', [&$this->sub_actions]);

		$this->callSubAction($_REQUEST['sa'] ?? null);
	}

	/**
	 * The main page for the Help section
	 */
	public function index()
	{
		// We need to know where our wiki is.
		Utils::$context['wiki_url'] = 'https://wiki.simplemachines.org/smf';
		Utils::$context['wiki_prefix'] = 'SMF2.1:';

		Utils::$context['canonical_url'] = Config::$scripturl . '?action=help';

		// Sections we are going to link...
		Utils::$context['manual_sections'] = [
			'registering' => 'Registering',
			'logging_in' => 'Logging_In',
			'profile' => 'Profile',
			'search' => 'Search',
			'posting' => 'Posting',
			'bbc' => 'Bulletin_board_code',
			'personal_messages' => 'Personal_messages',
			'memberlist' => 'Memberlist',
			'calendar' => 'Calendar',
			'features' => 'Features',
		];

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=help',
			'name' => Lang::$txt['help'],
		];

		// Lastly, some minor template stuff.
		Utils::$context['page_title'] = Lang::$txt['manual_smf_user_help'];
		Utils::$context['sub_template'] = 'manual';
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Backward compatibility wrapper for the index sub-action.
	 */
	public static function HelpIndex(): void
	{
		$obj = self::load();
		$obj->setDefaultSubAction('index');
		$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 *
	 * Redirect to the user help ;).
	 * It loads information needed for the help section.
	 * It is accessed by ?action=help.
	 *
	 * Uses Help template and Manual language file.
	 */
	protected function __construct()
	{
		$this->addSubAction('index', [$this, 'index']);

		Theme::loadTemplate('Help');
		Lang::load('Manual');
	}
}

?>