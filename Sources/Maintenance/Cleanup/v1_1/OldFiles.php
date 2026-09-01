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

namespace SMF\Maintenance\Cleanup\v1_1;

use SMF\Maintenance\Cleanup\OldFilesBase;

/**
 * Deletes files that were present in SMF 1.0 but not in SMF 1.1.
 */
class OldFiles extends OldFilesBase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * List of files removed in SMF 1.1.
	 */
	protected array $removed = [
		// Files in the Themes directory.
		'themedir' => [
			'default/Combat.template.php',
			'default/Modlog.template.php',
			'default/fader.js',
			'default/script.js',
			'default/spellcheck.js',
			'default/xml_board.js',
			'default/xml_topic.js',
		],
		// Files in the Sources directory.
		'sourcedir' => [],
		// Files in the Smileys directory.
		'smileysdir' => [],
		// Files in the avatars directory.
		'avatardir' => [],
		// Files in the forum's root directory.
		'boarddir' => [],
	];
}
