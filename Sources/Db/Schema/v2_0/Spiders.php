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
class Spiders extends Table
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
			'id_spider' => 1,
			'spider_name' => 'Google',
			'user_agent' => 'googlebot',
			'ip_info' => '',
		],
		[
			'id_spider' => 2,
			'spider_name' => 'Yahoo!',
			'user_agent' => 'slurp',
			'ip_info' => '',
		],
		[
			'id_spider' => 3,
			'spider_name' => 'MSN',
			'user_agent' => 'msnbot',
			'ip_info' => '',
		],
		[
			'id_spider' => 4,
			'spider_name' => 'Google (Mobile)',
			'user_agent' => 'Googlebot-Mobile',
			'ip_info' => '',
		],
		[
			'id_spider' => 5,
			'spider_name' => 'Google (Image)',
			'user_agent' => 'Googlebot-Image',
			'ip_info' => '',
		],
		[
			'id_spider' => 6,
			'spider_name' => 'Google (AdSense)',
			'user_agent' => 'Mediapartners-Google',
			'ip_info' => '',
		],
		[
			'id_spider' => 7,
			'spider_name' => 'Google (Adwords)',
			'user_agent' => 'AdsBot-Google',
			'ip_info' => '',
		],
		[
			'id_spider' => 8,
			'spider_name' => 'Yahoo! (Mobile)',
			'user_agent' => 'YahooSeeker/M1A1-R2D2',
			'ip_info' => '',
		],
		[
			'id_spider' => 9,
			'spider_name' => 'Yahoo! (Image)',
			'user_agent' => 'Yahoo-MMCrawler',
			'ip_info' => '',
		],
		[
			'id_spider' => 10,
			'spider_name' => 'MSN (Mobile)',
			'user_agent' => 'MSNBOT_Mobile',
			'ip_info' => '',
		],
		[
			'id_spider' => 11,
			'spider_name' => 'MSN (Media)',
			'user_agent' => 'msnbot-media',
			'ip_info' => '',
		],
		[
			'id_spider' => 12,
			'spider_name' => 'Cuil',
			'user_agent' => 'twiceler',
			'ip_info' => '',
		],
		[
			'id_spider' => 13,
			'spider_name' => 'Ask',
			'user_agent' => 'Teoma',
			'ip_info' => '',
		],
		[
			'id_spider' => 14,
			'spider_name' => 'Baidu',
			'user_agent' => 'Baiduspider',
			'ip_info' => '',
		],
		[
			'id_spider' => 15,
			'spider_name' => 'Gigablast',
			'user_agent' => 'Gigabot',
			'ip_info' => '',
		],
		[
			'id_spider' => 16,
			'spider_name' => 'InternetArchive',
			'user_agent' => 'ia_archiver-web.archive.org',
			'ip_info' => '',
		],
		[
			'id_spider' => 17,
			'spider_name' => 'Alexa',
			'user_agent' => 'ia_archiver',
			'ip_info' => '',
		],
		[
			'id_spider' => 18,
			'spider_name' => 'Omgili',
			'user_agent' => 'omgilibot',
			'ip_info' => '',
		],
		[
			'id_spider' => 19,
			'spider_name' => 'EntireWeb',
			'user_agent' => 'Speedy Spider',
			'ip_info' => '',
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
		$this->name = 'spiders';

		$this->columns = [
			'id_spider' => new Column(
				name: 'id_spider',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'spider_name' => new Column(
				name: 'spider_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'user_agent' => new Column(
				name: 'user_agent',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'ip_info' => new Column(
				name: 'ip_info',
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
					[
						'name' => 'id_spider',
					],
				],
			),
		];

		parent::__construct();
	}
}
