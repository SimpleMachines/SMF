<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class MailQueue extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'mail_queue';

		$this->columns = [
			'id_mail' => new Column(
				name: 'id_mail',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'time_sent' => new Column(
				name: 'time_sent',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'recipient' => new Column(
				name: 'recipient',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'body' => new Column(
				name: 'body',
				type: 'mediumtext',
				not_null: true,
			),
			'subject' => new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'headers' => new Column(
				name: 'headers',
				type: 'text',
				not_null: true,
			),
			'send_html' => new Column(
				name: 'send_html',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'priority' => new Column(
				name: 'priority',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'private' => new Column(
				name: 'private',
				type: 'tinyint',
				size: 1,
				not_null: true,
				default: 0,
			),
			'next_try' => new Column(
				name: 'next_try',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'tries' => new Column(
				name: 'tries',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'extra' => new Column(
				name: 'extra',
				type: 'varchar',
				size: 255,
				not_null: false,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_mail',
					],
				],
			),
			'idx_time_sent' => new DbIndex(
				name: 'idx_time_sent',
				columns: [
					[
						'name' => 'time_sent',
					],
				],
			),
			'idx_mail_priority' => new DbIndex(
				name: 'idx_mail_priority',
				columns: [
					[
						'name' => 'priority',
					],
					[
						'name' => 'id_mail',
					],
				],
			),
		];

		parent::__construct();
	}
}
