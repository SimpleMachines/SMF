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

use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\v3_0\MemberLogins;
use SMF\Maintenance;
use SMF\Maintenance\Migration;
use SMF\Db\Schema\v3_0;
use SMF\Utils;

class Migration0001 extends Migration
{
	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Fixing attachment directory setting';

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return empty(Config::$modSettings['json_done']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		if (!is_array(Config::$modSettings['attachmentUploadDir']) && is_dir(Config::$modSettings['attachmentUploadDir']))
		{
			Config::$modSettings['attachmentUploadDir'] = serialize(array(1 => Config::$modSettings['attachmentUploadDir']));
			Config::updateModSettings([
				'attachmentUploadDir' => Config::$modSettings['attachmentUploadDir'],
				'currentAttachmentUploadDir' => 1
			]);
		}
		elseif (is_array(Config::$modSettings['attachmentUploadDir']))
		{
			Config::updateModSettings([
				'attachmentUploadDir' => serialize(Config::$modSettings['attachmentUploadDir']),
			]);
			// Assume currentAttachmentUploadDir is already set
		}
	
			return true;
	}
}

?>