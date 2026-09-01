<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Utils;

class News extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting news';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		Config::updateModSettings([
			'news' => Utils::htmlspecialchars(
				Utils::entityDecode(
					stripslashes(Config::$modSettings['news']),
					nbsp_to_space: true,
				),
				ENT_QUOTES,
			),
		]);

		$this->handleTimeout();

		return true;
	}
}
