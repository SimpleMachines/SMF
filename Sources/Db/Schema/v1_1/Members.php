<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v1_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Members extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'members';

		$this->columns = [
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'memberName' => new Column(
				name: 'memberName',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'dateRegistered' => new Column(
				name: 'dateRegistered',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'posts' => new Column(
				name: 'posts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_GROUP' => new Column(
				name: 'ID_GROUP',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'lngfile' => new Column(
				name: 'lngfile',
				type: 'tinytext',
				not_null: true,
			),
			'lastLogin' => new Column(
				name: 'lastLogin',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'realName' => new Column(
				name: 'realName',
				type: 'tinytext',
				not_null: true,
			),
			'instantMessages' => new Column(
				name: 'instantMessages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'unreadMessages' => new Column(
				name: 'unreadMessages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'buddy_list' => new Column(
				name: 'buddy_list',
				type: 'text',
				not_null: true,
			),
			'pm_ignore_list' => new Column(
				name: 'pm_ignore_list',
				type: 'tinytext',
				not_null: true,
			),
			'messageLabels' => new Column(
				name: 'messageLabels',
				type: 'text',
				not_null: true,
			),
			'passwd' => new Column(
				name: 'passwd',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'emailAddress' => new Column(
				name: 'emailAddress',
				type: 'tinytext',
				not_null: true,
			),
			'personalText' => new Column(
				name: 'personalText',
				type: 'tinytext',
				not_null: true,
			),
			'gender' => new Column(
				name: 'gender',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'birthdate' => new Column(
				name: 'birthdate',
				type: 'date',
				not_null: true,
				default: '0001-01-01',
			),
			'websiteTitle' => new Column(
				name: 'websiteTitle',
				type: 'tinytext',
				not_null: true,
			),
			'websiteUrl' => new Column(
				name: 'websiteUrl',
				type: 'tinytext',
				not_null: true,
			),
			'location' => new Column(
				name: 'location',
				type: 'tinytext',
				not_null: true,
			),
			'ICQ' => new Column(
				name: 'ICQ',
				type: 'tinytext',
				not_null: true,
			),
			'AIM' => new Column(
				name: 'AIM',
				type: 'varchar',
				size: 16,
				not_null: true,
				default: '',
			),
			'YIM' => new Column(
				name: 'YIM',
				type: 'varchar',
				size: 32,
				not_null: true,
				default: '',
			),
			'MSN' => new Column(
				name: 'MSN',
				type: 'tinytext',
				not_null: true,
			),
			'hideEmail' => new Column(
				name: 'hideEmail',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'showOnline' => new Column(
				name: 'showOnline',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'timeFormat' => new Column(
				name: 'timeFormat',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'signature' => new Column(
				name: 'signature',
				type: 'text',
				not_null: true,
			),
			'timeOffset' => new Column(
				name: 'timeOffset',
				type: 'float',
				not_null: true,
				default: 0,
			),
			'avatar' => new Column(
				name: 'avatar',
				type: 'tinytext',
				not_null: true,
			),
			'pm_email_notify' => new Column(
				name: 'pm_email_notify',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'karmaBad' => new Column(
				name: 'karmaBad',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'karmaGood' => new Column(
				name: 'karmaGood',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'usertitle' => new Column(
				name: 'usertitle',
				type: 'tinytext',
				not_null: true,
			),
			'notifyAnnouncements' => new Column(
				name: 'notifyAnnouncements',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'notifyOnce' => new Column(
				name: 'notifyOnce',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'notifySendBody' => new Column(
				name: 'notifySendBody',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'notifyTypes' => new Column(
				name: 'notifyTypes',
				type: 'tinyint',
				not_null: true,
				default: 2,
			),
			'memberIP' => new Column(
				name: 'memberIP',
				type: 'tinytext',
				not_null: true,
			),
			'memberIP2' => new Column(
				name: 'memberIP2',
				type: 'tinytext',
				not_null: true,
			),
			'secretQuestion' => new Column(
				name: 'secretQuestion',
				type: 'tinytext',
				not_null: true,
			),
			'secretAnswer' => new Column(
				name: 'secretAnswer',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'ID_THEME' => new Column(
				name: 'ID_THEME',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_activated' => new Column(
				name: 'is_activated',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'validation_code' => new Column(
				name: 'validation_code',
				type: 'varchar',
				size: 10,
				not_null: true,
				default: '',
			),
			'ID_MSG_LAST_VISIT' => new Column(
				name: 'ID_MSG_LAST_VISIT',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'additionalGroups' => new Column(
				name: 'additionalGroups',
				type: 'tinytext',
				not_null: true,
			),
			'smileySet' => new Column(
				name: 'smileySet',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			'ID_POST_GROUP' => new Column(
				name: 'ID_POST_GROUP',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'totalTimeLoggedIn' => new Column(
				name: 'totalTimeLoggedIn',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'passwordSalt' => new Column(
				name: 'passwordSalt',
				type: 'varchar',
				size: 5,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
				],
			),
			'memberName' => new DbIndex(
				name: 'memberName',
				columns: [
					[
						'name' => 'memberName',
						'size' => 30,
					],
				],
			),
			'dateRegistered' => new DbIndex(
				name: 'dateRegistered',
				columns: [
					[
						'name' => 'dateRegistered',
					],
				],
			),
			'ID_GROUP' => new DbIndex(
				name: 'ID_GROUP',
				columns: [
					[
						'name' => 'ID_GROUP',
					],
				],
			),
			'birthdate' => new DbIndex(
				name: 'birthdate',
				columns: [
					[
						'name' => 'birthdate',
					],
				],
			),
			'posts' => new DbIndex(
				name: 'posts',
				columns: [
					[
						'name' => 'posts',
					],
				],
			),
			'lastLogin' => new DbIndex(
				name: 'lastLogin',
				columns: [
					[
						'name' => 'lastLogin',
					],
				],
			),
			'lngfile' => new DbIndex(
				name: 'lngfile',
				columns: [
					[
						'name' => 'lngfile',
						'size' => 30,
					],
				],
			),
			'ID_POST_GROUP' => new DbIndex(
				name: 'ID_POST_GROUP',
				columns: [
					[
						'name' => 'ID_POST_GROUP',
					],
				],
			),
		];

		parent::__construct();
	}
}
