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
			'ID_THEME' => 1,
			'variable' => 'name',
			'value' => '{$default_theme_name}',
		],
		[
			'ID_THEME' => 1,
			'variable' => 'theme_url',
			'value' => '{$boardurl}/Themes/default',
		],
		[
			'ID_THEME' => 1,
			'variable' => 'images_url',
			'value' => '{$boardurl}/Themes/default/images',
		],
		[
			'ID_THEME' => 1,
			'variable' => 'theme_dir',
			'value' => '{$boarddir}/Themes/default',
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_bbc',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_latest_member',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_modify',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_user_images',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_blurb',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_gender',
			'value' => 0,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_newsfader',
			'value' => 0,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'number_recent_posts',
			'value' => 0,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_member_bar',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'linktree_link',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_profile_buttons',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_mark_read',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_sp1_info',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'linktree_inline',
			'value' => 0,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'show_board_desc',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'newsfader_time',
			'value' => 5000,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'allow_no_censored',
			'value' => 0,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'additional_options_collapsable',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'use_image_buttons',
			'value' => 1,
		],
		[
			'ID_THEME' => 1,
			'variable' => 'enable_news',
			'value' => 1,
		],
		[
			'ID_THEME' => 2,
			'variable' => 'name',
			'value' => '{$default_classic_theme_name}',
		],
		[
			'ID_THEME' => 2,
			'variable' => 'theme_url',
			'value' => '{$boardurl}/Themes/classic',
		],
		[
			'ID_THEME' => 2,
			'variable' => 'images_url',
			'value' => '{$boardurl}/Themes/classic/images',
		],
		[
			'ID_THEME' => 2,
			'variable' => 'theme_dir',
			'value' => '{$boarddir}/Themes/classic',
		],
		[
			'ID_THEME' => 3,
			'variable' => 'name',
			'value' => '{$default_babylon_theme_name}',
		],
		[
			'ID_THEME' => 3,
			'variable' => 'theme_url',
			'value' => '{$boardurl}/Themes/babylon',
		],
		[
			'ID_THEME' => 3,
			'variable' => 'images_url',
			'value' => '{$boardurl}/Themes/babylon/images',
		],
		[
			'ID_THEME' => 3,
			'variable' => 'theme_dir',
			'value' => '{$boarddir}/Themes/babylon',
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
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'ID_THEME' => new Column(
				name: 'ID_THEME',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'variable' => new Column(
				name: 'variable',
				type: 'tinytext',
				not_null: true,
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
					[
						'name' => 'ID_THEME',
					],
					[
						'name' => 'ID_MEMBER',
					],
					[
						'name' => 'variable',
						'size' => 30,
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
				],
			),
		];

		parent::__construct();
	}
}
