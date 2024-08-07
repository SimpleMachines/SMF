<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class UserAlertsPrefs extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'id_member' => 0,
			'alert_pref' => 'alert_timeout',
			'alert_value' => 10,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'announcements',
			'alert_value' => 0,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'birthday',
			'alert_value' => 2,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'board_notify',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'buddy_request',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'groupr_approved',
			'alert_value' => 3,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'groupr_rejected',
			'alert_value' => 3,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'member_group_request',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'member_register',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'member_report',
			'alert_value' => 3,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'member_report_reply',
			'alert_value' => 3,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_auto_notify',
			'alert_value' => 0,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_like',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_mention',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_notify_pref',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_notify_type',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_quote',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_receive_body',
			'alert_value' => 0,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_report',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'msg_report_reply',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'pm_new',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'pm_notify',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'pm_reply',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'request_group',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'topic_notify',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'unapproved_attachment',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'unapproved_reply',
			'alert_value' => 3,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'unapproved_post',
			'alert_value' => 1,
		],
		[
			'id_member' => 0,
			'alert_pref' => 'warn_any',
			'alert_value' => 1,
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'user_alerts_prefs';

		$this->columns = [
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
			'alert_pref' => new Column(
				name: 'alert_pref',
				type: 'varchar',
				size: 32,
				default: '',
			),
			'alert_value' => new Column(
				name: 'alert_value',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_member',
					'alert_pref',
				],
			),
		];
	}
}

?>