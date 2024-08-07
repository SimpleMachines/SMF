<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class CalendarUpdates extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update holidays';

	private array $holidays = [
		['New Year\'s', '1004-01-01'],
		['Christmas', '1004-12-25'],
		['Valentine\'s Day', '1004-02-14'],
		['St. Patrick\'s Day', '1004-03-17'],
		['April Fools', '1004-04-01'],
		['Earth Day', '1004-04-22'],
		['United Nations Day', '1004-10-24'],
		['Halloween', '1004-10-31'],
		['Mother\'s Day', '2010-05-09'],
		['Mother\'s Day', '2011-05-08'],
		['Mother\'s Day', '2012-05-13'],
		['Mother\'s Day', '2013-05-12'],
		['Mother\'s Day', '2014-05-11'],
		['Mother\'s Day', '2015-05-10'],
		['Mother\'s Day', '2016-05-08'],
		['Mother\'s Day', '2017-05-14'],
		['Mother\'s Day', '2018-05-13'],
		['Mother\'s Day', '2019-05-12'],
		['Mother\'s Day', '2020-05-10'],
		['Mother\'s Day', '2021-05-09'],
		['Mother\'s Day', '2022-05-08'],
		['Mother\'s Day', '2023-05-14'],
		['Mother\'s Day', '2024-05-12'],
		['Mother\'s Day', '2025-05-11'],
		['Mother\'s Day', '2026-05-10'],
		['Mother\'s Day', '2027-05-09'],
		['Mother\'s Day', '2028-05-14'],
		['Mother\'s Day', '2029-05-13'],
		['Mother\'s Day', '2030-05-12'],
		['Father\'s Day', '2010-06-20'],
		['Father\'s Day', '2011-06-19'],
		['Father\'s Day', '2012-06-17'],
		['Father\'s Day', '2013-06-16'],
		['Father\'s Day', '2014-06-15'],
		['Father\'s Day', '2015-06-21'],
		['Father\'s Day', '2016-06-19'],
		['Father\'s Day', '2017-06-18'],
		['Father\'s Day', '2018-06-17'],
		['Father\'s Day', '2019-06-16'],
		['Father\'s Day', '2020-06-21'],
		['Father\'s Day', '2021-06-20'],
		['Father\'s Day', '2022-06-19'],
		['Father\'s Day', '2023-06-18'],
		['Father\'s Day', '2024-06-16'],
		['Father\'s Day', '2025-06-15'],
		['Father\'s Day', '2026-06-21'],
		['Father\'s Day', '2027-06-20'],
		['Father\'s Day', '2028-06-18'],
		['Father\'s Day', '2029-06-17'],
		['Father\'s Day', '2030-06-16'],
		['Summer Solstice', '2010-06-21'],
		['Summer Solstice', '2011-06-21'],
		['Summer Solstice', '2012-06-20'],
		['Summer Solstice', '2013-06-21'],
		['Summer Solstice', '2014-06-21'],
		['Summer Solstice', '2015-06-21'],
		['Summer Solstice', '2016-06-20'],
		['Summer Solstice', '2017-06-20'],
		['Summer Solstice', '2018-06-21'],
		['Summer Solstice', '2019-06-21'],
		['Summer Solstice', '2020-06-20'],
		['Summer Solstice', '2021-06-21'],
		['Summer Solstice', '2022-06-21'],
		['Summer Solstice', '2023-06-21'],
		['Summer Solstice', '2024-06-20'],
		['Summer Solstice', '2025-06-21'],
		['Summer Solstice', '2026-06-21'],
		['Summer Solstice', '2027-06-21'],
		['Summer Solstice', '2028-06-20'],
		['Summer Solstice', '2029-06-21'],
		['Summer Solstice', '2030-06-21'],
		['Vernal Equinox', '2010-03-20'],
		['Vernal Equinox', '2011-03-20'],
		['Vernal Equinox', '2012-03-20'],
		['Vernal Equinox', '2013-03-20'],
		['Vernal Equinox', '2014-03-20'],
		['Vernal Equinox', '2015-03-20'],
		['Vernal Equinox', '2016-03-20'],
		['Vernal Equinox', '2017-03-20'],
		['Vernal Equinox', '2018-03-20'],
		['Vernal Equinox', '2019-03-20'],
		['Vernal Equinox', '2020-03-20'],
		['Vernal Equinox', '2021-03-20'],
		['Vernal Equinox', '2022-03-20'],
		['Vernal Equinox', '2023-03-20'],
		['Vernal Equinox', '2024-03-20'],
		['Vernal Equinox', '2025-03-20'],
		['Vernal Equinox', '2026-03-20'],
		['Vernal Equinox', '2027-03-20'],
		['Vernal Equinox', '2028-03-20'],
		['Vernal Equinox', '2029-03-20'],
		['Vernal Equinox', '2030-03-20'],
		['Winter Solstice', '2010-12-21'],
		['Winter Solstice', '2011-12-22'],
		['Winter Solstice', '2012-12-21'],
		['Winter Solstice', '2013-12-21'],
		['Winter Solstice', '2014-12-21'],
		['Winter Solstice', '2015-12-22'],
		['Winter Solstice', '2016-12-21'],
		['Winter Solstice', '2017-12-21'],
		['Winter Solstice', '2018-12-21'],
		['Winter Solstice', '2019-12-22'],
		['Winter Solstice', '2020-12-21'],
		['Winter Solstice', '2021-12-21'],
		['Winter Solstice', '2022-12-21'],
		['Winter Solstice', '2023-12-22'],
		['Winter Solstice', '2024-12-21'],
		['Winter Solstice', '2025-12-21'],
		['Winter Solstice', '2026-12-21'],
		['Winter Solstice', '2027-12-22'],
		['Winter Solstice', '2028-12-21'],
		['Winter Solstice', '2029-12-21'],
		['Winter Solstice', '2030-12-21'],
		['Autumnal Equinox', '2010-09-23'],
		['Autumnal Equinox', '2011-09-23'],
		['Autumnal Equinox', '2012-09-22'],
		['Autumnal Equinox', '2013-09-22'],
		['Autumnal Equinox', '2014-09-23'],
		['Autumnal Equinox', '2015-09-23'],
		['Autumnal Equinox', '2016-09-22'],
		['Autumnal Equinox', '2017-09-22'],
		['Autumnal Equinox', '2018-09-23'],
		['Autumnal Equinox', '2019-09-23'],
		['Autumnal Equinox', '2020-09-22'],
		['Autumnal Equinox', '2021-09-22'],
		['Autumnal Equinox', '2022-09-23'],
		['Autumnal Equinox', '2023-09-23'],
		['Autumnal Equinox', '2024-09-22'],
		['Autumnal Equinox', '2025-09-22'],
		['Autumnal Equinox', '2026-09-23'],
		['Autumnal Equinox', '2027-09-23'],
		['Autumnal Equinox', '2028-09-22'],
		['Autumnal Equinox', '2029-09-22'],
		['Autumnal Equinox', '2030-09-22'],
		['Independence Day', '1004-07-04'],
		['Cinco de Mayo', '1004-05-05'],
		['Flag Day', '1004-06-14'],
		['Veterans Day', '1004-11-11'],
		['Groundhog Day', '1004-02-02'],
		['Thanksgiving', '2010-11-25'],
		['Thanksgiving', '2011-11-24'],
		['Thanksgiving', '2012-11-22'],
		['Thanksgiving', '2013-11-28'],
		['Thanksgiving', '2014-11-27'],
		['Thanksgiving', '2015-11-26'],
		['Thanksgiving', '2016-11-24'],
		['Thanksgiving', '2017-11-23'],
		['Thanksgiving', '2018-11-22'],
		['Thanksgiving', '2019-11-28'],
		['Thanksgiving', '2020-11-26'],
		['Thanksgiving', '2021-11-25'],
		['Thanksgiving', '2022-11-24'],
		['Thanksgiving', '2023-11-23'],
		['Thanksgiving', '2024-11-28'],
		['Thanksgiving', '2025-11-27'],
		['Thanksgiving', '2026-11-26'],
		['Thanksgiving', '2027-11-25'],
		['Thanksgiving', '2028-11-23'],
		['Thanksgiving', '2029-11-22'],
		['Thanksgiving', '2030-11-28'],
		['Memorial Day', '2010-05-31'],
		['Memorial Day', '2011-05-30'],
		['Memorial Day', '2012-05-28'],
		['Memorial Day', '2013-05-27'],
		['Memorial Day', '2014-05-26'],
		['Memorial Day', '2015-05-25'],
		['Memorial Day', '2016-05-30'],
		['Memorial Day', '2017-05-29'],
		['Memorial Day', '2018-05-28'],
		['Memorial Day', '2019-05-27'],
		['Memorial Day', '2020-05-25'],
		['Memorial Day', '2021-05-31'],
		['Memorial Day', '2022-05-30'],
		['Memorial Day', '2023-05-29'],
		['Memorial Day', '2024-05-27'],
		['Memorial Day', '2025-05-26'],
		['Memorial Day', '2026-05-25'],
		['Memorial Day', '2027-05-31'],
		['Memorial Day', '2028-05-29'],
		['Memorial Day', '2029-05-28'],
		['Memorial Day', '2030-05-27'],
		['Labor Day', '2010-09-06'],
		['Labor Day', '2011-09-05'],
		['Labor Day', '2012-09-03'],
		['Labor Day', '2013-09-02'],
		['Labor Day', '2014-09-01'],
		['Labor Day', '2015-09-07'],
		['Labor Day', '2016-09-05'],
		['Labor Day', '2017-09-04'],
		['Labor Day', '2018-09-03'],
		['Labor Day', '2019-09-02'],
		['Labor Day', '2020-09-07'],
		['Labor Day', '2021-09-06'],
		['Labor Day', '2022-09-05'],
		['Labor Day', '2023-09-04'],
		['Labor Day', '2024-09-02'],
		['Labor Day', '2025-09-01'],
		['Labor Day', '2026-09-07'],
		['Labor Day', '2027-09-06'],
		['Labor Day', '2028-09-04'],
		['Labor Day', '2029-09-03'],
		['Labor Day', '2030-09-02'],
		['D-Day', '1004-06-06'],
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
		$this->query(
			'',
			'
			DELETE FROM {db_prefix}calendar_holidays WHERE title in ({array_string:titles})
		',
			[
				'titles' => array_unique(array_keys($this->holidays)),
			],
		);

		Db::$db->insert(
			'ignore',
			'{db_prefix}calendar_holidays',
			['event_date' => 'date', 'title' => 'string-60'],
			$this->holidays,
			['id_holiday'],
		);

		return true;
	}
}

?>