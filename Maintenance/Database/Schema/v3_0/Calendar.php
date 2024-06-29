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

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Calendar extends Table
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
			'title' => 'April Fools\' Day',
			'start_date' => '2000-04-01',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Christmas',
			'start_date' => '2000-12-25',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Cinco de Mayo',
			'start_date' => '2000-05-05',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'Mexico, USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => 'FREQ=YEARLY',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'D-Day',
			'start_date' => '2000-06-06',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Easter',
			'start_date' => '2000-04-23',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'EASTER_W',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Earth Day',
			'start_date' => '2000-04-22',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Father\'s Day',
			'start_date' => '2000-06-19',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'Canada, USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=3SU',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Flag Day',
			'start_date' => '2000-06-14',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Good Friday',
			'start_date' => '2000-04-21',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'EASTER_W-P2D',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Groundhog Day',
			'start_date' => '2000-02-02',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'Canada, USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => 'FREQ=YEARLY',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Halloween',
			'start_date' => '2000-10-31',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Independence Day',
			'start_date' => '2000-07-04',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Labor Day',
			'start_date' => '2000-09-03',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1MO',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Labour Day',
			'start_date' => '2000-09-03',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'Canada',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1MO',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Memorial Day',
			'start_date' => '2000-05-31',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=-1MO',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Mother\'s Day',
			'start_date' => '2000-05-08',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'Canada, USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'New Year\'s Day',
			'start_date' => '2000-01-01',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Remembrance Day',
			'start_date' => '2000-11-11',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'St. Patrick\'s Day',
			'start_date' => '2000-03-17',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Thanksgiving',
			'start_date' => '2000-11-26',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=4TH',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'United Nations Day',
			'start_date' => '2000-10-24',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Valentine\'s Day',
			'start_date' => '2000-02-14',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => '',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Veterans Day',
			'start_date' => '2000-11-11',
			'end_date' => '9999-12-31',
			'start_time' => null,
			'timezone' => null,
			'location' => 'USA',
			'duration' => 'P1D',
			'rrule' => 'FREQ=YEARLY',
			'rdates' => '',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Vernal Equinox',
			'start_date' => '2000-03-20',
			'end_date' => '9999-12-31',
			'start_time' => '07:30:00',
			'timezone' => 'UTC',
			'location' => '',
			'duration' => 'PT1M',
			'rrule' => 'FREQ=YEARLY;COUNT=1',
			'rdates' => '20000320T073000Z,20010320T131900Z,20020320T190800Z,20030321T005800Z,20040320T064700Z,20050320T123600Z,20060320T182500Z,20070321T001400Z,20080320T060400Z,20090320T115300Z,20100320T174200Z,20110320T233100Z,20120320T052000Z,20130320T111000Z,20140320T165900Z,20150320T224800Z,20160320T043700Z,20170320T102600Z,20180320T161600Z,20190320T220500Z,20200320T035400Z,20210320T094300Z,20220320T153200Z,20230320T212200Z,20240320T031100Z,20250320T090000Z,20260320T144900Z,20270320T203800Z,20280320T022800Z,20290320T081700Z,20300320T140600Z,20310320T195500Z,20320320T014400Z,20330320T073400Z,20340320T132300Z,20350320T191200Z,20360320T010100Z,20370320T065000Z,20380320T124000Z,20390320T182900Z,20400320T001800Z,20410320T060700Z,20420320T115600Z,20430320T174600Z,20440319T233500Z,20450320T052400Z,20460320T111300Z,20470320T170200Z,20480319T225200Z,20490320T044100Z,20500320T103000Z,20510320T161900Z,20520319T220800Z,20530320T035800Z,20540320T094700Z,20550320T153600Z,20560319T212500Z,20570320T031400Z,20580320T090400Z,20590320T145300Z,20600319T204200Z,20610320T023100Z,20620320T082000Z,20630320T141000Z,20640319T195900Z,20650320T014800Z,20660320T073700Z,20670320T132600Z,20680319T191600Z,20690320T010500Z,20700320T065400Z,20710320T124300Z,20720319T183200Z,20730320T002200Z,20740320T061100Z,20750320T120000Z,20760319T174900Z,20770319T233800Z,20780320T052800Z,20790320T111700Z,20800319T170600Z,20810319T225500Z,20820320T044400Z,20830320T103400Z,20840319T162300Z,20850319T221200Z,20860320T040100Z,20870320T095000Z,20880319T154000Z,20890319T212900Z,20900320T031800Z,20910320T090700Z,20920319T145600Z,20930319T204600Z,20940320T023500Z,20950320T082400Z,20960319T141300Z,20970319T200200Z,20980320T015200Z,20990320T074100Z',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Summer Solstice',
			'start_date' => '2000-06-21',
			'end_date' => '9999-12-31',
			'start_time' => '01:44:00',
			'timezone' => 'UTC',
			'location' => '',
			'duration' => 'PT1M',
			'rrule' => 'FREQ=YEARLY;COUNT=1',
			'rdates' => '20000621T014400Z,20010621T073200Z,20020621T132000Z,20030621T190800Z,20040621T005600Z,20050621T064400Z,20060621T123200Z,20070621T182100Z,20080621T000900Z,20090621T055700Z,20100621T114500Z,20110621T173300Z,20120620T232100Z,20130621T050900Z,20140621T105700Z,20150621T164600Z,20160620T223400Z,20170621T042200Z,20180621T101000Z,20190621T155800Z,20200620T214600Z,20210621T033400Z,20220621T092300Z,20230621T151100Z,20240620T205900Z,20250621T024700Z,20260621T083500Z,20270621T142300Z,20280620T201100Z,20290621T015900Z,20300621T074800Z,20310621T133600Z,20320620T192400Z,20330621T011200Z,20340621T070000Z,20350621T124800Z,20360620T183600Z,20370621T002400Z,20380621T061300Z,20390621T120100Z,20400620T174900Z,20410620T233700Z,20420621T052500Z,20430621T111300Z,20440620T170100Z,20450620T224900Z,20460621T043700Z,20470621T102600Z,20480620T161400Z,20490620T220200Z,20500621T035000Z,20510621T093800Z,20520620T152600Z,20530620T211400Z,20540621T030200Z,20550621T085100Z,20560620T143900Z,20570620T202700Z,20580621T021500Z,20590621T080300Z,20600620T135100Z,20610620T193900Z,20620621T012700Z,20630621T071600Z,20640620T130400Z,20650620T185200Z,20660621T004000Z,20670621T062800Z,20680620T121600Z,20690620T180400Z,20700620T235200Z,20710621T054100Z,20720620T112900Z,20730620T171700Z,20740620T230500Z,20750621T045300Z,20760620T104100Z,20770620T162900Z,20780620T221700Z,20790621T040500Z,20800620T095400Z,20810620T154200Z,20820620T213000Z,20830621T031800Z,20840620T090600Z,20850620T145400Z,20860620T204200Z,20870621T023000Z,20880620T081900Z,20890620T140700Z,20900620T195500Z,20910621T014300Z,20920620T073100Z,20930620T131900Z,20940620T190700Z,20950621T005500Z,20960620T064300Z,20970620T123200Z,20980620T182000Z,20990621T000800Z',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Autumnal Equinox',
			'start_date' => '2000-09-22',
			'end_date' => '9999-12-31',
			'start_time' => '17:16:00',
			'timezone' => 'UTC',
			'location' => '',
			'duration' => 'PT1M',
			'rrule' => 'FREQ=YEARLY;COUNT=1',
			'rdates' => '20000922T171600Z,20010922T230500Z,20020923T045400Z,20030923T104200Z,20040922T163100Z,20050922T222000Z,20060923T040800Z,20070923T095700Z,20080922T154600Z,20090922T213400Z,20100923T032300Z,20110923T091200Z,20120922T150100Z,20130922T204900Z,20140923T023800Z,20150923T082700Z,20160922T141500Z,20170922T200400Z,20180923T015300Z,20190923T074100Z,20200922T133000Z,20210922T191900Z,20220923T010700Z,20230923T065600Z,20240922T124500Z,20250922T183300Z,20260923T002200Z,20270923T061100Z,20280922T115900Z,20290922T174800Z,20300922T233700Z,20310923T052600Z,20320922T111400Z,20330922T170300Z,20340922T225200Z,20350923T044000Z,20360922T102900Z,20370922T161800Z,20380922T220600Z,20390923T035500Z,20400922T094400Z,20410922T153200Z,20420922T212100Z,20430923T031000Z,20440922T085800Z,20450922T144700Z,20460922T203600Z,20470923T022400Z,20480922T081300Z,20490922T140200Z,20500922T195000Z,20510923T013900Z,20520922T072800Z,20530922T131600Z,20540922T190500Z,20550923T005400Z,20560922T064200Z,20570922T123100Z,20580922T182000Z,20590923T000800Z,20600922T055700Z,20610922T114600Z,20620922T173400Z,20630922T232300Z,20640922T051200Z,20650922T110000Z,20660922T164900Z,20670922T223800Z,20680922T042600Z,20690922T101500Z,20700922T160400Z,20710922T215200Z,20720922T034100Z,20730922T093000Z,20740922T151800Z,20750922T210700Z,20760922T025600Z,20770922T084400Z,20780922T143300Z,20790922T202200Z,20800922T021000Z,20810922T075900Z,20820922T134800Z,20830922T193600Z,20840922T012500Z,20850922T071400Z,20860922T130200Z,20870922T185100Z,20880922T003900Z,20890922T062800Z,20900922T121700Z,20910922T180500Z,20920921T235400Z,20930922T054300Z,20940922T113100Z,20950922T172000Z,20960921T230900Z,20970922T045700Z,20980922T104600Z,20990922T163500Z',
			'type' => 1,
			'enabled' => 1,
		],
		[
			'title' => 'Winter Solstice',
			'start_date' => '2000-12-21',
			'end_date' => '9999-12-31',
			'start_time' => '13:27:00',
			'timezone' => 'UTC',
			'location' => '',
			'duration' => 'PT1M',
			'rrule' => 'FREQ=YEARLY;COUNT=1',
			'rdates' => '20001221T132700Z,20011221T191600Z,20021222T010600Z,20031222T065600Z,20041221T124600Z,20051221T183500Z,20061222T002500Z,20071222T061500Z,20081221T120400Z,20091221T175400Z,20101221T234400Z,20111222T053400Z,20121221T112300Z,20131221T171300Z,20141221T230300Z,20151222T045300Z,20161221T104200Z,20171221T163200Z,20181221T222200Z,20191222T041100Z,20201221T100100Z,20211221T155100Z,20221221T214100Z,20231222T033000Z,20241221T092000Z,20251221T151000Z,20261221T205900Z,20271222T024900Z,20281221T083900Z,20291221T142900Z,20301221T201800Z,20311222T020800Z,20321221T075800Z,20331221T134800Z,20341221T193700Z,20351222T012700Z,20361221T071700Z,20371221T130600Z,20381221T185600Z,20391222T004600Z,20401221T063600Z,20411221T122500Z,20421221T181500Z,20431222T000500Z,20441221T055400Z,20451221T114400Z,20461221T173400Z,20471221T232400Z,20481221T051300Z,20491221T110300Z,20501221T165300Z,20511221T224200Z,20521221T043200Z,20531221T102200Z,20541221T161200Z,20551221T220100Z,20561221T035100Z,20571221T094100Z,20581221T153000Z,20591221T212000Z,20601221T031000Z,20611221T090000Z,20621221T144900Z,20631221T203900Z,20641221T022900Z,20651221T081800Z,20661221T140800Z,20671221T195800Z,20681221T014700Z,20691221T073700Z,20701221T132700Z,20711221T191700Z,20721221T010600Z,20731221T065600Z,20741221T124600Z,20751221T183500Z,20761221T002500Z,20771221T061500Z,20781221T120500Z,20791221T175400Z,20801220T234400Z,20811221T053400Z,20821221T112300Z,20831221T171300Z,20841220T230300Z,20851221T045200Z,20861221T104200Z,20871221T163200Z,20881220T222200Z,20891221T041100Z,20901221T100100Z,20911221T155100Z,20921220T214000Z,20931221T033000Z,20941221T092000Z,20951221T150900Z,20961220T205900Z,20971221T024900Z,20981221T083900Z,20991221T142800Z',
			'type' => 1,
			'enabled' => 1,
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
		$this->name = 'calendar';

		$this->columns = [
			new Column(
				name: 'id_event',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'start_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			new Column(
				name: 'end_date',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			new Column(
				name: 'start_time',
				type: 'time',
			),
			new Column(
				name: 'timezone',
				type: 'varchar',
				size: 80,
			),
			new Column(
				name: 'location',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'duration',
				type: 'varchar',
				size: 32,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'rrule',
				type: 'varchar',
				size: 1024,
				not_null: true,
				default: 'FREQ=YEARLY;COUNT=1',
			),
			new Column(
				name: 'rdates',
				type: 'text',
				not_null: true,
				default: '',
			),
			new Column(
				name: 'exdates',
				type: 'text',
				not_null: true,
				default: '',
			),
			new Column(
				name: 'adjustments',
				type: 'json',
				not_null: true,
			),
			new Column(
				name: 'sequence',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'uid',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'type',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'enabled',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_event',
				],
			),
			new DbIndex(
				name: 'idx_start_date',
				columns: [
					'start_date',
				],
			),
			new DbIndex(
				name: 'idx_end_date',
				columns: [
					'end_date',
				],
			),
			new DbIndex(
				name: 'idx_topic',
				columns: [
					'id_topic',
					'id_member',
				],
			),
		];
	}
}

?>