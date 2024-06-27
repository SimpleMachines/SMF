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

use SMF\Board;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Utils;

/**
 * Toggles email notification preferences for boards.
 */
class NotifyBoard extends Notify
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The notification type that this action handles.
	 */
	public string $type = 'board';

	/******************
	 * Internal methods
	 ******************/

	/**
	 * For board and topic, make sure we have the necessary ID.
	 */
	protected function setId(): void
	{
		if (empty(Board::$info->id)) {
			ErrorHandler::fatalLang('no_board', false);
		}

		$this->id = Board::$info->id;
	}

	/**
	 * Converts $_GET['sa'] to $_GET['mode'].
	 *
	 * sa=on/off is used for email subscribe/unsubscribe links.
	 */
	protected function saToMode(): void
	{
		if (!isset($_GET['mode']) && isset($_GET['sa'])) {
			$_GET['mode'] = $_GET['sa'] == 'on' ? 3 : -1;
			unset($_GET['sa']);
		}
	}

	/**
	 * Sets any additional data needed for the ask template.
	 */
	protected function askTemplateData(): void
	{
		Utils::$context['sub_template'] = 'notify_board';

		Utils::$context[$this->type . '_href'] = Config::$scripturl . '?' . $this->type . '=' . $this->id . '.' . ($_REQUEST['start'] ?? 0);
		Utils::$context['start'] = $_REQUEST['start'] ?? 0;
	}

	/**
	 * Updates the notification preference in the database.
	 */
	protected function changePref(): void
	{
		$this->setAlertPref();
		$this->changeBoardTopicPref();
	}

	/**
	 * Gets the success message to display.
	 */
	protected function getSuccessMsg(): string
	{
		return Lang::getTxt('notify_board' . (!empty($this->alert_pref & parent::PREF_EMAIL) ? '_subscribed' : '_unsubscribed'), self::$member_info);
	}
}

?>