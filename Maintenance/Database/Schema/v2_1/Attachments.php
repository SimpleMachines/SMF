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
			'id_attach' => new Column(
				name: 'id_attach',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			'id_thumb' => new Column(
				name: 'id_thumb',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_folder' => new Column(
				name: 'id_folder',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'attachment_type' => new Column(
				name: 'attachment_type',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'filename' => new Column(
				name: 'filename',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'file_hash' => new Column(
				name: 'file_hash',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			'fileext' => new Column(
				name: 'fileext',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: '',
			),
			'size' => new Column(
				name: 'size',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'downloads' => new Column(
				name: 'downloads',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'width' => new Column(
				name: 'width',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'height' => new Column(
				name: 'height',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'mime_type' => new Column(
				name: 'mime_type',
				type: 'varchar',
				size: 128,
				not_null: true,
				default: '',
			),
			'approved' => new Column(
				name: 'approved',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_attach',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					'id_member',
					'id_attach',
				],
			),
			'idx_id_msg' => new DbIndex(
				name: 'idx_id_msg',
				columns: [
					'id_msg',
				],
			),
			'idx_attachment_type' => new DbIndex(
				name: 'idx_attachment_type',
				columns: [
					'attachment_type',
				],
			),
			'idx_id_thumb' => new DbIndex(
				name: 'idx_id_thumb',
				columns: [
					'id_thumb',
				],
			),
		];
	}
}

?>