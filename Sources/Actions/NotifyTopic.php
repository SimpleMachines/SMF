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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Topic;
use SMF\Utils;

/**
 * Toggles email notification preferences for topics.
 */
class NotifyTopic extends Notify
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The notification type that this action handles.
	 */
	public string $type = 'topic';

	/******************
	 * Internal methods
	 ******************/

	/**
	 * For board and topic, make sure we have the necessary ID.
	 */
	protected function setId(): void
	{
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		$this->id = Topic::$topic_id;
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
		Utils::$context[$this->type . '_href'] = Config::$scripturl . '?' . $this->type . '=' . $this->id . '.' . ($_REQUEST['start'] ?? 0);
		Utils::$context['start'] = $_REQUEST['start'] ?? 0;
	}

	/**
	 * Updates the notification preference in the database.
	 */
	protected function changePref(): void
	{
		$this->setAlertPref();

		$request = Db::$db->query(
			'',
			'SELECT id_member, id_topic, id_msg, unwatched
			FROM {db_prefix}log_topics
			WHERE id_member = {int:member}
				AND {raw:column} = {int:id}',
			[
				'column' => 'id_' . $this->type,
				'id' => $this->id,
				'member' => self::$member_info['id'],
			],
		);
		$log = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		if (empty($log)) {
			$insert = true;
			$log = [
				'id_member' => self::$member_info['id'],
				'id_topic' => $this->id,
				'id_msg' => 0,
				'unwatched' => (int) ($this->mode === parent::MODE_IGNORE),
			];
		} else {
			$insert = false;
			$log['unwatched'] = (int) ($this->mode === parent::MODE_IGNORE);
		}

		Db::$db->insert(
			$insert ? 'insert' : 'replace',
			'{db_prefix}log_topics',
			[
				'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
			],
			$log,
			['id_member', 'id_topic'],
		);

		$this->changeBoardTopicPref();
	}

	/**
	 * Gets the success message to display.
	 */
	protected function getSuccessMsg(): string
	{
		return Lang::getTxt('notify_topic' . (!empty($this->alert_pref & parent::PREF_EMAIL) ? '_subscribed' : '_unsubscribed'), self::$member_info);
	}
}

?>