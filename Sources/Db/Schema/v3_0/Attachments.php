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
use SMF\Db\Schema\Index;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Attachments extends Table
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
		$this->name = 'attachments';

		$this->columns = [
			new Column(
				name: 'id_attach',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_thumb',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_folder',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'attachment_type',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'filename',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'file_hash',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'fileext',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'size',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'downloads',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'width',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'height',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'mime_type',
				type: 'varchar',
				size: 128,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'approved',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_attach',
				],
			),
			new Index(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					'id_member',
					'id_attach',
				],
			),
			new Index(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
			new Index(
				name: 'idx_attachment_type',
				columns: [
					'attachment_type',
				],
			),
			new Index(
				name: 'idx_id_thumb',
				columns: [
					'id_thumb',
				],
			),
		];
	}
}

?>