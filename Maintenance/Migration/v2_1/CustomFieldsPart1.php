<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class CustomFieldsPart1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Upgrade Custom Fields (Preparing)';

	private array $default_fields = [
		[
			'cust_icq',
			'{icq}',
			'{icq_desc}',
			'text',
			12,
			'',
			1,
			'regex~[1-9][0-9]{4,9}~i',
			0,
			1,
			0,
			'forumprofile',
			0,
			1,
			0,
			0,
			'',
			'<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" rel="noopener" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>',
			1,
		],
		[
			'cust_skype',
			'{skype}',
			'{skype_desc}',
			'text',
			32,
			'',
			2,
			'nohtml',
			0,
			1,
			0,
			'forumprofile',
			0,
			1,
			0,
			0,
			'',
			'<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ',
			1,
		],
		[
			'cust_loca',
			'{location}',
			'{location_desc}',
			'text',
			50,
			'',
			4,
			'nohtml',
			0,
			1,
			0,
			'forumprofile',
			0,
			1,
			0,
			0,
			'',
			'',
			0,
		],
		[
			'cust_gender',
			'{gender}',
			'{gender_desc}',
			'radio',
			255,
			'{gender_0},{gender_1},{gender_2}',
			5,
			'nohtml',
			1,
			1,
			0,
			'forumprofile',
			0,
			1,
			0,
			0,
			'{gender_0}',
			'<span class=" main_icons gender_{KEY}" title="{INPUT}"></span>',
			1,
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		if ($start <= 0) {
			$table = new \SMF\Maintenance\Database\Schema\v2_1\CustomFields();
			$existing_structure = $table->getCurrentStructure();

			foreach ($table->columns as $column) {
				// Add the columns.
				if (
					(
						$column->name === 'field_order'
						|| $column->name === 'show_mlist'
					)
					&& !isset($existing_structure['columns'][$column->name])
				) {
					$table->addColumn($column);
					continue;
				}
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}' . $table->name,
				[
					'col_name' => 'string',
					'field_name' => 'string',
					'field_desc' => 'string',
					'field_type' => 'string',
					'field_length' => 'int',
					'field_options' => 'string',
					'field_order' => 'int',
					'mask' => 'string',
					'show_reg' => 'int',
					'show_display' => 'int',
					'show_mlist' => 'int',
					'show_profile' => 'int',
					'private' => 'int',
					'active' => 'int',
					'bbc' => 'int',
					'can_search' => 'int',
					'default_value' => 'string',
					'enclose' => 'string',
					'placement' => 'int',
				],
				$this->default_fields,
				['id_theme', 'alert_pref'],
			);

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			// Add an order value to each existing cust profile field.
			$ocf = $this->query('', '
				SELECT id_field
				FROM {db_prefix}custom_fields
				WHERE field_order = 0');

			// We start counting from 5 because we already have the first 5 fields.
			$fields_count = 5;

			while ($row = Db::$db->fetch_assoc($ocf)) {
				++$fields_count;

				$this->query(
					'',
					'UPDATE {db_prefix}custom_fields
					SET field_order = {int:field_count}
					WHERE id_field = {int:id_field}',
					[
						'field_count' => $fields_count,
						'id_field' => $row['id_field'],
					],
				);
			}
			Db::$db->free_result($ocf);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>