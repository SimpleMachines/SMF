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
			'eventDate' => '0004-01-01',
		],
		[
			'title' => 'Christmas',
			'eventDate' => '0004-12-25',
		],
		[
			'title' => 'Valentine\'s Day',
			'eventDate' => '0004-02-14',
		],
		[
			'title' => 'St. Patrick\'s Day',
			'eventDate' => '0004-03-17',
		],
		[
			'title' => 'April Fools',
			'eventDate' => '0004-04-01',
		],
		[
			'title' => 'Earth Day',
			'eventDate' => '0004-04-22',
		],
		[
			'title' => 'United Nations Day',
			'eventDate' => '0004-10-24',
		],
		[
			'title' => 'Halloween',
			'eventDate' => '0004-10-31',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2004-05-09',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2005-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2006-05-14',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2007-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2008-05-11',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2009-05-10',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2010-05-09',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2011-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2012-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2013-05-12',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2014-05-11',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2015-05-10',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2016-05-08',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2017-05-14',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2018-05-13',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2019-05-12',
		],
		[
			'title' => 'Mother\'s Day',
			'eventDate' => '2020-05-10',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2004-06-20',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2005-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2006-06-18',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2007-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2008-06-15',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2009-06-21',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2010-06-20',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2011-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2012-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2013-06-16',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2014-06-15',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2015-06-21',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2016-06-19',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2017-06-18',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2018-06-17',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2019-06-16',
		],
		[
			'title' => 'Father\'s Day',
			'eventDate' => '2020-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2004-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2005-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2006-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2007-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2008-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2009-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2010-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2011-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2012-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2013-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2014-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2015-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2016-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2017-06-20',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2018-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2019-06-21',
		],
		[
			'title' => 'Summer Solstice',
			'eventDate' => '2020-06-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2004-03-19',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2005-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2006-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2007-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2008-03-19',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2009-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2010-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2011-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2012-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2013-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2014-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2015-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2016-03-19',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2017-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2018-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2019-03-20',
		],
		[
			'title' => 'Vernal Equinox',
			'eventDate' => '2020-03-19',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2004-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2005-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2006-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2007-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2008-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2009-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2010-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2011-12-22',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2012-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2013-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2014-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2015-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2016-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2017-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2018-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2019-12-21',
		],
		[
			'title' => 'Winter Solstice',
			'eventDate' => '2020-12-21',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2004-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2005-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2006-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2007-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2008-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2009-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2010-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2011-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2012-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2013-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2014-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2015-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2016-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2017-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2018-09-22',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2019-09-23',
		],
		[
			'title' => 'Autumnal Equinox',
			'eventDate' => '2020-09-22',
		],
		[
			'title' => 'Independence Day',
			'eventDate' => '0004-07-04',
		],
		[
			'title' => 'Cinco de Mayo',
			'eventDate' => '0004-05-05',
		],
		[
			'title' => 'Flag Day',
			'eventDate' => '0004-06-14',
		],
		[
			'title' => 'Veterans Day',
			'eventDate' => '0004-11-11',
		],
		[
			'title' => 'Groundhog Day',
			'eventDate' => '0004-02-02',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2004-11-25',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2005-11-24',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2006-11-23',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2007-11-22',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2008-11-27',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2009-11-26',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2010-11-25',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2011-11-24',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2012-11-22',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2013-11-21',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2014-11-20',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2015-11-26',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2016-11-24',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2017-11-23',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2018-11-22',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2019-11-21',
		],
		[
			'title' => 'Thanksgiving',
			'eventDate' => '2020-11-26',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2004-05-31',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2005-05-30',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2006-05-29',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2007-05-28',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2008-05-26',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2009-05-25',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2010-05-31',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2011-05-30',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2012-05-28',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2013-05-27',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2014-05-26',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2015-05-25',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2016-05-30',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2017-05-29',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2018-05-28',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2019-05-27',
		],
		[
			'title' => 'Memorial Day',
			'eventDate' => '2020-05-25',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2004-09-06',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2005-09-05',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2006-09-04',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2007-09-03',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2008-09-01',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2009-09-07',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2010-09-06',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2011-09-05',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2012-09-03',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2013-09-09',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2014-09-08',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2015-09-07',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2016-09-05',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2017-09-04',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2018-09-03',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2019-09-09',
		],
		[
			'title' => 'Labor Day',
			'eventDate' => '2020-09-07',
		],
		[
			'title' => 'D-Day',
			'eventDate' => '0004-06-06',
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
			'ID_HOLIDAY' => new Column(
				name: 'ID_HOLIDAY',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'eventDate' => new Column(
				name: 'eventDate',
				type: 'date',
				not_null: true,
				default: '0001-01-01',
			),
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_HOLIDAY',
					],
				],
			),
			'eventDate' => new DbIndex(
				name: 'eventDate',
				columns: [
					[
						'name' => 'eventDate',
					],
				],
			),
		];

		parent::__construct();
	}
}
