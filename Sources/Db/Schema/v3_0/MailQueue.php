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

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class MailQueue extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

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
			new Column(
				name: 'id_mail',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'time_sent',
				type: 'int',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'recipient',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'body',
				type: 'mediumtext',
				not_null: true,
			),
			new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'headers',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'send_html',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'priority',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'private',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_mail',
				],
			),
			new Indices(
				name: 'idx_time_sent',
				columns: [
					'time_sent',
				],
			),
			new Indices(
				name: 'idx_mail_priority',
				columns: [
					'priority',
					'id_mail',
				],
			),
		];
	}
}

?>