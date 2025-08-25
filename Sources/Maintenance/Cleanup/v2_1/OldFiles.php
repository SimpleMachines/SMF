<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup\v2_1;

use SMF\Maintenance\Cleanup\v3_0\OldFiles as OldFilesBase;

/**
 * Just like the v3_0 version of OldFiles, but with a different list of files.
 */
class OldFiles extends OldFilesBase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * List of files removed in SMF 2.1 (and earlier).
	 */
	protected array $removed = [
		// Files in the Themes directory.
		'themedir' => [
			// Removed in 2.1.
			'core',
			// Removed in 1.1.
			'default/Combat.template.php',
			'default/Modlog.template.php',
			'default/fader.js',
			'default/script.js',
			'default/spellcheck.js',
			'default/xml_board.js',
			'default/xml_topic.js',
		],
		// Files in the Sources directory.
		'sourcedir' => [
			// Removed in 2.1.
			'DumpDatabase.php',
			'LockTopic.php',
			// Removed in 2.0.
			'ModSettings.php',
		],
		// Files in the Smileys directory.
		'smileysdir' => [],
		// Files in the avatars directory.
		'avatardir' => [],
		// Files in the forum's root directory.
		'boarddir' => [],
	];
}
