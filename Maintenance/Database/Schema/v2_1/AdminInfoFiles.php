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

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class AdminInfoFiles extends Table
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
			'id_file' => 1,
			'filename' => 'current-version.js',
			'path' => '/smf/',
			'parameters' => 'version=%3$s',
			'data' => '',
			'filetype' => 'text/javascript',
		],
		[
			'id_file' => 2,
			'filename' => 'detailed-version.js',
			'path' => '/smf/',
			'parameters' => 'language=%1$s&version=%3$s',
			'data' => '',
			'filetype' => 'text/javascript',
		],
		[
			'id_file' => 3,
			'filename' => 'latest-news.js',
			'path' => '/smf/',
			'parameters' => 'language=%1$s&format=%2$s',
			'data' => '',
			'filetype' => 'text/javascript',
		],
		[
			'id_file' => 4,
			'filename' => 'latest-versions.txt',
			'path' => '/smf/',
			'parameters' => 'version=%3$s',
			'data' => '',
			'filetype' => 'text/plain',
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
		$this->name = 'admin_info_files';

		$this->columns = [
			new Column(
				name: 'id_file',
				type: 'tinyint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'filename',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'path',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'parameters',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'data',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'filetype',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_file',
				],
			),
			new DbIndex(
				name: 'idx_filename',
				columns: [
					'filename(30)',
				],
			),
		];
	}
}

?>