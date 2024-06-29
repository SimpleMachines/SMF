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
			'spider_name' => 'Google',
			'user_agent' => 'googlebot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Yahoo!',
			'user_agent' => 'slurp',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Bing',
			'user_agent' => 'bingbot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Google (Mobile)',
			'user_agent' => 'Googlebot-Mobile',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Google (Image)',
			'user_agent' => 'Googlebot-Image',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Google (AdSense)',
			'user_agent' => 'Mediapartners-Google',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Google (Adwords)',
			'user_agent' => 'AdsBot-Google',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Yahoo! (Mobile)',
			'user_agent' => 'YahooSeeker/M1A1-R2D2',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Yahoo! (Image)',
			'user_agent' => 'Yahoo-MMCrawler',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Bing (Preview)',
			'user_agent' => 'BingPreview',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Bing (Ads)',
			'user_agent' => 'adidxbot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Bing (MSNBot)',
			'user_agent' => 'msnbot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Bing (Media)',
			'user_agent' => 'msnbot-media',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Cuil',
			'user_agent' => 'twiceler',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Ask',
			'user_agent' => 'Teoma',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Baidu',
			'user_agent' => 'Baiduspider',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Gigablast',
			'user_agent' => 'Gigabot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'InternetArchive',
			'user_agent' => 'ia_archiver-web.archive.org',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Alexa',
			'user_agent' => 'ia_archiver',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Omgili',
			'user_agent' => 'omgilibot',
			'ip_info' => '',
		],
		[
			'spider_name' => 'EntireWeb',
			'user_agent' => 'Speedy Spider',
			'ip_info' => '',
		],
		[
			'spider_name' => 'Yandex',
			'user_agent' => 'yandex',
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
			new Column(
				name: 'id_spider',
				type: 'smallint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'spider_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'user_agent',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'ip_info',
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
					'id_spider',
				],
			),
		];
	}
}

?>