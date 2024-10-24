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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Themes extends Table
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
			'id_theme' => 1,
			'variable' => 'name',
			'value' => '{$default_theme_name}',
		],
		[
			'id_theme' => 1,
			'variable' => 'theme_url',
			'value' => '{$boardurl}/Themes/default',
		],
		[
			'id_theme' => 1,
			'variable' => 'images_url',
			'value' => '{$boardurl}/Themes/default/images',
		],
		[
			'id_theme' => 1,
			'variable' => 'theme_dir',
			'value' => '{$boarddir}/Themes/default',
		],
		[
			'id_theme' => 1,
			'variable' => 'show_latest_member',
			'value' => '1',
		],
		[
			'id_theme' => 1,
			'variable' => 'show_newsfader',
			'value' => '0',
		],
		[
			'id_theme' => 1,
			'variable' => 'number_recent_posts',
			'value' => '0',
		],
		[
			'id_theme' => 1,
			'variable' => 'show_stats_index',
			'value' => '1',
		],
		[
			'id_theme' => 1,
			'variable' => 'newsfader_time',
			'value' => '3000',
		],
		[
			'id_theme' => 1,
			'variable' => 'use_image_buttons',
			'value' => '1',
		],
		[
			'id_theme' => 1,
			'variable' => 'enable_news',
			'value' => '1',
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
		$this->name = 'themes';

		$this->columns = [
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				default: 0,
			),
			'id_theme' => new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				default: 1,
			),
			'variable' => new Column(
				name: 'variable',
				type: 'varchar',
				size: 255,
				default: '',
			),
			'value' => new Column(
				name: 'value',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_theme',
					'id_member',
					'variable(30)',
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					'id_member',
				],
			),
		];
	}
}

?>