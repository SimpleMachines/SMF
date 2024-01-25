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

use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Despite the name, which is what it is for historical reasons, this action
 * doesn't actually send anything. It just shows a message for a guest.
 */
class SendActivation implements ActionInterface
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
	 * Do the job.
	 */
	public function execute(): void
	{
		User::$me->is_guest = true;

		// Send them to the done-with-registration-login screen.
		Theme::loadTemplate('Register');

		Utils::$context['page_title'] = Lang::$txt['profile'];
		Utils::$context['sub_template'] = 'after';
		Utils::$context['title'] = Lang::$txt['activate_changed_email_title'];
		Utils::$context['description'] = Lang::$txt['activate_changed_email_desc'];

		// Aaand we're gone!
		Utils::obExit();
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
	}
}

?>