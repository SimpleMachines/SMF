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
class CalendarHolidays extends Table
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
			'title' => 'New Year\'s',
			'event_date' => '1004-01-01',
		],
		[
			'title' => 'Christmas',
			'event_date' => '1004-12-25',
		],
		[
			'title' => 'Valentine\'s Day',
			'event_date' => '1004-02-14',
		],
		[
			'title' => 'St. Patrick\'s Day',
			'event_date' => '1004-03-17',
		],
		[
			'title' => 'April Fools',
			'event_date' => '1004-04-01',
		],
		[
			'title' => 'Earth Day',
			'event_date' => '1004-04-22',
		],
		[
			'title' => 'United Nations Day',
			'event_date' => '1004-10-24',
		],
		[
			'title' => 'Halloween',
			'event_date' => '1004-10-31',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2010-05-09',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2011-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2012-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2013-05-12',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2014-05-11',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2015-05-10',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2016-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2017-05-14',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2018-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2019-05-12',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2020-05-10',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2021-05-09',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2022-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2023-05-14',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2024-05-12',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2025-05-11',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2026-05-10',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2027-05-09',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2028-05-14',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2029-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'event_date' => '2030-05-12',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2010-06-20',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2011-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2012-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2013-06-16',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2014-06-15',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2015-06-21',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2016-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2017-06-18',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2018-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2019-06-16',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2020-06-21',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2021-06-20',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2022-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2023-06-18',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2024-06-16',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2025-06-15',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2026-06-21',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2027-06-20',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2028-06-18',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2029-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'event_date' => '2030-06-16',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2010-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2011-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2012-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2013-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2014-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2015-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2016-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2017-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2018-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2019-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2020-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2021-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2022-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2023-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2024-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2025-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2026-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2027-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2028-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2029-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'event_date' => '2030-06-21',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2010-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2011-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2012-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2013-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2014-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2015-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2016-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2017-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2018-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2019-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2020-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2021-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2022-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2023-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2024-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2025-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2026-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2027-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2028-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2029-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'event_date' => '2030-03-20',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2010-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2011-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2012-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2013-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2014-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2015-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2016-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2017-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2018-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2019-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2020-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2021-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2022-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2023-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2024-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2025-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2026-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2027-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2028-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2029-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'event_date' => '2030-12-21',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2010-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2011-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2012-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2013-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2014-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2015-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2016-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2017-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2018-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2019-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2020-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2021-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2022-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2023-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2024-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2025-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2026-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2027-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2028-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2029-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'event_date' => '2030-09-22',
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
		$this->name = 'calendar_holidays';

		$this->columns = [
			'id_holiday' => new Column(
				name: 'id_holiday',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			'event_date' => new Column(
				name: 'event_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_holiday',
				],
			),
			'idx_event_date' => new DbIndex(
				name: 'idx_event_date',
				columns: [
					'event_date',
				],
			),
		];
	}
}

?>