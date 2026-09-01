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

namespace SMF\Db\Schema\v2_0;

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
			'variable' => 'show_bbc',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_latest_member',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_modify',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_user_images',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_blurb',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_gender',
			'value' => 0,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_newsfader',
			'value' => 0,
		],
		[
			'id_theme' => 1,
			'variable' => 'number_recent_posts',
			'value' => 0,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_member_bar',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'linktree_link',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_profile_buttons',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_mark_read',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_stats_index',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'linktree_inline',
			'value' => 0,
		],
		[
			'id_theme' => 1,
			'variable' => 'show_board_desc',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'newsfader_time',
			'value' => 5000,
		],
		[
			'id_theme' => 1,
			'variable' => 'allow_no_censored',
			'value' => 0,
		],
		[
			'id_theme' => 1,
			'variable' => 'additional_options_collapsable',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'use_image_buttons',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'enable_news',
			'value' => 1,
		],
		[
			'id_theme' => 1,
			'variable' => 'forum_width',
			'value' => '90%',
		],
		[
			'id_theme' => 2,
			'variable' => 'name',
			'value' => '{$default_core_theme_name}',
		],
		[
			'id_theme' => 2,
			'variable' => 'theme_url',
			'value' => '{$boardurl}/Themes/core',
		],
		[
			'id_theme' => 2,
			'variable' => 'images_url',
			'value' => '{$boardurl}/Themes/core/images',
		],
		[
			'id_theme' => 2,
			'variable' => 'theme_dir',
			'value' => '{$boarddir}/Themes/core',
		],
		[
			'id_member' => -1,
			'id_theme' => 1,
			'variable' => 'display_quick_reply',
			'value' => 1,
		],
		[
			'id_member' => -1,
			'id_theme' => 1,
			'variable' => 'posts_apply_ignore_list',
			'value' => 1,
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
				not_null: true,
				default: 0,
			),
			'id_theme' => new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'variable' => new Column(
				name: 'variable',
				type: 'varchar',
				size: 255,
				not_null: true,
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
					[
						'name' => 'id_theme',
					],
					[
						'name' => 'id_member',
					],
					[
						'name' => 'variable',
						'size' => 30,
					],
				],
			),
			'id_member' => new DbIndex(
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
		];

		parent::__construct();
	}
}
