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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * This class has the important job of taking care of help messages and the help center.
 */
class Help implements ActionInterface
{
	use ActionTrait;

	use BackwardCompatibility;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'index';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'index' => 'index',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
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
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
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
		Theme::loadTemplate('Help');
		Lang::load('Manual');

		// CRUD $subactions as needed.
		IntegrationHook::call('integrate_manage_help', [&self::$subactions]);

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}
}

?>